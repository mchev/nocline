<?php

namespace App\Modules\Audit\Observers;

use App\Models\User;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Checks\Models\Check;
use App\Modules\Coffre\Models\VaultEntry;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\Wallboard\Models\Wallboard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One generic observer for every audited model: records created / updated / deleted.
 * Updates carry only the NAMES of the changed attributes, never their values.
 * Changes made without a signed-in user (agents, schedulers, imports) are not logged.
 */
class AuditObserver
{
    /**
     * Audited models and the action prefix of each. Classes that do not exist yet are skipped,
     * so modules can be added later without touching the rest.
     *
     * @var array<class-string<Model>, string>
     */
    public const MODELS = [
        User::class => 'user',
        Check::class => 'check',
        Group::class => 'group',
        Host::class => 'host',
        NotificationChannel::class => 'channel',
        Wallboard::class => 'wallboard',
        Renewal::class => 'renewal',
        VaultEntry::class => 'vault',
        'App\Modules\Maintenance\Models\MaintenanceWindow' => 'maintenance',
        'App\Modules\StatusPages\Models\StatusPage' => 'status_page',
        'App\Modules\Api\Models\ApiToken' => 'api_token',
    ];

    /** Attributes touched by the system, not by a person: changing only these is not an activity. */
    private const NOISE = [
        'updated_at', 'last_seen_at', 'last_checked_at', 'check_error', 'agent_version', 'os',
        'enrolled_at', 'remember_token', 'email_verified_at',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ];

    public function __construct(private AuditLogger $logger) {}

    public static function register(): void
    {
        foreach (array_keys(self::MODELS) as $model) {
            if (class_exists($model)) {
                $model::observe(self::class);
            }
        }
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $changed = array_values(array_diff(array_keys($model->getChanges()), self::NOISE));

        if ($model instanceof User) {
            $this->userUpdated($model, $changed);

            return;
        }

        if ($changed !== []) {
            $this->record($model, 'updated', ['changed' => $changed]);
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function record(Model $model, string $verb, array $meta = []): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->logger->record(self::MODELS[$model::class].'.'.$verb, $model, $meta);
    }

    /**
     * @param  list<string>  $changed
     */
    private function userUpdated(User $user, array $changed): void
    {
        if (in_array('password', $changed, true)) {
            // Also covers "forgot password": nobody is signed in, the account owner is the actor.
            $this->logger->record(AuditAction::PasswordChanged, $user, actor: Auth::user() instanceof User ? null : $user);
        }

        if (in_array('role', $changed, true)) {
            $this->record($user, 'role_changed', [
                'from' => $user->getOriginal('role')?->value,
                'to' => $user->role->value,
            ]);
        }

        $rest = array_values(array_diff($changed, ['password', 'role']));

        if ($rest !== []) {
            $this->record($user, 'updated', ['changed' => $rest]);
        }
    }
}
