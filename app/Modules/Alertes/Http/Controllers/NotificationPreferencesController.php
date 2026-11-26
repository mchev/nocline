<?php

namespace App\Modules\Alertes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alertes\Http\Requests\NotificationPreferencesRequest;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Services\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class NotificationPreferencesController extends Controller
{
    public function update(NotificationPreferencesRequest $request, NotificationPreferences $preferences): RedirectResponse
    {
        $this->authorize('viewAny', NotificationChannel::class);

        $preferences->save($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences saved.')]);

        return back();
    }
}
