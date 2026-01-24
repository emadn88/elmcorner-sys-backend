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
        $query = Timetable::with(['student', 'teacher', 'course']);

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
            'data' => $timetable->load(['student', 'teacher', 'course']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $timetable = Timetable::with(['student', 'teacher', 'course', 'classes'])->findOrFail($id);

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
        $timetable->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Timetable updated successfully',
            'data' => $timetable->fresh()->load(['student', 'teacher', 'course']),
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
            'data' => $timetable->load(['student', 'teacher', 'course']),
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
            'data' => $timetable->load(['student', 'teacher', 'course']),
        ]);
    }
}
