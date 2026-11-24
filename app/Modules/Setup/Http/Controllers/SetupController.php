<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Setup\Http\Requests\SetupRequest;
use App\Modules\Setup\Services\Installer;
use App\Support\Locales;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SetupController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('setup/Index', [
            'defaults' => [
                'url' => url('/'),
                'timezone' => config('app.timezone'),
                'sqlite_path' => database_path('database.sqlite'),
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
            'locale' => app()->getLocale(),
        ]);
    }

    public function locale(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:'.implode(',', array_keys(Locales::available()))]]);
        $request->session()->put('locale', $data['locale']);

        return back();
    }

    public function testConnection(Request $request, Installer $installer): JsonResponse
    {
        $db = $request->validate([
            'driver' => ['required', 'in:sqlite,mysql,pgsql'],
            'database' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $error = $installer->testConnection($db + ['database' => null, 'host' => null, 'port' => null, 'username' => null, 'password' => null]);

        return response()->json(['ok' => $error === null, 'error' => $error]);
    }

    public function store(SetupRequest $request, Installer $installer): RedirectResponse|SymfonyResponse
    {
        $data = $request->validated();

        if ($error = $installer->testConnection($data['db'])) {
            return back()->withErrors(['db.driver' => __('Could not connect to the database: :error', ['error' => $error])])->withInput();
        }

        $installer->install($data);

        session(['locale' => $data['instance']['locale']]);

        // Full page load: the session/cache drivers changed with the new .env, so start from a clean state.
        session()->flash('status', __('Phare is installed. Sign in with the administrator account.'));

        return Inertia::location(route('login'));
    }
}
