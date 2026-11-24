<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->json('agent_meta')->nullable()->after('os');
            $table->timestamp('agent_meta_at')->nullable()->after('agent_meta');
        });
    }

    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->dropColumn(['agent_meta', 'agent_meta_at']);
        });
    }
};
