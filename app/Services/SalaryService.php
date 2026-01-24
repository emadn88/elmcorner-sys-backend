<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\ClassInstance;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalaryService
{
    /**
     * Get all teachers with their monthly salaries
     */
    public function getTeachersSalaries(array $filters = []): array
    {
        $month = $filters['month'] ?? date('m');
        $year = $filters['year'] ?? date('Y');
        $teacherId = $filters['teacher_id'] ?? null;

        $query = Teacher::with('user');

        if ($teacherId) {
            $query->where('id', $teacherId);
        }

        $teachers = $query->get();
        $salaries = [];

        foreach ($teachers as $teacher) {
            $salaryData = $this->calculateTeacherSalary($teacher->id, $month, $year);
            if ($salaryData) {
                $salaries[] = $salaryData;
            }
        }

        // Sort by salary descending
        usort($salaries, function ($a, $b) {
            return $b['salary'] <=> $a['salary'];
        });

        return $salaries;
    }

    /**
     * Get specific teacher's salary details
     */
    public function getTeacherSalary(int $teacherId, ?string $month = null, ?string $year = null): ?array
    {
        $month = $month ?? date('m');
        $year = $year ?? date('Y');

        return $this->calculateTeacherSalary($teacherId, $month, $year);
    }

    /**
     * Calculate salary for a teacher in a specific month
     */
    private function calculateTeacherSalary(int $teacherId, string $month, string $year): ?array
    {
        $teacher = Teacher::with('user')->find($teacherId);
        
        if (!$teacher) {
            return null;
        }

        // Get attended classes for the month
        $classes = ClassInstance::where('teacher_id', $teacherId)
            ->where('status', 'attended')
            ->whereYear('class_date', $year)
            ->whereMonth('class_date', $month)
            ->with(['student', 'course'])
            ->get();

        $totalMinutes = $classes->sum('duration');
        $totalHours = round($totalMinutes / 60, 2);
        $salary = round($teacher->hourly_rate * $totalHours, 2);

        return [
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->user->name ?? 'N/A',
            'teacher_email' => $teacher->user->email ?? 'N/A',
            'hourly_rate' => (float) $teacher->hourly_rate,
            'currency' => $teacher->currency,
            'month' => $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT),
            'total_hours' => $totalHours,
            'total_classes' => $classes->count(),
            'salary' => $salary,
            'status' => $teacher->status,
        ];
    }

    /**
     * Get monthly statistics
     */
    public function getMonthlyStatistics(?string $month = null, ?string $year = null): array
    {
        $month = $month ?? date('m');
        $year = $year ?? date('Y');

        // Get all teachers with salaries
        $salaries = $this->getTeachersSalaries(['month' => $month, 'year' => $year]);

        $totalSalary = array_sum(array_column($salaries, 'salary'));
        $totalHours = array_sum(array_column($salaries, 'total_hours'));
        $totalClasses = array_sum(array_column($salaries, 'total_classes'));
        $totalTeachers = count($salaries);
        $averageSalary = $totalTeachers > 0 ? round($totalSalary / $totalTeachers, 2) : 0;

        // Get previous month for comparison
        $prevMonth = Carbon::create($year, $month, 1)->subMonth();
        $prevSalaries = $this->getTeachersSalaries([
            'month' => $prevMonth->format('m'),
            'year' => $prevMonth->format('Y')
        ]);
        $prevTotalSalary = array_sum(array_column($prevSalaries, 'salary'));
        $salaryChange = $prevTotalSalary > 0 
            ? round((($totalSalary - $prevTotalSalary) / $prevTotalSalary) * 100, 2)
            : 0;

        return [
            'month' => $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT),
            'total_teachers' => $totalTeachers,
            'total_salary' => round($totalSalary, 2),
            'average_salary' => $averageSalary,
            'total_hours' => round($totalHours, 2),
            'total_classes' => $totalClasses,
            'previous_month_salary' => round($prevTotalSalary, 2),
            'salary_change_percentage' => $salaryChange,
        ];
    }

    /**
     * Get detailed breakdown for a teacher
     */
    public function getSalaryBreakdown(int $teacherId, ?string $month = null, ?string $year = null): array
    {
        $month = $month ?? date('m');
        $year = $year ?? date('Y');

        $teacher = Teacher::with('user')->findOrFail($teacherId);

        // Get attended classes with details
        $classes = ClassInstance::where('teacher_id', $teacherId)
            ->where('status', 'attended')
            ->whereYear('class_date', $year)
            ->whereMonth('class_date', $month)
            ->with(['student', 'course'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $breakdown = [];
        $totalMinutes = 0;
        $totalSalary = 0;

        foreach ($classes as $class) {
            $hours = round($class->duration / 60, 2);
            $classSalary = round($teacher->hourly_rate * $hours, 2);
            $totalMinutes += $class->duration;
            $totalSalary += $classSalary;

            $breakdown[] = [
                'class_id' => $class->id,
                'class_date' => $class->class_date->format('Y-m-d'),
                'start_time' => $class->start_time,
                'end_time' => $class->end_time,
                'duration_minutes' => $class->duration,
                'duration_hours' => $hours,
                'hourly_rate' => (float) $teacher->hourly_rate,
                'salary' => $classSalary,
                'student_name' => $class->student->full_name ?? 'N/A',
                'student_id' => $class->student_id,
                'course_name' => $class->course->name ?? 'N/A',
                'course_id' => $class->course_id,
            ];
        }

        $totalHours = round($totalMinutes / 60, 2);

        return [
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->user->name ?? 'N/A',
            'hourly_rate' => (float) $teacher->hourly_rate,
            'currency' => $teacher->currency,
            'month' => $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT),
            'total_hours' => $totalHours,
            'total_classes' => $classes->count(),
            'total_salary' => round($totalSalary, 2),
            'classes' => $breakdown,
        ];
    }

    /**
     * Get salary history for charts
     */
    public function getSalaryHistory(int $teacherId, int $months = 12): array
    {
        $history = [];
        $currentDate = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $currentDate->copy()->subMonths($i);
            $month = $date->format('m');
            $year = $date->format('Y');

            $salaryData = $this->calculateTeacherSalary($teacherId, $month, $year);
            
            $history[] = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'salary' => $salaryData ? $salaryData['salary'] : 0,
                'hours' => $salaryData ? $salaryData['total_hours'] : 0,
                'classes' => $salaryData ? $salaryData['total_classes'] : 0,
            ];
        }

        return $history;
    }

    /**
     * Get all teachers salary history for comparison chart
     */
    public function getAllTeachersSalaryHistory(?string $month = null, ?string $year = null, int $months = 12): array
    {
        $month = $month ?? date('m');
        $year = $year ?? date('Y');
        
        $currentDate = Carbon::create($year, $month, 1);
        $history = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $currentDate->copy()->subMonths($i);
            $monthNum = $date->format('m');
            $yearNum = $date->format('Y');

            $salaries = $this->getTeachersSalaries([
                'month' => $monthNum,
                'year' => $yearNum
            ]);

            $totalSalary = array_sum(array_column($salaries, 'salary'));

            $history[] = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'total_salary' => round($totalSalary, 2),
                'teacher_count' => count($salaries),
            ];
        }

        return $history;
    }
}
