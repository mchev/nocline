<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->string('accent_color', 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('show_incidents')->default(true);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_pages');
    }
};
