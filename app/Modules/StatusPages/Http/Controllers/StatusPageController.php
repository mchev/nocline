<?php

namespace App\Modules\StatusPages\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Groups\Models\Group;
use App\Modules\StatusPages\Http\Requests\StatusPageRequest;
use App\Modules\StatusPages\Models\StatusPage;
use App\Modules\StatusPages\Services\StatusPagePayload;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StatusPageController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', StatusPage::class);

        return Inertia::render('status-pages/Index', [
            'pages' => StatusPage::query()->with('group:id,name')->orderBy('title')->get()
                ->map(fn (StatusPage $p) => [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'description' => $p->description,
                    'group_id' => $p->group_id,
                    'group' => $p->group?->name,
                    'accent_color' => $p->accent_color,
                    'logo_url' => $p->logoUrl(),
                    'show_incidents' => $p->show_incidents,
                    'enabled' => $p->enabled,
                    'url' => route('status.show', $p->slug),
                ])->values(),
            'groups' => Group::options(),
        ]);
    }

    public function store(StatusPageRequest $request): RedirectResponse
    {
        $this->authorize('create', StatusPage::class);

        $page = new StatusPage($request->pageData());

        if ($request->hasFile('logo')) {
            $page->storeLogo($request->file('logo'));
        }

        $page->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status page created.')]);

        return back();
    }

    public function update(StatusPageRequest $request, StatusPage $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $previousSlug = $page->slug;
        $page->fill($request->pageData());

        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            $page->deleteLogo();
            $page->logo_path = null;

            if ($request->hasFile('logo')) {
                $page->storeLogo($request->file('logo'));
            }
        }

        $page->save();
        StatusPagePayload::forget($previousSlug);
        StatusPagePayload::forget($page->slug);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status page updated.')]);

        return back();
    }

    public function destroy(StatusPage $page): RedirectResponse
    {
        $this->authorize('delete', $page);

        $page->deleteLogo();
        $page->delete();
        StatusPagePayload::forget($page->slug);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status page deleted.')]);

        return back();
    }
}
