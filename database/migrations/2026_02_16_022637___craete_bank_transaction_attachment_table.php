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
        Schema::create('bank_transaction_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')
                ->constrained('bank_transactions')
                ->cascadeOnDelete();
            $table->foreignId('owner_id')
                ->constrained('owners')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('person_in_charge')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_attachments');
    }
};
