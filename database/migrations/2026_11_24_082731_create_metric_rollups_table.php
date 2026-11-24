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
        Schema::create('metric_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key', 64);
            $table->unsignedSmallInteger('bucket_size');
            $table->timestamp('bucket_start');
            $table->double('min')->nullable();
            $table->double('max')->nullable();
            $table->double('avg');
            $table->double('p95')->nullable();
            $table->unsignedInteger('sample_count')->default(1);

            $table->unique(['host_id', 'metric_key', 'bucket_size', 'bucket_start'], 'metric_rollups_bucket_unique');
            $table->index(['bucket_size', 'bucket_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_rollups');
    }
};
