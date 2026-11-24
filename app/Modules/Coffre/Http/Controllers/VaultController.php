<?php

namespace App\Modules\Coffre\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Audit;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Coffre\Http\Requests\VaultEntryRequest;
use App\Modules\Coffre\Models\VaultAccessLog;
use App\Modules\Coffre\Models\VaultEntry;
use App\Modules\Coffre\Services\VaultCrypto;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VaultController extends Controller
{
    public function index(VaultCrypto $crypto): Response
    {
        $this->authorize('viewAny', VaultEntry::class);

        return Inertia::render('coffre/Index', [
            'configured' => $crypto->isConfigured(),
            'entries' => VaultEntry::query()->with('group:id,name')->orderBy('label')->get()
                ->map(fn (VaultEntry $e) => [
                    'id' => $e->id,
                    'label' => $e->label,
                    'url' => $e->url,
                    'group' => $e->group?->name,
                ]),
            'groups' => Group::options(),
        ]);
    }

    public function store(VaultEntryRequest $request, VaultCrypto $crypto): RedirectResponse
    {
        $this->authorize('create', VaultEntry::class);
        $this->ensureConfigured($crypto);

        $data = $request->validated();
        $entry = new VaultEntry(['label' => $data['label'], 'url' => $data['url'] ?? null,
            'group_id' => $data['group_id'] ?? null, 'host_id' => $data['host_id'] ?? null]);
        $entry->seal($this->plainFields($data), $crypto);
        $entry->save();

        $this->audit($request, $entry, 'create');

        return back();
    }

    public function update(VaultEntryRequest $request, VaultEntry $entry, VaultCrypto $crypto): RedirectResponse
    {
        $this->authorize('update', $entry);
        $this->ensureConfigured($crypto);

        $data = $request->validated();
        $entry->fill(['label' => $data['label'], 'url' => $data['url'] ?? null,
            'group_id' => $data['group_id'] ?? null, 'host_id' => $data['host_id'] ?? null]);

        if (! empty($data['secret'])) {
            $entry->seal($this->plainFields($data), $crypto);
        }

        $entry->save();
        $this->audit($request, $entry, 'edit');

        return back();
    }

    public function destroy(Request $request, VaultEntry $entry): RedirectResponse
    {
        $this->authorize('delete', $entry);

        $request->validate(['password' => ['required', 'current_password']]);

        $this->audit($request, $entry, 'delete');
        $entry->delete();

        return back();
    }

    /**
     * Explicit reveal: requires the current password (re-auth) and is always audited.
     */
    public function reveal(Request $request, VaultEntry $entry, VaultCrypto $crypto): JsonResponse
    {
        $this->authorize('reveal', $entry);
        $this->ensureConfigured($crypto);

        $request->validate(['password' => ['required', 'current_password']]);

        $this->audit($request, $entry, 'view');
        Audit::record(AuditAction::VaultRevealed, $entry);

        return response()->json($entry->reveal($crypto))
            ->header('Cache-Control', 'no-store, private');
    }

    public function copied(Request $request, VaultEntry $entry): JsonResponse
    {
        $this->authorize('reveal', $entry);

        $this->audit($request, $entry, 'copy');

        return response()->json(['ok' => true]);
    }

    public function audits(): Response
    {
        $this->authorize('audit', VaultEntry::class);

        return Inertia::render('coffre/Audit', [
            'logs' => VaultAccessLog::query()->with('user:id,name')->latest('at')->paginate(50)
                ->through(fn (VaultAccessLog $l) => [
                    'id' => $l->id,
                    'entry' => $l->entry_label,
                    'user' => $l->user?->name,
                    'action' => $l->action,
                    'at' => $l->at->toIso8601String(),
                    'ip' => $l->ip,
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{username: string|null, secret: string, notes: string|null}
     */
    private function plainFields(array $data): array
    {
        return [
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'secret' => (string) $data['secret'],
            'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
        ];
    }

    private function ensureConfigured(VaultCrypto $crypto): void
    {
        abort_unless($crypto->isConfigured(), 503, 'Vault master key is not configured.');
    }

    private function audit(Request $request, VaultEntry $entry, string $action): void
    {
        VaultAccessLog::create([
            'vault_entry_id' => $action === 'delete' ? null : $entry->id,
            'entry_label' => $entry->label,
            'user_id' => $request->user()->id,
            'action' => $action,
            'at' => now(),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
