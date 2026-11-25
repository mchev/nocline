# Scheduled tasks, API, and tokens

## Monitor a scheduled task (heartbeat)

Backups, cron, Laravel scheduler: **the job pings Phare**. Missed pings trigger alerts.

1. **Add monitor → Something else → Scheduled task** — name and expected frequency (minute, hour, day…).
2. Phare shows a secret URL and copy-paste examples (cron, backup script, Laravel). The page updates when the first ping arrives.
3. Grace period (default 10% of interval, at least 60s) reduces false positives.

In Laravel, `phare/laravel` (`packages/phare-laravel`) adds `->pingPhareOnSuccess('token')` and `->pingPhareOnFailure('token')`; it never slows the app if Phare is unreachable.

## API tokens

**My profile → API tokens**: create a token (read-only or read/write, optional expiry). Shown **once** — copy immediately. Rights never exceed the owner; revoke in one click. Examples and OpenAPI: [REST API v1](/api).

## Automatic site detection

When adding a website, Phare detects Laravel or WordPress and may suggest monitoring `/up`. See [Add monitors](surveiller#automatic-detection).
