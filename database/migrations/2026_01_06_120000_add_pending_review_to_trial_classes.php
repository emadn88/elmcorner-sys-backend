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
        // Modify the enum to include 'pending_review'
        DB::statement("ALTER TABLE trial_classes MODIFY COLUMN status ENUM('pending', 'pending_review', 'completed', 'no_show', 'converted') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        // First, update any pending_review records to pending
        DB::table('trial_classes')
            ->where('status', 'pending_review')
            ->update(['status' => 'pending']);
        
        // Then modify the enum back
        DB::statement("ALTER TABLE trial_classes MODIFY COLUMN status ENUM('pending', 'completed', 'no_show', 'converted') DEFAULT 'pending'");
    }
};
