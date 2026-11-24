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
        Schema::create('check_probe_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('probe_id')->constrained()->cascadeOnDelete();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('last_ok')->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->text('last_error')->nullable();

            $table->unique(['check_id', 'probe_id']);
            $table->index(['probe_id', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_probe_states');
    }
};
