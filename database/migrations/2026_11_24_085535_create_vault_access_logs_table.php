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
        Schema::create('vault_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_entry_id')->nullable()->constrained('vault_entries')->nullOnDelete();
            $table->string('entry_label');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 16);
            $table->timestamp('at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->index(['vault_entry_id', 'at']);
            $table->index('at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_access_logs');
    }
};
