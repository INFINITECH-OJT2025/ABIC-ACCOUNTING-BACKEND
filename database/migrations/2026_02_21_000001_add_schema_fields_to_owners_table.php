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
            // Add is_system field (default false)
            $table->boolean('is_system')->default(false)->after('status');
            
            // Add created_by foreign key to users table
            $table->unsignedBigInteger('created_by')->nullable()->after('is_system');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Add index for is_system (as per schema)
            $table->index('is_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['created_by']);
            
            // Drop index
            $table->dropIndex(['is_system']);
            
            // Drop columns
            $table->dropColumn(['is_system', 'created_by']);
        });
    }
};
