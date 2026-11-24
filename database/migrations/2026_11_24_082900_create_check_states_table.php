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
        Schema::create('check_states', function (Blueprint $table) {
            $table->foreignId('check_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('unknown');
            $table->timestamp('since_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('next_run_at')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_states');
    }
};
