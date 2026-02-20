<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->cascadeOnDelete();

            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);

            $table->string('entry_description')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('transaction_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_ledger_entries');
    }
};
