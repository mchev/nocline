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
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('host_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 16);
            $table->string('target');
            $table->unsignedInteger('interval_sec')->default(60);
            $table->unsignedSmallInteger('timeout_sec')->default(10);
            $table->unsignedSmallInteger('failure_threshold')->default(2);
            $table->json('config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
