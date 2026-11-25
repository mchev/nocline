<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->boolean('agent_auto_update')->default(true)->change();
        });

        DB::table('hosts')->update(['agent_auto_update' => true]);
    }

    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->boolean('agent_auto_update')->default(false)->change();
        });
    }
};
