<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClassInstance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Course;
use App\Models\Package;
use Carbon\Carbon;

class TestNotificationClassesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        
        // Get or create students
        $students = Student::where('status', 'active')->limit(6)->get();
        if ($students->count() < 6) {
            $this->command->warn('Not enough students found. Creating test students...');
            for ($i = $students->count(); $i < 6; $i++) {
                $students->push(Student::create([
                    'full_name' => "Test Student " . ($i + 1),
                    'email' => "teststudent" . ($i + 1) . "@example.com",
                    'whatsapp' => '+96650000000' . ($i + 1),
                    'country' => 'Saudi Arabia',
                    'currency' => 'SAR',
                    'timezone' => 'Asia/Riyadh',
                    'status' => 'active',
                ]));
            }
        }

        // Get teachers
        $teachers = Teacher::whereHas('user', function ($query) {
            $query->where('status', 'active');
        })->limit(3)->get();

        if ($teachers->isEmpty()) {
            $this->command->error('No active teachers found. Please seed teachers first.');
            return;
        }

        // Get courses
        $courses = Course::where('status', 'active')->limit(3)->get();
        if ($courses->isEmpty()) {
            $this->command->error('No active courses found. Please seed courses first.');
            return;
        }

        // Ensure packages exist for students
        foreach ($students as $student) {
            $package = Package::where('student_id', $student->id)
                ->where('status', 'active')
                ->where('remaining_classes', '>', 0)
                ->first();

            if (!$package) {
                Package::create([
                    'student_id' => $student->id,
                    'start_date' => $now->copy()->subDays(30),
                    'total_classes' => 100,
                    'remaining_classes' => 100,
                    'hour_price' => 100,
                    'currency' => $student->currency ?? 'SAR',
                    'round_number' => 1,
                    'status' => 'active',
                ]);
            }
        }

        $today = $now->format('Y-m-d');

        // 1. Create 3 classes starting in 5 minutes (same time for grouping test)
        $startTimeIn5Minutes = $now->copy()->addMinutes(5);
        $endTimeIn5Minutes = $startTimeIn5Minutes->copy()->addHour();
        
        $this->command->info("Creating 3 classes starting at {$startTimeIn5Minutes->format('H:i:s')} (in 5 minutes)...");
        
        for ($i = 0; $i < 3; $i++) {
            $student = $students[$i];
            $teacher = $teachers[$i % $teachers->count()];
            $course = $courses[$i % $courses->count()];
            
            $package = Package::where('student_id', $student->id)
                ->where('status', 'active')
                ->first();

            ClassInstance::withoutEvents(function () use (
                $student,
                $teacher,
                $course,
                $package,
                $today,
                $startTimeIn5Minutes,
                $endTimeIn5Minutes
            ) {
                ClassInstance::create([
                    'student_id' => $student->id,
                    'teacher_id' => $teacher->id,
                    'course_id' => $course->id,
                    'package_id' => $package->id,
                    'class_date' => $today,
                    'start_time' => $startTimeIn5Minutes->format('H:i:s'),
                    'end_time' => $endTimeIn5Minutes->format('H:i:s'),
                    'duration' => 60,
                    'status' => 'pending',
                    'meet_link_used' => false,
                ]);
            });
        }

        // 2. Create 3 classes that started 3 minutes ago (same time for grouping test)
        $startTime3MinutesAgo = $now->copy()->subMinutes(3);
        $endTime3MinutesAgo = $startTime3MinutesAgo->copy()->addHour();
        
        $this->command->info("Creating 3 classes that started at {$startTime3MinutesAgo->format('H:i:s')} (3 minutes ago)...");
        
        for ($i = 3; $i < 6; $i++) {
            $student = $students[$i];
            $teacher = $teachers[$i % $teachers->count()];
            $course = $courses[$i % $courses->count()];
            
            $package = Package::where('student_id', $student->id)
                ->where('status', 'active')
                ->first();

            ClassInstance::withoutEvents(function () use (
                $student,
                $teacher,
                $course,
                $package,
                $today,
                $startTime3MinutesAgo,
                $endTime3MinutesAgo
            ) {
                ClassInstance::create([
                    'student_id' => $student->id,
                    'teacher_id' => $teacher->id,
                    'course_id' => $course->id,
                    'package_id' => $package->id,
                    'class_date' => $today,
                    'start_time' => $startTime3MinutesAgo->format('H:i:s'),
                    'end_time' => $endTime3MinutesAgo->format('H:i:s'),
                    'duration' => 60,
                    'status' => 'pending',
                    'meet_link_used' => false, // Teacher hasn't joined
                ]);
            });
        }

        $this->command->info('✅ Created 6 test classes:');
        $this->command->info("   - 3 classes starting in 5 minutes at {$startTimeIn5Minutes->format('H:i:s')}");
        $this->command->info("   - 3 classes that started 3 minutes ago at {$startTime3MinutesAgo->format('H:i:s')}");
        $this->command->info('');
        $this->command->info('Run: php artisan support:send-class-alerts');
    }
}
