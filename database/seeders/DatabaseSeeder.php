<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            SupportUserSeeder::class,
            SystemSettingsSeeder::class,
            StudentTimetableSeeder::class,
            TeacherAvailabilitySeeder::class,
            TrialClassSeeder::class,
            CalendarTestSeeder::class,
            FinishedPackagesSeeder::class,
            BillSeeder::class,
        ]);
    }
}
