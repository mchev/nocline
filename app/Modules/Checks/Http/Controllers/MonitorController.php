<?php

namespace App\Modules\Checks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\MonitorCreator;
use App\Modules\Checks\Support\CheckRules;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use App\Modules\Probes\Models\Probe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "What do you want to monitor?" - the guided journey: a website, a server, or something else.
 */
class MonitorController extends Controller
{
    public function __construct(private readonly MonitorCreator $monitors) {}

    public function create(): Response
    {
        $this->authorize('create', Check::class);

        return Inertia::render('monitor/Create', [
            'groups' => Group::options(),
            'has_probe' => Probe::query()->where('enabled', true)->get()->contains(fn (Probe $p) => $p->isOnline()),
        ]);
    }

    public function website(Request $request): RedirectResponse
    {
        $this->authorize('create', Check::class);

        $request->merge(['url' => MonitorCreator::normalizeUrl((string) $request->input('url'))]);
        $data = $request->validate(MonitorCreator::websiteRules());

        $this->authorizeGroupCreation($data);
        $this->monitors->website($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Website added. We will warn you if it goes down or if its domain or SSL certificate is about to expire.')]);

        return redirect()->route('dashboard');
    }

    public function server(Request $request): RedirectResponse
    {
        $this->authorize('create', Check::class);
        $this->authorize('create', Host::class);

        $data = $request->validate(['name' => ['required', 'string', 'max:255'], ...MonitorCreator::groupRules()]);

        $this->authorizeGroupCreation($data);
        $groupId = $this->monitors->resolveGroup($data);
        $host = Host::create(['name' => $data['name'], 'group_id' => $groupId]);

        Check::create([
            'name' => $data['name'],
            'type' => CheckType::Agent,
            'category' => CheckCategory::Server,
            'host_id' => $host->id,
            'group_id' => $groupId,
            'target' => '',
            'interval_sec' => 60,
            'timeout_sec' => 10,
            'failure_threshold' => 2,
        ]);

        return $this->showInstall($host);
    }

    /**
     * The "copy this command on your server" screen with live connection status.
     */
    public function install(Host $host): Response
    {
        $this->authorize('update', $host);

        return Inertia::render('monitor/Install', [
            'host' => [
                'id' => $host->id,
                'name' => $host->name,
                'connected' => $host->enrolled_at !== null,
                'online' => $host->isOnline(),
            ],
            'install_command' => session('install_command'),
        ]);
    }

    public function regenerate(Host $host): RedirectResponse
    {
        $this->authorize('update', $host);

        return $this->showInstall($host);
    }

    public function service(Request $request): RedirectResponse
    {
        $this->authorize('create', Check::class);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['port', 'mail', 'ping', 'dns'])],
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:\-\[\]]+$/'],
            'port' => ['nullable', 'integer', 'between:1,65535', 'required_if:kind,port', 'required_if:kind,mail'],
            'name' => ['nullable', 'string', 'max:255'],
            ...MonitorCreator::groupRules(),
        ]);

        [$type, $category, $target, $execution] = match ($data['kind']) {
            'port' => [CheckType::Tcp, CheckCategory::Server, "{$data['host']}:{$data['port']}", CheckExecution::Hub],
            'mail' => [CheckType::Tcp, CheckCategory::Mail, "{$data['host']}:{$data['port']}", CheckExecution::Hub],
            'ping' => [CheckType::Icmp, CheckCategory::Network, $data['host'], CheckExecution::Probe],
            default => [CheckType::Dns, CheckCategory::Dns, $data['host'], CheckExecution::Probe],
        };

        $this->authorizeGroupCreation($data);

        Check::create([
            'name' => ($data['name'] ?? null) ?: $data['host'],
            'type' => $type,
            'category' => $category,
            'target' => $target,
            'group_id' => $this->monitors->resolveGroup($data),
            'execution' => $execution,
            'interval_sec' => 60,
            'timeout_sec' => 10,
            'failure_threshold' => 2,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Monitor added.')]);

        return redirect()->route('dashboard');
    }

    public function heartbeat(Request $request): RedirectResponse
    {
        $this->authorize('create', Check::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'interval_sec' => ['required', 'integer', 'min:60', 'max:'.CheckRules::HEARTBEAT_MAX_INTERVAL_SEC],
            ...MonitorCreator::groupRules(),
        ]);

        $this->authorizeGroupCreation($data);

        return redirect()->route('monitor.heartbeat', $this->monitors->heartbeat($data));
    }

    /**
     * The "add this line to your scheduled job" screen with live first-ping detection.
     */
    public function heartbeatInstall(Check $check): Response
    {
        abort_unless($check->type === CheckType::Heartbeat, 404);
        $this->authorize('update', $check);

        $check->load('state');

        return Inertia::render('monitor/Heartbeat', [
            'check' => [
                'id' => $check->id,
                'name' => $check->name,
                'interval_sec' => $check->interval_sec,
                'grace_sec' => $check->heartbeatGraceSeconds(),
                'status' => $check->state?->status->value ?? 'unknown',
                'pinged' => $check->state?->last_ping_at !== null,
            ],
            'url' => $check->heartbeatUrl(),
        ]);
    }

    private function showInstall(Host $host): RedirectResponse
    {
        $token = $host->issueEnrollmentToken();

        return redirect()->route('monitor.install', $host)
            ->with('install_command', 'curl -fsSL '.url("/install/{$token}").' | sudo sh');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorizeGroupCreation(array $data): void
    {
        if (empty($data['group_id']) && ! empty($data['group_name'])) {
            $this->authorize('create', Group::class);
        }
    }
}
