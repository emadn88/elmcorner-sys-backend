<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update enum to include new report types
        DB::statement("ALTER TABLE reports MODIFY COLUMN report_type ENUM('lesson_summary', 'package_report', 'custom', 'student_single', 'students_multiple', 'students_family', 'students_all', 'teacher_performance', 'salaries', 'income') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE reports MODIFY COLUMN report_type ENUM('lesson_summary', 'package_report', 'custom') NOT NULL");
    }
};
