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
        Schema::create('bank_contact_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_contact_id')->constrained('bank_contacts')->cascadeOnDelete();
            $table->string('channel_type');
            $table->string('value')->nullable();
            $table->string('label')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('bank_contact_channels');
    }
};
