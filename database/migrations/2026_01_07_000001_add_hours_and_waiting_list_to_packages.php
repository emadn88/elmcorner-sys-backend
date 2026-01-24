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
        // Add hours columns to packages table
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('total_hours', 8, 2)->nullable()->after('total_classes');
            $table->decimal('remaining_hours', 8, 2)->nullable()->after('remaining_classes');
        });

        // Update existing packages: calculate hours from classes (assuming 1 class = 1 hour for existing data)
        // This is a default calculation, can be adjusted based on actual data
        DB::statement('UPDATE packages SET total_hours = total_classes, remaining_hours = remaining_classes WHERE total_hours IS NULL');

        // Add waiting_list status to classes table
        DB::statement("ALTER TABLE classes MODIFY COLUMN status ENUM('pending', 'attended', 'cancelled_by_student', 'cancelled_by_teacher', 'absent_student', 'waiting_list') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['total_hours', 'remaining_hours']);
        });

        // Remove waiting_list from enum (MySQL doesn't support removing enum values easily)
        // This would require recreating the column
        DB::statement("ALTER TABLE classes MODIFY COLUMN status ENUM('pending', 'attended', 'cancelled_by_student', 'cancelled_by_teacher', 'absent_student') DEFAULT 'pending'");
    }
};
