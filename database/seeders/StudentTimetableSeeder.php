<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Family;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Course;
use App\Models\Timetable;
use App\Models\Package;
use App\Models\ClassInstance;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class StudentTimetableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create families
        $family1 = Family::create([
            'name' => 'Al-Saud Family',
            'email' => 'alsaud@example.com',
            'whatsapp' => '+966501234567',
            'country' => 'Saudi Arabia',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'status' => 'active',
        ]);

        $family2 = Family::create([
            'name' => 'Al-Rashid Family',
            'email' => 'alrashid@example.com',
            'whatsapp' => '+966507654321',
            'country' => 'Saudi Arabia',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'status' => 'active',
        ]);

        // Create students
        $student1 = Student::create([
            'family_id' => $family1->id,
            'full_name' => 'Ahmed Al-Saud',
            'email' => 'ahmed@example.com',
            'whatsapp' => '+966501111111',
            'country' => 'Saudi Arabia',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'status' => 'active',
            'type' => 'confirmed',
            'tags' => ['active', 'beginner'],
        ]);

        $student2 = Student::create([
            'family_id' => $family1->id,
            'full_name' => 'Fatima Al-Saud',
            'email' => 'fatima@example.com',
            'whatsapp' => '+966502222222',
            'country' => 'Saudi Arabia',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'status' => 'active',
            'type' => 'confirmed',
            'tags' => ['active', 'intermediate'],
        ]);

        $student3 = Student::create([
            'family_id' => $family2->id,
            'full_name' => 'Mohammed Al-Rashid',
            'email' => 'mohammed@example.com',
            'whatsapp' => '+966503333333',
            'country' => 'Saudi Arabia',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'status' => 'active',
            'type' => 'confirmed',
            'tags' => ['active', 'advanced'],
        ]);

        // Create teacher users
        $teacherUser1 = User::firstOrCreate(
            ['email' => 'sarah@example.com'],
            [
                'name' => 'Sarah Johnson',
                'password' => Hash::make('password'),
                'role' => 'teacher',
                'timezone' => 'UTC',
                'status' => 'active',
            ]
        );

        $teacherUser2 = User::firstOrCreate(
            ['email' => 'ahmed.teacher@example.com'],
            [
                'name' => 'Ahmed Hassan',
                'password' => Hash::make('password'),
                'role' => 'teacher',
                'timezone' => 'Asia/Riyadh',
                'status' => 'active',
            ]
        );

        // Create teachers
        $teacher1 = Teacher::firstOrCreate(
            ['user_id' => $teacherUser1->id],
            [
                'hourly_rate' => 50.00,
                'currency' => 'USD',
                'timezone' => 'UTC',
                'status' => 'active',
                'bio' => 'Experienced English teacher with 10 years of experience',
            ]
        );

        $teacher2 = Teacher::firstOrCreate(
            ['user_id' => $teacherUser2->id],
            [
                'hourly_rate' => 200.00,
                'currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'status' => 'active',
                'bio' => 'Native Arabic speaker, specialized in Quranic studies',
            ]
        );

        // Create courses
        $course1 = Course::create([
            'name' => 'English Conversation',
            'category' => 'Language',
            'description' => 'Improve your English speaking skills',
            'status' => 'active',
        ]);

        $course2 = Course::create([
            'name' => 'Quran Recitation',
            'category' => 'Religious Studies',
            'description' => 'Learn proper Quran recitation with Tajweed',
            'status' => 'active',
        ]);

        $course3 = Course::create([
            'name' => 'Mathematics',
            'category' => 'Academic',
            'description' => 'Advanced mathematics tutoring',
            'status' => 'active',
        ]);

        // Assign courses to teachers
        $teacher1->courses()->attach([$course1->id, $course3->id]);
        $teacher2->courses()->attach([$course2->id]);

        // Create packages for students
        $package1 = Package::create([
            'student_id' => $student1->id,
            'start_date' => Carbon::now()->subDays(30),
            'total_classes' => 20,
            'remaining_classes' => 15,
            'hour_price' => 50.00,
            'currency' => 'USD',
            'round_number' => 1,
            'status' => 'active',
        ]);

        $package2 = Package::create([
            'student_id' => $student2->id,
            'start_date' => Carbon::now()->subDays(15),
            'total_classes' => 30,
            'remaining_classes' => 25,
            'hour_price' => 200.00,
            'currency' => 'SAR',
            'round_number' => 1,
            'status' => 'active',
        ]);

        $package3 = Package::create([
            'student_id' => $student3->id,
            'start_date' => Carbon::now()->subDays(7),
            'total_classes' => 15,
            'remaining_classes' => 12,
            'hour_price' => 200.00,
            'currency' => 'SAR',
            'round_number' => 1,
            'status' => 'active',
        ]);

        // Create timetables
        $timetable1 = Timetable::create([
            'student_id' => $student1->id,
            'teacher_id' => $teacher1->id,
            'course_id' => $course1->id,
            'days_of_week' => [1, 3, 5], // Monday, Wednesday, Friday
            'time_slots' => [
                ['day' => 1, 'start' => '10:00', 'end' => '11:00'],
                ['day' => 3, 'start' => '10:00', 'end' => '11:00'],
                ['day' => 5, 'start' => '10:00', 'end' => '11:00'],
            ],
            'student_timezone' => 'Asia/Riyadh',
            'teacher_timezone' => 'UTC',
            'status' => 'active',
        ]);

        $timetable2 = Timetable::create([
            'student_id' => $student2->id,
            'teacher_id' => $teacher2->id,
            'course_id' => $course2->id,
            'days_of_week' => [2, 4], // Tuesday, Thursday
            'time_slots' => [
                ['day' => 2, 'start' => '14:00', 'end' => '15:00'],
                ['day' => 4, 'start' => '14:00', 'end' => '15:00'],
            ],
            'student_timezone' => 'Asia/Riyadh',
            'teacher_timezone' => 'Asia/Riyadh',
            'status' => 'active',
        ]);

        $timetable3 = Timetable::create([
            'student_id' => $student3->id,
            'teacher_id' => $teacher1->id,
            'course_id' => $course3->id,
            'days_of_week' => [1, 4], // Monday, Thursday
            'time_slots' => [
                ['day' => 1, 'start' => '16:00', 'end' => '17:00'],
                ['day' => 4, 'start' => '16:00', 'end' => '17:00'],
            ],
            'student_timezone' => 'Asia/Riyadh',
            'teacher_timezone' => 'UTC',
            'status' => 'active',
        ]);

        // Generate classes for the next 3 months
        $this->generateClassesForTimetable($timetable1, $package1);
        $this->generateClassesForTimetable($timetable2, $package2);
        $this->generateClassesForTimetable($timetable3, $package3);
    }

    /**
     * Generate classes for a timetable
     */
    private function generateClassesForTimetable(Timetable $timetable, Package $package): void
    {
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addMonths(3);
        $daysOfWeek = $timetable->days_of_week;
        $timeSlots = $timetable->time_slots;

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dayOfWeek = $currentDate->dayOfWeekIso; // 1 = Monday, 7 = Sunday

            if (in_array($dayOfWeek, $daysOfWeek)) {
                $dayTimeSlots = array_filter($timeSlots, function ($slot) use ($dayOfWeek) {
                    return isset($slot['day']) && $slot['day'] == $dayOfWeek;
                });

                foreach ($dayTimeSlots as $slot) {
                    // Skip if class already exists
                    $existingClass = ClassInstance::where('timetable_id', $timetable->id)
                        ->where('class_date', $currentDate->format('Y-m-d'))
                        ->where('start_time', $slot['start'])
                        ->first();

                    if ($existingClass) {
                        continue;
                    }

                    $startTime = Carbon::parse($slot['start']);
                    $endTime = Carbon::parse($slot['end']);
                    $duration = $startTime->diffInMinutes($endTime);

                    // Randomly set some classes as attended (past dates)
                    $status = 'pending';
                    if ($currentDate->lt(Carbon::now()) && rand(1, 3) === 1) {
                        $status = 'attended';
                    }

                    // Create class without triggering observer during seeding
                    ClassInstance::withoutEvents(function () use ($timetable, $package, $currentDate, $slot, $duration, $status) {
                        ClassInstance::create([
                            'timetable_id' => $timetable->id,
                            'package_id' => $package->id,
                            'student_id' => $timetable->student_id,
                            'teacher_id' => $timetable->teacher_id,
                            'course_id' => $timetable->course_id,
                            'class_date' => $currentDate->format('Y-m-d'),
                            'start_time' => $slot['start'],
                            'end_time' => $slot['end'],
                            'duration' => $duration,
                            'status' => $status,
                        ]);
                    });
                }
            }

            $currentDate->addDay();
        }
    }
}
