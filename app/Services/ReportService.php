<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Course;
use App\Models\Package;
use App\Models\ClassInstance;
use App\Models\Bill;
use App\Models\Duty;
use App\Services\StudentService;
use App\Services\WhatsAppService;
use App\Services\SalaryService;
use App\Services\FinancialService;
use App\Models\Family;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportService
{
    protected $studentService;
    protected $whatsAppService;
    protected $salaryService;
    protected $financialService;

    public function __construct(
        StudentService $studentService,
        WhatsAppService $whatsAppService,
        SalaryService $salaryService,
        FinancialService $financialService
    ) {
        $this->studentService = $studentService;
        $this->whatsAppService = $whatsAppService;
        $this->salaryService = $salaryService;
        $this->financialService = $financialService;
    }

    /**
     * Generate student report (single student)
     */
    public function generateStudentReport(int $studentId, string $type, ?array $dateRange = null, ?string $currency = null): Report
    {
        $student = Student::with(['family', 'packages', 'timetables'])->findOrFail($studentId);
        
        $dateFrom = $dateRange['from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        // Get classes in date range
        $classesQuery = ClassInstance::where('student_id', $studentId)
            ->whereBetween('class_date', [$dateFrom, $dateTo]);
        
        $classes = $classesQuery->with(['teacher', 'course'])->get();
        $attendedClasses = $classes->where('status', 'attended');
        $cancelledClasses = $classes->whereIn('status', ['cancelled_by_student', 'cancelled_by_teacher']);
        
        // Get packages in date range
        $packages = Package::where('student_id', $studentId)
            ->where(function($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('start_date', [$dateFrom, $dateTo])
                  ->orWhereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->get();
        
        // Get bills in date range
        $billsQuery = Bill::where('student_id', $studentId)
            ->whereBetween('bill_date', [$dateFrom, $dateTo]);
        
        if ($currency) {
            $billsQuery->where('currency', $currency);
        }
        
        $bills = $billsQuery->get();
        
        // Get duties in date range
        $duties = Duty::where('student_id', $studentId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        // Calculate statistics
        $totalClasses = $classes->count();
        $attendedCount = $attendedClasses->count();
        $attendanceRate = $totalClasses > 0 ? ($attendedCount / $totalClasses) * 100 : 0;
        
        $totalHours = $attendedClasses->sum('duration') / 60; // Convert minutes to hours
        $totalRevenue = $bills->where('status', 'paid')->sum('amount');
        $pendingRevenue = $bills->where('status', 'pending')->sum('amount');
        
        $activityLevel = $this->studentService->getActivityLevel($studentId);

        $content = [
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'email' => $student->email,
                'whatsapp' => $student->whatsapp,
                'status' => $student->status,
                'activity_level' => $activityLevel,
            ],
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'statistics' => [
                'total_classes' => $totalClasses,
                'attended_classes' => $attendedCount,
                'cancelled_classes' => $cancelledClasses->count(),
                'attendance_rate' => round($attendanceRate, 2),
                'total_hours' => round($totalHours, 2),
                'total_packages' => $packages->count(),
                'active_packages' => $packages->where('status', 'active')->count(),
                'total_bills' => $bills->count(),
                'paid_bills' => $bills->where('status', 'paid')->count(),
                'pending_bills' => $bills->where('status', 'pending')->count(),
                'total_revenue' => $totalRevenue,
                'pending_revenue' => $pendingRevenue,
                'total_duties' => $duties->count(),
            ],
            'packages' => $packages->map(function($package) {
                return [
                    'id' => $package->id,
                    'round_number' => $package->round_number,
                    'total_classes' => $package->total_classes,
                    'remaining_classes' => $package->remaining_classes,
                    'total_hours' => $package->total_hours,
                    'remaining_hours' => $package->remaining_hours,
                    'status' => $package->status,
                    'start_date' => $package->start_date->format('Y-m-d'),
                ];
            }),
            'classes_summary' => [
                'by_status' => $classes->groupBy('status')->map->count(),
                'by_course' => $classes->groupBy('course_id')->map->count(),
            ],
            'bills_summary' => [
                'total_amount' => $bills->sum('amount'),
                'paid_amount' => $bills->where('status', 'paid')->sum('amount'),
                'pending_amount' => $bills->where('status', 'pending')->sum('amount'),
            ],
            'generated_at' => now()->toIso8601String(),
            'currency' => $currency,
        ];

        return Report::create([
            'student_id' => $studentId,
            'report_type' => $type,
            'content' => $content,
        ]);
    }

    /**
     * Generate multiple students report
     */
    public function generateMultipleStudentsReport(array $studentIds, ?array $dateRange = null, ?string $currency = null): Report
    {
        $dateFrom = $dateRange['from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        $students = Student::whereIn('id', $studentIds)->with('family')->get();
        
        $studentsData = [];
        $totalStats = [
            'total_classes' => 0,
            'attended_classes' => 0,
            'total_revenue' => 0,
            'total_bills' => 0,
        ];

        foreach ($students as $student) {
            $classesQuery = ClassInstance::where('student_id', $student->id)
                ->whereBetween('class_date', [$dateFrom, $dateTo]);
            $classes = $classesQuery->get();
            $attendedClasses = $classes->where('status', 'attended');
            
            $billsQuery = Bill::where('student_id', $student->id)
                ->whereBetween('bill_date', [$dateFrom, $dateTo]);
            if ($currency) {
                $billsQuery->where('currency', $currency);
            }
            $bills = $billsQuery->get();
            
            $studentData = [
                'id' => $student->id,
                'name' => $student->full_name,
                'total_classes' => $classes->count(),
                'attended_classes' => $attendedClasses->count(),
                'attendance_rate' => $classes->count() > 0 ? round(($attendedClasses->count() / $classes->count()) * 100, 2) : 0,
                'total_revenue' => $bills->where('status', 'paid')->sum('amount'),
                'total_bills' => $bills->count(),
            ];
            
            $studentsData[] = $studentData;
            $totalStats['total_classes'] += $studentData['total_classes'];
            $totalStats['attended_classes'] += $studentData['attended_classes'];
            $totalStats['total_revenue'] += $studentData['total_revenue'];
            $totalStats['total_bills'] += $studentData['total_bills'];
        }

        $content = [
            'report_type' => 'students_multiple',
            'students' => $studentsData,
            'total_statistics' => $totalStats,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'currency' => $currency,
            'generated_at' => now()->toIso8601String(),
        ];

        return Report::create([
            'report_type' => 'students_multiple',
            'content' => $content,
        ]);
    }

    /**
     * Generate family report
     */
    public function generateFamilyReport(int $familyId, ?array $dateRange = null, ?string $currency = null): Report
    {
        $family = Family::with('students')->findOrFail($familyId);
        $dateFrom = $dateRange['from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        $studentsData = [];
        $totalStats = [
            'total_classes' => 0,
            'attended_classes' => 0,
            'total_revenue' => 0,
            'total_bills' => 0,
        ];

        foreach ($family->students as $student) {
            $classesQuery = ClassInstance::where('student_id', $student->id)
                ->whereBetween('class_date', [$dateFrom, $dateTo]);
            $classes = $classesQuery->get();
            $attendedClasses = $classes->where('status', 'attended');
            
            $billsQuery = Bill::where('student_id', $student->id)
                ->whereBetween('bill_date', [$dateFrom, $dateTo]);
            if ($currency) {
                $billsQuery->where('currency', $currency);
            }
            $bills = $billsQuery->get();
            
            $studentData = [
                'id' => $student->id,
                'name' => $student->full_name,
                'total_classes' => $classes->count(),
                'attended_classes' => $attendedClasses->count(),
                'attendance_rate' => $classes->count() > 0 ? round(($attendedClasses->count() / $classes->count()) * 100, 2) : 0,
                'total_revenue' => $bills->where('status', 'paid')->sum('amount'),
                'total_bills' => $bills->count(),
            ];
            
            $studentsData[] = $studentData;
            $totalStats['total_classes'] += $studentData['total_classes'];
            $totalStats['attended_classes'] += $studentData['attended_classes'];
            $totalStats['total_revenue'] += $studentData['total_revenue'];
            $totalStats['total_bills'] += $studentData['total_bills'];
        }

        $content = [
            'family' => [
                'id' => $family->id,
                'name' => $family->name,
            ],
            'students' => $studentsData,
            'total_statistics' => $totalStats,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'currency' => $currency,
            'generated_at' => now()->toIso8601String(),
        ];

        return Report::create([
            'report_type' => 'students_family',
            'content' => $content,
        ]);
    }

    /**
     * Generate all students report
     */
    public function generateAllStudentsReport(?array $dateRange = null, ?string $currency = null): Report
    {
        $dateFrom = $dateRange['from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        $students = Student::with('family')->get();
        
        $studentsData = [];
        $totalStats = [
            'total_students' => $students->count(),
            'active_students' => 0,
            'total_classes' => 0,
            'attended_classes' => 0,
            'total_revenue' => 0,
            'total_bills' => 0,
        ];

        foreach ($students as $student) {
            if ($student->status === 'active') {
                $totalStats['active_students']++;
            }
            
            $classesQuery = ClassInstance::where('student_id', $student->id)
                ->whereBetween('class_date', [$dateFrom, $dateTo]);
            $classes = $classesQuery->get();
            $attendedClasses = $classes->where('status', 'attended');
            
            $billsQuery = Bill::where('student_id', $student->id)
                ->whereBetween('bill_date', [$dateFrom, $dateTo]);
            if ($currency) {
                $billsQuery->where('currency', $currency);
            }
            $bills = $billsQuery->get();
            
            $studentData = [
                'id' => $student->id,
                'name' => $student->full_name,
                'status' => $student->status,
                'total_classes' => $classes->count(),
                'attended_classes' => $attendedClasses->count(),
                'attendance_rate' => $classes->count() > 0 ? round(($attendedClasses->count() / $classes->count()) * 100, 2) : 0,
                'total_revenue' => $bills->where('status', 'paid')->sum('amount'),
                'total_bills' => $bills->count(),
            ];
            
            $studentsData[] = $studentData;
            $totalStats['total_classes'] += $studentData['total_classes'];
            $totalStats['attended_classes'] += $studentData['attended_classes'];
            $totalStats['total_revenue'] += $studentData['total_revenue'];
            $totalStats['total_bills'] += $studentData['total_bills'];
        }

        $content = [
            'students' => $studentsData,
            'total_statistics' => $totalStats,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'currency' => $currency,
            'generated_at' => now()->toIso8601String(),
        ];

        return Report::create([
            'report_type' => 'students_all',
            'content' => $content,
        ]);
    }

    /**
     * Generate salaries report
     */
    public function generateSalariesReport(?array $dateRange = null, ?int $teacherId = null, ?string $currency = null): Report
    {
        $dateFrom = $dateRange['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->endOfMonth()->format('Y-m-d');
        
        // Extract month and year from date range
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        
        $salariesData = [];
        $totalSalary = 0;
        $totalHours = 0;
        $totalClasses = 0;

        // Get salaries for each month in the range
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $month = $currentDate->format('m');
            $year = $currentDate->format('Y');
            
            $filters = [
                'month' => $month,
                'year' => $year,
            ];
            
            if ($teacherId) {
                $filters['teacher_id'] = $teacherId;
            }
            
            $monthSalaries = $this->salaryService->getTeachersSalaries($filters);
            
            // Filter by currency if specified
            if ($currency) {
                $monthSalaries = array_filter($monthSalaries, function($salary) use ($currency) {
                    return $salary['currency'] === $currency;
                });
            }
            
            foreach ($monthSalaries as $salary) {
                $salariesData[] = $salary;
                $totalSalary += $salary['salary'];
                $totalHours += $salary['total_hours'];
                $totalClasses += $salary['total_classes'];
            }
            
            $currentDate->addMonth();
        }

        $content = [
            'salaries' => $salariesData,
            'total_statistics' => [
                'total_salary' => round($totalSalary, 2),
                'total_hours' => round($totalHours, 2),
                'total_classes' => $totalClasses,
                'teacher_count' => count(array_unique(array_column($salariesData, 'teacher_id'))),
            ],
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'currency' => $currency,
            'generated_at' => now()->toIso8601String(),
        ];

        return Report::create([
            'teacher_id' => $teacherId,
            'report_type' => 'salaries',
            'content' => $content,
        ]);
    }

    /**
     * Generate income report
     */
    public function generateIncomeReport(?array $dateRange = null, ?string $currency = null): Report
    {
        $dateFrom = $dateRange['from'] ?? now()->subMonths(12)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        // Get income breakdown
        $incomeBreakdown = $this->financialService->getIncomeBreakdown($dateFrom, $dateTo, $currency, 'month');
        
        // Get income by currency if no currency specified
        $incomeByCurrency = $this->financialService->getIncomeByCurrency($dateFrom, $dateTo);
        
        // Get expense breakdown
        $expenseBreakdown = $this->financialService->getExpenseBreakdown($dateFrom, $dateTo, $currency);
        
        // Calculate net profit
        $netProfit = $this->financialService->calculateNetProfit($dateFrom, $dateTo, $currency);

        $content = [
            'income' => [
                'total' => $incomeBreakdown['paid_total'],
                'paid' => $incomeBreakdown['paid_total'],
                'pending' => $incomeBreakdown['pending_total'],
                'breakdown' => $incomeBreakdown['breakdown'],
                'by_currency' => $incomeByCurrency,
            ],
            'expenses' => [
                'total' => $expenseBreakdown['total'],
                'by_category' => $expenseBreakdown['by_category'],
                'by_month' => $expenseBreakdown['by_month'],
            ],
            'profit' => $netProfit,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'currency' => $currency,
            'generated_at' => now()->toIso8601String(),
        ];

        return Report::create([
            'report_type' => 'income',
            'content' => $content,
        ]);
    }

    /**
     * Generate course report (keeping for backward compatibility but will be removed)
     */
    public function generateCourseReport(int $courseId, ?array $dateRange = null): Report
    {
        $course = Course::with(['teachers'])->findOrFail($courseId);
        
        $dateFrom = $dateRange['from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        // Get classes in date range
        $classesQuery = ClassInstance::where('course_id', $courseId)
            ->whereBetween('class_date', [$dateFrom, $dateTo]);
        
        $classes = $classesQuery->with(['student', 'teacher'])->get();
        $attendedClasses = $classes->where('status', 'attended');
        
        // Get unique students
        $enrolledStudents = $classes->pluck('student_id')->unique()->count();
        
        // Get revenue from bills
        $bills = Bill::whereIn('class_id', $classes->pluck('id'))
            ->get();
        
        $totalRevenue = $bills->where('status', 'paid')->sum('amount');
        
        // Calculate metrics
        $totalClasses = $classes->count();
        $attendedCount = $attendedClasses->count();
        $attendanceRate = $totalClasses > 0 ? ($attendedCount / $totalClasses) * 100 : 0;
        $averageAttendance = $enrolledStudents > 0 ? ($attendedCount / $enrolledStudents) : 0;

        $content = [
            'course' => [
                'id' => $course->id,
                'name' => $course->name,
                'category' => $course->category,
                'status' => $course->status,
            ],
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'statistics' => [
                'total_classes' => $totalClasses,
                'attended_classes' => $attendedCount,
                'attendance_rate' => round($attendanceRate, 2),
                'enrolled_students' => $enrolledStudents,
                'average_attendance' => round($averageAttendance, 2),
                'total_revenue' => $totalRevenue,
                'active_teachers' => $course->teachers()->where('status', 'active')->count(),
            ],
            'classes_by_status' => $classes->groupBy('status')->map->count(),
            'classes_by_teacher' => $classes->groupBy('teacher_id')->map(function($teacherClasses) {
                return [
                    'count' => $teacherClasses->count(),
                    'attended' => $teacherClasses->where('status', 'attended')->count(),
                ];
            }),
            'generated_at' => now()->toIso8601String(),
        ];

        return Report::create([
            'report_type' => 'course_report',
            'content' => $content,
        ]);
    }

    /**
     * Generate teacher performance report
     */
    public function generateTeacherPerformanceReport(int $teacherId, ?array $dateRange = null, ?string $currency = null): Report
    {
        $teacher = Teacher::with(['user'])->findOrFail($teacherId);
        
        $dateFrom = $dateRange['from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        // Get classes in date range
        $classesQuery = ClassInstance::where('teacher_id', $teacherId)
            ->whereBetween('class_date', [$dateFrom, $dateTo]);
        
        $classes = $classesQuery->with(['student', 'course'])->get();
        $attendedClasses = $classes->where('status', 'attended');
        
        // Get unique students
        $studentCount = $classes->pluck('student_id')->unique()->count();
        
        // Get bills for revenue calculation
        $billsQuery = Bill::where('teacher_id', $teacherId)
            ->whereBetween('bill_date', [$dateFrom, $dateTo])
            ->where('status', 'paid');
        
        if ($currency) {
            $billsQuery->where('currency', $currency);
        }
        
        $bills = $billsQuery->get();
        
        $totalRevenue = $bills->sum('amount');
        $totalHours = $attendedClasses->sum('duration') / 60; // Convert minutes to hours
        $salaryEarned = $totalHours * $teacher->hourly_rate;
        
        // Calculate performance metrics
        $totalClasses = $classes->count();
        $attendedCount = $attendedClasses->count();
        $attendanceRate = $totalClasses > 0 ? ($attendedCount / $totalClasses) * 100 : 0;

        $content = [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->user->name ?? 'N/A',
                'hourly_rate' => $teacher->hourly_rate,
                'currency' => $teacher->currency,
                'status' => $teacher->status,
            ],
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'statistics' => [
                'total_classes' => $totalClasses,
                'attended_classes' => $attendedCount,
                'attendance_rate' => round($attendanceRate, 2),
                'student_count' => $studentCount,
                'total_hours' => round($totalHours, 2),
                'total_revenue' => $totalRevenue,
                'salary_earned' => round($salaryEarned, 2),
            ],
            'classes_by_status' => $classes->groupBy('status')->map->count(),
            'classes_by_course' => $classes->groupBy('course_id')->map->count(),
            'generated_at' => now()->toIso8601String(),
            'currency' => $currency,
        ];

        return Report::create([
            'teacher_id' => $teacherId,
            'report_type' => 'teacher_performance',
            'content' => $content,
        ]);
    }

    /**
     * Generate package report
     */
    public function generatePackageReport(int $packageId): Report
    {
        $package = Package::with(['student', 'classes'])->findOrFail($packageId);
        
        $classes = $package->classes;
        $attendedClasses = $classes->where('status', 'attended');
        
        // Get bills for this package
        $bills = Bill::whereIn('class_id', $classes->pluck('id'))->get();
        
        $totalHours = $attendedClasses->sum('duration') / 60;
        $totalRevenue = $bills->where('status', 'paid')->sum('amount');
        $completionRate = $package->total_hours > 0 
            ? (($package->total_hours - $package->remaining_hours) / $package->total_hours) * 100 
            : (($package->total_classes - $package->remaining_classes) / $package->total_classes) * 100;

        $content = [
            'package' => [
                'id' => $package->id,
                'round_number' => $package->round_number,
                'total_classes' => $package->total_classes,
                'remaining_classes' => $package->remaining_classes,
                'total_hours' => $package->total_hours,
                'remaining_hours' => $package->remaining_hours,
                'hour_price' => $package->hour_price,
                'currency' => $package->currency,
                'status' => $package->status,
                'start_date' => $package->start_date->format('Y-m-d'),
            ],
            'student' => [
                'id' => $package->student->id,
                'name' => $package->student->full_name,
            ],
            'statistics' => [
                'total_classes_taken' => $classes->count(),
                'attended_classes' => $attendedClasses->count(),
                'total_hours_taken' => round($totalHours, 2),
                'completion_rate' => round($completionRate, 2),
                'total_revenue' => $totalRevenue,
                'total_bills' => $bills->count(),
                'paid_bills' => $bills->where('status', 'paid')->count(),
            ],
            'bills_summary' => [
                'total_amount' => $bills->sum('amount'),
                'paid_amount' => $bills->where('status', 'paid')->sum('amount'),
                'pending_amount' => $bills->where('status', 'pending')->sum('amount'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        return Report::create([
            'student_id' => $package->student_id,
            'report_type' => 'package_report',
            'content' => $content,
        ]);
    }

    /**
     * Generate revenue report (analytics)
     */
    public function generateRevenueReport(?array $dateRange = null, ?string $groupBy = 'month'): array
    {
        $dateFrom = $dateRange['from'] ?? now()->subMonths(12)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        $bills = Bill::whereBetween('bill_date', [$dateFrom, $dateTo])
            ->where('status', 'paid')
            ->with(['student', 'teacher', 'class.course'])
            ->get();

        $data = [];
        
        if ($groupBy === 'month') {
            $grouped = $bills->groupBy(function($bill) {
                return Carbon::parse($bill->bill_date)->format('Y-m');
            });
            
            foreach ($grouped as $month => $monthBills) {
                $data[] = [
                    'period' => $month,
                    'revenue' => $monthBills->sum('amount'),
                    'count' => $monthBills->count(),
                ];
            }
        } elseif ($groupBy === 'course') {
            $grouped = $bills->groupBy(function($bill) {
                return $bill->class && $bill->class->course 
                    ? $bill->class->course->name 
                    : 'Unknown';
            });
            
            foreach ($grouped as $course => $courseBills) {
                $data[] = [
                    'course' => $course,
                    'revenue' => $courseBills->sum('amount'),
                    'count' => $courseBills->count(),
                ];
            }
        } elseif ($groupBy === 'teacher') {
            $grouped = $bills->groupBy('teacher_id');
            
            foreach ($grouped as $teacherId => $teacherBills) {
                $teacher = $teacherBills->first()->teacher;
                $data[] = [
                    'teacher_id' => $teacherId,
                    'teacher_name' => $teacher ? $teacher->user->name : 'Unknown',
                    'revenue' => $teacherBills->sum('amount'),
                    'count' => $teacherBills->count(),
                ];
            }
        }

        return [
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_revenue' => $bills->sum('amount'),
            'total_bills' => $bills->count(),
            'data' => $data,
        ];
    }

    /**
     * Generate attendance report (analytics)
     */
    public function generateAttendanceReport(?array $dateRange = null, ?int $courseId = null, ?int $teacherId = null): array
    {
        $dateFrom = $dateRange['from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $dateRange['to'] ?? now()->format('Y-m-d');

        $classesQuery = ClassInstance::whereBetween('class_date', [$dateFrom, $dateTo]);
        
        if ($courseId) {
            $classesQuery->where('course_id', $courseId);
        }
        
        if ($teacherId) {
            $classesQuery->where('teacher_id', $teacherId);
        }
        
        $classes = $classesQuery->get();
        
        $totalClasses = $classes->count();
        $attendedClasses = $classes->where('status', 'attended')->count();
        $attendanceRate = $totalClasses > 0 ? ($attendedClasses / $totalClasses) * 100 : 0;
        
        // Group by date for trends
        $byDate = $classes->groupBy(function($class) {
            return Carbon::parse($class->class_date)->format('Y-m-d');
        })->map(function($dayClasses) {
            $total = $dayClasses->count();
            $attended = $dayClasses->where('status', 'attended')->count();
            return [
                'total' => $total,
                'attended' => $attended,
                'rate' => $total > 0 ? ($attended / $total) * 100 : 0,
            ];
        });

        return [
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_classes' => $totalClasses,
            'attended_classes' => $attendedClasses,
            'attendance_rate' => round($attendanceRate, 2),
            'by_date' => $byDate,
        ];
    }

    /**
     * Export report to PDF
     */
    public function exportToPDF(int $reportId): string
    {
        $report = Report::with(['student', 'teacher'])->findOrFail($reportId);
        
        // Store PDF
        $filename = 'report_' . $reportId . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $path = 'reports/' . $filename;
        $fullPath = storage_path('app/' . $path);
        
        // Ensure directory exists
        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        
        // Generate PDF using Spatie PDF
        \Spatie\LaravelPdf\Facades\Pdf::view('reports.pdf', [
            'report' => $report,
            'content' => $report->content,
        ])
            ->format(\Spatie\LaravelPdf\Enums\Format::A4)
            ->orientation(\Spatie\LaravelPdf\Enums\Orientation::Portrait)
            ->save($fullPath);
        
        // Update report with PDF path
        $report->update(['pdf_path' => $path]);
        
        return $path;
    }

    /**
     * Send report via WhatsApp
     */
    public function sendReportViaWhatsApp(int $reportId, ?string $customMessage = null): bool
    {
        $report = Report::with(['student', 'teacher'])->findOrFail($reportId);
        
        // Generate PDF if not exists
        if (!$report->pdf_path) {
            $this->exportToPDF($reportId);
            $report->refresh();
        }
        
        // Determine recipient
        $recipient = null;
        if ($report->student_id && $report->student) {
            $recipient = $report->student->whatsapp;
        } elseif ($report->teacher_id && $report->teacher && $report->teacher->user) {
            $recipient = $report->teacher->user->whatsapp;
        }
        
        if (!$recipient) {
            throw new \Exception('No WhatsApp number found for report recipient');
        }
        
        // Get PDF URL (assuming public storage or signed URL)
        $pdfUrl = Storage::url($report->pdf_path);
        
        // Prepare message
        $message = $customMessage ?? "Your report is ready. Download it here: {$pdfUrl}";
        
        // Send via WhatsApp
        $success = $this->whatsAppService->sendMessage($recipient, $message);
        
        if ($success) {
            $report->update(['sent_via_whatsapp' => true]);
        }
        
        return $success;
    }
}
