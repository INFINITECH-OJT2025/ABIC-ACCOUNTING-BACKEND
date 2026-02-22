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
        Schema::create('saved_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->string('transaction_type'); // DEPOSIT or WITHDRAWAL
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->default('image/png');
            $table->integer('file_size')->nullable(); // in bytes
            $table->json('receipt_data')->nullable(); // Store transaction data for reference
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_receipts');
    }
};
