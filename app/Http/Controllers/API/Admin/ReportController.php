<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Generate a new report
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:lesson_summary,package_report,custom,student_single,students_multiple,students_family,students_all,teacher_performance,salaries,income',
            'student_id' => 'nullable|exists:students,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'family_id' => 'nullable|exists:families,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'package_id' => 'nullable|exists:packages,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'currency' => 'nullable|string|max:3',
        ]);

        $type = $request->input('type');
        $dateRange = null;
        $currency = $request->input('currency');
        
        if ($request->has('date_from') || $request->has('date_to')) {
            $dateRange = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];
        }

        try {
            $report = null;

            switch ($type) {
                case 'lesson_summary':
                case 'custom':
                case 'student_single':
                    if (!$request->has('student_id')) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Student ID is required for this report type',
                        ], 400);
                    }
                    $report = $this->reportService->generateStudentReport(
                        $request->input('student_id'),
                        $type,
                        $dateRange,
                        $currency
                    );
                    break;

                case 'students_multiple':
                    if (!$request->has('student_ids') || empty($request->input('student_ids'))) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Student IDs are required for multiple students report',
                        ], 400);
                    }
                    $report = $this->reportService->generateMultipleStudentsReport(
                        $request->input('student_ids'),
                        $dateRange,
                        $currency
                    );
                    break;

                case 'students_family':
                    if (!$request->has('family_id')) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Family ID is required for family report',
                        ], 400);
                    }
                    $report = $this->reportService->generateFamilyReport(
                        $request->input('family_id'),
                        $dateRange,
                        $currency
                    );
                    break;

                case 'students_all':
                    $report = $this->reportService->generateAllStudentsReport(
                        $dateRange,
                        $currency
                    );
                    break;

                case 'teacher_performance':
                    if (!$request->has('teacher_id')) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Teacher ID is required for teacher performance reports',
                        ], 400);
                    }
                    $report = $this->reportService->generateTeacherPerformanceReport(
                        $request->input('teacher_id'),
                        $dateRange,
                        $currency
                    );
                    break;

                case 'salaries':
                    $report = $this->reportService->generateSalariesReport(
                        $dateRange,
                        $request->input('teacher_id'),
                        $currency
                    );
                    break;

                case 'income':
                    $report = $this->reportService->generateIncomeReport(
                        $dateRange,
                        $currency
                    );
                    break;

                case 'package_report':
                    if (!$request->has('package_id')) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Package ID is required for package reports',
                        ], 400);
                    }
                    $report = $this->reportService->generatePackageReport(
                        $request->input('package_id')
                    );
                    break;
            }

            if (!$report) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to generate report',
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Report generated successfully',
                'data' => $report->load(['student', 'teacher']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all reports with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Report::with(['student', 'teacher']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('report_type', $request->input('type'));
        }

        // Filter by student
        if ($request->has('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        // Filter by teacher
        if ($request->has('teacher_id')) {
            $query->where('teacher_id', $request->input('teacher_id'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        $perPage = $request->input('per_page', 15);
        $reports = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Get a single report
     */
    public function show(string $id): JsonResponse
    {
        $report = Report::with(['student', 'teacher'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * Download report PDF
     */
    public function download(string $id)
    {
        $report = Report::findOrFail($id);

        // Generate PDF if not exists
        if (!$report->pdf_path || !Storage::exists($report->pdf_path)) {
            $this->reportService->exportToPDF($report->id);
            $report->refresh();
        }

        if (!Storage::exists($report->pdf_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'PDF file not found',
            ], 404);
        }

        return Storage::download($report->pdf_path, 'report_' . $report->id . '.pdf');
    }

    /**
     * Send report via WhatsApp
     */
    public function sendWhatsApp(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'custom_message' => 'nullable|string|max:500',
        ]);

        try {
            $success = $this->reportService->sendReportViaWhatsApp(
                (int) $id,
                $request->input('custom_message')
            );

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Report sent via WhatsApp successfully',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send report via WhatsApp',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a report
     */
    public function destroy(string $id): JsonResponse
    {
        $report = Report::findOrFail($id);

        // Delete PDF file if exists
        if ($report->pdf_path && Storage::exists($report->pdf_path)) {
            Storage::delete($report->pdf_path);
        }

        $report->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Report deleted successfully',
        ]);
    }
}
