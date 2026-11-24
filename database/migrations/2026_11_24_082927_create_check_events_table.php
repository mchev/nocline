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
        Schema::create('check_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 16);
            $table->string('to_status', 16);
            $table->timestamp('at');
            $table->json('metadata')->nullable();

            $table->index(['check_id', 'at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_events');
    }
};
