<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClassInstance;
use App\Models\Package;
use App\Models\User;
use App\Services\FirebaseService;
use Carbon\Carbon;

class SendClassAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:send-class-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send push notifications to support team for class alerts';

    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for class alerts...');

        $now = Carbon::now();
        $fifteenMinutesLater = $now->copy()->addMinutes(15);
        $fiveMinutesAgo = $now->copy()->subMinutes(5);

        // Get support users with FCM tokens
        $supportUsers = User::where('role', 'support')
            ->whereNotNull('fcm_token')
            ->where('status', 'active')
            ->get();

        if ($supportUsers->isEmpty()) {
            $this->warn('No support users with FCM tokens found');
            return Command::SUCCESS;
        }

        $fcmTokens = $supportUsers->pluck('fcm_token')->filter()->toArray();

        if (empty($fcmTokens)) {
            $this->warn('No FCM tokens available');
            return Command::SUCCESS;
        }

        $alertsSent = 0;

        // 1. Classes starting in 15 minutes - Group by start time
        $classesStartingSoon = ClassInstance::with(['student', 'teacher.user', 'course'])
            ->where('class_date', $now->format('Y-m-d'))
            ->whereBetween('start_time', [
                $now->format('H:i:s'),
                $fifteenMinutesLater->format('H:i:s')
            ])
            ->where('status', 'pending')
            ->get();

        // Group classes by start time
        $groupedByTime = $classesStartingSoon->groupBy(function ($class) {
            // Handle both Carbon instance and string
            if ($class->start_time instanceof \Carbon\Carbon) {
                return $class->start_time->format('H:i:s');
            }
            return Carbon::parse($class->start_time)->format('H:i:s');
        });

        foreach ($groupedByTime as $timeKey => $classes) {
            $count = $classes->count();
            
            if ($count === 1) {
                // Single class - send individual notification
                $class = $classes->first();
                $studentName = $class->student->full_name ?? $class->student->name ?? 'Student';
                $teacherName = $class->teacher->user->name ?? 'Teacher';
                $courseName = $class->course->name ?? 'Course';
                
                $classDateTime = Carbon::parse($class->class_date->format('Y-m-d') . ' ' . $class->start_time->format('H:i:s'));
                $formattedTime = $classDateTime->format('h:i A');

                $title = '⏰ Class Starting Soon';
                $body = "{$studentName} with {$teacherName} - {$courseName}";

                $result = $this->firebaseService->sendBatchNotifications(
                    $fcmTokens,
                    $title,
                    $body,
                    [
                        'type' => 'class_starting',
                        'class_id' => (string)$class->id,
                        'student_id' => (string)$class->student_id,
                        'teacher_id' => (string)$class->teacher_id,
                        'student_name' => $studentName,
                        'teacher_name' => $teacherName,
                        'course_name' => $courseName,
                        'class_time' => $formattedTime,
                        'class_count' => '1',
                    ],
                    'normal'
                );
            } else {
                // Multiple classes - send grouped notification
                $classDateTime = Carbon::parse($classes->first()->class_date->format('Y-m-d') . ' ' . $classes->first()->start_time->format('H:i:s'));
                $formattedTime = $classDateTime->format('h:i A');

                $title = '⏰ Classes Starting Soon';
                $body = "{$count} classes scheduled to start soon";

                $result = $this->firebaseService->sendBatchNotifications(
                    $fcmTokens,
                    $title,
                    $body,
                    [
                        'type' => 'class_starting',
                        'class_count' => (string)$count,
                        'class_time' => $formattedTime,
                    ],
                    'normal'
                );
            }

            $alertsSent += $result['success'];
            $this->info("Sent 'starting soon' alert for {$count} class(es) at {$timeKey}");
        }

        // 2. Classes that started but teacher hasn't joined (within last 5 minutes) - Group by start time
        $classesNoTeacher = ClassInstance::with(['student', 'teacher.user', 'course'])
            ->where(function ($query) use ($now, $fiveMinutesAgo) {
                $query->whereRaw("CONCAT(class_date, ' ', start_time) <= ?", [$now->format('Y-m-d H:i:s')])
                    ->whereRaw("CONCAT(class_date, ' ', start_time) >= ?", [$fiveMinutesAgo->format('Y-m-d H:i:s')])
                    ->where('status', 'pending')
                    ->where('meet_link_used', false);
            })
            ->get();

        // Group classes by start time
        $groupedNoTeacher = $classesNoTeacher->groupBy(function ($class) {
            // Handle both Carbon instance and string
            if ($class->start_time instanceof \Carbon\Carbon) {
                return $class->start_time->format('H:i:s');
            }
            return Carbon::parse($class->start_time)->format('H:i:s');
        });

        foreach ($groupedNoTeacher as $timeKey => $classes) {
            $count = $classes->count();
            
            if ($count === 1) {
                // Single class - send individual notification
                $class = $classes->first();
                $studentName = $class->student->full_name ?? $class->student->name ?? 'Student';
                $teacherName = $class->teacher->user->name ?? 'Teacher';
                $courseName = $class->course->name ?? 'Course';
                
                // Get the full class datetime by combining date and time
                $classDate = $class->class_date instanceof \Carbon\Carbon 
                    ? $class->class_date 
                    : Carbon::parse($class->class_date);
                
                $startTime = $class->start_time instanceof \Carbon\Carbon 
                    ? $class->start_time 
                    : Carbon::parse($class->start_time);
                
                // Combine date and time to get the full class start datetime
                $classDateTime = $classDate->copy()->setTime(
                    $startTime->hour,
                    $startTime->minute,
                    $startTime->second
                );
                
                $formattedTime = $classDateTime->format('h:i A');
                
                // Calculate the difference in minutes between now and class start time
                // diffInMinutes() returns absolute difference by default
                // We want: elapsed = now - classStartTime (how many minutes have passed)
                $elapsedMinutes = $now->diffInMinutes($classDateTime);
                
                // Ensure at least 1 minute if class has started (to avoid "0 minutes")
                if ($elapsedMinutes == 0 && $now->greaterThanOrEqualTo($classDateTime)) {
                    $elapsedMinutes = 1; // Just started, show 1 minute
                }

                $title = '⚠️ No Teacher Joined';
                $body = "{$studentName} with {$teacherName} - {$courseName}";

                $result = $this->firebaseService->sendBatchNotifications(
                    $fcmTokens,
                    $title,
                    $body,
                    [
                        'type' => 'teacher_no_join',
                        'class_id' => (string)$class->id,
                        'student_id' => (string)$class->student_id,
                        'teacher_id' => (string)$class->teacher_id,
                        'student_name' => $studentName,
                        'teacher_name' => $teacherName,
                        'course_name' => $courseName,
                        'class_time' => $formattedTime,
                        'elapsed_minutes' => (string)$elapsedMinutes,
                        'class_count' => '1',
                    ],
                    'high'
                );
            } else {
                // Multiple classes - send grouped notification
                $firstClass = $classes->first();
                
                // Get the full class datetime by combining date and time
                $classDate = $firstClass->class_date instanceof \Carbon\Carbon 
                    ? $firstClass->class_date 
                    : Carbon::parse($firstClass->class_date);
                
                $startTime = $firstClass->start_time instanceof \Carbon\Carbon 
                    ? $firstClass->start_time 
                    : Carbon::parse($firstClass->start_time);
                
                // Combine date and time to get the full class start datetime
                $classDateTime = $classDate->copy()->setTime(
                    $startTime->hour,
                    $startTime->minute,
                    $startTime->second
                );
                
                // Calculate the difference in minutes between now and class start time
                // diffInMinutes() returns absolute difference by default
                // We want: elapsed = now - classStartTime (how many minutes have passed)
                $elapsedMinutes = $now->diffInMinutes($classDateTime);
                
                // Ensure at least 1 minute if class has started (to avoid "0 minutes")
                if ($elapsedMinutes == 0 && $now->greaterThanOrEqualTo($classDateTime)) {
                    $elapsedMinutes = 1; // Just started, show 1 minute
                }

                $title = '⚠️ No Teacher Joined';
                $body = "{$count} classes started, no teacher";

                $result = $this->firebaseService->sendBatchNotifications(
                    $fcmTokens,
                    $title,
                    $body,
                    [
                        'type' => 'teacher_no_join',
                        'class_count' => (string)$count,
                        'elapsed_minutes' => (string)$elapsedMinutes,
                    ],
                    'high'
                );
            }

            $alertsSent += $result['success'];
            $this->info("Sent 'no teacher' alert for {$count} class(es) at {$timeKey}");
        }

        // 3. Finished packages (check for packages finished in last hour)
        $oneHourAgo = $now->copy()->subHour();
        $finishedPackages = Package::with('student')
            ->where('status', 'finished')
            ->where('updated_at', '>=', $oneHourAgo)
            ->where(function ($query) {
                $query->whereNull('last_notification_sent')
                    ->orWhereColumn('last_notification_sent', '<', 'updated_at');
            })
            ->get();

        foreach ($finishedPackages as $package) {
            $studentName = $package->student->name ?? 'Student';
            $remainingHours = $package->remaining_hours ?? 0;

            $title = 'Package Finished';
            $body = "{$studentName}'s package has ended. Remaining: {$remainingHours} hours";

            $result = $this->firebaseService->sendBatchNotifications(
                $fcmTokens,
                $title,
                $body,
                [
                    'type' => 'package_finished',
                    'package_id' => (string)$package->id,
                    'student_id' => (string)$package->student_id,
                ],
                'normal'
            );

            $alertsSent += $result['success'];
            $this->info("Sent 'package finished' alert for package #{$package->id}");
        }

        $this->info("Total alerts sent: {$alertsSent}");
        return Command::SUCCESS;
    }
}
