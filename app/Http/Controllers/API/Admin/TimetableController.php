<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\StoreTimetableRequest;
use App\Http\Requests\Timetable\UpdateTimetableRequest;
use App\Models\Timetable;
use App\Services\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    protected $timetableService;

    public function __construct(TimetableService $timetableService)
    {
        $this->timetableService = $timetableService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Timetable::with(['student', 'teacher.user', 'course']);

        // Apply filters
        if ($request->has('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->has('teacher_id')) {
            $query->where('teacher_id', $request->input('teacher_id'));
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 15);
        $timetables = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $timetables->items(),
            'meta' => [
                'current_page' => $timetables->currentPage(),
                'last_page' => $timetables->lastPage(),
                'per_page' => $timetables->perPage(),
                'total' => $timetables->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTimetableRequest $request): JsonResponse
    {
        $timetable = Timetable::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Timetable created successfully',
            'data' => $timetable->load(['student', 'teacher.user', 'course']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $timetable = Timetable::with(['student', 'teacher.user', 'course', 'classes'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $timetable,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTimetableRequest $request, int $id): JsonResponse
    {
        $timetable = Timetable::findOrFail($id);
        
        // Store old data for comparison (optional, for future use)
        $oldData = [
            'student_id' => $timetable->student_id,
            'teacher_id' => $timetable->teacher_id,
            'course_id' => $timetable->course_id,
            'days_of_week' => $timetable->days_of_week,
            'time_slots' => $timetable->time_slots,
            'student_timezone' => $timetable->student_timezone,
            'teacher_timezone' => $timetable->teacher_timezone,
            'time_difference_minutes' => $timetable->time_difference_minutes,
        ];
        
        // Update the timetable
        $timetable->update($request->validated());
        
        // Sync future/pending classes with new timetable settings
        // This updates only pending/waiting_list classes with dates >= today
        // Past classes (attended, cancelled, etc.) are not modified to preserve data integrity
        $syncResult = $this->timetableService->syncFutureClasses($id, $oldData);

        $message = 'Timetable updated successfully';
        $messageParts = [];
        
        if ($syncResult['updated'] > 0) {
            $messageParts[] = sprintf('updated %d class(es)', $syncResult['updated']);
        }
        if ($syncResult['deleted'] > 0) {
            $messageParts[] = sprintf('removed %d class(es)', $syncResult['deleted']);
        }
        if ($syncResult['generated'] > 0) {
            $messageParts[] = sprintf('generated %d new class(es)', $syncResult['generated']);
        }
        
        if (!empty($messageParts)) {
            $message .= '. ' . ucfirst(implode(', ', $messageParts));
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $timetable->fresh()->load(['student', 'teacher.user', 'course']),
            'sync_result' => $syncResult,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $timetable = Timetable::findOrFail($id);
        
        // Option to delete future classes
        if ($request->input('delete_future_classes', false)) {
            $this->timetableService->deleteFutureClasses($id, now()->format('Y-m-d'));
        }

        $timetable->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Timetable deleted successfully',
        ]);
    }

    /**
     * Generate classes for a timetable.
     */
    public function generateClasses(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = $this->timetableService->generateClasses(
            $id,
            $request->input('from_date'),
            $request->input('to_date')
        );

        return response()->json([
            'status' => 'success',
            'message' => "Generated {$result['generated']} classes, skipped {$result['skipped']} existing classes",
            'data' => $result,
        ]);
    }

    /**
     * Pause a timetable.
     */
    public function pause(int $id): JsonResponse
    {
        $timetable = $this->timetableService->pauseTimetable($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Timetable paused successfully',
            'data' => $timetable->load(['student', 'teacher.user', 'course']),
        ]);
    }

    /**
     * Resume a timetable.
     */
    public function resume(int $id): JsonResponse
    {
        $timetable = $this->timetableService->resumeTimetable($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Timetable resumed successfully',
            'data' => $timetable->load(['student', 'teacher.user', 'course']),
        ]);
    }

    /**
     * Delete all pending classes for a timetable.
     * Only deletes classes with status 'pending' or 'waiting_list'.
     * Preserves attended, cancelled, and other completed classes.
     */
    public function deleteAllPendingClasses(int $id): JsonResponse
    {
        $result = $this->timetableService->deleteAllPendingClasses($id);

        return response()->json([
            'status' => 'success',
            'message' => sprintf('Deleted %d pending class(es) successfully', $result['deleted']),
            'data' => $result,
        ]);
    }
}
