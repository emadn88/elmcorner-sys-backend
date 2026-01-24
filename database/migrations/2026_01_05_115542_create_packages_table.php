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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->date('start_date');
            $table->integer('total_classes');
            $table->integer('remaining_classes');
            $table->decimal('hour_price', 10, 2);
            $table->string('currency')->default('USD');
            $table->integer('round_number')->default(1);
            $table->enum('status', ['active', 'finished'])->default('active');
            $table->timestamps();
            
            $table->index('student_id');
            $table->index('status');
            $table->index('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
