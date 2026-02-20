<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {

            $table->foreignId('chart_of_account_id')
                ->nullable()
                ->after('created_by')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {

            $table->dropForeign(['chart_of_account_id']);
            $table->dropColumn('chart_of_account_id');

        });
    }
};