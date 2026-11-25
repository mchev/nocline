# Installation and first run

## Requirements

PHP 8.3+, Composer, Node (for building assets), and SQLite, MySQL, or PostgreSQL. A queue worker (`php artisan queue:work`) and the scheduler (`php artisan schedule:work` or cron `* * * * * php artisan schedule:run`) must run: they execute checks, send alerts, and prune metrics.

## Setup wizard

On first visit, Phare redirects to `/setup`. Six steps (progress indicator, validation at each step, you can go back):

0. **Language** (first step; English by default; the rest of the wizard switches immediately). See [translations.md](translations.md) to add locales.
1. **Database**: SQLite (trials, small instances), MySQL, or PostgreSQL (recommended in production). A “Test connection” button is offered; the connection is also tested before the next step; migrations run without wiping existing data.
2. **Administrator**: name, e-mail, password (12+ characters, upper, lower, digit, symbol).
3. **Instance**: name, public hub URL (used in enrollment commands), timezone.
4. **E-mail (optional, skippable)**: SMTP server for alerts.
5. **Summary** then install.

When finished, the wizard is **locked** (`/setup` returns 404) and you are redirected to login. Settings are written to `.env`; the install marker is `storage/app/phare-installed` (keep `storage/` persistent, especially under Docker).

## Non-interactive install (Docker, Forge)

```bash
php artisan phare:install --db=pgsql --db-host=127.0.0.1 --db-name=phare \
  --db-user=phare --db-password=*** \
  --admin-name="Admin" --admin-email=admin@example.com --admin-password='***' \
  --name="My NOC" --url=https://noc.example.com --locale=en --timezone=Europe/Paris
```

The command refuses to run if the instance is already installed. `PHARE_FORCE_INSTALLED=true` disables the wizard (pre-provisioned deploys).

There is **no public registration**: accounts are created by an administrator.
