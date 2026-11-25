<?php

namespace App\Modules\Wallboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\CheckFaviconResponse;
use App\Modules\Groups\Models\Group;
use App\Modules\Wallboard\Models\Wallboard;
use App\Modules\Wallboard\Services\WallboardAggregator;
use App\Modules\Wallboard\Support\HubRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WallboardController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Wallboard::class);

        return Inertia::render('wallboard/Manage', [
            'wallboards' => Wallboard::query()->with('group:id,name')->orderBy('name')->get()
                ->map(fn (Wallboard $w) => ['id' => $w->id, 'name' => $w->name, 'group' => $w->group?->name]),
            'groups' => Group::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Wallboard::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        $created = Wallboard::createWithToken($data);

        return back()->with('wallboard_url', route('wallboard.show', $created['token']));
    }

    public function destroy(Wallboard $wallboard): RedirectResponse
    {
        $this->authorize('delete', $wallboard);

        $wallboard->delete();

        return back();
    }

    public function regenerate(Wallboard $wallboard): RedirectResponse
    {
        $this->authorize('regenerate', $wallboard);

        $token = $wallboard->regenerateToken();
        Cache::forget("wallboard:{$wallboard->id}");

        return back()->with('wallboard_url', route('wallboard.show', $token));
    }

    /**
     * Public kiosk page, protected only by the unguessable token.
     */
    public function show(string $token): Response
    {
        $wallboard = Wallboard::findByToken($token) ?? abort(404);

        return Inertia::render('wallboard/Show', [
            'token' => $token,
            'name' => $wallboard->name,
            'hubRevision' => app(HubRevision::class)->current(),
        ]);
    }

    public function data(string $token, WallboardAggregator $aggregator): JsonResponse
    {
        $wallboard = Wallboard::findByToken($token) ?? abort(404);

        return response()->json([
            ...$aggregator->payload($wallboard, $token),
            'hub_revision' => app(HubRevision::class)->current(),
        ])->header('Cache-Control', 'no-store');
    }

    public function favicon(
        string $token,
        Check $check,
        WallboardAggregator $aggregator,
        CheckFaviconResponse $favicons,
    ): BinaryFileResponse {
        $wallboard = Wallboard::findByToken($token) ?? abort(404);

        $visible = $aggregator->visibleSites($wallboard);
        abort_unless($visible->contains('id', $check->id), 404);

        return $favicons->file($check, publicCache: true);
    }
}
