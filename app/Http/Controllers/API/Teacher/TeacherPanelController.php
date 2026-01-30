<?php

namespace App\Http\Controllers\API\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\ClassInstance;
use App\Models\TeacherAvailability;
use App\Models\EvaluationOption;
use App\Models\TrialClass;
use App\Services\TeacherService;
use App\Services\TeacherClassService;
use App\Services\ClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TeacherPanelController extends Controller
{
    protected $teacherService;
    protected $classService;
    protected $mainClassService;

    public function __construct(TeacherService $teacherService, TeacherClassService $classService, ClassService $mainClassService)
    {
        $this->teacherService = $teacherService;
        $this->classService = $classService;
        $this->mainClassService = $mainClassService;
    }

    /**
     * Get teacher's dashboard data
     */
    public function dashboard(): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        $today = Carbon::today()->format('Y-m-d');
        $todayClasses = $teacher->classes()
            ->where('class_date', $today)
            ->with(['student', 'course'])
            ->orderBy('start_time', 'asc')
            ->get();

        $upcomingClasses = $teacher->classes()
            ->where('class_date', '>=', $today)
            ->where('class_date', '<=', Carbon::now()->addDays(7)->format('Y-m-d'))
            ->with(['student', 'course'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $assignedStudents = DB::table('classes')
            ->where('teacher_id', $teacher->id)
            ->distinct()
            ->pluck('student_id')
            ->count();

        $thisMonthClasses = $teacher->classes()
            ->whereMonth('class_date', Carbon::now()->month)
            ->whereYear('class_date', Carbon::now()->year)
            ->get();

        // Calculate this month's hours from all classes (not just attended)
        $thisMonthHours = $thisMonthClasses->sum('duration') / 60; // Convert minutes to hours
        $thisMonthHours = round($thisMonthHours, 2);

        $pendingClasses = $teacher->classes()
            ->where('status', 'pending')
            ->where('class_date', '>=', $today)
            ->count();

        // Calculate attendance rate
        $allClasses = $teacher->classes()->get();
        $attendedCount = $allClasses->where('status', 'attended')->count();
        $attendanceRate = $allClasses->count() > 0 
            ? round(($attendedCount / $allClasses->count()) * 100, 2) 
            : 0;

        // Calculate total hours from attended classes
        $attendedClasses = $allClasses->where('status', 'attended');
        $totalHours = $attendedClasses->sum('duration') / 60; // Convert minutes to hours
        $totalHours = round($totalHours, 2);

        // Calculate total salary (total hours * hourly rate)
        $totalSalary = round($totalHours * $teacher->hourly_rate, 2);

        // Get total classes count
        $totalClasses = $allClasses->count();

        // Get total courses count
        $totalCourses = $teacher->courses()->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'today_classes_count' => $todayClasses->count(),
                    'upcoming_classes_count' => $upcomingClasses->count(),
                    'pending_classes_count' => $pendingClasses,
                    'assigned_students_count' => $assignedStudents,
                    'this_month_hours' => $thisMonthHours,
                    'attendance_rate' => $attendanceRate,
                    'total_hours' => $totalHours,
                    'total_salary' => $totalSalary,
                    'total_classes' => $totalClasses,
                    'total_courses' => $totalCourses,
                    'currency' => $teacher->currency,
                ],
                'today_classes' => $todayClasses,
                'upcoming_classes' => $upcomingClasses,
            ],
        ]);
    }

    /**
     * Get teacher's classes with filters (default: today's classes)
     */
    public function getClasses(Request $request): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        $query = $teacher->classes()->with(['student', 'course', 'package']);

        // Default to today's classes if no date filter
        if (!$request->has('date_from') && !$request->has('date_to')) {
            $query->where('class_date', Carbon::today()->format('Y-m-d'));
        } else {
            if ($request->has('date_from')) {
                $query->where('class_date', '>=', $request->input('date_from'));
            }
            if ($request->has('date_to')) {
                $query->where('class_date', '<=', $request->input('date_to'));
            }
        }

        // Filter by status
        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter by student
        if ($request->has('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        $classes = $query->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        // Add meet link availability info
        $classes = $classes->map(function ($class) use ($teacher) {
            $class->can_enter_meet = $this->classService->canEnterMeet($class) && $this->classService->hasMeetLink($teacher);
            $class->meet_link = $teacher->meet_link;
            return $class;
        });

        // Calculate statistics
        $stats = [
            'total' => $classes->count(),
            'attended' => $classes->where('status', 'attended')->count(),
            'pending' => $classes->where('status', 'pending')->count(),
            'cancelled' => $classes->whereIn('status', ['cancelled_by_student', 'cancelled_by_teacher'])->count(),
            'attendance_rate' => $classes->count() > 0 
                ? round(($classes->where('status', 'attended')->count() / $classes->count()) * 100, 2) 
                : 0,
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'classes' => $classes,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Get single class details
     */
    public function getClass(int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $class = ClassInstance::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->with(['student', 'course', 'package', 'timetable'])
            ->firstOrFail();

        $class->can_enter_meet = $this->classService->canEnterMeet($class) && $this->classService->hasMeetLink($teacher);
        $class->meet_link = $teacher->meet_link;

        return response()->json([
            'status' => 'success',
            'data' => $class,
        ]);
    }

    /**
     * Update class status
     */
    public function updateClassStatus(Request $request, int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $request->validate([
            'status' => 'required|in:pending,attended,absent_student',
        ]);

        $class = ClassInstance::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Use ClassService to handle status update with proper business logic
        // (package deduction, billing, etc.)
        $updatedClass = $this->mainClassService->updateClassStatus(
            $id,
            $request->input('status'),
            Auth::id(),
            null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Class status updated successfully',
            'data' => $updatedClass,
        ]);
    }

    /**
     * Enter meet link
     */
    public function enterMeet(int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $class = ClassInstance::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Validate meet link exists
        if (!$this->classService->hasMeetLink($teacher)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Meet link not configured for this teacher',
            ], 400);
        }

        // Validate class time has started
        if (!$this->classService->canEnterMeet($class)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class time has not started yet',
            ], 400);
        }

        // Check if already entered
        if ($class->meet_link_used) {
            return response()->json([
                'status' => 'error',
                'message' => 'Meet link already accessed for this class',
            ], 400);
        }

        $this->classService->enterMeet($class);

        return response()->json([
            'status' => 'success',
            'message' => 'Meet link accessed successfully',
            'data' => [
                'class' => $class->fresh(),
                'meet_link' => $teacher->meet_link,
            ],
        ]);
    }

    /**
     * End class (mark as ready for details)
     */
    public function endClass(int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $class = ClassInstance::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Validate that meet was entered
        if (!$class->meet_link_used) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must enter the meet first',
            ], 400);
        }

        // Validate that class is still pending (not already completed)
        if ($class->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Class has already been completed',
            ], 400);
        }

        $this->classService->endClass($class);

        return response()->json([
            'status' => 'success',
            'message' => 'Class ended. Please fill in the details.',
            'data' => $class->fresh(),
        ]);
    }

    /**
     * Update class details (evaluation, report, notes)
     */
    public function updateClassDetails(Request $request, int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $request->validate([
            'student_evaluation' => 'nullable|string',
            'class_report' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $class = ClassInstance::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        if ($request->has('student_evaluation')) {
            $class->student_evaluation = $request->input('student_evaluation');
        }
        if ($request->has('class_report')) {
            $class->class_report = $request->input('class_report');
        }
        if ($request->has('notes')) {
            $class->notes = $request->input('notes');
        }

        $class->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Class details updated successfully',
            'data' => $class,
        ]);
    }

    /**
     * Request class cancellation
     */
    public function cancelClass(Request $request, int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $class = ClassInstance::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Check if class can be cancelled (not already cancelled or completed)
        if (in_array($class->status, ['cancelled_by_student', 'cancelled_by_teacher', 'attended'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'This class cannot be cancelled',
            ], 400);
        }

        $this->classService->requestCancellation($class, $request->input('reason'), Auth::id());

        return response()->json([
            'status' => 'success',
            'message' => 'Cancellation request submitted. Waiting for admin approval.',
            'data' => $class->fresh(),
        ]);
    }

    /**
     * Get classes for calendar view
     */
    public function getCalendar(Request $request): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        $query = $teacher->classes()->with(['student', 'course']);

        if ($request->has('start_date')) {
            $query->where('class_date', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('class_date', '<=', $request->input('end_date'));
        }

        $classes = $query->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $classes,
        ]);
    }

    /**
     * Get teacher's assigned students
     */
    public function getStudents(Request $request): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        $studentIds = DB::table('classes')
            ->where('teacher_id', $teacher->id)
            ->distinct()
            ->pluck('student_id')
            ->toArray();

        $query = DB::table('students')
            ->whereIn('id', $studentIds);

        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $students = $query->get();

        // Calculate statistics
        $activeStudents = 0;
        $lessActiveStudents = 0;
        $stoppedStudents = 0;
        $totalSalary = 0;

        // Add class statistics for each student
        $students = $students->map(function ($student) use ($teacher, &$activeStudents, &$lessActiveStudents, &$stoppedStudents, &$totalSalary) {
            $studentClasses = ClassInstance::where('teacher_id', $teacher->id)
                ->where('student_id', $student->id)
                ->get();
            
            $attendedClasses = $studentClasses->where('status', 'attended');
            $totalHours = $attendedClasses->sum('duration') / 60; // Convert minutes to hours
            
            $student->total_hours = round($totalHours, 2);
            
            // Calculate activity level based on recent classes (last 30 days)
            $recentClasses = $studentClasses
                ->where('class_date', '>=', now()->subDays(30))
                ->where('status', 'attended')
                ->count();
            
            // Determine activity level
            if ($recentClasses >= 8) {
                $activityLevel = 'highly_active';
                $activeStudents++;
            } elseif ($recentClasses >= 4) {
                $activityLevel = 'medium';
                $lessActiveStudents++;
            } elseif ($recentClasses >= 1) {
                $activityLevel = 'low';
                $lessActiveStudents++;
            } else {
                $activityLevel = 'stopped';
                $stoppedStudents++;
            }
            
            $student->activity_level = $activityLevel;
            
            // Calculate salary contribution (total hours * hourly rate)
            $studentSalary = $totalHours * $teacher->hourly_rate;
            $totalSalary += $studentSalary;
            
            return $student;
        });

        // Calculate salary statistics
        $thisMonthClasses = ClassInstance::where('teacher_id', $teacher->id)
            ->whereMonth('class_date', now()->month)
            ->whereYear('class_date', now()->year)
            ->where('status', 'attended')
            ->get();
        
        $thisMonthHours = $thisMonthClasses->sum('duration') / 60;
        $thisMonthSalary = round($thisMonthHours * $teacher->hourly_rate, 2);

        return response()->json([
            'status' => 'success',
            'data' => $students,
            'stats' => [
                'total_students' => $students->count(),
                'active_students' => $activeStudents,
                'less_active_students' => $lessActiveStudents,
                'stopped_students' => $stoppedStudents,
                'total_salary' => round($totalSalary, 2),
                'this_month_salary' => $thisMonthSalary,
                'this_month_hours' => round($thisMonthHours, 2),
                'currency' => $teacher->currency,
            ],
        ]);
    }

    /**
     * Get evaluation options
     */
    public function getEvaluationOptions(): JsonResponse
    {
        $options = EvaluationOption::active()
            ->ordered()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $options,
        ]);
    }

    /**
     * Get duties assigned to teacher's students
     */
    public function duties(): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        $studentIds = DB::table('classes')
            ->where('teacher_id', $teacher->id)
            ->distinct()
            ->pluck('student_id')
            ->toArray();

        $duties = DB::table('duties')
            ->whereIn('student_id', $studentIds)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $duties,
        ]);
    }

    /**
     * Get teacher's own profile
     */
    public function profile(): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        $profile = $this->teacherService->getTeacherProfile($teacher->id);

        return response()->json([
            'status' => 'success',
            'data' => $profile,
        ]);
    }

    /**
     * Get teacher's own performance stats
     */
    public function performance(Request $request): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $performance = $this->teacherService->getTeacherPerformance($teacher->id, $dateFrom, $dateTo);

        return response()->json([
            'status' => 'success',
            'data' => $performance,
        ]);
    }

    /**
     * Get teacher's availability
     */
    public function getAvailability(): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $availability = $teacher->availability()
            ->where('is_available', true)
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $availability,
        ]);
    }

    /**
     * Update teacher's availability
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $request->validate([
            'availability' => 'required|array',
            'availability.*.day_of_week' => 'required|integer|min:1|max:7',
            'availability.*.start_time' => 'required|date_format:H:i',
            'availability.*.end_time' => 'required|date_format:H:i|after:availability.*.start_time',
            'availability.*.timezone' => 'nullable|string|max:255',
            'availability.*.is_available' => 'boolean',
        ]);

        // Delete existing availability
        $teacher->availability()->delete();

        // Create new availability slots
        foreach ($request->input('availability', []) as $slot) {
            $teacher->availability()->create([
                'day_of_week' => $slot['day_of_week'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'timezone' => $slot['timezone'] ?? $teacher->timezone ?? 'UTC',
                'is_available' => $slot['is_available'] ?? true,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Availability updated successfully',
            'data' => $teacher->availability()->get(),
        ]);
    }

    /**
     * Get teacher's assigned trials
     */
    public function getTrials(Request $request): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        $query = TrialClass::where('teacher_id', $teacher->id)
            ->with(['student', 'teacher.user', 'course']);

        // Filter by status
        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('trial_date', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->where('trial_date', '<=', $request->input('date_to'));
        }

        // Search by student name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $trials = $query->orderBy('trial_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        // Add can_enter_meet flag to each trial
        $trials->transform(function ($trial) {
            $trial->can_enter_meet = $this->classService->canEnterTrial($trial);
            return $trial;
        });

        // Calculate statistics
        $totalTrials = $trials->count();
        $pendingTrials = $trials->where('status', 'pending')->count();
        $pendingReviewTrials = $trials->where('status', 'pending_review')->count();
        $completedTrials = $trials->where('status', 'completed')->count();
        $noShowTrials = $trials->where('status', 'no_show')->count();
        $convertedTrials = $trials->where('status', 'converted')->count();
        
        // Successful trials = completed + converted
        $successfulTrials = $completedTrials + $convertedTrials;
        
        // Unsuccessful trials = no_show
        $unsuccessfulTrials = $noShowTrials;
        
        // Conversion rate (converted / completed)
        $conversionRate = $completedTrials > 0 
            ? round(($convertedTrials / $completedTrials) * 100, 2) 
            : 0;

        return response()->json([
            'status' => 'success',
            'data' => $trials,
            'stats' => [
                'total_trials' => $totalTrials,
                'pending_trials' => $pendingTrials,
                'pending_review_trials' => $pendingReviewTrials,
                'completed_trials' => $completedTrials,
                'no_show_trials' => $noShowTrials,
                'converted_trials' => $convertedTrials,
                'successful_trials' => $successfulTrials,
                'unsuccessful_trials' => $unsuccessfulTrials,
                'conversion_rate' => $conversionRate,
            ],
        ]);
    }

    /**
     * Get single trial details
     */
    public function getTrial(int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $trial = TrialClass::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->with(['student', 'course', 'convertedPackage'])
            ->firstOrFail();

        // Add can_enter_meet flag
        $trial->can_enter_meet = $this->classService->canEnterTrial($trial);

        return response()->json([
            'status' => 'success',
            'data' => $trial,
        ]);
    }

    /**
     * Submit trial for review (teacher can only submit pending trials)
     */
    public function submitTrialForReview(Request $request, int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $request->validate([
            'notes' => 'required|string|max:5000',
        ]);

        $trial = TrialClass::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Only pending trials can be submitted for review
        if ($trial->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending trials can be submitted for review',
            ], 400);
        }

        $trial->status = 'pending_review';
        $trial->notes = $request->input('notes');
        $trial->save();

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'student_id' => $trial->student_id,
            'action' => 'submit_trial_review',
            'description' => "Trial class submitted for review for student {$trial->student->full_name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Trial submitted for review successfully',
            'data' => $trial->fresh()->load(['student', 'course']),
        ]);
    }

    /**
     * Enter trial (mark as entered)
     */
    public function enterTrial(int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $trial = TrialClass::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Validate trial time has started
        if (!$this->classService->canEnterTrial($trial)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Trial time has not started yet',
            ], 400);
        }

        // Check if already entered
        if ($trial->meet_link_used) {
            return response()->json([
                'status' => 'error',
                'message' => 'Trial already marked as entered',
            ], 400);
        }

        $this->classService->enterTrial($trial);

        return response()->json([
            'status' => 'success',
            'message' => 'Trial marked as entered successfully',
            'data' => $trial->fresh()->load(['student', 'course']),
        ]);
    }

    /**
     * Get current authenticated teacher
     */
    private function getCurrentTeacher(): Teacher
    {
        $user = Auth::user();
        
        if ($user->role !== 'teacher') {
            abort(403, 'Only teachers can access this resource');
        }

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            abort(404, 'Teacher profile not found');
        }

        return $teacher;
    }
}
