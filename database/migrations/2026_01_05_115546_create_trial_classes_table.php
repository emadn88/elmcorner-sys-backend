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
        Schema::create('trial_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->date('trial_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['pending', 'completed', 'no_show', 'converted'])->default('pending');
            $table->foreignId('converted_to_package_id')->nullable()->constrained('packages')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('course_id');
            $table->index('status');
            $table->index('trial_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trial_classes');
    }
};
