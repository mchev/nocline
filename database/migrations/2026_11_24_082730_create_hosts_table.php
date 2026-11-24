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
        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('tags')->nullable();
            $table->string('enrollment_token_hash', 64)->nullable()->unique();
            $table->timestamp('enrollment_expires_at')->nullable();
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('agent_version', 32)->nullable();
            $table->string('os', 64)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hosts');
    }
};
