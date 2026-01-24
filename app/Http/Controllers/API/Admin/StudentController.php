<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Models\Student;
use App\Models\ActivityLog;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status', 'all'),
            'family_id' => $request->input('family_id'),
        ];

        $perPage = $request->input('per_page', 15);
        $students = $this->studentService->searchStudents($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    /**
     * Get student statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->studentService->getStudentStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Default to 'trial' if type is not provided
        if (!isset($data['type'])) {
            $data['type'] = 'trial';
        }
        $student = Student::create($data);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $student->id,
            'action' => 'create',
            'description' => "Student {$student->full_name} was created",
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Student created successfully',
            'data' => $student->load('family'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $profile = $this->studentService->getStudentProfile((int) $id);

        return response()->json([
            'status' => 'success',
            'data' => $profile,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, string $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $oldData = $student->toArray();
        
        $student->update($request->validated());

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $student->id,
            'action' => 'update',
            'description' => "Student {$student->full_name} was updated",
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Student updated successfully',
            'data' => $student->fresh()->load('family'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $studentName = $student->full_name;

        // Log activity before deletion
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $student->id,
            'action' => 'delete',
            'description' => "Student {$studentName} was deleted",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $student->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Student deleted successfully',
        ]);
    }
}
