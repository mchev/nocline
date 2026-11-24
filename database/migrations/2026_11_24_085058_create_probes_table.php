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
        Schema::create('probes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role', 16)->default('external');
            $table->boolean('enabled')->default(true);
            $table->string('enrollment_token_hash', 64)->nullable()->unique();
            $table->timestamp('enrollment_expires_at')->nullable();
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('agent_version', 32)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('probes');
    }
};
