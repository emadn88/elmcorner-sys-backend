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
            $table->boolean('meet_link_used')->default(false)->after('notes');
            $table->timestamp('meet_link_accessed_at')->nullable()->after('meet_link_used');
            $table->boolean('reminder_5min_before_sent')->default(false)->after('meet_link_accessed_at');
            $table->boolean('reminder_start_time_sent')->default(false)->after('reminder_5min_before_sent');
            $table->boolean('reminder_5min_after_sent')->default(false)->after('reminder_start_time_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trial_classes', function (Blueprint $table) {
            $table->dropColumn([
                'meet_link_used',
                'meet_link_accessed_at',
                'reminder_5min_before_sent',
                'reminder_start_time_sent',
                'reminder_5min_after_sent',
            ]);
        });
    }
};
