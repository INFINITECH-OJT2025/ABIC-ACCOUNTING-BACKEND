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
        Schema::table('owners', function (Blueprint $table) {
            // Add owner_code field (unique, before owner_type)
            $table->string('owner_code', 30)->unique()->after('id');
            
            // Add description field (after name)
            $table->text('description')->nullable()->after('name');
            
            // Add index for owner_code (already unique, but explicit index for performance)
            $table->index('owner_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            // Drop index first
            $table->dropIndex(['owner_code']);
            
            // Drop columns
            $table->dropColumn(['owner_code', 'description']);
        });
    }
};
