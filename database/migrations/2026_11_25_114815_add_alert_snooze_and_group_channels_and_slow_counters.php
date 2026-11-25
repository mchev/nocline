<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table): void {
            $table->timestamp('alerts_snoozed_until')->nullable()->after('enabled');
        });

        Schema::table('check_states', function (Blueprint $table): void {
            $table->unsignedSmallInteger('consecutive_slow')->default(0)->after('consecutive_failures');
        });

        Schema::create('group_notification_channel', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->primary(['group_id', 'notification_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_notification_channel');

        Schema::table('check_states', function (Blueprint $table): void {
            $table->dropColumn('consecutive_slow');
        });

        Schema::table('checks', function (Blueprint $table): void {
            $table->dropColumn('alerts_snoozed_until');
        });
    }
};
