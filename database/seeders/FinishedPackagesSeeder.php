<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Package;
use Carbon\Carbon;

class FinishedPackagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing students or create new ones if needed
        $students = Student::where('status', 'active')->take(3)->get();

        // If we don't have enough students, create some
        while ($students->count() < 3) {
            $student = Student::create([
                'full_name' => 'Test Student ' . ($students->count() + 1) . ' (Finished Package)',
                'email' => 'finished.student' . ($students->count() + 1) . '@example.com',
                'whatsapp' => '+96650' . str_pad($students->count() + 1, 6, '0', STR_PAD_LEFT),
                'country' => 'Saudi Arabia',
                'currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'status' => 'active',
                'type' => 'confirmed',
            ]);
            $students->push($student);
        }

        // Create 3 finished packages with different scenarios
        $now = Carbon::now();

        // Package 1: Finished with hours-based tracking (remaining_hours = 0)
        Package::create([
            'student_id' => $students[0]->id,
            'start_date' => $now->copy()->subDays(60),
            'total_classes' => 20,
            'remaining_classes' => 0,
            'total_hours' => 20.00,
            'remaining_hours' => 0.00, // Finished - no hours remaining
            'hour_price' => 50.00,
            'currency' => 'USD',
            'round_number' => 1,
            'status' => 'finished',
            'last_notification_sent' => null, // Never sent notification
            'notification_count' => 0,
            'created_at' => $now->copy()->subDays(60),
            'updated_at' => $now->copy()->subDays(2), // Finished 2 days ago
        ]);

        // Package 2: Finished with classes-based tracking (remaining_classes = 0)
        Package::create([
            'student_id' => $students[1]->id,
            'start_date' => $now->copy()->subDays(45),
            'total_classes' => 30,
            'remaining_classes' => 0, // Finished - no classes remaining
            'total_hours' => null,
            'remaining_hours' => null,
            'hour_price' => 200.00,
            'currency' => 'SAR',
            'round_number' => 1,
            'status' => 'finished',
            'last_notification_sent' => $now->copy()->subDays(5), // Notification sent 5 days ago
            'notification_count' => 1,
            'created_at' => $now->copy()->subDays(45),
            'updated_at' => $now->copy()->subDays(1), // Finished 1 day ago
        ]);

        // Package 3: Finished with hours-based tracking, notification already sent
        Package::create([
            'student_id' => $students[2]->id,
            'start_date' => $now->copy()->subDays(30),
            'total_classes' => 15,
            'remaining_classes' => 0,
            'total_hours' => 15.00,
            'remaining_hours' => 0.00, // Finished - no hours remaining
            'hour_price' => 150.00,
            'currency' => 'SAR',
            'round_number' => 1,
            'status' => 'finished',
            'last_notification_sent' => $now->copy()->subHours(12), // Notification sent 12 hours ago
            'notification_count' => 2,
            'created_at' => $now->copy()->subDays(30),
            'updated_at' => $now->copy()->subHours(12), // Finished 12 hours ago
        ]);

        $this->command->info('Created 3 finished packages for testing notifications page.');
    }
}
