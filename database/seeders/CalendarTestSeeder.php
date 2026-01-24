<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Course;
use App\Models\ClassInstance;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CalendarTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $targetDate = Carbon::parse('2026-01-06');
        $numberOfClasses = 100;

        // Get all available students, teachers, and courses
        $students = Student::all();
        $teachers = Teacher::with('user')->get();
        $courses = Course::all();

        // Create more students if we have less than 20
        while ($students->count() < 20) {
            $student = Student::create([
                'full_name' => 'Test Student ' . ($students->count() + 1),
                'email' => 'teststudent' . ($students->count() + 1) . '@example.com',
                'whatsapp' => '+96650' . str_pad($students->count() + 1, 6, '0', STR_PAD_LEFT),
                'country' => 'Saudi Arabia',
                'currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'status' => 'active',
                'type' => 'confirmed',
            ]);
            $students->push($student);
        }

        // Create more teachers if we have less than 10
        while ($teachers->count() < 10) {
            $user = User::create([
                'name' => 'Test Teacher ' . ($teachers->count() + 1),
                'email' => 'testteacher' . ($teachers->count() + 1) . '@example.com',
                'password' => Hash::make('password'),
            ]);

            $teacher = Teacher::create([
                'user_id' => $user->id,
                'hourly_rate' => 100,
                'currency' => 'USD',
                'timezone' => 'UTC',
                'status' => 'active',
            ]);
            $teachers->push($teacher);
        }

        // Create more courses if we have less than 5
        while ($courses->count() < 5) {
            $course = Course::create([
                'name' => 'Test Course ' . ($courses->count() + 1),
                'description' => 'Test course for calendar testing',
                'status' => 'active',
            ]);
            $courses->push($course);
        }

        // Refresh collections
        $students = Student::all();
        $teachers = Teacher::with('user')->get();
        $courses = Course::all();

        if ($students->isEmpty() || $teachers->isEmpty() || $courses->isEmpty()) {
            $this->command->warn('Failed to create students, teachers, or courses.');
            return;
        }

        $this->command->info("Creating {$numberOfClasses} classes on {$targetDate->format('Y-m-d')}...");

        // Generate time slots throughout the day (from 8:00 AM to 10:00 PM, every 30 minutes)
        $timeSlots = [];
        for ($hour = 8; $hour <= 22; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $startTime = sprintf('%02d:%02d:00', $hour, $minute);
                $endHour = $minute + 30 >= 60 ? $hour + 1 : $hour;
                $endMinute = ($minute + 30) % 60;
                $endTime = sprintf('%02d:%02d:00', $endHour, $endMinute);
                
                if ($endHour <= 23) {
                    $timeSlots[] = [
                        'start' => $startTime,
                        'end' => $endTime,
                    ];
                }
            }
        }

        $created = 0;
        $attempts = 0;
        $maxAttempts = $numberOfClasses * 10; // Try up to 10x the target to find valid combinations
        $statuses = ['pending', 'attended', 'cancelled_by_student', 'cancelled_by_teacher', 'absent_student'];
        
        // Track used student-teacher-time combinations to avoid duplicates
        $usedCombinations = [];
        
        // Track which students and teachers are busy at each time slot
        $busyAtTime = [];

        // Create classes
        while ($created < $numberOfClasses && $attempts < $maxAttempts) {
            $attempts++;
            
            // Get random time slot
            $timeSlot = $timeSlots[array_rand($timeSlots)];
            $timeKey = $timeSlot['start'];
            
            // Initialize busy tracking for this time if not exists
            if (!isset($busyAtTime[$timeKey])) {
                $busyAtTime[$timeKey] = [
                    'students' => [],
                    'teachers' => [],
                ];
            }
            
            // Get random student and teacher that are not busy at this time
            $availableStudents = $students->filter(function ($student) use ($timeKey, $busyAtTime) {
                return !in_array($student->id, $busyAtTime[$timeKey]['students']);
            });
            
            $availableTeachers = $teachers->filter(function ($teacher) use ($timeKey, $busyAtTime) {
                return !in_array($teacher->id, $busyAtTime[$timeKey]['teachers']);
            });
            
            // If no available students or teachers at this time, try next time slot
            if ($availableStudents->isEmpty() || $availableTeachers->isEmpty()) {
                continue;
            }
            
            $student = $availableStudents->random();
            $teacher = $availableTeachers->random();
            $course = $courses->random();

            // Create unique key for this combination
            $combinationKey = $timeKey . '_' . $student->id . '_' . $teacher->id;
            
            // Skip if this exact combination already exists
            if (isset($usedCombinations[$combinationKey])) {
                continue;
            }

            // Get or create an active package for the student
            $package = Package::where('student_id', $student->id)
                ->where('status', 'active')
                ->where('remaining_classes', '>', 0)
                ->first();

            if (!$package) {
                // Create a package if none exists
                $package = Package::create([
                    'student_id' => $student->id,
                    'start_date' => $targetDate->copy()->subDays(30),
                    'total_classes' => 100,
                    'remaining_classes' => 100,
                    'hour_price' => 100,
                    'currency' => $student->currency ?? 'USD',
                    'round_number' => 1,
                    'status' => 'active',
                ]);
            }

            // Calculate duration
            $start = Carbon::parse($timeSlot['start']);
            $end = Carbon::parse($timeSlot['end']);
            $duration = $start->diffInMinutes($end);

            // Random status (weighted towards pending for future date)
            $status = 'pending';
            if (rand(1, 10) <= 2) {
                $status = $statuses[array_rand($statuses)];
            }

            // Create class without triggering observer
            ClassInstance::withoutEvents(function () use (
                $student,
                $teacher,
                $course,
                $package,
                $targetDate,
                $timeSlot,
                $duration,
                $status
            ) {
                ClassInstance::create([
                    'student_id' => $student->id,
                    'teacher_id' => $teacher->id,
                    'course_id' => $course->id,
                    'package_id' => $package->id,
                    'class_date' => $targetDate->format('Y-m-d'),
                    'start_time' => $timeSlot['start'],
                    'end_time' => $timeSlot['end'],
                    'duration' => $duration,
                    'status' => $status,
                ]);
            });

            // Mark this combination as used
            $usedCombinations[$combinationKey] = true;
            
            // Mark student and teacher as busy at this time
            $busyAtTime[$timeKey]['students'][] = $student->id;
            $busyAtTime[$timeKey]['teachers'][] = $teacher->id;
            
            $created++;
        }

        $this->command->info("Successfully created {$created} classes on {$targetDate->format('Y-m-d')}!");
    }
}
