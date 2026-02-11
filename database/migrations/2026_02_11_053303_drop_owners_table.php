<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('owners');
    }

    public function down(): void
    {
        // optional — recreate if needed
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
