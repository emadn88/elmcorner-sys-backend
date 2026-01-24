<?php

namespace App\Services;

use App\Models\Student;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentService
{
    /**
     * Get student profile with all related data
     */
    public function getStudentProfile(int $studentId): array
    {
        $student = Student::with([
            'family',
            'packages' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'timetables' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'classes' => function ($query) {
                $query->orderBy('class_date', 'desc')->limit(50);
            },
            'bills' => function ($query) {
                $query->orderBy('bill_date', 'desc')->limit(50);
            },
            'duties' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(50);
            },
            'reports' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(50);
            },
            'activityLogs' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(100);
            },
        ])->findOrFail($studentId);

        return [
            'student' => $student,
            'activity_level' => $this->getActivityLevel($studentId),
            'stats' => [
                'total_packages' => $student->packages->count(),
                'active_packages' => $student->packages->where('status', 'active')->count(),
                'total_classes' => $student->classes->count(),
                'attended_classes' => $student->classes->where('status', 'attended')->count(),
                'total_bills' => $student->bills->count(),
                'paid_bills' => $student->bills->where('status', 'paid')->count(),
                'pending_bills' => $student->bills->where('status', 'pending')->count(),
                'total_duties' => $student->duties->count(),
                'total_reports' => $student->reports->count(),
            ],
            'packages' => $student->packages,
            'timetables' => $student->timetables,
            'classes' => $student->classes,
            'bills' => $student->bills,
            'duties' => $student->duties,
            'reports' => $student->reports,
            'activityLogs' => $student->activityLogs,
        ];
    }

    /**
     * Update student status and log activity
     */
    public function updateStudentStatus(int $studentId, string $status, ?string $notes = null): Student
    {
        $student = Student::findOrFail($studentId);
        $oldStatus = $student->status;
        
        $student->update([
            'status' => $status,
            'notes' => $notes ?? $student->notes,
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $studentId,
            'action' => 'update_status',
            'description' => "Student status changed from {$oldStatus} to {$status}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $student->fresh();
    }

    /**
     * Get activity level based on class frequency
     */
    public function getActivityLevel(int $studentId): string
    {
        $recentClasses = DB::table('classes')
            ->where('student_id', $studentId)
            ->where('class_date', '>=', now()->subDays(30))
            ->where('status', 'attended')
            ->count();

        if ($recentClasses >= 8) {
            return 'highly_active';
        } elseif ($recentClasses >= 4) {
            return 'medium';
        } elseif ($recentClasses >= 1) {
            return 'low';
        } else {
            return 'stopped';
        }
    }

    /**
     * Search and filter students
     */
    public function searchStudents(array $filters = [], int $perPage = 15)
    {
        $query = Student::with('family');

        // Search by name, email, or whatsapp
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Filter by family
        if (!empty($filters['family_id'])) {
            $query->where('family_id', $filters['family_id']);
        }

        return $query->orderBy('full_name', 'asc')->paginate($perPage);
    }

    /**
     * Get student statistics
     */
    public function getStudentStats(): array
    {
        return [
            'total' => Student::count(),
            'active' => Student::where('status', 'active')->count(),
            'paused' => Student::where('status', 'paused')->count(),
            'stopped' => Student::where('status', 'stopped')->count(),
        ];
    }
}
