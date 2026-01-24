<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\TeacherAvailability;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    protected $teacherService;

    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status', 'all'),
            'course_id' => $request->input('course_id'),
        ];

        $perPage = $request->input('per_page', 15);
        $teachers = $this->teacherService->getTeachers($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $teachers->items(),
            'meta' => [
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
            ],
        ]);
    }

    /**
     * Get teacher statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->teacherService->getTeacherStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Create user first
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password'] ?? 'password'), // Default password if not provided
            'role' => 'teacher',
            'whatsapp' => $validated['whatsapp'] ?? null,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'status' => 'active',
        ]);

        // Create teacher profile
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'hourly_rate' => $validated['hourly_rate'],
            'currency' => $validated['currency'] ?? 'USD',
            'timezone' => $validated['timezone'] ?? 'UTC',
            'status' => $validated['status'],
            'bio' => $validated['bio'] ?? null,
            'meet_link' => $validated['meet_link'] ?? null,
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create',
            'description' => "Teacher {$user->name} was created",
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher created successfully',
            'data' => $teacher->load(['user', 'courses']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $profile = $this->teacherService->getTeacherProfile($id);

        return response()->json([
            'status' => 'success',
            'data' => $profile,
        ]);
    }

    /**
     * Get teacher performance metrics
     */
    public function performance(Request $request, string $id): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $performance = $this->teacherService->getTeacherPerformance($id, $dateFrom, $dateTo);

        return response()->json([
            'status' => 'success',
            'data' => $performance,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeacherRequest $request, string $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        $validated = $request->validated();
        
        // Update user if user fields are provided
        if ($teacher->user) {
            $userData = [];
            if (isset($validated['name'])) {
                $userData['name'] = $validated['name'];
            }
            if (isset($validated['email'])) {
                $userData['email'] = $validated['email'];
            }
            if (isset($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }
            if (isset($validated['whatsapp'])) {
                $userData['whatsapp'] = $validated['whatsapp'];
            }
            
            if (!empty($userData)) {
                $teacher->user->update($userData);
            }
        }
        
        // Update teacher fields
        $teacherData = [];
        if (isset($validated['hourly_rate'])) {
            $teacherData['hourly_rate'] = $validated['hourly_rate'];
        }
        if (isset($validated['currency'])) {
            $teacherData['currency'] = $validated['currency'];
        }
        if (isset($validated['timezone'])) {
            $teacherData['timezone'] = $validated['timezone'];
        }
        if (isset($validated['status'])) {
            $teacherData['status'] = $validated['status'];
        }
        if (isset($validated['bio'])) {
            $teacherData['bio'] = $validated['bio'];
        }
        if (isset($validated['meet_link'])) {
            $teacherData['meet_link'] = $validated['meet_link'];
        }
        
        if (!empty($teacherData)) {
            $teacher->update($teacherData);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'description' => "Teacher {$teacher->full_name} was updated",
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher updated successfully',
            'data' => $teacher->fresh()->load(['user', 'courses']),
        ]);
    }

    /**
     * Get teacher's availability
     */
    public function getAvailability(string $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        
        $availability = $teacher->availability()
            ->where('is_available', true)
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $availability,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        $teacherName = $teacher->full_name;

        // Check for active classes
        $hasActiveClasses = $teacher->classes()
            ->where('class_date', '>=', now())
            ->exists();

        if ($hasActiveClasses) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete teacher with active classes',
            ], 422);
        }

        // Log activity before deletion
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'description' => "Teacher {$teacherName} was deleted",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $teacher->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher deleted successfully',
        ]);
    }

    /**
     * Assign courses to teacher
     */
    public function assignCourses(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'course_ids' => ['required', 'array'],
            'course_ids.*' => ['exists:courses,id'],
        ]);

        $teacher = $this->teacherService->assignCourses($id, $request->course_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Courses assigned successfully',
            'data' => $teacher,
        ]);
    }

    /**
     * Get teacher monthly statistics and students
     */
    public function monthlyStats(Request $request, string $id): JsonResponse
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $stats = $this->teacherService->getTeacherMonthlyStats((int) $id, (int) $month, (int) $year);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }
}
