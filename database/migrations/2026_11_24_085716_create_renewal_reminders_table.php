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
        Schema::create('renewal_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_id')->constrained()->cascadeOnDelete();
            $table->date('expires_on');
            $table->unsignedSmallInteger('days_before');
            $table->timestamp('sent_at');

            $table->unique(['renewal_id', 'expires_on', 'days_before']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_reminders');
    }
};
