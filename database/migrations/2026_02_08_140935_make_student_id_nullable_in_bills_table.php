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
        // Check if using MySQL
        if (DB::getDriverName() === 'mysql') {
            Schema::table('bills', function (Blueprint $table) {
                // Drop the existing foreign key constraint
                $table->dropForeign(['student_id']);
            });
            
            // Use raw SQL to modify the column to be nullable (MySQL syntax)
            DB::statement('ALTER TABLE bills MODIFY student_id BIGINT UNSIGNED NULL');
            
            Schema::table('bills', function (Blueprint $table) {
                // Re-add the foreign key constraint with nullable
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        } else {
            // For other databases, use Laravel's schema builder
            Schema::table('bills', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
                $table->unsignedBigInteger('student_id')->nullable()->change();
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('bills', function (Blueprint $table) {
                // Drop the foreign key constraint
                $table->dropForeign(['student_id']);
            });
            
            // Use raw SQL to modify the column to be NOT NULL
            // Note: This will fail if there are NULL values in the column
            DB::statement('ALTER TABLE bills MODIFY student_id BIGINT UNSIGNED NOT NULL');
            
            Schema::table('bills', function (Blueprint $table) {
                // Re-add the foreign key constraint
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        } else {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
                $table->unsignedBigInteger('student_id')->nullable(false)->change();
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }
    }
};
