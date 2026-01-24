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
        Schema::table('classes', function (Blueprint $table) {
            $table->text('student_evaluation')->nullable()->after('notes');
            $table->text('class_report')->nullable()->after('student_evaluation');
            $table->boolean('meet_link_used')->default(false)->after('class_report');
            $table->timestamp('meet_link_accessed_at')->nullable()->after('meet_link_used');
            $table->enum('cancellation_request_status', ['pending', 'approved', 'rejected'])->nullable()->after('meet_link_accessed_at');
            
            $table->index('cancellation_request_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex(['cancellation_request_status']);
            $table->dropColumn([
                'student_evaluation',
                'class_report',
                'meet_link_used',
                'meet_link_accessed_at',
                'cancellation_request_status',
            ]);
        });
    }
};
