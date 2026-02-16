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
        Schema::table('users', function (Blueprint $table) {
            // Remove activated_at column if it exists
            if (Schema::hasColumn('users', 'activated_at')) {
                $table->dropColumn('activated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back activated_at column if it doesn't exist
            if (!Schema::hasColumn('users', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('email_verified_at');
            }
        });
    }
};
