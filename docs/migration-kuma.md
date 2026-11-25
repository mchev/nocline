# Migrate from Uptime Kuma

## Wallboard

Nocline wallboards use a token URL, ~15s refresh, and a _stale data_ banner. See [Probes and wallboard](user/sondes-wallboard). API: `GET /api/wallboard/{token}` ([technical reference](/guideline/reference)).

## Import monitors

Two sources: Kuma **JSON backup** (1.x: Settings → Backup) or **`kuma.db`** (SQLite, `data/kuma.db` — useful for 2.x without JSON export).

- UI: **Checks → Import from Uptime Kuma** (preview, then import).
- CLI: `php artisan nocline:import-kuma path/kuma.db --dry-run`, then without `--dry-run`.

Mapping: `http`/`keyword`/`json-query` → http; `port` → tcp; `ping` → icmp (probes); `dns` → dns; `push` → **scheduled task** (Nocline generates a new heartbeat URL). Kuma tags → Nocline tags; Kuma groups → nested Nocline groups. Retries + 1 → failure threshold; paused monitors import disabled. Types like docker/mqtt and notification channels are not imported (listed in the UI). Idempotent: same name + target = skipped.

## Run in parallel

You can keep Kuma until Nocline covers your checks and wallboard needs.
