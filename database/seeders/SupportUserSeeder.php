<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SupportUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supportRole = Role::where('name', 'support')->first();
        
        if (!$supportRole) {
            $supportRole = Role::create(['name' => 'support']);
        }

        $supportUser = User::firstOrCreate(
            ['email' => 'support@elmcorner.com'],
            [
                'name' => 'Support Team',
                'password' => Hash::make('support123'),
                'role' => 'support',
                'timezone' => 'UTC',
                'status' => 'active',
            ]
        );

        if (!$supportUser->hasRole('support')) {
            $supportUser->assignRole($supportRole);
        }

        $this->command->info('Support user created:');
        $this->command->info('Email: support@elmcorner.com');
        $this->command->info('Password: support123');
    }
}
