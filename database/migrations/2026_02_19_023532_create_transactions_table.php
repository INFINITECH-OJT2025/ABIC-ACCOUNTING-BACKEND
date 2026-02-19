<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Voucher
            $table->string('voucher_no', 100)->unique();
            $table->date('voucher_date');

            // Type
            $table->string('trans_type', 30);

            // References
            $table->string('transaction_reference', 150)->nullable();
            $table->string('document_reference', 150)->nullable();

            // Ownership
            $table->foreignId('owner_id')
                ->constrained('owners')
                ->cascadeOnDelete();

            // Bank linkage
            $table->foreignId('bank_account_id')
                ->constrained('bank_accounts')
                ->cascadeOnDelete();

            $table->foreignId('counterparty_bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete();

            // Description
            $table->text('particulars');

            // Amounts
            $table->decimal('deposit_amount', 14, 2)->default(0);
            $table->decimal('withdrawal_amount', 14, 2)->default(0);

            // Responsibility
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index('voucher_date');
            $table->index('trans_type');
            $table->index('owner_id');
            $table->index('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
