<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\TeacherAvailability;

class TeacherAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all teachers
        $teachers = Teacher::all();

        if ($teachers->isEmpty()) {
            $this->command->warn('No teachers found. Please run StudentTimetableSeeder first.');
            return;
        }

        foreach ($teachers as $teacher) {
            // Clear existing availability
            TeacherAvailability::where('teacher_id', $teacher->id)->delete();

            // Teacher 1 (Sarah Johnson) - Available Monday, Wednesday, Friday
            if ($teacher->user && $teacher->user->email === 'sarah@example.com') {
                // Monday (2)
                TeacherAvailability::create([
                    'teacher_id' => $teacher->id,
                    'day_of_week' => 2,
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                    'timezone' => $teacher->timezone ?? 'UTC',
                    'is_available' => true,
                ]);

                // Wednesday (4)
                TeacherAvailability::create([
                    'teacher_id' => $teacher->id,
                    'day_of_week' => 4,
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                    'timezone' => $teacher->timezone ?? 'UTC',
                    'is_available' => true,
                ]);

                // Friday (6)
                TeacherAvailability::create([
                    'teacher_id' => $teacher->id,
                    'day_of_week' => 6,
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                    'timezone' => $teacher->timezone ?? 'UTC',
                    'is_available' => true,
                ]);

                // Additional afternoon slot on Monday
                TeacherAvailability::create([
                    'teacher_id' => $teacher->id,
                    'day_of_week' => 2,
                    'start_time' => '14:00',
                    'end_time' => '17:00',
                    'timezone' => $teacher->timezone ?? 'UTC',
                    'is_available' => true,
                ]);
            }

            // Teacher 2 (Ahmed Hassan) - Available Sunday, Tuesday, Thursday
            if ($teacher->user && $teacher->user->email === 'ahmed.teacher@example.com') {
                // Sunday (1)
                TeacherAvailability::create([
                    'teacher_id' => $teacher->id,
                    'day_of_week' => 1,
                    'start_time' => '10:00',
                    'end_time' => '13:00',
                    'timezone' => $teacher->timezone ?? 'Asia/Riyadh',
                    'is_available' => true,
                ]);

                // Tuesday (3)
                TeacherAvailability::create([
                    'teacher_id' => $teacher->id,
                    'day_of_week' => 3,
                    'start_time' => '10:00',
                    'end_time' => '13:00',
                    'timezone' => $teacher->timezone ?? 'Asia/Riyadh',
                    'is_available' => true,
                ]);

                // Thursday (5)
                TeacherAvailability::create([
                    'teacher_id' => $teacher->id,
                    'day_of_week' => 5,
                    'start_time' => '10:00',
                    'end_time' => '13:00',
                    'timezone' => $teacher->timezone ?? 'Asia/Riyadh',
                    'is_available' => true,
                ]);

                // Additional evening slot on Tuesday
                TeacherAvailability::create([
                    'teacher_id' => $teacher->id,
                    'day_of_week' => 3,
                    'start_time' => '18:00',
                    'end_time' => '21:00',
                    'timezone' => $teacher->timezone ?? 'Asia/Riyadh',
                    'is_available' => true,
                ]);
            }
        }

        $this->command->info('Teacher availability seeded successfully!');
    }
}
