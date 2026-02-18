<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id')
                ->constrained('owners')
                ->cascadeOnDelete();

            $table->foreignId('bank_id')
                ->constrained('banks')
                ->cascadeOnDelete();

            $table->string('account_name');
            $table->string('account_number')->unique();
            $table->string('account_holder');
            $table->string('account_type'); // Savings, Current, etc.
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_date');
            $table->string('currency', 10);

            $table->enum('status', ['active', 'archived'])->default('active');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
