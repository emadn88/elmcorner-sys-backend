<?php

namespace App\Services;

use App\Models\Timetable;
use App\Models\ClassInstance;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TimetableService
{
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

        // Get active package for the student
        $activePackage = Package::where('student_id', $timetable->student_id)
            ->where('status', 'active')
            ->where('remaining_classes', '>', 0)
            ->first();

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

                    // Parse start and end times
                    $startTime = Carbon::parse($slot['start']);
                    $endTime = Carbon::parse($slot['end']);
                    $duration = $startTime->diffInMinutes($endTime);

                    // Create class instance
                    $classInstance = ClassInstance::create([
                        'timetable_id' => $timetableId,
                        'package_id' => $activePackage?->id,
                        'student_id' => $timetable->student_id,
                        'teacher_id' => $timetable->teacher_id,
                        'course_id' => $timetable->course_id,
                        'class_date' => $currentDate->format('Y-m-d'),
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
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
}
