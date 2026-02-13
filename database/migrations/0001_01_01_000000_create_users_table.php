<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->string('role')->default('user');

            $table->enum('account_status', [
                'pending',
                'active',
                'suspended'
            ])->default('pending');

            $table->timestamp('password_expires_at')->nullable();
            $table->boolean('is_password_expired')->default(false);
            $table->timestamp('last_password_change')->nullable();

            $table->timestamp('suspended_at')->nullable();
            $table->text('suspended_reason')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index('account_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}