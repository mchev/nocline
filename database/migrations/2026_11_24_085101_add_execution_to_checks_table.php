<?php

use App\Modules\Checks\Enums\CheckExecution;
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
            $table->string('execution', 8)->default(CheckExecution::Hub->value);
            $table->string('quorum', 16)->default('majority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn(['execution', 'quorum']);
        });
    }
};
