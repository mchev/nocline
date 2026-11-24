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
        Schema::create('vault_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('host_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->text('username_enc')->nullable();
            $table->text('secret_enc');
            $table->text('notes_enc')->nullable();
            $table->text('dek_wrapped');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_entries');
    }
};
