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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->string('company_name');
            $table->foreignId('bank_id')->constrained('banks')->cascadeOnDelete();
            $table->string('account_number')->unique();
            $table->string('account_name')->nullable();
            $table->boolean('is_pmo')->default(false);
            $table->json('contact_number')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn([
                'company_name',
                'bank_id',
                'account_number',
                'account_name',
                'is_pmo',
                'contact_number',
                'status',
            ]);
        });
    }
};
