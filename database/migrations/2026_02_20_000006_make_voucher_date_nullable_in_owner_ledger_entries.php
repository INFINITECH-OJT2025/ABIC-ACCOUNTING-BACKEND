<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make voucher_date nullable to handle transactions without vouchers.
     */
    public function up(): void
    {
        Schema::table('owner_ledger_entries', function (Blueprint $table) {
            $table->date('voucher_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_ledger_entries', function (Blueprint $table) {
            $table->date('voucher_date')->nullable(false)->change();
        });
    }
};
