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
        Schema::table('packages', function (Blueprint $table) {
            $table->timestamp('last_notification_sent')->nullable()->after('status');
            $table->integer('notification_count')->default(0)->after('last_notification_sent');
            
            $table->index('last_notification_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['last_notification_sent']);
            $table->dropColumn(['last_notification_sent', 'notification_count']);
        });
    }
};
