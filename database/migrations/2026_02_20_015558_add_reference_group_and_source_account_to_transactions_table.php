<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            // Group identifier for mirrored transactions
            $table->string('reference_group_id')
                  ->nullable()
                  ->after('voucher_no')
                  ->index();

            // Source account (who initiated the money movement)
            $table->foreignId('source_account_id')
                  ->nullable()
                  ->after('bank_account_id')
                  ->constrained('bank_accounts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            $table->dropForeign(['source_account_id']);
            $table->dropColumn(['reference_group_id', 'source_account_id']);
        });
    }
};