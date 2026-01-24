<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TrialClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Course;
use Carbon\Carbon;

class TrialClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get students (prefer trial type students, or create new ones)
        $student1 = Student::where('type', 'trial')->first();
        $student2 = Student::where('type', 'trial')->where('id', '!=', $student1?->id)->first();

        // If no trial students exist, create new ones
        if (!$student1) {
            $student1 = Student::create([
                'full_name' => 'Omar Trial Student',
                'email' => 'omar.trial@example.com',
                'whatsapp' => '+966504444444',
                'country' => 'Saudi Arabia',
                'currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'status' => 'active',
                'type' => 'trial',
            ]);
        }

        if (!$student2) {
            $student2 = Student::create([
                'full_name' => 'Layla Trial Student',
                'email' => 'layla.trial@example.com',
                'whatsapp' => '+966505555555',
                'country' => 'Saudi Arabia',
                'currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'status' => 'active',
                'type' => 'trial',
            ]);
        }

        // Get teachers
        $teacher1 = Teacher::whereHas('user', function ($query) {
            $query->where('email', 'sarah@example.com');
        })->first();

        $teacher2 = Teacher::whereHas('user', function ($query) {
            $query->where('email', 'ahmed.teacher@example.com');
        })->first();

        if (!$teacher1 || !$teacher2) {
            $this->command->warn('Teachers not found. Please run StudentTimetableSeeder first.');
            return;
        }

        // Get courses
        $course1 = Course::where('name', 'English Conversation')->first();
        $course2 = Course::where('name', 'Quran Recitation')->first();

        if (!$course1 || !$course2) {
            $this->command->warn('Courses not found. Please run StudentTimetableSeeder first.');
            return;
        }

        // Create first trial class - scheduled for tomorrow
        TrialClass::create([
            'student_id' => $student1->id,
            'teacher_id' => $teacher1->id,
            'course_id' => $course1->id,
            'trial_date' => Carbon::tomorrow(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'pending',
            'notes' => 'First trial class for new student',
        ]);

        // Create second trial class - scheduled for day after tomorrow
        TrialClass::create([
            'student_id' => $student2->id,
            'teacher_id' => $teacher2->id,
            'course_id' => $course2->id,
            'trial_date' => Carbon::tomorrow()->addDay(),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'status' => 'pending',
            'notes' => 'Second trial class for new student',
        ]);

        $this->command->info('Trial classes seeded successfully!');
    }
}
