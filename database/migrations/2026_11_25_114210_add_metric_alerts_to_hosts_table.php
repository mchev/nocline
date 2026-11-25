<?php

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Hosts\Models\Host;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosts', function (Blueprint $table): void {
            $table->json('metric_alerts')->nullable()->after('agent_auto_update');
        });

        Host::query()->whereNotNull('enrolled_at')->each(function (Host $host): void {
            $this->ensureResourcesCheck($host);
        });
    }

    public function down(): void
    {
        Check::query()->where('type', CheckType::HostResources)->delete();

        Schema::table('hosts', function (Blueprint $table): void {
            $table->dropColumn('metric_alerts');
        });
    }

    private function ensureResourcesCheck(Host $host): void
    {
        if ($host->enrolled_at === null) {
            return;
        }

        $exists = Check::query()
            ->where('host_id', $host->id)
            ->where('type', CheckType::HostResources)
            ->exists();

        if ($exists) {
            return;
        }

        Check::create([
            'name' => $host->name.' — '.__('Server load'),
            'type' => CheckType::HostResources,
            'category' => CheckCategory::Server,
            'host_id' => $host->id,
            'group_id' => $host->group_id,
            'target' => '',
            'interval_sec' => 60,
            'timeout_sec' => 10,
            'failure_threshold' => 1,
            'enabled' => true,
        ]);
    }
};
