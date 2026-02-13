<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bank_accounts');
    }

    public function down(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->foreignId('bank_id')->constrained('banks');
            $table->string('account_number')->unique();
            $table->string('account_name')->nullable();
            $table->boolean('is_pmo')->default(false);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->json('contact_numbers')->nullable();
            $table->timestamps();
        });
    }
};
