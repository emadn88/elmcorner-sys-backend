<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trial_classes', function (Blueprint $table) {
            $table->date('student_date')->nullable()->after('trial_date');
            $table->time('student_start_time')->nullable()->after('start_time');
            $table->time('student_end_time')->nullable()->after('end_time');
            $table->date('teacher_date')->nullable()->after('student_end_time');
            $table->time('teacher_start_time')->nullable()->after('teacher_date');
            $table->time('teacher_end_time')->nullable()->after('teacher_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trial_classes', function (Blueprint $table) {
            $table->dropColumn([
                'student_date',
                'student_start_time',
                'student_end_time',
                'teacher_date',
                'teacher_start_time',
                'teacher_end_time',
            ]);
        });
    }
};
