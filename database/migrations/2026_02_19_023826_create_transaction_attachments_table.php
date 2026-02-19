<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->string('attachment_type', 30); // VOUCHER | SUPPORTING
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('file_type', 100)->nullable();

            $table->timestamp('uploaded_at')->nullable();

            $table->timestamps();

            $table->index('transaction_id');
            $table->index('attachment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_attachments');
    }
};
