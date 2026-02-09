<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClassInstance;
use Carbon\Carbon;

class FixClassDurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classes:fix-durations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix class durations for time slots that span midnight';

    /**
     * Calculate duration in minutes for time slots that may span midnight.
     * 
     * @param string $startTime Time in H:i format (e.g., "23:00")
     * @param string $endTime Time in H:i format (e.g., "00:00")
     * @return int Duration in minutes
     */
    protected function calculateDuration($startTime, $endTime): int
    {
        // Handle Carbon objects or strings
        if ($startTime instanceof Carbon) {
            $startTime = $startTime->format('H:i');
        }
        if ($endTime instanceof Carbon) {
            $endTime = $endTime->format('H:i');
        }
        
        // Convert to string and extract HH:MM
        $startTime = (string)$startTime;
        $endTime = (string)$endTime;
        
        // Extract HH:MM from H:i:s format if needed
        if (strlen($startTime) > 5) {
            $startTime = substr($startTime, 0, 5);
        }
        if (strlen($endTime) > 5) {
            $endTime = substr($endTime, 0, 5);
        }
        
        // Parse time components
        $startParts = explode(':', $startTime);
        $endParts = explode(':', $endTime);
        
        if (count($startParts) < 2 || count($endParts) < 2) {
            throw new \Exception("Invalid time format: start={$startTime}, end={$endTime}");
        }
        
        $startHour = (int)$startParts[0];
        $startMin = (int)$startParts[1];
        $endHour = (int)$endParts[0];
        $endMin = (int)$endParts[1];
        
        $startMinutes = $startHour * 60 + $startMin;
        $endMinutes = $endHour * 60 + $endMin;
        
        // If end time is less than start time, it spans midnight
        if ($endMinutes < $startMinutes) {
            // Duration = (24 hours - start) + end
            return (24 * 60 - $startMinutes) + $endMinutes;
        }
        
        // Normal case: end - start
        return $endMinutes - $startMinutes;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Fixing class durations for midnight-spanning time slots...');

        // Get all classes with negative durations or where end < start
        $classes = ClassInstance::whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get();

        $fixed = 0;
        $skipped = 0;

        foreach ($classes as $class) {
            $startTime = $class->start_time;
            $endTime = $class->end_time;

            if (!$startTime || !$endTime) {
                $skipped++;
                continue;
            }

            // Calculate correct duration
            $correctDuration = $this->calculateDuration($startTime, $endTime);

            // Only update if duration is different (negative or incorrect)
            if ($class->duration != $correctDuration) {
                $oldDuration = $class->duration;
                $class->duration = $correctDuration;
                $class->save();

                $this->line("Fixed class #{$class->id}: {$oldDuration} min -> {$correctDuration} min ({$startTime} to {$endTime})");
                $fixed++;
            } else {
                $skipped++;
            }
        }

        $this->info("Fixed {$fixed} classes, skipped {$skipped} classes.");
        return 0;
    }
}
