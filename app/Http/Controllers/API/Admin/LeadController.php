<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    protected $leadService;

    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->input('status', 'all'),
            'priority' => $request->input('priority', 'all'),
            'country' => $request->input('country'),
            'assigned_to' => $request->input('assigned_to'),
            'source' => $request->input('source'),
            'overdue_follow_up' => $request->boolean('overdue_follow_up'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        $perPage = $request->input('per_page', 15);
        $leads = $this->leadService->getLeads($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $leads->items(),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
            ],
        ]);
    }

    /**
     * Get lead statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->leadService->getLeadStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'whatsapp' => 'required|string|max:20',
            'country' => 'nullable|string|max:2',
            'timezone' => 'nullable|string|max:50',
            'number_of_students' => 'required|integer|min:1',
            'ages' => 'nullable|array',
            'ages.*' => 'integer|min:1|max:100',
            'source' => 'nullable|string|max:255',
            'status' => 'nullable|in:new,contacted,needs_follow_up,trial_scheduled,trial_confirmed,converted,not_interested,cancelled',
            'priority' => 'nullable|in:high,medium,low',
            'assigned_to' => 'nullable|exists:users,id',
            'next_follow_up' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $lead = $this->leadService->createLead($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Lead created successfully',
                'data' => $lead,
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
            $lead = $this->leadService->getLead($id);

            return response()->json([
                'status' => 'success',
                'data' => $lead,
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
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'whatsapp' => 'sometimes|string|max:20',
            'country' => 'nullable|string|max:2',
            'timezone' => 'nullable|string|max:50',
            'number_of_students' => 'sometimes|integer|min:1',
            'ages' => 'nullable|array',
            'ages.*' => 'integer|min:1|max:100',
            'source' => 'nullable|string|max:255',
            'status' => 'nullable|in:new,contacted,needs_follow_up,trial_scheduled,trial_confirmed,converted,not_interested,cancelled',
            'priority' => 'nullable|in:high,medium,low',
            'assigned_to' => 'nullable|exists:users,id',
            'next_follow_up' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $lead = $this->leadService->updateLead($id, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Lead updated successfully',
                'data' => $lead,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update lead status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,contacted,needs_follow_up,trial_scheduled,trial_confirmed,converted,not_interested,cancelled',
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $lead = $this->leadService->updateLeadStatus(
                $id,
                $validated['status'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Lead status updated successfully',
                'data' => $lead,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk update lead status
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'integer|exists:leads,id',
            'status' => 'required|in:new,contacted,needs_follow_up,trial_scheduled,trial_confirmed,converted,not_interested,cancelled',
        ]);

        try {
            $updated = $this->leadService->bulkUpdateStatus(
                $validated['lead_ids'],
                $validated['status']
            );

            return response()->json([
                'status' => 'success',
                'message' => "{$updated} leads updated successfully",
                'data' => ['updated_count' => $updated],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Convert lead to student and optionally create trial
     */
    public function convert(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'student' => 'required|array',
            'student.full_name' => 'required|string|max:255',
            'student.email' => 'nullable|email|max:255',
            'student.whatsapp' => 'nullable|string|max:20',
            'student.country' => 'nullable|string|max:2',
            'student.currency' => 'nullable|string|max:3',
            'student.timezone' => 'nullable|string|max:50',
            'trial' => 'nullable|array',
            'trial.teacher_id' => 'required_with:trial|exists:teachers,id',
            'trial.course_id' => 'required_with:trial|exists:courses,id',
            'trial.trial_date' => 'required_with:trial|date',
            'trial.start_time' => 'required_with:trial|string',
            'trial.end_time' => 'required_with:trial|string',
            'trial.notes' => 'nullable|string|max:5000',
        ]);

        try {
            $result = $this->leadService->convertLead(
                $id,
                $validated['student'],
                $validated['trial'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Lead converted to student successfully',
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
            $this->leadService->deleteLead($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Lead deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
