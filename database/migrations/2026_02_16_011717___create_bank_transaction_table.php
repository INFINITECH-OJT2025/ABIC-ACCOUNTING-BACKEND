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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')
                ->constrained('bank_accounts')
                ->cascadeOnDelete();
            $table->foreignId('owner_id')
                ->constrained('owners')
                ->cascadeOnDelete();
            $table->datetime('transaction_date');
            $table->string('reference_number')->nullable();
            $table->enum('transaction_type', ['cash', 'cheque']);
            $table->text('particulars');
            $table->decimal('depost', 15, 2)->default(0);
            $table->decimal('withdrawal', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
