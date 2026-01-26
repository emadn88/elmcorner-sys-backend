<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignAdminRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all roles
        $roles = Role::all()->keyBy('name');
        
        // Assign Spatie roles to users based on their role field
        $users = User::all();
        
        foreach ($users as $user) {
            $roleName = $user->role;
            
            if ($roleName && isset($roles[$roleName])) {
                // Assign the Spatie role if not already assigned
                if (!$user->hasRole($roleName)) {
                    $user->assignRole($roleName);
                    $this->command->info("Assigned role '{$roleName}' to user {$user->email}");
                }
            }
        }
        
        $this->command->info("Role assignment completed!");
    }
}
