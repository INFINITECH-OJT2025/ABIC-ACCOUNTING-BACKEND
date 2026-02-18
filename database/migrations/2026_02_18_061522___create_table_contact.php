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
        Schema::create('bank_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->cascadeOnDelete();
            $table->string('branch_name');
            $table->string('contact_person');
            $table->string('position');
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('bank_contacts');
    }
};
