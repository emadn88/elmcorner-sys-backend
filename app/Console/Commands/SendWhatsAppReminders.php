<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrialClass;
use App\Models\ClassInstance;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendWhatsAppReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp reminders for trials and classes';

    protected $reminderService;

    public function __construct(ReminderService $reminderService)
    {
        parent::__construct();
        $this->reminderService = $reminderService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $this->info("Checking for reminders at {$now->format('Y-m-d H:i:s')}");

        $sentCount = 0;

        // Process trials
        $sentCount += $this->processTrialReminders($now);

        // Process classes
        $sentCount += $this->processClassReminders($now);

        if ($sentCount > 0) {
            $this->info("Sent {$sentCount} reminders");
        } else {
            $this->info("No reminders to send");
        }

        return Command::SUCCESS;
    }

    /**
     * Process trial reminders
     */
    protected function processTrialReminders(Carbon $now): int
    {
        $sentCount = 0;

        // 2 hours before - check if trial starts in 2 hours (within current minute) - STUDENT ONLY
        // Use teacher time for timing (student time is only for display)
        $twoHoursBefore = $now->copy()->addHours(2);
        $trials2HoursBefore = TrialClass::where('status', 'pending')
            ->where('reminder_2hours_before_sent', false)
            ->whereDate('trial_date', $twoHoursBefore->format('Y-m-d'))
            ->get()
            ->filter(function ($trial) use ($twoHoursBefore) {
                // Always use teacher time for timing
                if ($trial->teacher_date && $trial->teacher_start_time) {
                    $trialDate = Carbon::parse($trial->teacher_date);
                    $startTime = Carbon::parse($trial->teacher_start_time);
                } else {
                    $trialDate = Carbon::parse($trial->trial_date);
                    $startTime = Carbon::parse($trial->start_time);
                }
                $trialDateTime = $trialDate->copy()->setTime($startTime->hour, $startTime->minute, 0);
                // Check if trial starts within the current minute (2 hours from now)
                return $trialDateTime->format('Y-m-d H:i') === $twoHoursBefore->format('Y-m-d H:i');
            });

        foreach ($trials2HoursBefore as $trial) {
            // Only send to student for 2 hours before reminder
            if ($this->reminderService->sendTrialReminderToStudentOnly($trial, '2hours_before')) {
                $trial->reminder_2hours_before_sent = true;
                $trial->save();
                $sentCount++;
            }
        }

        // 5 minutes before - check if trial starts in 5 minutes (within current minute)
        $fiveMinutesBefore = $now->copy()->addMinutes(5);
        $trials5MinBefore = TrialClass::where('status', 'pending')
            ->where('reminder_5min_before_sent', false)
            ->whereDate('trial_date', $fiveMinutesBefore->format('Y-m-d'))
            ->get()
            ->filter(function ($trial) use ($fiveMinutesBefore) {
                $trialDate = Carbon::parse($trial->trial_date);
                $startTime = Carbon::parse($trial->start_time);
                $trialDateTime = $trialDate->copy()->setTime($startTime->hour, $startTime->minute, 0);
                // Check if trial starts within the current minute (5 minutes from now)
                return $trialDateTime->format('Y-m-d H:i') === $fiveMinutesBefore->format('Y-m-d H:i');
            });

        foreach ($trials5MinBefore as $trial) {
            if ($this->reminderService->sendTrialReminder($trial, '5min_before')) {
                $trial->reminder_5min_before_sent = true;
                $trial->save();
                $sentCount++;
            }
        }

        // At start time - check if trial starts now (within current minute)
        $trialsStartTime = TrialClass::where('status', 'pending')
            ->where('reminder_start_time_sent', false)
            ->whereDate('trial_date', $now->format('Y-m-d'))
            ->get()
            ->filter(function ($trial) use ($now) {
                $trialDate = Carbon::parse($trial->trial_date);
                $startTime = Carbon::parse($trial->start_time);
                $trialDateTime = $trialDate->copy()->setTime($startTime->hour, $startTime->minute, 0);
                // Check if trial starts within the current minute
                return $trialDateTime->format('Y-m-d H:i') === $now->format('Y-m-d H:i');
            });

        foreach ($trialsStartTime as $trial) {
            if ($this->reminderService->sendTrialReminder($trial, 'start_time')) {
                $trial->reminder_start_time_sent = true;
                $trial->save();
                $sentCount++;
            }
        }

        // 5 minutes after (only if not entered) - check if trial started 5 minutes ago (within current minute)
        // Also stop if trial time has passed significantly (more than 15 minutes) to prevent endless reminders
        $fiveMinutesAgo = $now->copy()->subMinutes(5);
        $trials5MinAfter = TrialClass::where('status', 'pending')
            ->where('reminder_5min_after_sent', false)
            ->where('meet_link_used', false)
            ->whereDate('trial_date', $fiveMinutesAgo->format('Y-m-d'))
            ->get()
            ->filter(function ($trial) use ($fiveMinutesAgo, $now) {
                $trialDate = Carbon::parse($trial->trial_date);
                $startTime = Carbon::parse($trial->start_time);
                $trialDateTime = $trialDate->copy()->setTime($startTime->hour, $startTime->minute, 0);
                
                // Don't send if trial time has passed more than 15 minutes ago (safety check)
                $minutesSinceStart = $now->diffInMinutes($trialDateTime, false);
                if ($minutesSinceStart > 15) {
                    return false; // Trial started more than 15 minutes ago, stop reminders
                }
                
                // Check if trial started within the current minute (5 minutes ago)
                return $trialDateTime->format('Y-m-d H:i') === $fiveMinutesAgo->format('Y-m-d H:i');
            });

        foreach ($trials5MinAfter as $trial) {
            if ($this->reminderService->sendTrialReminder($trial, '5min_after')) {
                $trial->reminder_5min_after_sent = true;
                $trial->save();
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Process class reminders
     */
    protected function processClassReminders(Carbon $now): int
    {
        $sentCount = 0;

        // 2 hours before - check if class starts in 2 hours (within current minute) - STUDENT ONLY
        // Use teacher time for timing (student time is only for display)
        $twoHoursBefore = $now->copy()->addHours(2);
        $classes2HoursBefore = ClassInstance::where('status', 'pending')
            ->where('reminder_2hours_before_sent', false)
            ->whereDate('class_date', $twoHoursBefore->format('Y-m-d'))
            ->get()
            ->filter(function ($class) use ($twoHoursBefore) {
                // Always use teacher time for timing
                $classDate = Carbon::parse($class->class_date);
                $startTime = is_string($class->start_time) 
                    ? Carbon::parse($class->start_time) 
                    : Carbon::parse($class->start_time);
                $classDateTime = $classDate->copy()->setTime($startTime->hour, $startTime->minute, 0);
                // Check if class starts within the current minute (2 hours from now)
                return $classDateTime->format('Y-m-d H:i') === $twoHoursBefore->format('Y-m-d H:i');
            });

        foreach ($classes2HoursBefore as $class) {
            // Only send to student for 2 hours before reminder
            if ($this->reminderService->sendClassReminderToStudentOnly($class, '2hours_before')) {
                $class->reminder_2hours_before_sent = true;
                $class->save();
                $sentCount++;
            }
        }

        // 5 minutes before - check if class starts in 5 minutes (within current minute)
        // Use teacher time for timing (student time is only for display)
        $fiveMinutesBefore = $now->copy()->addMinutes(5);
        $classes5MinBefore = ClassInstance::where('status', 'pending')
            ->where('reminder_5min_before_sent', false)
            ->whereDate('class_date', $fiveMinutesBefore->format('Y-m-d'))
            ->get()
            ->filter(function ($class) use ($fiveMinutesBefore) {
                // Always use teacher time for timing
                $startTime = is_string($class->start_time) 
                    ? Carbon::parse($class->start_time) 
                    : Carbon::parse($class->start_time);
                $classDate = Carbon::parse($class->class_date);
                $classDateTime = $classDate->copy()->setTime($startTime->hour, $startTime->minute, 0);
                // Check if class starts within the current minute (5 minutes from now)
                return $classDateTime->format('Y-m-d H:i') === $fiveMinutesBefore->format('Y-m-d H:i');
            });

        foreach ($classes5MinBefore as $class) {
            if ($this->reminderService->sendClassReminder($class, '5min_before')) {
                $class->reminder_5min_before_sent = true;
                $class->save();
                $sentCount++;
            }
        }

        // At start time - check if class starts now (within current minute)
        $classesStartTime = ClassInstance::where('status', 'pending')
            ->where('reminder_start_time_sent', false)
            ->whereDate('class_date', $now->format('Y-m-d'))
            ->get()
            ->filter(function ($class) use ($now) {
                $startTime = is_string($class->start_time) 
                    ? Carbon::parse($class->start_time) 
                    : Carbon::parse($class->start_time);
                $classDate = Carbon::parse($class->class_date);
                $classDateTime = $classDate->copy()->setTime($startTime->hour, $startTime->minute, 0);
                // Check if class starts within the current minute
                return $classDateTime->format('Y-m-d H:i') === $now->format('Y-m-d H:i');
            });

        foreach ($classesStartTime as $class) {
            if ($this->reminderService->sendClassReminder($class, 'start_time')) {
                $class->reminder_start_time_sent = true;
                $class->save();
                $sentCount++;
            }
        }

        // 5 minutes after (only if teacher hasn't clicked start meet before 5 minutes) - check if class started 5 minutes ago (within current minute)
        // Also stop if class time has passed significantly (more than 15 minutes) to prevent endless reminders
        $fiveMinutesAgo = $now->copy()->subMinutes(5);
        $classes5MinAfter = ClassInstance::where('status', 'pending')
            ->where('reminder_5min_after_sent', false)
            ->where('meet_link_used', false)
            ->whereDate('class_date', $fiveMinutesAgo->format('Y-m-d'))
            ->get()
            ->filter(function ($class) use ($fiveMinutesAgo, $now) {
                $startTime = is_string($class->start_time) 
                    ? Carbon::parse($class->start_time) 
                    : Carbon::parse($class->start_time);
                $classDate = Carbon::parse($class->class_date);
                $classDateTime = $classDate->copy()->setTime($startTime->hour, $startTime->minute, 0);
                
                // Don't send if class time has passed more than 15 minutes ago (safety check)
                $minutesSinceStart = $now->diffInMinutes($classDateTime, false);
                if ($minutesSinceStart > 15) {
                    return false; // Class started more than 15 minutes ago, stop reminders
                }
                
                // Check if teacher clicked start meet before 5 minutes (if meet_link_accessed_at exists and is before 5 minutes after start)
                if ($class->meet_link_accessed_at) {
                    $accessedAt = Carbon::parse($class->meet_link_accessed_at);
                    $fiveMinutesAfterStart = $classDateTime->copy()->addMinutes(5);
                    // If teacher accessed meet before 5 minutes after start, don't send reminder
                    if ($accessedAt->lessThanOrEqualTo($fiveMinutesAfterStart)) {
                        return false;
                    }
                }
                
                // Check if class started within the current minute (5 minutes ago)
                return $classDateTime->format('Y-m-d H:i') === $fiveMinutesAgo->format('Y-m-d H:i');
            });

        foreach ($classes5MinAfter as $class) {
            if ($this->reminderService->sendClassReminder($class, '5min_after')) {
                $class->reminder_5min_after_sent = true;
                $class->save();
                $sentCount++;
            }
        }

        return $sentCount;
    }
}
