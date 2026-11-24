<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewals', function (Blueprint $table) {
            $table->string('source', 16)->default('manual')->after('kind');
            $table->timestamp('last_checked_at')->nullable();
            $table->string('check_error')->nullable();
        });

        DB::table('renewals')->whereNotNull('check_id')->update(['source' => 'auto']);
    }

    public function down(): void
    {
        Schema::table('renewals', function (Blueprint $table) {
            $table->dropColumn(['source', 'last_checked_at', 'check_error']);
        });
    }
};
