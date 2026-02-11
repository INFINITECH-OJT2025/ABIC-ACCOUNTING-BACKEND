<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('accountants');
    }

    public function down(): void
    {
        // optional — only if you want reversible
    }
};
