<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'timezone' => 'UTC',
                'status' => 'active',
            ]
        );

        if ($adminRole && !$admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        // Create teacher user
        $teacherRole = Role::where('name', 'teacher')->first();
        
        $teacherUser = User::firstOrCreate(
            ['email' => 'teacher@example.com'],
            [
                'name' => 'Teacher User',
                'password' => Hash::make('password'),
                'role' => 'teacher',
                'timezone' => 'UTC',
                'status' => 'active',
            ]
        );

        if ($teacherRole && !$teacherUser->hasRole('teacher')) {
            $teacherUser->assignRole($teacherRole);
        }

        // Create teacher profile
        Teacher::firstOrCreate(
            ['user_id' => $teacherUser->id],
            [
                'hourly_rate' => 50.00,
                'currency' => 'USD',
                'timezone' => 'UTC',
                'status' => 'active',
                'bio' => 'Experienced teacher',
                'meet_link' => 'https://meet.google.com/abc-defg-hij', // Example meet link
            ]
        );
    }
}
