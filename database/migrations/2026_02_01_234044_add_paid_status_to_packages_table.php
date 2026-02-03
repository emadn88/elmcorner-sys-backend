<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add 'paid' status to packages table to differentiate between:
     * - 'active': Package is currently in use
     * - 'finished': Package has reached its limit, awaiting payment (shows in notifications)
     * - 'paid': Package has been paid, archived (does not show in notifications, classes excluded from classes page)
     */
    public function up(): void
    {
        // For SQLite, we need to handle enum differently
        // SQLite doesn't have true ENUM types, so we store as string
        // The application logic will validate the values
        
        // Check if we're using SQLite or MySQL
        $connection = config('database.default');
        
        if ($connection === 'sqlite') {
            // SQLite: status column is already a string, no changes needed
            // Just update existing 'finished' with notification_sent to 'paid' if needed
            // This will be handled by the application logic
        } else {
            // MySQL: Modify the enum to include 'paid' status
            DB::statement("ALTER TABLE packages MODIFY COLUMN status ENUM('active', 'finished', 'paid') DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('database.default');
        
        if ($connection !== 'sqlite') {
            // MySQL: Remove 'paid' status (convert any 'paid' back to 'finished')
            DB::statement("UPDATE packages SET status = 'finished' WHERE status = 'paid'");
            DB::statement("ALTER TABLE packages MODIFY COLUMN status ENUM('active', 'finished') DEFAULT 'active'");
        }
    }
};
