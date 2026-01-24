<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\ClassInstance;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeacherService
{
    /**
     * Search and filter teachers
     */
    public function getTeachers(array $filters = [], int $perPage = 15)
    {
        $query = Teacher::with(['user', 'courses']);

        // Search by name, email
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Filter by course
        if (!empty($filters['course_id'])) {
            $query->whereHas('courses', function ($q) use ($filters) {
                $q->where('courses.id', $filters['course_id']);
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    /**
     * Get teacher profile with all related data
     */
    public function getTeacherProfile(int $teacherId): array
    {
        $teacher = Teacher::with([
            'user',
            'courses',
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
        ])->findOrFail($teacherId);

        // Get assigned students (unique students from classes)
        $assignedStudents = DB::table('classes')
            ->where('teacher_id', $teacherId)
            ->distinct()
            ->pluck('student_id')
            ->toArray();

        $students = DB::table('students')
            ->whereIn('id', $assignedStudents)
            ->get();

        return [
            'teacher' => $teacher,
            'assigned_students' => $students,
            'stats' => [
                'total_courses' => $teacher->courses->count(),
                'active_courses' => $teacher->courses->where('status', 'active')->count(),
                'total_classes' => $teacher->classes->count(),
                'attended_classes' => $teacher->classes->where('status', 'attended')->count(),
                'total_bills' => $teacher->bills->count(),
                'paid_bills' => $teacher->bills->where('status', 'paid')->count(),
                'total_duties' => $teacher->duties->count(),
                'total_reports' => $teacher->reports->count(),
                'student_count' => count($assignedStudents),
            ],
        ];
    }

    /**
     * Get teacher performance metrics
     */
    public function getTeacherPerformance(int $teacherId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $teacher = Teacher::findOrFail($teacherId);
        return $teacher->getPerformanceStats($dateFrom, $dateTo);
    }

    /**
     * Assign courses to teacher
     */
    public function assignCourses(int $teacherId, array $courseIds): Teacher
    {
        $teacher = Teacher::findOrFail($teacherId);
        $teacher->courses()->sync($courseIds);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'assign_courses',
            'description' => "Courses assigned to teacher {$teacher->full_name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $teacher->fresh()->load('courses');
    }

    /**
     * Get teacher statistics
     */
    public function getTeacherStats(): array
    {
        return [
            'total' => Teacher::count(),
            'active' => Teacher::where('status', 'active')->count(),
            'inactive' => Teacher::where('status', 'inactive')->count(),
        ];
    }

    /**
     * Get teacher monthly statistics and assigned students
     */
    public function getTeacherMonthlyStats(int $teacherId, ?int $month = null, ?int $year = null): array
    {
        $teacher = Teacher::with('user')->findOrFail($teacherId);
        
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        // Get classes for current month using model
        $monthClasses = ClassInstance::where('teacher_id', $teacherId)
            ->whereYear('class_date', $year)
            ->whereMonth('class_date', $month)
            ->get();
        
        $attendedClasses = $monthClasses->where('status', 'attended');
        $totalClasses = $monthClasses->count();
        $attendedCount = $attendedClasses->count();
        
        // Calculate total hours
        $totalMinutes = $attendedClasses->sum('duration') ?? 0;
        $totalHours = round($totalMinutes / 60, 2);
        
        // Calculate salary
        $salary = round($teacher->hourly_rate * $totalHours, 2);
        
        // Get assigned students
        $studentIds = DB::table('classes')
            ->where('teacher_id', $teacherId)
            ->distinct()
            ->pluck('student_id')
            ->toArray();
        
        $students = DB::table('students')
            ->whereIn('id', $studentIds)
            ->select('id', 'full_name', 'email', 'whatsapp', 'status', 'country', 'currency')
            ->get();
        
        // Get teacher availability
        $availability = $teacher->availability()
            ->where('is_available', true)
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        
        return [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->user->name ?? 'N/A',
                'email' => $teacher->user->email ?? 'N/A',
                'hourly_rate' => $teacher->hourly_rate,
                'currency' => $teacher->currency,
            ],
            'month' => $month,
            'year' => $year,
            'stats' => [
                'total_classes' => $totalClasses,
                'attended_classes' => $attendedCount,
                'total_hours' => $totalHours,
                'salary' => $salary,
            ],
            'students' => $students,
            'availability' => $availability,
        ];
    }
}
