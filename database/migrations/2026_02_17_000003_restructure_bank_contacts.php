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
        // Create bank_contact_channels table
        Schema::create('bank_contact_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('bank_contacts')->onDelete('cascade');
            $table->string('channel_type', 30); // PHONE | MOBILE | EMAIL | VIBER
            $table->string('value', 255);
            $table->string('label', 100)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('contact_id');
            $table->index('channel_type');
        });

        // Update bank_contacts table
        Schema::table('bank_contacts', function (Blueprint $table) {
            // Remove old fields
            $table->dropColumn(['phone', 'email', 'viber']);
            
            // Add notes field
            $table->text('notes')->nullable()->after('position');
            
            // Update branch_name to be required (if not already)
            $table->string('branch_name', 150)->nullable(false)->change();
            
            // Make contact_person and position nullable (if not already)
            $table->string('contact_person', 255)->nullable()->change();
            $table->string('position', 150)->nullable()->change();
            
            // Add index on branch_name
            $table->index('branch_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop bank_contact_channels table
        Schema::dropIfExists('bank_contact_channels');

        // Revert bank_contacts table changes
        Schema::table('bank_contacts', function (Blueprint $table) {
            $table->dropIndex(['branch_name']);
            $table->dropColumn('notes');
            $table->string('phone', 100)->nullable()->after('position');
            $table->string('email', 255)->nullable()->after('phone');
            $table->string('viber', 100)->nullable()->after('email');
        });
    }
};
