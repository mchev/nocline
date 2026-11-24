<?php

namespace App\Modules\Audit\Listeners;

use App\Models\User;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

/**
 * Sign-ins, failed attempts, sign-outs and two-factor changes. Never stores the attempted password.
 */
class RecordAuthActivity
{
    public function __construct(private AuditLogger $logger) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, $this->login(...));
        $events->listen(Failed::class, $this->failed(...));
        $events->listen(Logout::class, $this->logout(...));
        $events->listen(TwoFactorAuthenticationConfirmed::class, $this->twoFactorEnabled(...));
        $events->listen(TwoFactorAuthenticationDisabled::class, $this->twoFactorDisabled(...));
    }

    public function login(Login $event): void
    {
        $this->logger->record(AuditAction::AuthLogin, $this->asUser($event->user), actor: $this->asUser($event->user));
    }

    public function failed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->logger->record(AuditAction::AuthLoginFailed, subjectLabel: is_string($email) ? $email : null);
    }

    public function logout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->record(AuditAction::AuthLogout, $event->user, actor: $event->user);
        }
    }

    public function twoFactorEnabled(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->logger->record(AuditAction::TwoFactorEnabled, $this->asUser($event->user), actor: $this->asUser($event->user));
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->logger->record(AuditAction::TwoFactorDisabled, $this->asUser($event->user), actor: $this->asUser($event->user));
    }

    private function asUser(mixed $user): ?User
    {
        return $user instanceof User ? $user : null;
    }
}
