<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Increase file_path column size to TEXT to accommodate Firebase Storage URLs
     * which can be very long due to signed URL parameters.
     */
    public function up(): void
    {
        // Update transaction_attachments table
        Schema::table('transaction_attachments', function (Blueprint $table) {
            $table->text('file_path')->change();
        });

        // Update saved_receipts table
        Schema::table('saved_receipts', function (Blueprint $table) {
            $table->text('file_path')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert transaction_attachments table
        Schema::table('transaction_attachments', function (Blueprint $table) {
            $table->string('file_path', 500)->change();
        });

        // Revert saved_receipts table
        Schema::table('saved_receipts', function (Blueprint $table) {
            $table->string('file_path', 255)->change();
        });
    }
};
