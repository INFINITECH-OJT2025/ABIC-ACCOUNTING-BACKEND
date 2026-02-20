<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('transaction_instruments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->enum('instrument_type', [
                'CASH',
                'CHEQUE',
                'DEPOSIT_SLIP',
                'INTERNAL'
            ])->nullable();

            $table->string('instrument_no')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_instruments');
    }
};