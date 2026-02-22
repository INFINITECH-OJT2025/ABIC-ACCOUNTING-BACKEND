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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transaction_category', 30)->nullable()->after('trans_method'); // DEPOSIT, WITHDRAWAL, OPENING, etc.
            $table->boolean('is_posted')->default(false)->after('status');
            $table->timestamp('posted_at')->nullable()->after('is_posted');
            
            $table->index('transaction_category');
            $table->index('is_posted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['transaction_category']);
            $table->dropIndex(['is_posted']);
            $table->dropColumn(['transaction_category', 'is_posted', 'posted_at']);
        });
    }
};
