<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['hosts', 'checks', 'wallboards', 'vault_entries', 'renewals'];

    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('name');
            $table->string('color', 7)->nullable();
            $table->string('layout', 16)->default('list');
            $table->boolean('collapsed')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            });
        }

        $this->carryClientsOver();

        foreach ($this->tables as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('client_id'));
        }

        Schema::drop('clients');
    }

    public function down(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
                $table->dropConstrainedForeignId('group_id');
            });
        }

        Schema::drop('groups');
    }

    /**
     * Every former client becomes a top-level group holding what it used to hold.
     */
    private function carryClientsOver(): void
    {
        foreach (DB::table('clients')->orderBy('id')->get() as $client) {
            $groupId = DB::table('groups')->insertGetId([
                'name' => $client->name,
                'created_at' => $client->created_at,
                'updated_at' => $client->updated_at,
            ]);

            foreach ($this->tables as $name) {
                DB::table($name)->where('client_id', $client->id)->update(['group_id' => $groupId]);
            }
        }
    }
};
