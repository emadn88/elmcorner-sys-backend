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
        Schema::table('bills', function (Blueprint $table) {
            // Add package_id to link bills to packages
            $table->foreignId('package_id')->nullable()->after('class_id')->constrained('packages')->onDelete('cascade');
            
            // Add payment_token for public payment page access
            $table->string('payment_token')->nullable()->unique()->after('payment_method');
            
            // Add is_custom to distinguish custom bills from lesson-based bills
            $table->boolean('is_custom')->default(false)->after('payment_token');
            
            // Add sent_at to track when bill was sent
            $table->timestamp('sent_at')->nullable()->after('is_custom');
            
            // Add class_ids JSON column to store array of class IDs included in this bill
            $table->json('class_ids')->nullable()->after('class_id');
            
            // Add total_hours to store cumulative hours for the bill
            $table->decimal('total_hours', 8, 2)->nullable()->after('duration');
            
            // Add description for custom bills
            $table->text('description')->nullable()->after('is_custom');
            
            // Add indexes
            $table->index('package_id');
            $table->index('payment_token');
            $table->index('is_custom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropIndex(['package_id']);
            $table->dropIndex(['payment_token']);
            $table->dropIndex(['is_custom']);
            
            $table->dropColumn([
                'package_id',
                'payment_token',
                'is_custom',
                'sent_at',
                'class_ids',
                'total_hours',
                'description',
            ]);
        });
    }
};
