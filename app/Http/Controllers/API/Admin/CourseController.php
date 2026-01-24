<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Models\Course;
use App\Models\ActivityLog;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    protected $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status', 'all'),
            'category' => $request->input('category'),
        ];

        $perPage = $request->input('per_page', 15);
        $courses = $this->courseService->getCourses($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $courses->items(),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    /**
     * Get course statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->courseService->getCourseStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = Course::create($request->validated());

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create',
            'description' => "Course {$course->name} was created",
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Course created successfully',
            'data' => $course->load('teachers'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $course = $this->courseService->getCourse($id);

        return response()->json([
            'status' => 'success',
            'data' => $course,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        
        $course->update($request->validated());

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'description' => "Course {$course->name} was updated",
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Course updated successfully',
            'data' => $course->fresh()->load('teachers'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $courseName = $course->name;

        // Check for active timetables
        $hasActiveTimetables = $course->timetables()
            ->where('status', 'active')
            ->exists();

        if ($hasActiveTimetables) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete course with active timetables',
            ], 422);
        }

        // Log activity before deletion
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'description' => "Course {$courseName} was deleted",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $course->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Course deleted successfully',
        ]);
    }

    /**
     * Assign teachers to course
     */
    public function assignTeachers(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'teacher_ids' => ['required', 'array'],
            'teacher_ids.*' => ['exists:teachers,id'],
        ]);

        $course = $this->courseService->assignTeachers($id, $request->teacher_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Teachers assigned successfully',
            'data' => $course,
        ]);
    }
}
