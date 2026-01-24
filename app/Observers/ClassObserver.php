<?php

namespace App\Observers;

use App\Models\ClassInstance;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class ClassObserver
{
    /**
     * Handle the ClassInstance "created" event.
     */
    public function created(ClassInstance $classInstance): void
    {
        // Log class creation
        ActivityLog::create([
            'user_id' => auth()->id(),
            'student_id' => $classInstance->student_id,
            'action' => 'class_created',
            'description' => "Class created for {$classInstance->class_date}",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);
    }

    /**
     * Handle the ClassInstance "updated" event.
     */
    public function updated(ClassInstance $classInstance): void
    {
        // Check if status changed
        if ($classInstance->wasChanged('status')) {
            $oldStatus = $classInstance->getOriginal('status');
            $newStatus = $classInstance->status;

            // Log status change
            ActivityLog::create([
                'user_id' => auth()->id(),
                'student_id' => $classInstance->student_id,
                'action' => 'class_status_updated',
                'description' => "Class status changed from {$oldStatus} to {$newStatus}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);

            // Queue WhatsApp notifications for specific statuses
            // This will be fully implemented in Phase 9
            if (in_array($newStatus, ['attended', 'cancelled_by_student'])) {
                // TODO: Queue WhatsApp notification job
                // dispatch(new SendClassStatusWhatsAppJob($classInstance));
            }
        }
    }

    /**
     * Handle the ClassInstance "deleted" event.
     */
    public function deleted(ClassInstance $classInstance): void
    {
        // Log class deletion
        ActivityLog::create([
            'user_id' => auth()->id(),
            'student_id' => $classInstance->student_id,
            'action' => 'class_deleted',
            'description' => "Class deleted for {$classInstance->class_date}",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);
    }
}
