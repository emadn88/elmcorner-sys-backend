<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trial\StoreTrialRequest;
use App\Http\Requests\Trial\UpdateTrialRequest;
use App\Http\Requests\Trial\UpdateTrialStatusRequest;
use App\Http\Requests\Trial\ConvertTrialRequest;
use App\Services\TrialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrialClassController extends Controller
{
    protected $trialService;

    public function __construct(TrialService $trialService)
    {
        $this->trialService = $trialService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->input('status', 'all'),
            'student_id' => $request->input('student_id'),
            'teacher_id' => $request->input('teacher_id'),
            'course_id' => $request->input('course_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        $perPage = $request->input('per_page', 15);
        $trials = $this->trialService->getTrials($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $trials->items(),
            'meta' => [
                'current_page' => $trials->currentPage(),
                'last_page' => $trials->lastPage(),
                'per_page' => $trials->perPage(),
                'total' => $trials->total(),
            ],
        ]);
    }

    /**
     * Get trial statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->trialService->getTrialStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTrialRequest $request): JsonResponse
    {
        try {
            $trial = $this->trialService->createTrial($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Trial class created successfully',
                'data' => $trial,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $trial = $this->trialService->getTrial($id);

            return response()->json([
                'status' => 'success',
                'data' => $trial,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrialRequest $request, int $id): JsonResponse
    {
        try {
            $trial = $this->trialService->updateTrial($id, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Trial class updated successfully',
                'data' => $trial,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update trial status
     */
    public function updateStatus(UpdateTrialStatusRequest $request, int $id): JsonResponse
    {
        try {
            $trial = $this->trialService->updateTrialStatus(
                $id,
                $request->input('status'),
                $request->input('notes')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Trial status updated successfully',
                'data' => $trial,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Approve or reject trial (admin only)
     * Approve: mark as completed
     * Reject: mark as no_show
     */
    public function reviewTrial(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $trial = \App\Models\TrialClass::findOrFail($id);

            // Only pending_review trials can be reviewed
            if ($trial->status !== 'pending_review') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only trials pending review can be approved or rejected',
                ], 400);
            }

            $newStatus = $request->input('action') === 'approve' ? 'completed' : 'no_show';
            
            $trial->status = $newStatus;
            if ($request->has('notes')) {
                $trial->notes = ($trial->notes ? $trial->notes . "\n\n" : '') . 
                    'Admin Notes: ' . $request->input('notes');
            }
            $trial->save();

            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => \Illuminate\Support\Facades\Auth::id(),
                'student_id' => $trial->student_id,
                'action' => 'review_trial',
                'description' => "Trial class {$request->input('action')}d for student {$trial->student->full_name}",
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Trial {$request->input('action')}d successfully",
                'data' => $trial->fresh()->load(['student', 'teacher', 'course']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Convert trial to regular package and timetable
     */
    public function convert(ConvertTrialRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $this->trialService->convertToRegular(
                $id,
                $validated['package'],
                $validated['timetable']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Trial converted to regular package and timetable successfully',
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->trialService->deleteTrial($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Trial class deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
