<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->string('favicon_path', 255)->nullable()->after('target');
            $table->char('favicon_hash', 64)->nullable()->after('favicon_path');
            $table->timestamp('favicon_fetched_at')->nullable()->after('favicon_hash');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn(['favicon_path', 'favicon_hash', 'favicon_fetched_at']);
        });
    }
};
