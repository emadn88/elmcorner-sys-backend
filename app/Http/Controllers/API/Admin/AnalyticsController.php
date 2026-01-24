<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Models\Student;
use App\Models\ClassInstance;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get revenue analytics
     */
    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'group_by' => 'nullable|string|in:month,course,teacher',
        ]);

        $dateRange = null;
        if ($request->has('date_from') || $request->has('date_to')) {
            $dateRange = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];
        }

        $groupBy = $request->input('group_by', 'month');
        $data = $this->reportService->generateRevenueReport($dateRange, $groupBy);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Get attendance analytics
     */
    public function attendance(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'course_id' => 'nullable|exists:courses,id',
            'teacher_id' => 'nullable|exists:teachers,id',
        ]);

        $dateRange = null;
        if ($request->has('date_from') || $request->has('date_to')) {
            $dateRange = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];
        }

        $data = $this->reportService->generateAttendanceReport(
            $dateRange,
            $request->input('course_id'),
            $request->input('teacher_id')
        );

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Get course performance analytics
     */
    public function courses(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

        // Get all courses with their statistics
        $courses = \App\Models\Course::with(['teachers', 'classes' => function($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('class_date', [$dateFrom, $dateTo]);
        }])->get();

        $courseData = $courses->map(function($course) use ($dateFrom, $dateTo) {
            $classes = $course->classes;
            $attendedClasses = $classes->where('status', 'attended');
            
            $enrolledStudents = $classes->pluck('student_id')->unique()->count();
            $totalClasses = $classes->count();
            $attendedCount = $attendedClasses->count();
            $attendanceRate = $totalClasses > 0 ? ($attendedCount / $totalClasses) * 100 : 0;
            
            $bills = Bill::whereIn('class_id', $classes->pluck('id'))->get();
            $revenue = $bills->where('status', 'paid')->sum('amount');

            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'category' => $course->category,
                'total_classes' => $totalClasses,
                'attended_classes' => $attendedCount,
                'attendance_rate' => round($attendanceRate, 2),
                'enrolled_students' => $enrolledStudents,
                'revenue' => $revenue,
                'active_teachers' => $course->teachers()->where('status', 'active')->count(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                'courses' => $courseData,
            ],
        ]);
    }

    /**
     * Get dashboard overview analytics
     */
    public function overview(): JsonResponse
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // Total active students
        $activeStudents = Student::where('status', 'active')->count();

        // Classes this month
        $classesThisMonth = ClassInstance::whereBetween('class_date', [
            $startOfMonth->format('Y-m-d'),
            $endOfMonth->format('Y-m-d')
        ])->count();

        // Revenue this month
        $revenueThisMonth = Bill::whereBetween('bill_date', [
            $startOfMonth->format('Y-m-d'),
            $endOfMonth->format('Y-m-d')
        ])->where('status', 'paid')->sum('amount');

        // Pending bills
        $pendingBills = Bill::where('status', 'pending')->count();

        // Today's classes
        $todayClasses = ClassInstance::where('class_date', $now->format('Y-m-d'))->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'active_students' => $activeStudents,
                'classes_this_month' => $classesThisMonth,
                'revenue_this_month' => $revenueThisMonth,
                'pending_bills' => $pendingBills,
                'today_classes' => $todayClasses,
            ],
        ]);
    }
}
