<?php

namespace App\Modules\Audit\Enums;

/**
 * Every action the activity log knows how to phrase in plain language.
 * Unknown actions are still stored and shown with a generic sentence.
 */
enum AuditAction: string
{
    case AuthLogin = 'auth.login';
    case AuthLoginFailed = 'auth.login_failed';
    case AuthLogout = 'auth.logout';
    case PasswordChanged = 'password.changed';
    case TwoFactorEnabled = 'settings.two_factor_enabled';
    case TwoFactorDisabled = 'settings.two_factor_disabled';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserRoleChanged = 'user.role_changed';
    case UserDeleted = 'user.deleted';

    case CheckCreated = 'check.created';
    case CheckUpdated = 'check.updated';
    case CheckDeleted = 'check.deleted';

    case GroupCreated = 'group.created';
    case GroupUpdated = 'group.updated';
    case GroupDeleted = 'group.deleted';

    case HostCreated = 'host.created';
    case HostUpdated = 'host.updated';
    case HostDeleted = 'host.deleted';

    case ChannelCreated = 'channel.created';
    case ChannelUpdated = 'channel.updated';
    case ChannelDeleted = 'channel.deleted';

    case WallboardCreated = 'wallboard.created';
    case WallboardUpdated = 'wallboard.updated';
    case WallboardDeleted = 'wallboard.deleted';

    case StatusPageCreated = 'status_page.created';
    case StatusPageUpdated = 'status_page.updated';
    case StatusPageDeleted = 'status_page.deleted';

    case MaintenanceCreated = 'maintenance.created';
    case MaintenanceUpdated = 'maintenance.updated';
    case MaintenanceDeleted = 'maintenance.deleted';

    case ApiTokenCreated = 'api_token.created';
    case ApiTokenUpdated = 'api_token.updated';
    case ApiTokenDeleted = 'api_token.deleted';

    case RenewalCreated = 'renewal.created';
    case RenewalUpdated = 'renewal.updated';
    case RenewalDeleted = 'renewal.deleted';

    case VaultCreated = 'vault.created';
    case VaultUpdated = 'vault.updated';
    case VaultDeleted = 'vault.deleted';
    case VaultRevealed = 'vault.revealed';

    /**
     * Filter choices: the part before the dot, with a plain-language name.
     *
     * @return array<string, string> prefix => label
     */
    public static function families(): array
    {
        return [
            'auth' => __('Sign-ins'),
            'password' => __('Passwords'),
            'settings' => __('Two-factor authentication'),
            'user' => __('Users'),
            'check' => __('Monitors'),
            'group' => __('Groups'),
            'host' => __('Servers'),
            'channel' => __('Notification channels'),
            'wallboard' => __('Wallboards'),
            'status_page' => __('Status pages'),
            'maintenance' => __('Maintenance'),
            'api_token' => __('API tokens'),
            'renewal' => __('Renewals'),
            'vault' => __('Vault'),
        ];
    }

    /**
     * A plain sentence such as "Marie created the monitor Site A".
     *
     * @param  array<string, mixed>  $meta
     */
    public static function sentence(string $action, string $user, ?string $subject, array $meta = []): string
    {
        $template = self::tryFrom($action)?->template() ?? ':user did :action';

        return strtr($template, [
            ':user' => $user,
            ':subject' => $subject ?? '',
            ':from' => (string) ($meta['from'] ?? ''),
            ':to' => (string) ($meta['to'] ?? ''),
            ':action' => $action,
        ]);
    }

    /**
     * The translated template, placeholders still in place.
     */
    private function template(): string
    {
        return match ($this) {
            self::AuthLogin => __(':user signed in'),
            self::AuthLoginFailed => __('Failed sign-in attempt for :subject'),
            self::AuthLogout => __(':user signed out'),
            self::PasswordChanged => __(':user changed the password of :subject'),
            self::TwoFactorEnabled => __(':user turned on two-factor authentication'),
            self::TwoFactorDisabled => __(':user turned off two-factor authentication'),
            self::UserCreated => __(':user created the account :subject'),
            self::UserUpdated => __(':user edited the account :subject'),
            self::UserRoleChanged => __(':user changed the role of :subject from :from to :to'),
            self::UserDeleted => __(':user deleted the account :subject'),
            self::CheckCreated => __(':user created the monitor :subject'),
            self::CheckUpdated => __(':user edited the monitor :subject'),
            self::CheckDeleted => __(':user deleted the monitor :subject'),
            self::GroupCreated => __(':user created the group :subject'),
            self::GroupUpdated => __(':user edited the group :subject'),
            self::GroupDeleted => __(':user deleted the group :subject'),
            self::HostCreated => __(':user added the server :subject'),
            self::HostUpdated => __(':user edited the server :subject'),
            self::HostDeleted => __(':user deleted the server :subject'),
            self::ChannelCreated => __(':user created the notification channel :subject'),
            self::ChannelUpdated => __(':user edited the notification channel :subject'),
            self::ChannelDeleted => __(':user deleted the notification channel :subject'),
            self::WallboardCreated => __(':user created the wallboard :subject'),
            self::WallboardUpdated => __(':user edited the wallboard :subject'),
            self::WallboardDeleted => __(':user deleted the wallboard :subject'),
            self::StatusPageCreated => __(':user created the status page :subject'),
            self::StatusPageUpdated => __(':user edited the status page :subject'),
            self::StatusPageDeleted => __(':user deleted the status page :subject'),
            self::MaintenanceCreated => __(':user planned the maintenance :subject'),
            self::MaintenanceUpdated => __(':user edited the maintenance :subject'),
            self::MaintenanceDeleted => __(':user deleted the maintenance :subject'),
            self::ApiTokenCreated => __(':user created the API token :subject'),
            self::ApiTokenUpdated => __(':user edited the API token :subject'),
            self::ApiTokenDeleted => __(':user deleted the API token :subject'),
            self::RenewalCreated => __(':user created the renewal :subject'),
            self::RenewalUpdated => __(':user edited the renewal :subject'),
            self::RenewalDeleted => __(':user deleted the renewal :subject'),
            self::VaultCreated => __(':user added the vault entry :subject'),
            self::VaultUpdated => __(':user edited the vault entry :subject'),
            self::VaultDeleted => __(':user deleted the vault entry :subject'),
            self::VaultRevealed => __(':user revealed the secret :subject'),
        };
    }
}
