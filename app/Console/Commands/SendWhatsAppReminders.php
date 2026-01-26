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

        // 5 minutes before
        $fiveMinutesBefore = $now->copy()->addMinutes(5);
        $trials5MinBefore = TrialClass::where('status', 'pending')
            ->where('reminder_5min_before_sent', false)
            ->whereDate('trial_date', $fiveMinutesBefore->format('Y-m-d'))
            ->get()
            ->filter(function ($trial) use ($fiveMinutesBefore) {
                $startTime = Carbon::parse($trial->start_time);
                return $startTime->format('H:i:s') === $fiveMinutesBefore->format('H:i:s');
            });

        foreach ($trials5MinBefore as $trial) {
            if ($this->reminderService->sendTrialReminder($trial, '5min_before')) {
                $trial->reminder_5min_before_sent = true;
                $trial->save();
                $sentCount++;
            }
        }

        // At start time
        $trialsStartTime = TrialClass::where('status', 'pending')
            ->where('reminder_start_time_sent', false)
            ->whereDate('trial_date', $now->format('Y-m-d'))
            ->get()
            ->filter(function ($trial) use ($now) {
                $startTime = Carbon::parse($trial->start_time);
                return $startTime->format('H:i:s') === $now->format('H:i:s');
            });

        foreach ($trialsStartTime as $trial) {
            if ($this->reminderService->sendTrialReminder($trial, 'start_time')) {
                $trial->reminder_start_time_sent = true;
                $trial->save();
                $sentCount++;
            }
        }

        // 5 minutes after (only if not entered)
        $fiveMinutesAgo = $now->copy()->subMinutes(5);
        $trials5MinAfter = TrialClass::where('status', 'pending')
            ->where('reminder_5min_after_sent', false)
            ->where('meet_link_used', false)
            ->whereDate('trial_date', $fiveMinutesAgo->format('Y-m-d'))
            ->get()
            ->filter(function ($trial) use ($fiveMinutesAgo) {
                $startTime = Carbon::parse($trial->start_time);
                return $startTime->format('H:i:s') === $fiveMinutesAgo->format('H:i:s');
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

        // 5 minutes before
        $fiveMinutesBefore = $now->copy()->addMinutes(5);
        $classes5MinBefore = ClassInstance::where('status', 'pending')
            ->where('reminder_5min_before_sent', false)
            ->whereDate('class_date', $fiveMinutesBefore->format('Y-m-d'))
            ->get()
            ->filter(function ($class) use ($fiveMinutesBefore) {
                $startTime = is_string($class->start_time) 
                    ? Carbon::parse($class->start_time) 
                    : Carbon::parse($class->start_time);
                return $startTime->format('H:i:s') === $fiveMinutesBefore->format('H:i:s');
            });

        foreach ($classes5MinBefore as $class) {
            if ($this->reminderService->sendClassReminder($class, '5min_before')) {
                $class->reminder_5min_before_sent = true;
                $class->save();
                $sentCount++;
            }
        }

        // At start time
        $classesStartTime = ClassInstance::where('status', 'pending')
            ->where('reminder_start_time_sent', false)
            ->whereDate('class_date', $now->format('Y-m-d'))
            ->get()
            ->filter(function ($class) use ($now) {
                $startTime = is_string($class->start_time) 
                    ? Carbon::parse($class->start_time) 
                    : Carbon::parse($class->start_time);
                return $startTime->format('H:i:s') === $now->format('H:i:s');
            });

        foreach ($classesStartTime as $class) {
            if ($this->reminderService->sendClassReminder($class, 'start_time')) {
                $class->reminder_start_time_sent = true;
                $class->save();
                $sentCount++;
            }
        }

        // 5 minutes after (only if not entered)
        $fiveMinutesAgo = $now->copy()->subMinutes(5);
        $classes5MinAfter = ClassInstance::where('status', 'pending')
            ->where('reminder_5min_after_sent', false)
            ->where('meet_link_used', false)
            ->whereDate('class_date', $fiveMinutesAgo->format('Y-m-d'))
            ->get()
            ->filter(function ($class) use ($fiveMinutesAgo) {
                $startTime = is_string($class->start_time) 
                    ? Carbon::parse($class->start_time) 
                    : Carbon::parse($class->start_time);
                return $startTime->format('H:i:s') === $fiveMinutesAgo->format('H:i:s');
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
