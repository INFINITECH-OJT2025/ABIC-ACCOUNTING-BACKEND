<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE owners 
            MODIFY status ENUM('ACTIVE','INACTIVE') 
            NOT NULL DEFAULT 'ACTIVE'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE owners 
            MODIFY status VARCHAR(50)
        ");
    }
};