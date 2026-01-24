<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EvaluationOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            ['option_text' => 'Excellent - Student performed exceptionally well', 'order' => 1],
            ['option_text' => 'Very Good - Student showed strong understanding', 'order' => 2],
            ['option_text' => 'Good - Student met expectations', 'order' => 3],
            ['option_text' => 'Satisfactory - Student needs improvement', 'order' => 4],
            ['option_text' => 'Needs Improvement - Student requires additional support', 'order' => 5],
            ['option_text' => 'Poor - Student did not meet expectations', 'order' => 6],
        ];

        foreach ($options as $option) {
            DB::table('evaluation_options')->insert([
                'option_text' => $option['option_text'],
                'order' => $option['order'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
