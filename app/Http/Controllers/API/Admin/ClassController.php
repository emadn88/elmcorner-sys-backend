<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Class\UpdateClassStatusRequest;
use App\Http\Requests\Class\UpdateClassRequest;
use App\Models\ClassInstance;
use App\Services\ClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    protected $classService;

    public function __construct(ClassService $classService)
    {
        $this->classService = $classService;
    }

    /**
     * Display a listing of the resource (calendar view).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'student_id' => $request->input('student_id'),
            'teacher_id' => $request->input('teacher_id'),
            'course_id' => $request->input('course_id'),
            'status' => $request->input('status'),
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 50),
        ];

        $classes = $this->classService->getCalendarData($filters);

        return response()->json([
            'status' => 'success',
            'data' => $classes->items(),
            'meta' => [
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $class = ClassInstance::with([
            'student',
            'teacher.user',
            'course',
            'timetable',
            'package',
            'bill',
            'cancelledByUser'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $class,
        ]);
    }

    /**
     * Update the class status.
     */
    public function updateStatus(UpdateClassStatusRequest $request, int $id): JsonResponse
    {
        $class = $this->classService->updateClassStatus(
            $id,
            $request->input('status'),
            $request->user()->id,
            $request->input('cancellation_reason')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Class status updated successfully',
            'data' => $class->load(['student', 'teacher.user', 'course', 'package', 'bill']),
        ]);
    }

    /**
     * Update the class time/details.
     */
    public function update(UpdateClassRequest $request, int $id): JsonResponse
    {
        $class = $this->classService->updateClassTime(
            $id,
            $request->input('class_date'),
            $request->input('start_time'),
            $request->input('end_time'),
            $request->input('student_id'),
            $request->input('teacher_id')
        );

        if ($request->has('notes')) {
            $class->notes = $request->input('notes');
            $class->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Class updated successfully',
            'data' => $class->load(['student', 'teacher.user', 'course', 'timetable', 'package']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->classService->deleteClass($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Class deleted successfully',
        ]);
    }

    /**
     * Delete this instance and all future recurring instances.
     */
    public function deleteFuture(Request $request, int $id): JsonResponse
    {
        $deleted = $this->classService->deleteFutureRecurring($id);

        return response()->json([
            'status' => 'success',
            'message' => "Deleted {$deleted} future class instances",
            'data' => ['deleted_count' => $deleted],
        ]);
    }

    /**
     * Export classes to PDF
     */
    public function exportPdf(Request $request)
    {
        try {
            $filters = [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'student_id' => $request->input('student_id'),
                'teacher_id' => $request->input('teacher_id'),
                'course_id' => $request->input('course_id'),
                'status' => $request->input('status'),
                'per_page' => 10000, // Get all classes
            ];

            $classes = $this->classService->getCalendarData($filters);
            $classesData = collect($classes->items());

            // Determine date range for filename
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $dateRange = $startDate === $endDate 
                ? $startDate 
                : $startDate . '_to_' . $endDate;
            $filename = 'classes_' . $dateRange . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';
            $path = 'temp/' . $filename;
            $fullPath = storage_path('app/' . $path);

            // Ensure directory exists
            if (!file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            // Generate PDF using Spatie PDF
            \Spatie\LaravelPdf\Facades\Pdf::view('classes.pdf', [
                'classes' => $classesData,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ])
                ->format(\Spatie\LaravelPdf\Enums\Format::A4)
                ->orientation(\Spatie\LaravelPdf\Enums\Orientation::Landscape)
                ->save($fullPath);

            // Check if file exists
            if (!file_exists($fullPath)) {
                \Log::error('PDF file not found at: ' . $fullPath);
                return response()->json([
                    'status' => 'error',
                    'message' => 'PDF file not found',
                ], 404);
            }

            // Return the file as download
            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            \Log::error('PDF export error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }
}
