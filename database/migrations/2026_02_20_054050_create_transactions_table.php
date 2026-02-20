<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {

            $table->id();

            $table->string('voucher_no')->unique();
            $table->date('voucher_date');

            $table->enum('trans_method', ['DEPOSIT', 'WITHDRAW']);

            $table->foreignId('from_owner_id')
                ->constrained('owners')
                ->restrictOnDelete();

            $table->foreignId('to_owner_id')
                ->constrained('owners')
                ->restrictOnDelete();

            $table->decimal('amount', 15, 2);

            $table->string('fund_reference')->nullable();
            $table->text('particulars');

            $table->string('transfer_group_id')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('person_in_charge')->nullable();

            $table->enum('status', ['ACTIVE', 'INACTIVE'])
                ->default('ACTIVE');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};