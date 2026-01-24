<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    protected $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Display a listing of activity logs with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'student_id' => $request->input('student_id'),
            'action' => $request->input('action'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        $perPage = $request->input('per_page', 50);
        $logs = $this->activityService->getActivityLogs($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get activity statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $stats = $this->activityService->getActivityStats($dateFrom, $dateTo);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Get recent activities.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $activities = $this->activityService->getRecentActivity($limit);

        return response()->json([
            'status' => 'success',
            'data' => $activities,
        ]);
    }

    /**
     * Get students with activity levels
     */
    public function getStudents(Request $request): JsonResponse
    {
        $activityLevel = $request->input('activity_level', 'all');
        $threshold = $request->input('threshold');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $studentService = new \App\Services\StudentService();
        
        // Build query
        $query = \App\Models\Student::with('family');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        // Get all students and calculate activity levels
        $students = $query->get();
        
        // Calculate activity metrics for each student
        $studentsWithActivity = $students->map(function ($student) use ($studentService) {
            $metrics = $this->activityService->calculateStudentActivity($student->id);
            return array_merge($student->toArray(), $metrics);
        });

        // Filter by activity level
        if ($activityLevel !== 'all') {
            $studentsWithActivity = $studentsWithActivity->filter(function ($student) use ($activityLevel) {
                return $student['activity_level'] === $activityLevel;
            });
        }

        // Filter by threshold (days since last class)
        if ($threshold) {
            $studentsWithActivity = $studentsWithActivity->filter(function ($student) use ($threshold) {
                return $student['days_since_last_class'] === null || $student['days_since_last_class'] >= $threshold;
            });
        }

        // Paginate manually
        $total = $studentsWithActivity->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $studentsWithActivity->slice($offset, $perPage)->values();

        return response()->json([
            'status' => 'success',
            'data' => $paginated,
            'meta' => [
                'current_page' => (int) $page,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => (int) $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Send reactivation offer to student
     */
    public function reactivate(Request $request, string $studentId): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        try {
            $message = $request->input('message');
            $success = $this->activityService->sendReactivationOffer((int) $studentId, $message);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Reactivation offer sent successfully',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reactivation offer',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
