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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('whatsapp');
            $table->string('country')->nullable();
            $table->string('timezone')->nullable();
            $table->integer('number_of_students')->default(1);
            $table->json('ages')->nullable(); // Array of ages e.g. [8, 12]
            $table->string('source')->nullable(); // Ad campaign/source
            $table->enum('status', [
                'new',
                'contacted',
                'needs_follow_up',
                'trial_scheduled',
                'trial_confirmed',
                'converted',
                'not_interested',
                'cancelled'
            ])->default('new');
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('next_follow_up')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('converted_to_student_id')->nullable()->constrained('students')->onDelete('set null');
            $table->dateTime('last_contacted_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to');
            $table->index('next_follow_up');
            $table->index('country');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
