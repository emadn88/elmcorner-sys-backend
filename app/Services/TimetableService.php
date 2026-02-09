<?php

namespace App\Services;

use App\Models\Timetable;
use App\Models\ClassInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimetableService
{
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
     * Generate class instances from recurring timetable rules.
     *
     * @param int $timetableId
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    public function generateClasses(int $timetableId, string $fromDate, string $toDate): array
    {
        $timetable = Timetable::with(['student', 'teacher', 'course'])->findOrFail($timetableId);

        if ($timetable->status !== 'active') {
            throw new \Exception('Cannot generate classes for inactive timetable');
        }

        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);
        $daysOfWeek = $timetable->days_of_week ?? [];
        $timeSlots = $timetable->time_slots ?? [];

        if (empty($daysOfWeek) || empty($timeSlots)) {
            throw new \Exception('Timetable must have days_of_week and time_slots configured');
        }

        $generated = [];
        $skipped = 0;

        // NOTE: Classes are NOT linked to packages when generated
        // They are linked to the student only
        // Package assignment happens when class status changes (attended, cancelled, etc.)
        // This allows classes to be assigned to the current active package at the time of completion

        // Iterate through each day in the date range
        $currentDate = $from->copy();
        while ($currentDate->lte($to)) {
            $dayOfWeek = $currentDate->dayOfWeekIso; // 1 = Monday, 7 = Sunday

            // Check if this day is in the timetable's days_of_week
            if (in_array($dayOfWeek, $daysOfWeek)) {
                // Get time slots for this day
                $dayTimeSlots = array_filter($timeSlots, function ($slot) use ($dayOfWeek) {
                    return isset($slot['day']) && $slot['day'] == $dayOfWeek;
                });

                foreach ($dayTimeSlots as $slot) {
                    // Check if class already exists for this date and time
                    $existingClass = ClassInstance::where('timetable_id', $timetableId)
                        ->where('class_date', $currentDate->format('Y-m-d'))
                        ->where('start_time', $slot['start'])
                        ->first();

                    if ($existingClass) {
                        $skipped++;
                        continue;
                    }

                    // Calculate duration (handles midnight-spanning times)
                    $duration = $this->calculateDuration($slot['start'], $slot['end']);

                    // Calculate student date and time
                    // Use manual time_difference_minutes if available, otherwise use timezone conversion
                    $teacherDate = $currentDate->copy();
                    
                    if ($timetable->time_difference_minutes !== null && $timetable->time_difference_minutes !== 0) {
                        // Use manual time difference in minutes
                        // Parse teacher's start and end times
                        [$startHour, $startMinute] = explode(':', $slot['start']);
                        [$endHour, $endMinute] = explode(':', $slot['end']);
                        
                        // Create teacher datetime
                        $teacherStartDateTime = Carbon::create(
                            $teacherDate->year,
                            $teacherDate->month,
                            $teacherDate->day,
                            (int)$startHour,
                            (int)$startMinute,
                            0
                        );
                        $teacherEndDateTime = Carbon::create(
                            $teacherDate->year,
                            $teacherDate->month,
                            $teacherDate->day,
                            (int)$endHour,
                            (int)$endMinute,
                            0
                        );
                        
                        // Apply time difference (add minutes)
                        $studentStartDateTime = $teacherStartDateTime->copy()->addMinutes($timetable->time_difference_minutes);
                        $studentEndDateTime = $teacherEndDateTime->copy()->addMinutes($timetable->time_difference_minutes);
                        
                        // Get student date and times
                        $studentDate = $studentStartDateTime->format('Y-m-d');
                        $studentStartTimeStr = $studentStartDateTime->format('H:i:s');
                        $studentEndTimeStr = $studentEndDateTime->format('H:i:s');
                    } else {
                        // Fallback to timezone conversion if no manual difference is set
                        // Create teacher datetime in teacher's timezone
                        $teacherStartDateTime = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $teacherDate->format('Y-m-d') . ' ' . $slot['start'] . ':00',
                            $timetable->teacher_timezone
                        );
                        $teacherEndDateTime = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $teacherDate->format('Y-m-d') . ' ' . $slot['end'] . ':00',
                            $timetable->teacher_timezone
                        );
                        
                        // Convert to student timezone
                        $studentStartDateTime = $teacherStartDateTime->copy()->setTimezone($timetable->student_timezone);
                        $studentEndDateTime = $teacherEndDateTime->copy()->setTimezone($timetable->student_timezone);
                        
                        // Get student date and times
                        $studentDate = $studentStartDateTime->format('Y-m-d');
                        $studentStartTimeStr = $studentStartDateTime->format('H:i:s');
                        $studentEndTimeStr = $studentEndDateTime->format('H:i:s');
                    }

                    // Create class instance - NO package_id assigned
                    // Package will be assigned when class status changes
                    $classInstance = ClassInstance::create([
                        'timetable_id' => $timetableId,
                        'package_id' => null, // Not assigned at generation time
                        'student_id' => $timetable->student_id,
                        'teacher_id' => $timetable->teacher_id,
                        'course_id' => $timetable->course_id,
                        'class_date' => $currentDate->format('Y-m-d'), // Teacher's date
                        'start_time' => $slot['start'], // Teacher's time
                        'end_time' => $slot['end'], // Teacher's time
                        'student_date' => $studentDate, // Student's date
                        'student_start_time' => $studentStartTimeStr, // Student's time
                        'student_end_time' => $studentEndTimeStr, // Student's time
                        'duration' => $duration,
                        'status' => 'pending',
                    ]);

                    $generated[] = $classInstance;
                }
            }

            $currentDate->addDay();
        }

        return [
            'generated' => count($generated),
            'skipped' => $skipped,
            'classes' => $generated,
        ];
    }

    /**
     * Pause a timetable.
     *
     * @param int $timetableId
     * @return Timetable
     */
    public function pauseTimetable(int $timetableId): Timetable
    {
        $timetable = Timetable::findOrFail($timetableId);
        $timetable->status = 'paused';
        $timetable->save();

        return $timetable;
    }

    /**
     * Resume a timetable.
     *
     * @param int $timetableId
     * @return Timetable
     */
    public function resumeTimetable(int $timetableId): Timetable
    {
        $timetable = Timetable::findOrFail($timetableId);
        $timetable->status = 'active';
        $timetable->save();

        return $timetable;
    }

    /**
     * Delete all future class instances for a timetable.
     *
     * @param int $timetableId
     * @param string $fromDate
     * @return int
     */
    public function deleteFutureClasses(int $timetableId, string $fromDate): int
    {
        $from = Carbon::parse($fromDate);

        return ClassInstance::where('timetable_id', $timetableId)
            ->where('class_date', '>=', $from->format('Y-m-d'))
            ->where('status', 'pending')
            ->delete();
    }

    /**
     * Get upcoming classes for a timetable.
     *
     * @param int $timetableId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUpcomingClasses(int $timetableId, int $limit = 10)
    {
        return ClassInstance::where('timetable_id', $timetableId)
            ->where('class_date', '>=', Carbon::today())
            ->orderBy('class_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete all pending classes for a timetable.
     * Only deletes classes with status 'pending' or 'waiting_list'.
     * Preserves attended, cancelled, and other completed classes.
     *
     * @param int $timetableId
     * @return array Statistics about deleted classes
     */
    public function deleteAllPendingClasses(int $timetableId): array
    {
        $deletedCount = ClassInstance::where('timetable_id', $timetableId)
            ->whereIn('status', ['pending', 'waiting_list'])
            ->delete();

        return [
            'deleted' => $deletedCount,
        ];
    }

    /**
     * Sync future/pending classes with updated timetable settings.
     * Updates only pending and waiting_list classes with dates >= today.
     * Past classes (attended, cancelled, etc.) are not modified to preserve data integrity.
     *
     * @param int $timetableId
     * @param array $oldTimetableData Optional: old timetable data before update (for comparison)
     * @return array Statistics about updated classes
     */
    public function syncFutureClasses(int $timetableId, ?array $oldTimetableData = null): array
    {
        $timetable = Timetable::with(['student', 'teacher', 'course'])->findOrFail($timetableId);
        $today = Carbon::today();

        // Find all future/pending classes for this timetable
        $futureClasses = ClassInstance::where('timetable_id', $timetableId)
            ->where('class_date', '>=', $today->format('Y-m-d'))
            ->whereIn('status', ['pending', 'waiting_list'])
            ->get();

        $updated = 0;
        $deleted = 0;
        $daysOfWeek = $timetable->days_of_week ?? [];
        $timeSlots = $timetable->time_slots ?? [];

        foreach ($futureClasses as $class) {
            $classDate = Carbon::parse($class->class_date);
            $dayOfWeek = $classDate->dayOfWeekIso; // 1 = Monday, 7 = Sunday

            // Check if this day is still in the timetable's days_of_week
            if (!in_array($dayOfWeek, $daysOfWeek)) {
                // Day was removed from schedule - delete this class
                $class->delete();
                $deleted++;
                continue;
            }

            // Get time slots for this day
            $dayTimeSlots = array_filter($timeSlots, function ($slot) use ($dayOfWeek) {
                return isset($slot['day']) && $slot['day'] == $dayOfWeek;
            });

            // Check if the class's current time slot still exists
            // Get start_time in HH:MM format for comparison
            $currentStartTime = is_string($class->start_time) 
                ? substr($class->start_time, 0, 5) // Extract HH:MM from string
                : Carbon::parse($class->start_time)->format('H:i');
            
            $timeSlotExists = false;
            $matchingSlot = null;

            foreach ($dayTimeSlots as $slot) {
                if ($slot['start'] === $currentStartTime) {
                    $timeSlotExists = true;
                    $matchingSlot = $slot;
                    break;
                }
            }

            if (!$timeSlotExists) {
                // Time slot was removed - delete this class
                $class->delete();
                $deleted++;
                continue;
            }

            // Update class with new timetable settings
            $class->student_id = $timetable->student_id;
            $class->teacher_id = $timetable->teacher_id;
            $class->course_id = $timetable->course_id;

            // Always update time slot to ensure consistency (even if same, recalculate duration)
            // Store time values as strings (HH:MM format from time slots)
            $startTimeStr = $matchingSlot['start']; // e.g., "10:00"
            $endTimeStr = $matchingSlot['end']; // e.g., "11:00"
            
            $class->start_time = $startTimeStr;
            $class->end_time = $endTimeStr;
            
            // Recalculate duration (handles midnight-spanning times)
            $class->duration = $this->calculateDuration($startTimeStr, $endTimeStr);

            // Recalculate student date and time based on new timezone settings
            $teacherDate = $classDate->copy();
            
            if ($timetable->time_difference_minutes !== null && $timetable->time_difference_minutes !== 0) {
                // Use manual time difference in minutes
                // Use the string values directly, not the model property (which may be cast to Carbon)
                [$startHour, $startMinute] = explode(':', $startTimeStr);
                [$endHour, $endMinute] = explode(':', $endTimeStr);
                
                // Create teacher datetime
                $teacherStartDateTime = Carbon::create(
                    $teacherDate->year,
                    $teacherDate->month,
                    $teacherDate->day,
                    (int)$startHour,
                    (int)$startMinute,
                    0
                );
                $teacherEndDateTime = Carbon::create(
                    $teacherDate->year,
                    $teacherDate->month,
                    $teacherDate->day,
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
                // Use UTC as fallback if timezones are not set
                $teacherTimezone = $timetable->teacher_timezone ?? 'UTC';
                $studentTimezone = $timetable->student_timezone ?? 'UTC';
                
                // Create teacher datetime in teacher's timezone
                // Use the string values directly, not the model property (which may be cast to Carbon)
                $teacherStartDateTime = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $teacherDate->format('Y-m-d') . ' ' . $startTimeStr . ':00',
                    $teacherTimezone
                );
                $teacherEndDateTime = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $teacherDate->format('Y-m-d') . ' ' . $endTimeStr . ':00',
                    $teacherTimezone
                );
                
                // Convert to student timezone
                $studentStartDateTime = $teacherStartDateTime->copy()->setTimezone($studentTimezone);
                $studentEndDateTime = $teacherEndDateTime->copy()->setTimezone($studentTimezone);
                
                // Get student date and times
                $class->student_date = $studentStartDateTime->format('Y-m-d');
                $class->student_start_time = $studentStartDateTime->format('H:i:s');
                $class->student_end_time = $studentEndDateTime->format('H:i:s');
            }

            $class->save();
            $updated++;
        }

        // Generate classes for new days/time slots that were added
        // Find the date range: from today to the latest existing class date, or 3 months ahead
        $latestClassDate = ClassInstance::where('timetable_id', $timetableId)
            ->where('class_date', '>=', $today->format('Y-m-d'))
            ->max('class_date');
        
        $toDate = $latestClassDate 
            ? Carbon::parse($latestClassDate)->addDays(7) // Extend 1 week past latest class
            : $today->copy()->addMonths(3); // Or 3 months from today if no classes exist
        
        $generated = 0;
        
        // Generate classes for the date range (generateClasses will skip existing ones)
        try {
            $generateResult = $this->generateClasses($timetableId, $today->format('Y-m-d'), $toDate->format('Y-m-d'));
            $generated = $generateResult['generated'];
        } catch (\Exception $e) {
            // Silently fail if generation fails (e.g., timetable not active)
            // This is okay because user can manually regenerate if needed
        }

        return [
            'updated' => $updated,
            'deleted' => $deleted,
            'generated' => $generated,
            'total_future_classes' => $futureClasses->count(),
        ];
    }

}
