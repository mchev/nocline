<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'family' => ['nullable', 'string', Rule::in(array_keys(AuditAction::families()))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['family'] ?? null, fn ($query, $family) => $query->where('action', 'like', $family.'.%'))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', now()->parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', now()->parse($to)->endOfDay()))
            ->latest('created_at')->latest('id')
            ->paginate(50)->withQueryString();

        return Inertia::render('audit/Index', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'sentence' => AuditAction::sentence(
                        $log->action,
                        $log->user->name ?? (string) ($log->meta['actor'] ?? __('Someone')),
                        $log->subject_label,
                        $log->meta ?? [],
                    ),
                    'at' => $log->created_at->toIso8601String(),
                    'ip' => $log->ip,
                    'user_agent' => $log->user_agent,
                ])->values(),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'family' => $filters['family'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'families' => AuditAction::families(),
            'retention_days' => (int) config('nocline.audit_retention_days'),
        ]);
    }
}
