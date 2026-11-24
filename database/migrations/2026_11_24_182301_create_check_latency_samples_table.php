<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_latency_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained()->cascadeOnDelete();
            $table->timestamp('at');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('ok');
            $table->index(['check_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_latency_samples');
    }
};
