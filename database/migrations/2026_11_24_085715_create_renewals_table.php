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
        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('check_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('label');
            $table->date('expires_on');
            $table->json('remind_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('expires_on');
            $table->unique(['check_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewals');
    }
};
