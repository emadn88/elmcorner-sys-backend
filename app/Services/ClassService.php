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
     * Calculate duration in minutes for time slots that may span midnight.
     * 
     * @param string $startTime Time in H:i format (e.g., "23:00")
     * @param string $endTime Time in H:i format (e.g., "00:00")
     * @return int Duration in minutes
     */
    protected function calculateDuration(string $startTime, string $endTime): int
    {
        list($startHour, $startMin) = explode(':', $startTime);
        list($endHour, $endMin) = explode(':', $endTime);
        
        $startMinutes = (int)$startHour * 60 + (int)$startMin;
        $endMinutes = (int)$endHour * 60 + (int)$endMin;
        
        // If end time is less than start time, it spans midnight
        if ($endMinutes < $startMinutes) {
            // Duration = (24 hours - start) + end
            return (24 * 60 - $startMinutes) + $endMinutes;
        }
        
        // Normal case: end - start
        return $endMinutes - $startMinutes;
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
                    $this->handleCancelledByTeacher($class);
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
     * Handle attended class: assign to package, create bill, and deduct.
     * 
     * IMPORTANT ORDER:
     * 1. Find active package (or auto-create if none exists)
     * 2. Assign class to package (immutable once assigned)
     * 3. Create bill FIRST (so it exists when notification is sent)
     * 4. Deduct hours from package (triggers notification if package reaches limit)
     */
    protected function handleAttendedClass(ClassInstance $class): void
    {
        $durationHours = $class->duration / 60.0;
        
        // Check if already processed
        $hasBill = Bill::where('class_id', $class->id)->exists();
        
        // Find or create active package
        if (!$class->package_id) {
            $activePackage = $this->findOrCreateActivePackage($class->student_id, $durationHours);
            
            if ($activePackage) {
                $class->package_id = $activePackage->id;
                $class->save();
            }
        }
        
        // Create bill FIRST (before deducting, so it exists when notification is sent)
        if (!$hasBill && $class->package_id) {
            $this->createBillForClass($class);
        }
        
        // Then deduct from package (this may trigger notification if package reaches limit)
        if (!$hasBill && $class->package_id) {
            $this->packageService->deductClass($class->package_id, $durationHours, true);
        }
    }

    /**
     * Handle cancelled by student: assign to package, deduct.
     */
    protected function handleCancelledByStudent(ClassInstance $class): void
    {
        $durationHours = $class->duration / 60.0;
        
        if (!$class->package_id) {
            $activePackage = $this->findOrCreateActivePackage($class->student_id, $durationHours);
            if ($activePackage) {
                $class->package_id = $activePackage->id;
                $class->save();
                $this->packageService->deductClass($activePackage->id, $durationHours);
            }
        } else {
            $this->packageService->deductClass($class->package_id, $durationHours);
        }
    }

    /**
     * Handle cancelled by teacher: assign to package, but NO deduction and NO bill.
     * 
     * IMPORTANT: Class must be assigned to package to show in package classes,
     * but it does NOT count towards package hours limit.
     */
    protected function handleCancelledByTeacher(ClassInstance $class): void
    {
        // Assign to package if not already assigned (so it shows in package classes)
        if (!$class->package_id) {
            $activePackage = $this->findOrCreateActivePackage($class->student_id, 0);
            if ($activePackage) {
                $class->package_id = $activePackage->id;
                $class->save();
            }
        }
        // NO package deduction (cancelled_by_teacher doesn't count towards limit)
        // NO bill creation (teacher cancelled = no charge)
    }

    /**
     * Handle absent student: assign to package, create bill, and deduct.
     * 
     * IMPORTANT: Create bill first, then deduct (so notification includes the bill)
     */
    protected function handleAbsentStudent(ClassInstance $class): void
    {
        $durationHours = $class->duration / 60.0;
        
        if (!$class->package_id) {
            $activePackage = $this->findOrCreateActivePackage($class->student_id, $durationHours);
            if ($activePackage) {
                $class->package_id = $activePackage->id;
                $class->save();
            }
        }
        
        // Create bill FIRST
        if ($class->package_id) {
            $this->createBillForClass($class);
        }
        
        // Then deduct (triggers notification if package reaches limit)
        if ($class->package_id) {
            $this->packageService->deductClass($class->package_id, $durationHours, true);
        }
    }
    
    /**
     * Find active package or auto-create one if none exists.
     * 
     * AUTO-CREATE LOGIC:
     * - If no active package exists, create one from the last package template
     * - This ensures classes always have a package to be assigned to
     * - When a package reaches 0 hours, it's marked as 'finished' and a new one is created
     */
    protected function findOrCreateActivePackage(int $studentId, float $durationHours): ?Package
    {
        // Try to find active package with enough hours
        $activePackage = Package::where('student_id', $studentId)
            ->where('status', 'active')
            ->where('remaining_hours', '>=', $durationHours)
            ->orderBy('created_at', 'asc')
            ->first();
        
        if ($activePackage) {
            return $activePackage;
        }
        
        // No active package with enough hours - auto-create a new one
        $lastPackage = Package::where('student_id', $studentId)
            ->orderBy('round_number', 'desc')
            ->first();
        
        if (!$lastPackage) {
            \Log::error('Cannot auto-create package - no template found', [
                'student_id' => $studentId,
            ]);
            return null;
        }
        
        // Mark current active package as finished if it exists
        Package::where('student_id', $studentId)
            ->where('status', 'active')
            ->update([
                'status' => 'finished',
                'remaining_hours' => 0,
                'updated_at' => now(),
            ]);
        
        // Create new package (next round)
        $newPackage = $this->packageService->activateNewRound($studentId, [
            'total_hours' => $lastPackage->total_hours,
            'hour_price' => $lastPackage->hour_price,
            'currency' => $lastPackage->currency,
            'start_date' => now()->format('Y-m-d'),
        ]);
        
        \Log::info('Auto-created new package for class', [
            'student_id' => $studentId,
            'package_id' => $newPackage->id,
            'round_number' => $newPackage->round_number,
        ]);
        
        return $newPackage;
    }

    /**
     * Create bill for a class instance.
     */
    protected function createBillForClass(ClassInstance $class): void
    {
        try {
            if (!$class->package_id) {
                \Log::warning('Cannot create bill - class has no package', [
                    'class_id' => $class->id,
                ]);
                return;
            }

            $this->billingService->createBillForClass($class);
        } catch (\Exception $e) {
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
        $class = ClassInstance::with(['timetable'])->findOrFail($classId);
        
        // Calculate duration (handles midnight-spanning times)
        $duration = $this->calculateDuration($newStartTime, $newEndTime);

        $oldStudentId = $class->student_id;
        $oldTeacherId = $class->teacher_id;

        $class->class_date = $newDate;
        $class->start_time = $newStartTime;
        $class->end_time = $newEndTime;
        $class->duration = $duration;
        
        // Calculate student date and time based on timetable's time difference or existing class data
        if ($class->timetable) {
            $timetable = $class->timetable;
            
            if ($timetable->time_difference_minutes !== null && $timetable->time_difference_minutes !== 0) {
                // Use manual time difference in minutes
                [$startHour, $startMinute] = explode(':', $newStartTime);
                [$endHour, $endMinute] = explode(':', $newEndTime);
                
                // Create teacher datetime
                $teacherStartDateTime = Carbon::create(
                    Carbon::parse($newDate)->year,
                    Carbon::parse($newDate)->month,
                    Carbon::parse($newDate)->day,
                    (int)$startHour,
                    (int)$startMinute,
                    0
                );
                $teacherEndDateTime = Carbon::create(
                    Carbon::parse($newDate)->year,
                    Carbon::parse($newDate)->month,
                    Carbon::parse($newDate)->day,
                    (int)$endHour,
                    (int)$endMinute,
                    0
                );
                
                // Apply time difference (add minutes)
                $studentStartDateTime = $teacherStartDateTime->copy()->addMinutes($timetable->time_difference_minutes);
                $studentEndDateTime = $teacherEndDateTime->copy()->addMinutes($timetable->time_difference_minutes);
                
                // Get student date and times
                $class->student_date = $studentStartDateTime->format('Y-m-d');
                $class->student_start_time = $studentStartDateTime->format('H:i:s');
                $class->student_end_time = $studentEndDateTime->format('H:i:s');
            } else {
                // Fallback to timezone conversion if no manual difference is set
                $teacherStartDateTime = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $newDate . ' ' . $newStartTime . ':00',
                    $timetable->teacher_timezone
                );
                $teacherEndDateTime = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $newDate . ' ' . $newEndTime . ':00',
                    $timetable->teacher_timezone
                );
                
                // Convert to student timezone
                $studentStartDateTime = $teacherStartDateTime->copy()->setTimezone($timetable->student_timezone);
                $studentEndDateTime = $teacherEndDateTime->copy()->setTimezone($timetable->student_timezone);
                
                // Get student date and times
                $class->student_date = $studentStartDateTime->format('Y-m-d');
                $class->student_start_time = $studentStartDateTime->format('H:i:s');
                $class->student_end_time = $studentEndDateTime->format('H:i:s');
            }
        } else {
            // Class without timetable - calculate time difference from existing student_time or use timezones
            $timeDifferenceMinutes = null;
            
            // Try to calculate time difference from existing student_time fields
            if ($class->student_start_time && $class->start_time && $class->class_date && $class->student_date) {
                try {
                    // Parse existing times to calculate difference
                    $oldTeacherStart = Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $class->class_date->format('Y-m-d') . ' ' . 
                        (is_string($class->start_time) ? $class->start_time : Carbon::parse($class->start_time)->format('H:i:s'))
                    );
                    $oldStudentStart = Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $class->student_date->format('Y-m-d') . ' ' . 
                        (is_string($class->student_start_time) ? $class->student_start_time : Carbon::parse($class->student_start_time)->format('H:i:s'))
                    );
                    
                    // Calculate difference in minutes
                    $timeDifferenceMinutes = $oldStudentStart->diffInMinutes($oldTeacherStart, false);
                } catch (\Exception $e) {
                    // If calculation fails, try to get from student/teacher timezones
                }
            }
            
            // If we couldn't calculate from existing data, try timezones
            if ($timeDifferenceMinutes === null) {
                $class->load(['student', 'teacher']);
                $student = $class->student;
                $teacher = $class->teacher;
                
                if ($student && $teacher && $student->timezone && $teacher->timezone) {
                    try {
                        // Calculate timezone difference
                        $now = Carbon::now();
                        $teacherTime = Carbon::createFromTimestamp($now->timestamp, $teacher->timezone);
                        $studentTime = Carbon::createFromTimestamp($now->timestamp, $student->timezone);
                        $timeDifferenceMinutes = $studentTime->diffInMinutes($teacherTime, false);
                    } catch (\Exception $e) {
                        // If timezone calculation fails, default to 0 (same time)
                        $timeDifferenceMinutes = 0;
                    }
                } else {
                    // No timezone data available, default to 0
                    $timeDifferenceMinutes = 0;
                }
            }
            
            // Apply the calculated time difference
            [$startHour, $startMinute] = explode(':', $newStartTime);
            [$endHour, $endMinute] = explode(':', $newEndTime);
            
            // Create teacher datetime
            $teacherStartDateTime = Carbon::create(
                Carbon::parse($newDate)->year,
                Carbon::parse($newDate)->month,
                Carbon::parse($newDate)->day,
                (int)$startHour,
                (int)$startMinute,
                0
            );
            $teacherEndDateTime = Carbon::create(
                Carbon::parse($newDate)->year,
                Carbon::parse($newDate)->month,
                Carbon::parse($newDate)->day,
                (int)$endHour,
                (int)$endMinute,
                0
            );
            
            // Apply time difference (add minutes)
            $studentStartDateTime = $teacherStartDateTime->copy()->addMinutes($timeDifferenceMinutes);
            $studentEndDateTime = $teacherEndDateTime->copy()->addMinutes($timeDifferenceMinutes);
            
            // Get student date and times
            $class->student_date = $studentStartDateTime->format('Y-m-d');
            $class->student_start_time = $studentStartDateTime->format('H:i:s');
            $class->student_end_time = $studentEndDateTime->format('H:i:s');
        }
        
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
     * EXCLUDES classes that belong to paid packages.
     * Simple logic: if package_id points to a paid package, exclude it.
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getCalendarData(array $filters = [])
    {
        $query = ClassInstance::with(['student', 'teacher.user', 'course', 'timetable', 'package']);

        // SIMPLE: Exclude classes that have a paid package
        $query->where(function ($q) {
            $q->whereNull('package_id') // Classes without package
              ->orWhereHas('package', function ($packageQuery) {
                  $packageQuery->where('status', '!=', 'paid'); // Package is not paid
              });
        });

        // Date filtering: Filter by teacher's date (class_date) only
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
