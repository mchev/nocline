# Nocline REST API (v1)

Everything the UI does for checks and groups can be automated with the REST API. JSON only, base path `/api/v1`.

- [Authentication](#authentication)
- [Conventions](#conventions) (pagination, errors, rate limits)
- [Endpoints](#endpoints): [me](#current-user) · [checks](#checks) · [groups](#groups) · [hosts](#hosts) · [website monitor](#create-website-monitor)
- [Heartbeat](#heartbeat-scheduled-task-monitoring) (public ping endpoint) and the [`nocline/laravel` package](https://github.com/mchev/nocline/tree/main/packages/nocline-laravel)
- Machine-readable spec: [openapi.yaml](openapi.yaml) (OpenAPI 3.1)

In the examples, `$NOCLINE` is your hub base URL (`https://nocline.example.com`) and `$TOKEN` a personal API token.

## Authentication

Create a token in **Settings → API tokens**. Choose _Look only_ (`read`) or _Look and change_ (`read` + `write`), and an optional expiry. The secret (`phr_` followed by 40 characters) is shown **once**; Nocline only stores its SHA-256 hash. Lost? Revoke it and create another.

Send it as a bearer token:

```bash
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" $NOCLINE/api/v1/me
```

A token acts as **its owner**: the owner's role (`admin` / `tech` / `viewer`) still applies on top of the token abilities.

| Request                                  | Token ability needed | Role needed                                                     |
| ---------------------------------------- | -------------------- | --------------------------------------------------------------- |
| `GET`                                    | `read`               | any (some fields are hidden from `viewer`, see `heartbeat_url`) |
| create / update (`POST`, `PATCH`, `PUT`) | `write`              | `admin` or `tech`                                               |
| delete a check                           | `write`              | `admin`                                                         |
| delete a group                           | `write`              | `admin` or `tech`                                               |

So a `viewer`'s read/write token can still only read.

Missing, malformed, unknown, expired, revoked tokens and tokens of a deleted user all get the same answer, so nothing reveals whether a token exists:

```json
{ "message": "Unauthenticated." }
```

(HTTP 401, with `WWW-Authenticate: Bearer`.) A token without the needed ability gets `403`.

## Conventions

**Pagination.** List endpoints return `data`, `links` and `meta` (Laravel pagination). `?per_page=` (1–100, default 25) and `?page=`.

```json
{ "data": [ … ], "links": { "first": "…", "last": "…", "prev": null, "next": "…" }, "meta": { "current_page": 1, "per_page": 25, "total": 51, "last_page": 3 } }
```

Single resources are wrapped in `data`.

**Errors.** Always JSON, always a `message`:

| Status | Meaning                                   | Body                                                                                               |
| ------ | ----------------------------------------- | -------------------------------------------------------------------------------------------------- |
| 401    | bad / missing token                       | `{"message": "Unauthenticated."}`                                                                  |
| 403    | token ability or user role not sufficient | `{"message": "This action is unauthorized."}` (or `This token does not have the "write" ability.`) |
| 404    | no such resource                          | `{"message": "Not found."}`                                                                        |
| 422    | validation error                          | `{"message": "The name field is required.", "errors": {"name": ["The name field is required."]}}`  |
| 429    | rate limit                                | `{"message": "Too many requests."}` + `Retry-After` header (seconds)                               |

**Rate limits.** 120 requests per minute **per token** (`X-RateLimit-Limit` and `X-RateLimit-Remaining` response headers). Repeated failed authentications are also throttled per IP address (30 per minute). `last_used_at` of a token is updated at most once a minute.

**Dates** are ISO 8601 (UTC offsets included).

## Endpoints

### Current user

`GET /me`

The user behind the token and what the token may do.

```bash
curl -H "Authorization: Bearer $TOKEN" $NOCLINE/api/v1/me
```

```json
{
    "data": {
        "id": 1,
        "name": "Ada",
        "email": "ada@example.com",
        "role": "tech",
        "token": {
            "name": "CRM",
            "abilities": ["read", "write"],
            "expires_at": null
        }
    }
}
```

### Checks

A check object:

| Field                                                                           | Description                                                                                          |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `id`, `name`                                                                    |                                                                                                      |
| `type`                                                                          | `http`, `tcp`, `icmp`, `dns`, `agent` or `heartbeat`                                                 |
| `category`                                                                      | `website`, `server`, `mail`, `dns`, `network`, `other`                                               |
| `target`                                                                        | URL (`http`), `host:port` (`tcp`), host or domain (`icmp`, `dns`); empty for `agent` and `heartbeat` |
| `group_id`, `host_id`                                                           | `host_id` is required for `agent` checks                                                             |
| `tags`                                                                          | up to 20 strings (50 characters each)                                                                |
| `interval_sec`                                                                  | 30–86400 (heartbeat: up to 2592000, 30 days)                                                         |
| `timeout_sec`, `failure_threshold`                                              | 1–60 s, 1–10 (heartbeat checks always use 1)                                                         |
| `config`                                                                        | `keyword`, `expected_status` (http); `grace_sec` (heartbeat)                                         |
| `enabled`, `execution` (`hub` / `probe`), `quorum` (`any` / `majority` / `all`) |                                                                                                      |
| `status`                                                                        | `unknown`, `up`, `down`; `since_at` when it last changed                                             |
| `heartbeat_url`                                                                 | heartbeat checks only, **only for `admin` / `tech`** (it contains the secret ping token)             |
| `created_at`, `updated_at`                                                      |                                                                                                      |

The validation rules are the ones of the web form.

#### List checks

`GET /checks`

Filters: `group_id`, `type`, `enabled` (`0`/`1`), `per_page`, `page`.

```bash
curl -H "Authorization: Bearer $TOKEN" "$NOCLINE/api/v1/checks?type=http&enabled=1&per_page=50"
```

#### Create a check

`POST /checks`

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" $NOCLINE/api/v1/checks -d '{
  "name": "Shop", "type": "http", "target": "https://shop.example.com",
  "group_id": 3, "category": "website", "tags": ["prod"], "interval_sec": 60,
  "config": {"keyword": "Add to cart"}
}'
```

`201` with the created check. Required: `name`, `type`, `target` (except for `agent` and `heartbeat`), `host_id` (for `agent`).

#### Get one check

`GET /checks/{id}`

#### Patch a check

`PATCH /checks/{id}`

Send only the fields to change (`PUT` is accepted and behaves the same). The conditional rules are applied to the stored check completed with your fields.

```bash
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" $NOCLINE/api/v1/checks/12 -d '{"enabled": false}'
```

#### Delete a check

`DELETE /checks/{id}`

`204` with no body. Admins only.

#### Check status

`GET /checks/{id}/status`

Current state of the check (one row, updated in place):

```json
{
    "data": {
        "check_id": 12,
        "status": "down",
        "since_at": "2026-11-24T10:00:00+00:00",
        "last_checked_at": "2026-11-24T10:05:00+00:00",
        "last_ping_at": null,
        "latency_ms": null,
        "last_error": "HTTP 503",
        "consecutive_failures": 3
    }
}
```

### Groups

Groups are folders that nest through `parent_id` (`null` = top level): read the flat list and build the tree client-side.

Fields: `id`, `parent_id`, `name`, `color` (`#RRGGBB` or null), `layout` (`list` / `grid` / `compact`), `collapsed`, `position`, `created_at`, `updated_at`.

| Endpoint              | Notes                                                                                  |
| --------------------- | -------------------------------------------------------------------------------------- |
| `GET /groups`         | paginated, ordered by `position` then `name`                                           |
| `POST /groups`        | `name` required; `parent_id`, `color`, `layout`, `collapsed`, `position` optional      |
| `GET /groups/{id}`    |                                                                                        |
| `PATCH /groups/{id}`  | partial; a group cannot be moved inside itself or its descendants (422 on `parent_id`) |
| `DELETE /groups/{id}` | sub-groups move up one level, its checks become ungrouped. `204`                       |

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" $NOCLINE/api/v1/groups -d '{"name": "Production", "parent_id": 1}'
```

### Hosts

#### List hosts

`GET /hosts`

Read only (servers are enrolled with the agent, see `agent/README.md`). Fields: `id`, `name`, `group_id`, `tags`, `enrolled`, `online`, `last_seen_at`, `agent_version`, `os`. Agent credentials are never exposed.

### Create website monitor

`POST /monitors/website`

"Site created → monitor it". Same recipe as the guided _A website_ screen: an HTTP check every 60 s, category `website`, 2 failures before it is down; domain and SSL expiry are tracked automatically.

| Field                      |                                                                                              |
| -------------------------- | -------------------------------------------------------------------------------------------- |
| `url`                      | required, `http`/`https`; a bare domain such as `example.com` becomes `https://example.com`  |
| `name`                     | optional, defaults to the domain without `www.`                                              |
| `group_id` or `group_name` | optional; `group_name` finds or creates a top-level group (needs the right to create groups) |

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" $NOCLINE/api/v1/monitors/website \
  -d '{"url": "client-site.com", "group_name": "Acme"}'
```

`201` with the created check. Calling it twice creates two checks.

## Heartbeat (scheduled task monitoring)

A _heartbeat_ check is the reverse of an uptime check: your job pings Nocline, and Nocline raises an incident when the pings stop (a "dead man's switch" for cron jobs, Laravel schedulers, backups).

Create one from the UI (_Monitor → Something else → A scheduled task_, which shows ready-to-paste snippets) or with `POST /checks` and `"type": "heartbeat"`. Its `heartbeat_url` looks like `$NOCLINE/api/v1/heartbeat/<token>`.

### Ping endpoint

No authentication; the random token in the path is the credential. `GET` or `POST`:

| Call                                               | Effect                                                                                                                      |
| -------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `/api/v1/heartbeat/{token}`                        | success: the check goes (or stays) **up**                                                                                   |
| `/api/v1/heartbeat/{token}/fail` or `?status=fail` | explicit failure: the check goes **down** at once and an incident opens. `?msg=` (max 200 characters) is shown as the error |

```bash
# end of a backup script
curl -fsS -m 10 --retry 3 -o /dev/null $NOCLINE/api/v1/heartbeat/$HEARTBEAT_TOKEN

# crontab, with failure reporting
0 3 * * * /usr/local/bin/backup.sh && curl -fsS -m 10 -o /dev/null $NOCLINE/api/v1/heartbeat/$T || curl -fsS -m 10 -o /dev/null "$NOCLINE/api/v1/heartbeat/$T/fail?msg=backup+failed"
```

The answer is `200` with the plain text `ok`. Any unknown or malformed token gets the same `404` `{"message": "Not found."}` as every unknown URL. A **paused** check answers `ok` and ignores the ping. The endpoint is throttled to 120 requests per minute per IP address.

### When does it go down?

A scheduled command (`nocline:heartbeats:check`, every minute) marks a heartbeat down when

> now > last ping (or creation date if it never pinged) + `interval_sec` + grace

- **Grace** is `config.grace_sec` when set, otherwise 10% of the interval with a 60 s minimum.
- A check that never received its first ping stays **unknown** (not down) until creation + interval + grace has passed; then it goes down.
- It is marked down **once** (no repeated events or alerts); the next ping brings it back up and resolves the incident through the normal pipeline. Heartbeat checks always use a failure threshold of 1.
- Paused checks are not evaluated. When you resume one that has been silent for long, it goes down at the next minute until its next ping.

### Threat model

The token is stored in clear in a unique column (it has to be looked up from the URL) and is a 40-character random string (about 238 bits), hidden from `viewer` users in the UI and the API. Whoever holds it can only send success/failure signals for that one check: the endpoint reveals nothing else (always `ok` or an identical `404`), and failed lookups are rate limited. Treat the URL like a password and regenerate the check if it leaks.

### Laravel

The [`nocline/laravel`](https://github.com/mchev/nocline/tree/main/packages/nocline-laravel) package adds `->pingNoclineOnSuccess('token')` / `->pingNoclineOnFailure('token')` to the scheduler, a `Nocline::heartbeat()` facade and `php artisan nocline:ping`. It never throws and uses a 3 s timeout.
