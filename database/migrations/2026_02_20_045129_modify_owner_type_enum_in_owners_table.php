<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE owners 
            MODIFY owner_type ENUM(
                'MAIN',
                'COMPANY',
                'EMPLOYEE',
                'CLIENT',
                'UNIT',
                'PROJECT'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE owners 
            MODIFY owner_type VARCHAR(255) NOT NULL
        ");
    }
};