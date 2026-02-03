<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\ClassInstance;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        $teachers = $query->orderBy('id', 'desc')->paginate($perPage);
        
        // Add cumulative rates to each teacher
        foreach ($teachers->items() as $teacher) {
            $allClasses = ClassInstance::where('teacher_id', $teacher->id)
                ->with(['student', 'course'])
                ->get();
            
            // Calculate punctuality rate
            $punctualityData = $this->calculatePunctualityRate($allClasses);
            
            // Calculate report submission rate
            $reportSubmissionData = $this->calculateReportSubmissionRate($allClasses);
            
            // Calculate attendance rate
            $attendanceData = $this->calculateAttendanceRate($allClasses);
            
            // Add rates to teacher object
            $teacher->punctuality_rate = $punctualityData['rate'];
            $teacher->punctuality_score = $punctualityData['score'];
            $teacher->report_submission_rate = $reportSubmissionData['rate'];
            $teacher->report_submission_score = $reportSubmissionData['score'];
            $teacher->attendance_rate = $attendanceData['rate'];
            $teacher->attendance_score = $attendanceData['score'];
        }
        
        return $teachers;
    }

    /**
     * Calculate punctuality rate
     */
    private function calculatePunctualityRate($classes): array
    {
        $onTime = 0;
        $late = 0;
        $veryLate = 0;
        $totalJoined = 0;

        foreach ($classes as $class) {
            if (!$class->meet_link_accessed_at) {
                continue;
            }

            $totalJoined++;
            $classStartTime = Carbon::parse($class->class_date->format('Y-m-d') . ' ' . $class->start_time->format('H:i:s'));
            $joinedTime = Carbon::parse($class->meet_link_accessed_at);
            
            if ($joinedTime->lte($classStartTime)) {
                $onTime++;
            } else {
                $minutesLate = $joinedTime->diffInMinutes($classStartTime);
                if ($minutesLate <= 10) {
                    $late++;
                } else {
                    $veryLate++;
                }
            }
        }

        $punctualityRate = $totalJoined > 0 
            ? round(($onTime / $totalJoined) * 100, 2) 
            : 0;

        $punctualityScore = $totalJoined > 0
            ? round((($onTime * 100) + ($late * 50) + ($veryLate * 0)) / $totalJoined, 2)
            : 0;

        return [
            'rate' => $punctualityRate,
            'score' => $punctualityScore,
            'on_time' => $onTime,
            'late' => $late,
            'very_late' => $veryLate,
            'total_joined' => $totalJoined,
        ];
    }

    /**
     * Calculate report submission rate
     */
    private function calculateReportSubmissionRate($classes): array
    {
        $immediate = 0;
        $late = 0;
        $veryLate = 0;
        $totalReports = 0;

        foreach ($classes as $class) {
            if (!$class->report_submitted_at) {
                continue;
            }

            $totalReports++;
            $classEndTime = Carbon::parse($class->class_date->format('Y-m-d') . ' ' . $class->end_time->format('H:i:s'));
            $reportSubmittedTime = Carbon::parse($class->report_submitted_at);
            
            if ($reportSubmittedTime->lte($classEndTime)) {
                $immediate++;
            } else {
                $minutesAfterEnd = $reportSubmittedTime->diffInMinutes($classEndTime);
                if ($minutesAfterEnd <= 5) {
                    $immediate++;
                } elseif ($minutesAfterEnd <= 10) {
                    $late++;
                } else {
                    $veryLate++;
                }
            }
        }

        $reportSubmissionRate = $totalReports > 0 
            ? round(($immediate / $totalReports) * 100, 2) 
            : 0;

        $reportSubmissionScore = $totalReports > 0
            ? round((($immediate * 100) + ($late * 70) + ($veryLate * 40)) / $totalReports, 2)
            : 0;

        return [
            'rate' => $reportSubmissionRate,
            'score' => $reportSubmissionScore,
            'immediate' => $immediate,
            'late' => $late,
            'very_late' => $veryLate,
            'total_reports' => $totalReports,
        ];
    }

    /**
     * Calculate attendance rate
     */
    private function calculateAttendanceRate($classes): array
    {
        $attended = 0;
        $cancelledByStudent = 0;
        $total = $classes->count();

        foreach ($classes as $class) {
            if ($class->status === 'attended') {
                $attended++;
            } elseif ($class->status === 'cancelled_by_student') {
                $cancelledByStudent++;
            }
        }

        // Attendance rate = (attended + cancelled_by_student) / total * 100
        // This means classes that were either attended or cancelled by student (not by teacher) count as "good attendance"
        $attendanceRate = $total > 0 
            ? round((($attended + $cancelledByStudent) / $total) * 100, 2) 
            : 0;

        // Score: attended = 100, cancelled_by_student = 80, others = 0
        $attendanceScore = $total > 0
            ? round((($attended * 100) + ($cancelledByStudent * 80)) / $total, 2)
            : 0;

        return [
            'rate' => $attendanceRate,
            'score' => $attendanceScore,
            'attended' => $attended,
            'cancelled_by_student' => $cancelledByStudent,
            'total' => $total,
        ];
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
        $approvedCancellations = $monthClasses->where('status', 'cancelled_by_student')
            ->where('cancellation_request_status', 'approved');
        
        // Combine attended and approved cancellations for salary calculation
        // Rejected cancellations and classes cancelled by teacher/admin do NOT count for salary
        $classesForSalary = $attendedClasses->merge($approvedCancellations);
        
        $totalClasses = $monthClasses->count();
        $attendedCount = $attendedClasses->count();
        
        // Calculate total hours from attended + approved cancellations
        $totalMinutes = $classesForSalary->sum('duration') ?? 0;
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
