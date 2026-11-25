# Scheduled tasks, API, and tokens

## Monitor a scheduled task (heartbeat)

Backups, cron, Laravel scheduler: **the job pings Nocline**. Missed pings trigger alerts.

1. **Add monitor → Something else → Scheduled task** — name and expected frequency (minute, hour, day…).
2. Nocline shows a secret URL and copy-paste examples (cron, backup script, Laravel). The page updates when the first ping arrives.
3. Grace period (default 10% of interval, at least 60s) reduces false positives.

In Laravel, `nocline/laravel` (`packages/nocline-laravel`) adds `->pingNoclineOnSuccess('token')` and `->pingNoclineOnFailure('token')`; it never slows the app if Nocline is unreachable.

## API tokens

**My profile → API tokens**: create a token (read-only or read/write, optional expiry). Shown **once** — copy immediately. Rights never exceed the owner; revoke in one click. Examples and OpenAPI: [REST API v1](/api).

## Automatic site detection

When adding a website, Nocline detects Laravel or WordPress and may suggest monitoring `/up`. See [Add monitors](surveiller#automatic-detection).
