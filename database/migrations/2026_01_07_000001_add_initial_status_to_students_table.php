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
        // Modify the status enum to include 'initial'
        DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('initial', 'active', 'paused', 'stopped') DEFAULT 'initial'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('active', 'paused', 'stopped') DEFAULT 'active'");
    }
};
