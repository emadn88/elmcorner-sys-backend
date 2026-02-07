<?php

namespace App\Services;

use App\Models\TrialClass;
use App\Models\Package;
use App\Models\Timetable;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class TrialService
{
    /**
     * Get trials list with filters and pagination
     */
    public function getTrials(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = TrialClass::with(['student', 'teacher.user', 'course', 'convertedPackage']);

        // Apply filters
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('trial_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('trial_date', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('trial_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get single trial with relationships
     */
    public function getTrial(int $id): TrialClass
    {
        return TrialClass::with(['student', 'teacher.user', 'course', 'convertedPackage'])
            ->findOrFail($id);
    }

    /**
     * Create new trial class
     */
    public function createTrial(array $data): TrialClass
    {
        // No timezone conversion - store student and teacher times as-is
        // Use teacher times directly for trial_date, start_time, end_time (for conflict checking)
        if (isset($data['teacher_date']) && isset($data['teacher_start_time'])) {
            $data['trial_date'] = $data['teacher_date'];
            $data['start_time'] = $data['teacher_start_time'];
            $data['end_time'] = $data['teacher_end_time'] ?? $data['teacher_start_time'];
        } elseif (isset($data['student_date']) && isset($data['student_start_time'])) {
            // Fallback: use student time if teacher time not provided
            $data['trial_date'] = $data['student_date'];
            $data['start_time'] = $data['student_start_time'];
            $data['end_time'] = $data['student_end_time'] ?? $data['student_start_time'];
        }

        // Check for time conflicts with existing trials and classes
        $this->validateNoTimeConflict(
            $data['teacher_id'],
            $data['trial_date'],
            $data['start_time'],
            $data['end_time'],
            null // No trial to exclude for new trials
        );

        $trial = TrialClass::create($data);

        // Load relationships for notification
        $trial->load(['student', 'teacher.user', 'course']);

        // Generate and send trial image via WhatsApp
        try {
            $trialImageService = app(\App\Services\TrialImageService::class);
            $whatsAppService = app(\App\Services\WhatsAppService::class);
            
            // Generate trial image (save to storage and get URL)
            $imageUrl = $trialImageService->generateTrialImage($trial, false);
            
            // Send image via WhatsApp if student has WhatsApp number
            if ($imageUrl && $trial->student && $trial->student->whatsapp) {
                // Use the full URL for WhatsApp (needs to be publicly accessible)
                $fullImageUrl = $imageUrl;
                if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    // If it's a relative URL, make it absolute
                    $fullImageUrl = config('app.url') . '/' . ltrim($imageUrl, '/');
                }
                
                $whatsAppService->sendImage(
                    $trial->student->whatsapp,
                    $fullImageUrl,
                    null, // No caption needed, image contains all info
                    'trial_image'
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail trial creation
            \Illuminate\Support\Facades\Log::error('Failed to generate and send trial image', [
                'trial_id' => $trial->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Send WhatsApp notification to student and teacher
        try {
            $reminderService = app(\App\Services\ReminderService::class);
            $reminderService->sendTrialCreationNotification($trial);
        } catch (\Exception $e) {
            // Log error but don't fail trial creation
            \Illuminate\Support\Facades\Log::error('Failed to send trial creation notification', [
                'trial_id' => $trial->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $trial->student_id,
            'action' => 'create',
            'description' => "Trial class created for student {$trial->student->full_name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $trial;
    }

    /**
     * Update trial details
     */
    public function updateTrial(int $id, array $data): TrialClass
    {
        $trial = TrialClass::findOrFail($id);

        // Only allow editing pending trials
        if ($trial->status !== 'pending') {
            throw new \Exception('Only pending trials can be edited');
        }

        // Check for time conflicts (excluding current trial)
        if (isset($data['teacher_id']) || isset($data['trial_date']) || isset($data['start_time']) || isset($data['end_time'])) {
            $teacherId = $data['teacher_id'] ?? $trial->teacher_id;
            $trialDate = $data['trial_date'] ?? $trial->trial_date->format('Y-m-d');
            $startTime = $data['start_time'] ?? $trial->start_time;
            $endTime = $data['end_time'] ?? $trial->end_time;
            
            $this->validateNoTimeConflict(
                $teacherId,
                $trialDate,
                $startTime,
                $endTime,
                $trial->id // Exclude current trial from conflict check
            );
        }

        $trial->update($data);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $trial->student_id,
            'action' => 'update',
            'description' => "Trial class updated for student {$trial->student->full_name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $trial->fresh()->load(['student', 'teacher.user', 'course']);
    }

    /**
     * Update trial status
     */
    public function updateTrialStatus(int $id, string $status, ?string $notes = null): TrialClass
    {
        $trial = TrialClass::findOrFail($id);
        $oldStatus = $trial->status;

        // Validate status transition
        if ($trial->status === 'converted') {
            throw new \Exception('Converted trials cannot have their status changed');
        }

        $trial->update([
            'status' => $status,
            'notes' => $notes ?? $trial->notes,
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $trial->student_id,
            'action' => 'update_status',
            'description' => "Trial class status changed from {$oldStatus} to {$status}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $trial->fresh()->load(['student', 'teacher.user', 'course']);
    }

    /**
     * Convert trial to regular package and timetable
     */
    public function convertToRegular(int $trialId, array $packageData, array $timetableData): array
    {
        $trial = TrialClass::findOrFail($trialId);

        // Validate that trial can be converted
        if ($trial->status === 'converted') {
            throw new \Exception('Trial has already been converted');
        }

        if (!in_array($trial->status, ['pending', 'completed'])) {
            throw new \Exception('Only pending or completed trials can be converted');
        }

        return DB::transaction(function () use ($trial, $packageData, $timetableData) {
            // Create package (using total_hours)
            $totalHours = $packageData['total_hours'] ?? 0;
            $package = Package::create([
                'student_id' => $trial->student_id,
                'start_date' => $packageData['start_date'],
                'total_classes' => 0, // Not used, kept for backward compatibility
                'remaining_classes' => 0, // Not used, kept for backward compatibility
                'total_hours' => $totalHours,
                'remaining_hours' => $totalHours,
                'hour_price' => $packageData['hour_price'],
                'currency' => $packageData['currency'] ?? $trial->student->currency ?? 'USD',
                'round_number' => $this->getNextRoundNumber($trial->student_id),
                'status' => 'active',
            ]);

            // Create timetable
            $timetable = Timetable::create([
                'student_id' => $trial->student_id,
                'teacher_id' => $trial->teacher_id,
                'course_id' => $trial->course_id,
                'days_of_week' => $timetableData['days_of_week'],
                'time_slots' => $timetableData['time_slots'],
                'student_timezone' => $timetableData['student_timezone'] ?? $trial->student->timezone,
                'teacher_timezone' => $timetableData['teacher_timezone'] ?? $trial->teacher->timezone,
                'status' => 'active',
            ]);

            // Update trial status
            $trial->update([
                'status' => 'converted',
                'converted_to_package_id' => $package->id,
            ]);

            // Update student type to confirmed when trial is converted
            $trial->student->update([
                'type' => 'confirmed',
            ]);

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'student_id' => $trial->student_id,
                'action' => 'convert',
                'description' => "Trial class converted to package #{$package->id} and timetable #{$timetable->id}",
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return [
                'trial' => $trial->fresh()->load(['student', 'teacher.user', 'course', 'convertedPackage']),
                'package' => $package->load('student'),
                'timetable' => $timetable->load(['student', 'teacher.user', 'course']),
            ];
        });
    }

    /**
     * Delete trial (only if not converted)
     */
    public function deleteTrial(int $id): bool
    {
        $trial = TrialClass::findOrFail($id);

        if ($trial->status === 'converted') {
            throw new \Exception('Converted trials cannot be deleted');
        }

        // Log activity before deletion
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $trial->student_id,
            'action' => 'delete',
            'description' => "Trial class deleted for student {$trial->student->full_name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $trial->delete();
    }

    /**
     * Get trial statistics
     */
    public function getTrialStats(): array
    {
        return [
            'total' => TrialClass::count(),
            'pending' => TrialClass::where('status', 'pending')->count(),
            'completed' => TrialClass::where('status', 'completed')->count(),
            'no_show' => TrialClass::where('status', 'no_show')->count(),
            'converted' => TrialClass::where('status', 'converted')->count(),
        ];
    }

    /**
     * Get next round number for student
     */
    private function getNextRoundNumber(int $studentId): int
    {
        $lastPackage = Package::where('student_id', $studentId)
            ->orderBy('round_number', 'desc')
            ->first();

        return $lastPackage ? $lastPackage->round_number + 1 : 1;
    }

    /**
     * Validate that there's no time conflict with existing trials or classes
     */
    protected function validateNoTimeConflict(int $teacherId, string $date, string $startTime, string $endTime, ?int $excludeTrialId = null): void
    {
        $trialDate = \Carbon\Carbon::parse($date)->format('Y-m-d');
        
        // Normalize times - ensure H:i format
        $startTimeNormalized = strlen($startTime) === 5 ? $startTime : substr($startTime, 0, 5);
        $endTimeNormalized = strlen($endTime) === 5 ? $endTime : substr($endTime, 0, 5);
        
        // Create datetime objects for comparison
        $newStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$trialDate} {$startTimeNormalized}");
        $newEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$trialDate} {$endTimeNormalized}");
        
        // Check for conflicting trials
        $trialsQuery = TrialClass::where('teacher_id', $teacherId)
            ->where('trial_date', $trialDate)
            ->where('status', '!=', 'cancelled');
        
        // Exclude current trial if editing
        if ($excludeTrialId !== null) {
            $trialsQuery->where('id', '!=', $excludeTrialId);
        }
        
        $trials = $trialsQuery->get();
        
        foreach ($trials as $trial) {
            $trialStartTime = strlen($trial->start_time) === 5 ? $trial->start_time : substr($trial->start_time, 0, 5);
            $trialEndTime = strlen($trial->end_time) === 5 ? $trial->end_time : substr($trial->end_time, 0, 5);
            
            $trialStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$trialDate} {$trialStartTime}");
            $trialEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$trialDate} {$trialEndTime}");
            
            // Check for time overlap: new start < existing end AND new end > existing start
            if ($newStart->lt($trialEnd) && $newEnd->gt($trialStart)) {
                throw new \Exception('Teacher already has a trial scheduled at this time. Please choose a different time slot.');
            }
        }

        // Check for conflicting classes
        $classes = \App\Models\ClassInstance::where('teacher_id', $teacherId)
            ->where('class_date', $trialDate)
            ->where('status', '!=', 'cancelled')
            ->get();
        
        foreach ($classes as $class) {
            // ClassInstance has datetime fields, extract time portion
            $classStartTime = $class->start_time instanceof \Carbon\Carbon 
                ? $class->start_time->format('H:i')
                : (is_string($class->start_time) ? substr($class->start_time, 11, 5) : '00:00');
            $classEndTime = $class->end_time instanceof \Carbon\Carbon 
                ? $class->end_time->format('H:i')
                : (is_string($class->end_time) ? substr($class->end_time, 11, 5) : '00:00');
            
            $classStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$trialDate} {$classStartTime}");
            $classEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$trialDate} {$classEndTime}");
            
            // Check for time overlap
            if ($newStart->lt($classEnd) && $newEnd->gt($classStart)) {
                throw new \Exception('Teacher already has a class scheduled at this time. Please choose a different time slot.');
            }
        }
    }
}
