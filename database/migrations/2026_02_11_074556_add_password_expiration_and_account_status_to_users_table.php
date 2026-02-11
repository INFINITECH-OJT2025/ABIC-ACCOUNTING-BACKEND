<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Password expiration fields
            $table->timestamp('password_expires_at')->nullable()->after('email_verified_at');
            $table->boolean('is_password_expired')->default(false)->after('password_expires_at');
            $table->timestamp('last_password_change')->nullable()->after('is_password_expired');
            
            // Account status fields
            $table->enum('account_status', ['active', 'suspended', 'expired', 'pending'])->default('active')->after('last_password_change');
            $table->timestamp('suspended_at')->nullable()->after('account_status');
            $table->text('suspended_reason')->nullable()->after('suspended_at');
            
            // Indexes for performance
            $table->index('password_expires_at');
            $table->index('account_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['password_expires_at']);
            $table->dropIndex(['account_status']);
            $table->dropColumn([
                'password_expires_at',
                'is_password_expired',
                'last_password_change',
                'account_status',
                'suspended_at',
                'suspended_reason'
            ]);
        });
    }
};