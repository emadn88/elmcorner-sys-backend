<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'reminder_before_minutes',
                'value' => json_encode(30),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_timezone',
                'value' => json_encode('UTC'),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_currency',
                'value' => json_encode('USD'),
                'updated_at' => now(),
            ],
            [
                'key' => 'academy_name',
                'value' => json_encode('Online Academy'),
                'updated_at' => now(),
            ],
            [
                'key' => 'academy_logo',
                'value' => json_encode(null),
                'updated_at' => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
