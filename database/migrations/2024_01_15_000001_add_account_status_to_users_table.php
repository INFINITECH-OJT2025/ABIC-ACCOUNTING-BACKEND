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
            // Add account_status column if it doesn't exist
            if (!Schema::hasColumn('users', 'account_status')) {
                $table->enum('account_status', ['active', 'inactive', 'suspended'])->default('inactive')->after('role');
            }
            
            // Add password_expires_at column if it doesn't exist
            if (!Schema::hasColumn('users', 'password_expires_at')) {
                $table->timestamp('password_expires_at')->nullable()->after('account_status');
            }
            
            // Add is_password_expired column if it doesn't exist
            if (!Schema::hasColumn('users', 'is_password_expired')) {
                $table->boolean('is_password_expired')->default(false)->after('password_expires_at');
            }
            
            // Add last_password_change column if it doesn't exist
            if (!Schema::hasColumn('users', 'last_password_change')) {
                $table->timestamp('last_password_change')->nullable()->after('is_password_expired');
            }
            
            // Add suspended_at column if it doesn't exist
            if (!Schema::hasColumn('users', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('last_password_change');
            }
            
            // Add suspended_reason column if it doesn't exist
            if (!Schema::hasColumn('users', 'suspended_reason')) {
                $table->text('suspended_reason')->nullable()->after('suspended_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'account_status',
                'password_expires_at', 
                'is_password_expired',
                'last_password_change',
                'suspended_at',
                'suspended_reason'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
