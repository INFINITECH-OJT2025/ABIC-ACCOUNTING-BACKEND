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
        Schema::table('bank_contacts', function (Blueprint $table) {
            // Add viber field
            $table->string('viber', 100)->nullable()->after('phone');
            
            // Make branch_name, phone, email required
            $table->string('branch_name', 150)->nullable(false)->change();
            $table->string('phone', 100)->nullable(false)->change();
            $table->string('email', 255)->nullable(false)->change();
            
            // Make contact_person and position optional
            $table->string('contact_person', 255)->nullable()->change();
            $table->string('position', 150)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_contacts', function (Blueprint $table) {
            // Remove viber field
            $table->dropColumn('viber');
            
            // Revert to original nullable states
            $table->string('branch_name', 150)->nullable()->change();
            $table->string('phone', 100)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
            $table->string('contact_person', 255)->nullable(false)->change();
        });
    }
};
