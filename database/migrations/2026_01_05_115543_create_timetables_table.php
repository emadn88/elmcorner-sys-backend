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
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->json('days_of_week'); // [1,3,5] for Monday, Wednesday, Friday
            $table->json('time_slots'); // [{day:1, start:'10:00', end:'11:00'}]
            $table->string('student_timezone')->default('UTC');
            $table->string('teacher_timezone')->default('UTC');
            $table->enum('status', ['active', 'paused', 'stopped'])->default('active');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('course_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
