<?php

namespace App\Services;

use App\Models\ClassInstance;
use App\Models\Package;
use App\Models\Bill;
use App\Models\ActivityLog;
use App\Models\Teacher;
use App\Services\PackageService;
use App\Services\BillingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClassService
{
    protected $packageService;
    protected $billingService;

    public function __construct(PackageService $packageService, BillingService $billingService)
    {
        $this->packageService = $packageService;
        $this->billingService = $billingService;
    }

    /**
     * Update class status with business logic for package deduction and billing.
     *
     * @param int $classId
     * @param string $newStatus
     * @param int|null $userId
     * @param string|null $reason
     * @return ClassInstance
     */
    public function updateClassStatus(int $classId, string $newStatus, ?int $userId = null, ?string $reason = null): ClassInstance
    {
        $class = ClassInstance::with(['package', 'student', 'teacher.user'])->findOrFail($classId);
        $oldStatus = $class->status;
        $userId = $userId ?? Auth::id();

        DB::beginTransaction();
        try {
            // Update status
            $class->status = $newStatus;
            
            if (in_array($newStatus, ['cancelled_by_student', 'cancelled_by_teacher'])) {
                $class->cancelled_by = $userId;
                $class->cancellation_reason = $reason;
            }
            
            $class->save();

            // Handle package deduction and billing based on status
            switch ($newStatus) {
                case 'attended':
                    $this->handleAttendedClass($class);
                    break;
                
                case 'cancelled_by_student':
                    $this->handleCancelledByStudent($class);
                    break;
                
                case 'cancelled_by_teacher':
                    // No package deduction, no bill
                    break;
                
                case 'absent_student':
                    $this->handleAbsentStudent($class);
                    break;
                
                case 'pending':
                    // No action
                    break;
            }

            // Log activity
        ActivityLog::create([
            'user_id' => $userId,
            'student_id' => $class->student_id,
            'action' => 'class_status_updated',
            'description' => "Class status changed from {$oldStatus} to {$newStatus}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

            DB::commit();
            return $class->fresh()->load(['student', 'teacher.user', 'course', 'package', 'bill']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle attended class: deduct package and create bill.
     */
    protected function handleAttendedClass(ClassInstance $class): void
    {
        // Deduct from package using PackageService (with duration in hours)
        if ($class->package_id) {
            $durationHours = $class->duration / 60.0; // Convert minutes to hours
            $this->packageService->deductClass($class->package_id, $durationHours);
        }

        // Create bill (will be fully implemented in Phase 8)
        $this->createBillForClass($class);
    }

    /**
     * Handle cancelled by student: deduct package (configurable), optionally create bill.
     */
    protected function handleCancelledByStudent(ClassInstance $class): void
    {
        // Deduct from package (configurable via settings)
        // For now, we'll deduct by default using PackageService
        if ($class->package_id) {
            $durationHours = $class->duration / 60.0; // Convert minutes to hours
            $this->packageService->deductClass($class->package_id, $durationHours);
        }

        // Optionally create bill based on policy (can be configured later)
        // For now, we'll skip bill creation for student cancellations
    }

    /**
     * Handle absent student: deduct package, optionally no bill.
     */
    protected function handleAbsentStudent(ClassInstance $class): void
    {
        // Deduct from package using PackageService
        if ($class->package_id) {
            $durationHours = $class->duration / 60.0; // Convert minutes to hours
            $this->packageService->deductClass($class->package_id, $durationHours);
        }

        // Create bill (absent students still get billed)
        $this->createBillForClass($class);
    }

    /**
     * Create bill for a class instance using BillingService.
     * Bills accumulate incrementally by package.
     */
    protected function createBillForClass(ClassInstance $class): void
    {
        try {
            // Only create bills if class has a package
            if (!$class->package_id) {
                return;
            }

            // Use BillingService to create or update incremental bill
            $this->billingService->createBillForClass($class);
        } catch (\Exception $e) {
            // Log error but don't fail the class status update
            \Log::error('Failed to create bill for class', [
                'class_id' => $class->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update class time/details.
     *
     * @param int $classId
     * @param string $newDate
     * @param string $newStartTime
     * @param string $newEndTime
     * @param int|null $studentId
     * @param int|null $teacherId
     * @return ClassInstance
     */
    public function updateClassTime(int $classId, string $newDate, string $newStartTime, string $newEndTime, ?int $studentId = null, ?int $teacherId = null): ClassInstance
    {
        $class = ClassInstance::findOrFail($classId);
        
        $start = Carbon::parse($newStartTime);
        $end = Carbon::parse($newEndTime);
        $duration = $start->diffInMinutes($end);

        $oldStudentId = $class->student_id;
        $oldTeacherId = $class->teacher_id;

        $class->class_date = $newDate;
        $class->start_time = $newStartTime;
        $class->end_time = $newEndTime;
        $class->duration = $duration;
        
        if ($studentId !== null) {
            $class->student_id = $studentId;
        }
        
        if ($teacherId !== null) {
            $class->teacher_id = $teacherId;
        }
        
        $class->save();

        // Log activity
        $description = "Class rescheduled to {$newDate} {$newStartTime}";
        if ($studentId !== null && $studentId !== $oldStudentId) {
            $description .= " and student changed";
        }
        if ($teacherId !== null && $teacherId !== $oldTeacherId) {
            $description .= " and teacher changed";
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $class->student_id,
            'action' => 'class_updated',
            'description' => $description,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $class->fresh()->load(['student', 'teacher.user', 'course', 'timetable', 'package']);
    }

    /**
     * Delete a single class instance.
     *
     * @param int $classId
     * @return bool
     */
    public function deleteClass(int $classId): bool
    {
        $class = ClassInstance::findOrFail($classId);
        
        // Only allow deletion of pending classes
        if ($class->status !== 'pending') {
            throw new \Exception('Cannot delete class that is not pending');
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $class->student_id,
            'action' => 'class_deleted',
            'description' => "Class deleted for {$class->class_date}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $class->delete();
    }

    /**
     * Delete this instance and all future recurring instances from same timetable.
     *
     * @param int $classId
     * @return int
     */
    public function deleteFutureRecurring(int $classId): int
    {
        $class = ClassInstance::findOrFail($classId);
        
        if (!$class->timetable_id) {
            throw new \Exception('Class is not part of a recurring timetable');
        }

        $deleted = ClassInstance::where('timetable_id', $class->timetable_id)
            ->where('class_date', '>=', $class->class_date)
            ->where('status', 'pending')
            ->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $class->student_id,
            'action' => 'future_classes_deleted',
            'description' => "Deleted future classes from timetable starting {$class->class_date}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $deleted;
    }

    /**
     * Get classes for calendar view with filters.
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getCalendarData(array $filters = [])
    {
        $query = ClassInstance::with(['student', 'teacher.user', 'course', 'timetable', 'package']);

        if (isset($filters['start_date'])) {
            $query->where('class_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('class_date', '<=', $filters['end_date']);
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

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 50;
        $page = $filters['page'] ?? 1;

        return $query->orderBy('class_date')
            ->orderBy('start_time')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
