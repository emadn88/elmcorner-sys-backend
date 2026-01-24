<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    protected $salaryService;

    public function __construct(SalaryService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    /**
     * Display a listing of teachers with their salaries
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'month' => $request->input('month', date('m')),
            'year' => $request->input('year', date('Y')),
            'teacher_id' => $request->input('teacher_id'),
        ];

        $salaries = $this->salaryService->getTeachersSalaries($filters);

        return response()->json([
            'status' => 'success',
            'data' => $salaries,
        ]);
    }

    /**
     * Get specific teacher salary details
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $salary = $this->salaryService->getTeacherSalary($id, $month, $year);

        if (!$salary) {
            return response()->json([
                'status' => 'error',
                'message' => 'Teacher not found or no salary data available',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $salary,
        ]);
    }

    /**
     * Get monthly statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $statistics = $this->salaryService->getMonthlyStatistics($month, $year);

        return response()->json([
            'status' => 'success',
            'data' => $statistics,
        ]);
    }

    /**
     * Get detailed breakdown for a teacher
     */
    public function breakdown(string $id, Request $request): JsonResponse
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        try {
            $breakdown = $this->salaryService->getSalaryBreakdown($id, $month, $year);

            return response()->json([
                'status' => 'success',
                'data' => $breakdown,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Teacher not found',
            ], 404);
        }
    }

    /**
     * Get salary history for charts
     */
    public function history(string $id, Request $request): JsonResponse
    {
        $months = (int) $request->input('months', 12);

        try {
            $history = $this->salaryService->getSalaryHistory($id, $months);

            return response()->json([
                'status' => 'success',
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Teacher not found',
            ], 404);
        }
    }

    /**
     * Get all teachers salary history for comparison
     */
    public function allHistory(Request $request): JsonResponse
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        $months = (int) $request->input('months', 12);

        $history = $this->salaryService->getAllTeachersSalaryHistory($month, $year, $months);

        return response()->json([
            'status' => 'success',
            'data' => $history,
        ]);
    }
}
