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
        Schema::table('checks', function (Blueprint $table) {
            $table->string('heartbeat_token', 64)->nullable()->unique();
        });

        Schema::table('check_states', function (Blueprint $table) {
            $table->timestamp('last_ping_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_states', function (Blueprint $table) {
            $table->dropColumn('last_ping_at');
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->dropUnique(['heartbeat_token']);
            $table->dropColumn('heartbeat_token');
        });
    }
};
