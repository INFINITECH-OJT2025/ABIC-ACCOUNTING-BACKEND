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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 100)->nullable();
            $table->date('voucher_date')->nullable();
            $table->string('trans_method', 30)->nullable(false); // DEPOSIT | WITHDRAWAL
            $table->string('trans_type', 30)->nullable(false);   // CASH | CHEQUE | DEPOSIT_SLIP | INTERNAL
            $table->foreignId('from_owner_id')->nullable()->constrained('owners')->onDelete('restrict');
            $table->foreignId('to_owner_id')->nullable()->constrained('owners')->onDelete('restrict');
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->decimal('amount', 15, 2)->nullable(false)->default(0);
            $table->string('fund_reference', 255)->nullable();
            $table->text('particulars')->nullable();
            $table->unsignedBigInteger('transfer_group_id')->nullable();
            $table->string('person_in_charge', 255)->nullable();
            $table->string('status', 20)->nullable(false)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('trans_method');
            $table->index('trans_type');
            $table->index('from_owner_id');
            $table->index('to_owner_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
