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
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 30)->nullable(false);
            $table->string('name', 255)->nullable(false);
            $table->string('email', 255)->nullable();
            $table->string('phone', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->nullable(false)->default('ACTIVE');
            $table->timestamps();

            // Indexes
            $table->index('owner_type');
            $table->index('status');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
