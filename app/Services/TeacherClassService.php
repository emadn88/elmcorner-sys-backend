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
     * Check if class time has started or passed
     */
    public function canEnterMeet(ClassInstance $class): bool
    {
        $now = Carbon::now();
        $classDateTime = Carbon::parse($class->class_date->format('Y-m-d') . ' ' . $class->start_time->format('H:i:s'));
        
        return $now->greaterThanOrEqualTo($classDateTime);
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
        $class->save();

        // Create notification entry (we'll use packages table pattern or create notifications table)
        // For now, we'll mark it in the class and admin can see it via cancellation_request_status

        return $class;
    }

    /**
     * Approve cancellation request
     */
    public function approveCancellation(ClassInstance $class): ClassInstance
    {
        $class->status = 'cancelled_by_teacher';
        $class->cancellation_request_status = 'approved';
        $class->save();

        return $class;
    }

    /**
     * Reject cancellation request
     */
    public function rejectCancellation(ClassInstance $class): ClassInstance
    {
        $class->cancellation_request_status = 'rejected';
        $class->save();

        return $class;
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
     * Check if trial time has started or passed
     */
    public function canEnterTrial(TrialClass $trial): bool
    {
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
