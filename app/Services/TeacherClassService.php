<?php

namespace App\Services;

use App\Models\ClassInstance;
use App\Models\TrialClass;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeacherClassService
{
    /**
     * Check if teacher can enter meet (no time restriction)
     */
    public function canEnterMeet(ClassInstance $class): bool
    {
        // Removed time check - teachers can enter meet at any time
        return true;
    }

    /**
     * Check if teacher has meet link
     */
    public function hasMeetLink(Teacher $teacher): bool
    {
        return !empty($teacher->meet_link);
    }

    /**
     * Mark class as meet link accessed
     */
    public function enterMeet(ClassInstance $class): ClassInstance
    {
        $class->meet_link_used = true;
        $class->meet_link_accessed_at = now();
        $class->status = 'pending';
        $class->save();

        return $class;
    }

    /**
     * End class (mark as ready for details)
     */
    public function endClass(ClassInstance $class): ClassInstance
    {
        // Class is already pending and meet_link_used is true
        // Just ensure it's ready for details submission
        // Status remains 'pending' until teacher fills details and confirms
        return $class;
    }

    /**
     * Request class cancellation (creates notification)
     */
    public function requestCancellation(ClassInstance $class, string $reason, int $userId): ClassInstance
    {
        $class->cancellation_request_status = 'pending';
        $class->cancellation_reason = $reason;
        $class->cancelled_by = $userId;
        // Set status to 'pending' - class remains pending until admin approves/rejects
        // The cancellation_request_status field indicates it's waiting for approval
        $class->status = 'pending';
        $class->save();

        return $class;
    }

    /**
     * Approve cancellation request
     */
    public function approveCancellation(ClassInstance $class): ClassInstance
    {
        // When approved, class is counted from student package
        // Use ClassService to handle status update and package deduction
        $classService = app(\App\Services\ClassService::class);
        $classService->updateClassStatus($class->id, 'cancelled_by_student', auth()->id(), $class->cancellation_reason);
        
        // Update cancellation request status
        $class->cancellation_request_status = 'approved';
        $class->save();

        return $class->fresh();
    }

    /**
     * Reject cancellation request
     */
    public function rejectCancellation(ClassInstance $class, string $adminReason): ClassInstance
    {
        // When rejected, class is NOT counted from student package
        // Use ClassService to handle status update and package assignment
        // This ensures the class is assigned to a package (but doesn't deduct hours)
        $classService = app(\App\Services\ClassService::class);
        $classService->updateClassStatus($class->id, 'cancelled_by_teacher', auth()->id(), $class->cancellation_reason);
        
        // Update cancellation request status and admin reason
        $class->cancellation_request_status = 'rejected';
        $class->admin_rejection_reason = $adminReason;
        $class->save();

        return $class->fresh();
    }

    /**
     * Get classes with cancellation requests
     */
    public function getCancellationRequests(): \Illuminate\Database\Eloquent\Collection
    {
        return ClassInstance::where('cancellation_request_status', 'pending')
            ->with(['student', 'teacher.user', 'course'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Get all cancellation requests with filters (for log)
     */
    public function getAllCancellationRequests(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = ClassInstance::whereNotNull('cancellation_request_status')
            ->with(['student', 'teacher.user', 'course']);

        // Filter by status
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('cancellation_request_status', $filters['status']);
        }

        // Filter by date range
        if (isset($filters['date_from'])) {
            $query->whereDate('class_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('class_date', '<=', $filters['date_to']);
        }

        // Filter by teacher
        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        // Filter by student
        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        // Filter by course
        if (isset($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }

        // Search
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->whereHas('student', function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%");
                })
                ->orWhereHas('teacher.user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('course', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->orderBy('class_date', 'desc')
            ->get();
    }

    /**
     * Check if trial can be entered (always allow for pending trials)
     */
    public function canEnterTrial(TrialClass $trial): bool
    {
        // Always allow entering meet for pending trials, regardless of time
        // This allows teachers to access the Zoom link at any time before/during the trial
        if ($trial->status === 'pending') {
            return true;
        }
        
        // For other statuses, check if trial time has started
        $now = Carbon::now();
        $trialDateTime = Carbon::parse($trial->trial_date->format('Y-m-d') . ' ' . $trial->start_time);
        
        return $now->greaterThanOrEqualTo($trialDateTime);
    }

    /**
     * Mark trial as entered
     */
    public function enterTrial(TrialClass $trial): TrialClass
    {
        $trial->meet_link_used = true;
        $trial->meet_link_accessed_at = now();
        $trial->save();

        return $trial;
    }
}
