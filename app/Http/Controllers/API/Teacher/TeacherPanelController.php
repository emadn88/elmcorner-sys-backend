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
     * Map common short timezone names to valid PHP timezone identifiers
     */
    private function resolveTimezone(?string $timezone): string
    {
        if (empty($timezone)) {
            return config('app.timezone', 'UTC');
        }

        // Map of common short names to valid PHP timezone identifiers
        $timezoneMap = [
            'Cairo' => 'Africa/Cairo',
            'cairo' => 'Africa/Cairo',
            'Riyadh' => 'Asia/Riyadh',
            'riyadh' => 'Asia/Riyadh',
            'Dubai' => 'Asia/Dubai',
            'dubai' => 'Asia/Dubai',
            'Istanbul' => 'Europe/Istanbul',
            'istanbul' => 'Europe/Istanbul',
            'London' => 'Europe/London',
            'london' => 'Europe/London',
        ];

        if (isset($timezoneMap[$timezone])) {
            return $timezoneMap[$timezone];
        }

        // Validate the timezone
        try {
            new \DateTimeZone($timezone);
            return $timezone;
        } catch (\Exception $e) {
            return config('app.timezone', 'UTC');
        }
    }

    /**
     * Get teacher's dashboard data
     */
    public function dashboard(): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();

        // Use teacher's timezone to determine "today"
        $teacherTimezone = $this->resolveTimezone($teacher->timezone);
        $today = Carbon::now($teacherTimezone)->format('Y-m-d');
        
        $todayClasses = $teacher->classes()
            ->where('class_date', $today)
            ->with(['student', 'course'])
            ->orderBy('start_time', 'asc')
            ->get();

        $upcomingClasses = $teacher->classes()
            ->where('class_date', '>=', $today)
            ->where('class_date', '<=', Carbon::now($teacherTimezone)->addDays(7)->format('Y-m-d'))
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
            ->whereMonth('class_date', Carbon::now($teacherTimezone)->month)
            ->whereYear('class_date', Carbon::now($teacherTimezone)->year)
            ->get();

        // Calculate this month's hours from all classes (not just attended)
        $thisMonthHours = $thisMonthClasses->sum('duration') / 60; // Convert minutes to hours
        $thisMonthHours = round($thisMonthHours, 2);

        // Count only pending classes for TODAY
        $pendingClasses = $teacher->classes()
            ->where('status', 'pending')
            ->where('class_date', $today)
            ->count();

        // Calculate attendance rate
        // Exclude classes cancelled by students from the calculation
        $allClasses = $teacher->classes()->get();
        $attendedCount = $allClasses->where('status', 'attended')->count();
        // Total classes excluding those cancelled by students (student cancellations shouldn't count against attendance)
        $totalClassesForAttendance = $allClasses->reject(function ($class) {
            return $class->status === 'cancelled_by_student';
        })->count();
        $attendanceRate = $totalClassesForAttendance > 0 
            ? round(($attendedCount / $totalClassesForAttendance) * 100, 2) 
            : 0;

        // Calculate punctuality rate (based on when teacher joined meet vs class start time)
        $punctualityData = $this->calculatePunctualityRate($allClasses);

        // Calculate report submission rate (based on when report was sent vs class end time)
        $reportSubmissionData = $this->calculateReportSubmissionRate($allClasses);

        // Calculate total hours from attended classes AND approved cancellations (both count for salary)
        // Rejected cancellations and classes cancelled by teacher/admin do NOT count for salary
        $attendedClasses = $allClasses->where('status', 'attended');
        $approvedCancellations = $allClasses->where('status', 'cancelled_by_student')
            ->where('cancellation_request_status', 'approved');
        
        // Combine attended and approved cancellations for salary calculation
        $classesForSalary = $attendedClasses->merge($approvedCancellations);
        $totalHours = $classesForSalary->sum('duration') / 60; // Convert minutes to hours
        $totalHours = round($totalHours, 2);
        
        // Get classes for display (attended + approved cancellations)
        // Rejected cancellations and classes cancelled by teacher/admin are excluded
        $attendedClassesForDisplay = $teacher->classes()
            ->where(function($query) {
                $query->where('status', 'attended')
                    ->orWhere(function($q) {
                        $q->where('status', 'cancelled_by_student')
                          ->where('cancellation_request_status', 'approved');
                    });
            })
            ->with(['student', 'course'])
            ->orderBy('class_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        // Calculate total salary (total hours * hourly rate)
        $totalSalary = round($totalHours * ($teacher->hourly_rate ?? 0), 2);

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
                    'punctuality_rate' => $punctualityData['rate'],
                    'punctuality_score' => $punctualityData['score'],
                    'on_time_classes' => $punctualityData['on_time'],
                    'late_classes' => $punctualityData['late'],
                    'very_late_classes' => $punctualityData['very_late'],
                    'report_submission_rate' => $reportSubmissionData['rate'],
                    'report_submission_score' => $reportSubmissionData['score'],
                    'immediate_reports' => $reportSubmissionData['immediate'],
                    'late_reports' => $reportSubmissionData['late'],
                    'very_late_reports' => $reportSubmissionData['very_late'],
                    'total_hours' => $totalHours,
                    'total_salary' => $totalSalary,
                    'total_classes' => $totalClasses,
                    'total_courses' => $totalCourses,
                    'currency' => $teacher->currency,
                ],
                'today_classes' => $todayClasses,
                'upcoming_classes' => $upcomingClasses,
                'attended_classes' => $attendedClassesForDisplay, // Return attended classes used for calculation
            ],
        ]);
    }

    /**
     * Get teacher's classes with filters (default: today's classes)
     * Filter options: 'past' - shows only past classes
     */
    public function getClasses(Request $request): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        // Use teacher's timezone to determine "today"
        $teacherTimezone = $this->resolveTimezone($teacher->timezone);
        $today = Carbon::now($teacherTimezone)->format('Y-m-d');

        $query = $teacher->classes()->with(['student', 'course', 'package']);

        // Check for filter parameter: 'past' shows only past classes, default shows today's classes
        $filter = $request->input('filter', 'today');
        
        if ($filter === 'past') {
            // Show only past classes (before today)
            $query->where('class_date', '<', $today);
        } else {
            // Default: show only today's classes
            $query->where('class_date', $today);
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
     * Submit class report
     */
    public function submitClassReport(Request $request, int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $request->validate([
            'status' => 'required|in:attended,cancelled',
            'student_evaluation' => 'required_if:status,attended|string|max:500',
            'class_report' => 'required_if:status,attended|string',
            'notes' => 'nullable|string',
            'send_whatsapp' => 'required|boolean',
        ]);

        $class = ClassInstance::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->with(['student', 'course'])
            ->firstOrFail();

        // Check if meet was entered
        if (!$class->meet_link_used) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must start the class first',
            ], 400);
        }

        // Update class details
        if ($request->input('status') === 'attended') {
            // Only update status if not already attended (to avoid double package deduction)
            if ($class->status !== 'attended') {
                // Use ClassService to update status (handles package deduction and billing)
                $this->mainClassService->updateClassStatus($class->id, 'attended', Auth::id());
            }
            
            // Refresh class to get updated data
            $class->refresh();
            
            // Update report-specific fields
            $class->student_evaluation = $request->input('student_evaluation');
            $class->class_report = $request->input('class_report');
            $class->notes = $request->input('notes');
            // Track when report is submitted (only if WhatsApp is sent, as per requirement)
            if ($request->input('send_whatsapp')) {
                $class->report_submitted_at = now();
            }
            $class->save();

            // Check if package was finished and send bill notification after report is saved
            if ($class->package_id) {
                $package = \App\Models\Package::with('student')->find($class->package_id);
                if ($package && $package->status === 'finished') {
                    try {
                        $packageService = app(\App\Services\PackageService::class);
                        $packageService->sendAutomaticBillNotification($package->fresh());
                    } catch (\Exception $e) {
                        // Log error but don't fail the request
                        \Log::error('Failed to send automatic bill notification after report', [
                            'package_id' => $package->id,
                            'class_id' => $class->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Send WhatsApp if requested
            if ($request->input('send_whatsapp')) {
                try {
                    $this->sendReportViaWhatsApp($class);
                } catch (\Exception $e) {
                    // Log error but don't fail the request
                    \Log::error('Failed to send report via WhatsApp', [
                        'class_id' => $class->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Class report submitted successfully',
            'data' => $class->fresh(),
        ]);
    }

    /**
     * Request class cancellation (creates admin notification)
     */
    public function requestClassCancellation(Request $request, int $id): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $class = ClassInstance::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Check if class can be cancelled
        if (in_array($class->status, ['cancelled_by_student', 'cancelled_by_teacher', 'attended'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'This class cannot be cancelled',
            ], 400);
        }

        // Create cancellation request (creates notification for admin)
        $this->classService->requestCancellation($class, $request->input('reason'), Auth::id());

        return response()->json([
            'status' => 'success',
            'message' => 'Cancellation request submitted. Waiting for admin approval.',
            'data' => $class->fresh(),
        ]);
    }

    /**
     * Send class report via WhatsApp
     */
    protected function sendReportViaWhatsApp(ClassInstance $class): void
    {
        $student = $class->student;
        if (!$student || !$student->whatsapp) {
            throw new \Exception('Student WhatsApp number not found');
        }

        // Get student language
        $language = strtolower($student->language ?? 'ar');
        if (!in_array($language, ['ar', 'en', 'fr'])) {
            $language = 'ar';
        }

        // Generate report message based on language
        $message = $this->generateReportMessage($class, $language);

        // Send via WhatsApp service
        $whatsAppService = app(\App\Services\WhatsAppService::class);
        $whatsAppService->sendMessage($student->whatsapp, $message);
    }

    /**
     * Generate report message in specified language
     */
    protected function generateReportMessage(ClassInstance $class, string $language): string
    {
        $academyName = config('app.name', 'Elm Corner Academy');
        $supportPhone = config('whatsapp.support_phone', '+201099471391');
        $studentName = $class->student->full_name ?? 'Student';
        $courseName = $class->course->name ?? 'Course';
        $evaluation = $class->student_evaluation ?? '';
        $report = $class->class_report ?? '';
        
        // Format class date with day name and date
        $dayName = '';
        $formattedDate = '';
        if ($class->class_date) {
            $date = Carbon::parse($class->class_date);
            if ($language === 'en') {
                $dayName = $date->format('l'); // Full day name (Monday, Tuesday, etc.)
                $formattedDate = $date->format('F d, Y'); // Month day, year (January 15, 2024)
            } elseif ($language === 'fr') {
                $date->locale('fr');
                $dayName = $date->translatedFormat('l'); // Jour de la semaine
                $formattedDate = $date->translatedFormat('d F Y'); // 15 janvier 2024
            } else {
                // Arabic
                $date->locale('ar');
                $dayName = $date->translatedFormat('l'); // يوم الأسبوع
                $formattedDate = $date->translatedFormat('d F Y'); // ١٥ يناير ٢٠٢٤
            }
        }
        
        // Map evaluation to readable text
        $evaluationText = '';
        if ($evaluation) {
            $evaluationMap = [
                'good' => 'Good',
                'very_good' => 'Very Good',
                'excellent' => 'Excellent',
            ];
            $evaluationText = $evaluationMap[$evaluation] ?? ucfirst($evaluation);
        }

        if ($language === 'en') {
            return <<<MSG
🎓 *ELM CORNER ACADEMY*

📋 *Class Report*

👤 *Student:* {$studentName}
📚 *Course:* {$courseName}
📅 *Date:* {$dayName}, {$formattedDate}
⭐ *Evaluation:* {$evaluationText}

📝 *Report:*
{$report}

📞 *Support:* {$supportPhone}
MSG;
        } elseif ($language === 'fr') {
            return <<<MSG
🎓 *ELM CORNER ACADEMY*

📋 *Rapport de Classe*

👤 *Étudiant:* {$studentName}
📚 *Cours:* {$courseName}
📅 *Date:* {$dayName}, {$formattedDate}
⭐ *Évaluation:* {$evaluationText}

📝 *Rapport:*
{$report}

📞 *Support:* {$supportPhone}
MSG;
        } else {
            // Arabic (default)
            $evaluationTextAr = '';
            if ($evaluation) {
                $evaluationMapAr = [
                    'good' => 'جيد',
                    'very_good' => 'جيد جداً',
                    'excellent' => 'ممتاز',
                ];
                $evaluationTextAr = $evaluationMapAr[$evaluation] ?? $evaluation;
            }
            
            return <<<MSG
🎓 *أكاديمية إلم كورنر*

📋 *تقرير الحصة*

👤 *الطالب:* {$studentName}
📚 *الدورة:* {$courseName}
📅 *التاريخ:* {$dayName}، {$formattedDate}
⭐ *التقييم:* {$evaluationTextAr}

📝 *التقرير:*
{$report}

📞 *الدعم:* {$supportPhone}
MSG;
        }
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

        // Validate trial can be entered (allows pending trials at any time)
        if (!$this->classService->canEnterTrial($trial)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Trial cannot be entered at this time',
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
     * Calculate punctuality rate based on when teacher joined meet vs class start time
     */
    private function calculatePunctualityRate($classes): array
    {
        $onTime = 0;
        $late = 0;
        $veryLate = 0;
        $totalJoined = 0;

        foreach ($classes as $class) {
            // Only count classes where teacher actually joined (has meet_link_accessed_at)
            if (!$class->meet_link_accessed_at) {
                continue;
            }

            // Skip if required date/time fields are missing
            if (!$class->class_date || !$class->start_time) {
                continue;
            }

            $totalJoined++;

            try {
                // Get class start datetime - handle both Carbon instances and strings
                $classDate = $class->class_date instanceof Carbon 
                    ? $class->class_date->format('Y-m-d') 
                    : Carbon::parse($class->class_date)->format('Y-m-d');
                
                $startTime = $class->start_time instanceof Carbon 
                    ? $class->start_time->format('H:i:s') 
                    : (is_string($class->start_time) ? $class->start_time : Carbon::parse($class->start_time)->format('H:i:s'));
                
                $classStartTime = Carbon::parse($classDate . ' ' . $startTime);
                
                // Get when teacher joined
                $joinedTime = Carbon::parse($class->meet_link_accessed_at);
                
                // Calculate minutes difference (positive = joined after start, negative = joined before start)
                // diffInMinutes returns absolute difference, so we need to check which is later
                if ($joinedTime->lte($classStartTime)) {
                    // Joined on time or before start time
                    $onTime++;
                } else {
                    // Joined after start time - calculate how many minutes late
                    $minutesLate = $joinedTime->diffInMinutes($classStartTime);
                    
                    if ($minutesLate <= 10) {
                        // Joined late but within 10 minutes
                        $late++;
                    } else {
                        // Joined very late (more than 10 minutes)
                        $veryLate++;
                    }
                }
            } catch (\Exception $e) {
                // Skip this class if there's an error parsing dates
                $totalJoined--;
                continue;
            }
        }

        // Calculate punctuality rate (percentage of on-time joins)
        $punctualityRate = $totalJoined > 0 
            ? round(($onTime / $totalJoined) * 100, 2) 
            : 0;

        // Calculate punctuality score (weighted: on-time = 100, late = 50, very late = 0)
        // Score = (onTime * 100 + late * 50 + veryLate * 0) / totalJoined
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
     * Calculate report submission rate based on when report was sent vs class end time
     */
    private function calculateReportSubmissionRate($classes): array
    {
        $immediate = 0; // Sent within 5 minutes of class end
        $late = 0; // Sent 5-10 minutes after class end
        $veryLate = 0; // Sent more than 10 minutes after class end
        $totalReports = 0;

        foreach ($classes as $class) {
            // Only count classes where report was actually submitted
            if (!$class->report_submitted_at) {
                continue;
            }

            // Skip if required date/time fields are missing
            if (!$class->class_date || !$class->end_time) {
                continue;
            }

            $totalReports++;

            try {
                // Get class end datetime - handle both Carbon instances and strings
                $classDate = $class->class_date instanceof Carbon 
                    ? $class->class_date->format('Y-m-d') 
                    : Carbon::parse($class->class_date)->format('Y-m-d');
                
                $endTime = $class->end_time instanceof Carbon 
                    ? $class->end_time->format('H:i:s') 
                    : (is_string($class->end_time) ? $class->end_time : Carbon::parse($class->end_time)->format('H:i:s'));
                
                $classEndTime = Carbon::parse($classDate . ' ' . $endTime);
                
                // Get when report was submitted
                $reportSubmittedTime = Carbon::parse($class->report_submitted_at);
                
                // Calculate minutes difference (positive = submitted after class end)
                if ($reportSubmittedTime->lte($classEndTime)) {
                    // Submitted before or at class end time (immediate)
                    $immediate++;
                } else {
                    $minutesAfterEnd = $reportSubmittedTime->diffInMinutes($classEndTime);
                    
                    if ($minutesAfterEnd <= 5) {
                        // Submitted within 5 minutes after class end
                        $immediate++;
                    } elseif ($minutesAfterEnd <= 10) {
                        // Submitted 5-10 minutes after class end
                        $late++;
                    } else {
                        // Submitted more than 10 minutes after class end
                        $veryLate++;
                    }
                }
            } catch (\Exception $e) {
                // Skip this class if there's an error parsing dates
                $totalReports--;
                continue;
            }
        }

        // Calculate report submission rate (percentage of immediate submissions)
        $reportSubmissionRate = $totalReports > 0 
            ? round(($immediate / $totalReports) * 100, 2) 
            : 0;

        // Calculate report submission score (weighted: immediate = 100, late = 70, very late = 40)
        // Score decreases by 5 points for every 5 minutes after class end
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
     * Get monthly rate details (punctuality or report submission)
     */
    public function getMonthlyRateDetails(Request $request): JsonResponse
    {
        $teacher = $this->getCurrentTeacher();
        $rateType = $request->input('type', 'punctuality'); // 'punctuality' or 'report_submission'
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        // Get classes for the specified month
        $monthClasses = $teacher->classes()
            ->whereYear('class_date', $year)
            ->whereMonth('class_date', $month)
            ->with(['student', 'course'])
            ->get();

        if ($rateType === 'punctuality') {
            $data = $this->calculatePunctualityRate($monthClasses);
            
            // Get detailed class information
            $classes = [];
            foreach ($monthClasses as $class) {
                if (!$class->meet_link_accessed_at) {
                    continue;
                }
                
                // Skip if required date/time fields are missing
                if (!$class->class_date || !$class->start_time) {
                    continue;
                }
                
                try {
                    // Get class start datetime - handle both Carbon instances and strings
                    $classDate = $class->class_date instanceof Carbon 
                        ? $class->class_date->format('Y-m-d') 
                        : Carbon::parse($class->class_date)->format('Y-m-d');
                    
                    $startTime = $class->start_time instanceof Carbon 
                        ? $class->start_time->format('H:i:s') 
                        : (is_string($class->start_time) ? $class->start_time : Carbon::parse($class->start_time)->format('H:i:s'));
                    
                    $classStartTime = Carbon::parse($classDate . ' ' . $startTime);
                    $joinedTime = Carbon::parse($class->meet_link_accessed_at);
                
                    $status = 'on_time';
                    $minutesLate = 0;
                    
                    if ($joinedTime->gt($classStartTime)) {
                        $minutesLate = $joinedTime->diffInMinutes($classStartTime);
                        if ($minutesLate <= 10) {
                            $status = 'late';
                        } else {
                            $status = 'very_late';
                        }
                    }
                    
                    $classes[] = [
                        'id' => $class->id,
                        'student_name' => $class->student->full_name ?? 'N/A',
                        'course_name' => $class->course->name ?? 'N/A',
                        'class_date' => $classDate,
                        'start_time' => $startTime,
                        'joined_time' => $joinedTime->format('Y-m-d H:i:s'),
                        'status' => $status,
                        'minutes_late' => $minutesLate,
                    ];
                } catch (\Exception $e) {
                    // Skip this class if there's an error parsing dates
                    continue;
                }
            }
            
            $data['classes'] = $classes;
        } else {
            $data = $this->calculateReportSubmissionRate($monthClasses);
            
            // Get detailed class information
            $classes = [];
            foreach ($monthClasses as $class) {
                if (!$class->report_submitted_at) {
                    continue;
                }
                
                // Skip if required date/time fields are missing
                if (!$class->class_date || !$class->end_time) {
                    continue;
                }
                
                try {
                    // Get class end datetime - handle both Carbon instances and strings
                    $classDate = $class->class_date instanceof Carbon 
                        ? $class->class_date->format('Y-m-d') 
                        : Carbon::parse($class->class_date)->format('Y-m-d');
                    
                    $endTime = $class->end_time instanceof Carbon 
                        ? $class->end_time->format('H:i:s') 
                        : (is_string($class->end_time) ? $class->end_time : Carbon::parse($class->end_time)->format('H:i:s'));
                    
                    $classEndTime = Carbon::parse($classDate . ' ' . $endTime);
                    $reportSubmittedTime = Carbon::parse($class->report_submitted_at);
                    
                    $status = 'immediate';
                    $minutesAfterEnd = 0;
                    
                    if ($reportSubmittedTime->gt($classEndTime)) {
                        $minutesAfterEnd = $reportSubmittedTime->diffInMinutes($classEndTime);
                        if ($minutesAfterEnd <= 5) {
                            $status = 'immediate';
                        } elseif ($minutesAfterEnd <= 10) {
                            $status = 'late';
                        } else {
                            $status = 'very_late';
                        }
                    }
                    
                    $classes[] = [
                        'id' => $class->id,
                        'student_name' => $class->student->full_name ?? 'N/A',
                        'course_name' => $class->course->name ?? 'N/A',
                        'class_date' => $classDate,
                        'end_time' => $endTime,
                        'submitted_time' => $reportSubmittedTime->format('Y-m-d H:i:s'),
                        'status' => $status,
                        'minutes_after_end' => $minutesAfterEnd,
                    ];
                } catch (\Exception $e) {
                    // Skip this class if there's an error parsing dates
                    continue;
                }
            }
            
            $data['classes'] = $classes;
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'month' => $month,
            'year' => $year,
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
