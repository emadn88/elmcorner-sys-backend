<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\ActivityLog as ActivityLogModel;
use App\Services\StudentService;
use App\Services\WhatsAppService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    /**
     * Get activity logs with filters and pagination
     */
    public function getActivityLogs(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = ActivityLog::with(['user', 'student'])
            ->orderBy('created_at', 'desc');

        // Apply filters using scopes
        $query->byUser($filters['user_id'] ?? null)
              ->byStudent($filters['student_id'] ?? null)
              ->byAction($filters['action'] ?? null)
              ->byDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
              ->search($filters['search'] ?? null);

        return $query->paginate($perPage);
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = ActivityLog::query();

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $total = $query->count();

        // Count by action type
        $byAction = ActivityLog::select('action', DB::raw('count(*) as count'))
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->where('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->where('created_at', '<=', $dateTo . ' 23:59:59');
            })
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->action => $item->count];
            })
            ->toArray();

        // Today's activities
        $todayCount = ActivityLog::whereDate('created_at', today())->count();

        // Top users by activity count
        $topUsers = ActivityLog::select('user_id', DB::raw('count(*) as count'))
            ->whereNotNull('user_id')
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->where('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->where('created_at', '<=', $dateTo . ' 23:59:59');
            })
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $user = \App\Models\User::find($item->user_id);
                return [
                    'user_id' => $item->user_id,
                    'user_name' => $user ? $user->name : 'Unknown',
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return [
            'total' => $total,
            'today' => $todayCount,
            'by_action' => $byAction,
            'top_users' => $topUsers,
        ];
    }

    /**
     * Get recent activities
     */
    public function getRecentActivity(int $limit = 10): array
    {
        return ActivityLog::with(['user', 'student'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Calculate detailed student activity metrics
     */
    public function calculateStudentActivity(int $studentId): array
    {
        $studentService = new StudentService();
        $activityLevel = $studentService->getActivityLevel($studentId);

        // Get recent classes (last 30 days)
        $recentClasses = DB::table('classes')
            ->where('student_id', $studentId)
            ->where('class_date', '>=', now()->subDays(30))
            ->where('status', 'attended')
            ->count();

        // Get last class date
        $lastClass = DB::table('classes')
            ->where('student_id', $studentId)
            ->where('status', 'attended')
            ->orderBy('class_date', 'desc')
            ->first();

        $lastClassDate = $lastClass ? $lastClass->class_date : null;
        $daysSinceLastClass = $lastClassDate ? now()->diffInDays($lastClassDate) : null;

        // Calculate attendance rate (attended / total scheduled in last 30 days)
        $totalScheduled = DB::table('classes')
            ->where('student_id', $studentId)
            ->where('class_date', '>=', now()->subDays(30))
            ->whereIn('status', ['attended', 'absent_student', 'cancelled_by_student'])
            ->count();

        $attendanceRate = $totalScheduled > 0 
            ? round(($recentClasses / $totalScheduled) * 100, 2) 
            : 0;

        return [
            'activity_level' => $activityLevel,
            'recent_classes_count' => $recentClasses,
            'last_class_date' => $lastClassDate,
            'days_since_last_class' => $daysSinceLastClass,
            'attendance_rate' => $attendanceRate,
        ];
    }

    /**
     * Get inactive students with activity levels
     */
    public function getInactiveStudents(?int $threshold = null, ?string $activityLevel = null): Collection
    {
        $threshold = $threshold ?? 30;
        
        $query = Student::query();

        // Get students who haven't had a class in threshold days
        $inactiveStudentIds = DB::table('classes')
            ->select('student_id')
            ->where('status', 'attended')
            ->groupBy('student_id')
            ->havingRaw("MAX(class_date) < ?", [now()->subDays($threshold)->toDateString()])
            ->pluck('student_id')
            ->toArray();

        // Also include students with no classes at all
        $studentsWithNoClasses = DB::table('students')
            ->leftJoin('classes', 'students.id', '=', 'classes.student_id')
            ->whereNull('classes.id')
            ->pluck('students.id')
            ->toArray();

        $allInactiveIds = array_unique(array_merge($inactiveStudentIds, $studentsWithNoClasses));

        if (!empty($allInactiveIds)) {
            $query->whereIn('id', $allInactiveIds);
        } else {
            // No inactive students found
            return collect([]);
        }

        // Filter by activity level if specified
        if ($activityLevel && in_array($activityLevel, ['low', 'stopped'])) {
            $students = $query->get();
            $filtered = $students->filter(function ($student) use ($activityLevel) {
                $studentService = new StudentService();
                $level = $studentService->getActivityLevel($student->id);
                return $level === $activityLevel;
            });
            return $filtered;
        }

        return $query->get();
    }

    /**
     * Send reactivation offer via WhatsApp
     */
    public function sendReactivationOffer(int $studentId, ?string $message = null): bool
    {
        $student = Student::findOrFail($studentId);

        if (!$student->whatsapp) {
            throw new \Exception('Student does not have a WhatsApp number');
        }

        $whatsappService = new WhatsAppService();

        // Use custom message or template
        if ($message) {
            $success = $whatsappService->sendMessage($student->whatsapp, $message);
        } else {
            // Use template with variables
            $template = config('whatsapp.templates.reactivation_offer', 'Hello {name}! We would like to see you again. Special offer: {link}');
            
            // Replace template variables
            $message = str_replace('{name}', $student->full_name, $template);
            $message = str_replace('{link}', url('/dashboard/students/' . $student->id), $message);
            
            $success = $whatsappService->sendMessage($student->whatsapp, $message);
        }

        // Log activity
        if ($success) {
            ActivityLogModel::create([
                'user_id' => Auth::id(),
                'student_id' => $studentId,
                'action' => 'reactivation_offer_sent',
                'description' => "Reactivation offer sent to student {$student->full_name}",
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        return $success;
    }
}
