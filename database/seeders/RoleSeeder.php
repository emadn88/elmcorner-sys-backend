<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);
        $supportRole = Role::firstOrCreate(['name' => 'support']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);

        // Get all permissions
        $allPermissions = Permission::all();

        // Admin gets all permissions
        $adminRole->syncPermissions($allPermissions);

        // Teacher permissions
        $teacherPermissions = [
            'view_students',
            'view_timetables',
            'view_courses',
            'view_packages',
            'view_trials',
            'view_duties',
            'view_reports',
        ];
        $teacherRole->syncPermissions(
            Permission::whereIn('name', $teacherPermissions)->get()
        );

        // Support permissions
        $supportPermissions = [
            'view_students',
            'view_teachers',
            'view_timetables',
            'view_courses',
            'view_packages',
            'view_trials',
            'view_duties',
            'view_reports',
            'send_whatsapp',
        ];
        $supportRole->syncPermissions(
            Permission::whereIn('name', $supportPermissions)->get()
        );

        // Accountant permissions
        $accountantPermissions = [
            'view_students',
            'view_teachers',
            'view_billing',
            'manage_billing',
            'view_financials',
            'manage_expenses',
            'view_reports',
        ];
        $accountantRole->syncPermissions(
            Permission::whereIn('name', $accountantPermissions)->get()
        );
    }
}
