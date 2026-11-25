# Security

This page explains, plainly, how Phare protects your data, what it does **not** protect, and how to run it safely. To report a vulnerability, see [SECURITY.md](https://github.com/mchev/phare/blob/main/SECURITY.md).

## What is stored how

| Data                                                            | Protection                                                                        |
| --------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| User passwords                                                  | Hashed (bcrypt by default). Never readable, never logged.                         |
| Vault entries (secret, username, notes)                         | **Encrypted** (see below).                                                        |
| Notification channel settings (webhook URLs, SMTP passwords...) | Encrypted with `APP_KEY`.                                                         |
| Agent / probe API tokens, enrollment tokens, wallboard tokens   | Only a **SHA-256 hash** is stored. The clear token is shown once.                 |
| Two-factor secret and recovery codes                            | Encrypted with `APP_KEY` by Laravel Fortify.                                      |
| Monitor names, targets, groups, metrics, incidents              | **Not encrypted** in the database. Do not put secrets in a monitor URL or name.   |
| Vault entry **label** and **URL**                               | Not encrypted (they are shown in the list). Put secrets in the secret field only. |

Anyone with direct access to the database or its backups can read the unencrypted rows. Treat database dumps as sensitive.

## The vault

- Each entry has its own random 256-bit key (the _DEK_). The secret, username and notes are encrypted with it using **XChaCha20-Poly1305** (libsodium, authenticated encryption).
- The DEK is itself encrypted ("wrapped") with the **master key** `VAULT_MASTER_KEY`, which lives only in the environment (`.env`), never in the database. It must be 32 random bytes, base64-encoded, and **different from `APP_KEY`**.
- Tampered data or a wrong key makes decryption fail; the vault never returns partial data.
- Revealing a secret requires the current password again, is throttled (20 per minute) and is written to both the vault access log and the [activity log](#activity-log). Responses are marked `no-store`.
- **Back up `VAULT_MASTER_KEY` separately from the database** (for example in a password manager). Losing it makes every vault secret permanently unreadable. Storing it next to the database backup defeats the encryption.
- Rotating the master key is not automated yet.

## Agents, probes and transport

- An agent is installed with a one-line command that contains a **one-time enrollment token** (valid 24 hours). The agent exchanges it for a permanent API token, stored hashed on the hub and in a `0600` file on the server.
- Agents and probes **push** to the hub over HTTPS; they never open a listening port. Revoking a server or probe in the interface invalidates its token immediately.
- Use **HTTPS** for the hub. Over plain HTTP, tokens and passwords travel in clear text.

## Public endpoints and throttling

Some URLs are reachable without signing in by design. Each one is rate-limited and protected by an unguessable token (or by being read-only):

| Endpoint                                       | Purpose                              | Limit               |
| ---------------------------------------------- | ------------------------------------ | ------------------- |
| `POST /api/v1/enroll`                          | Agent enrollment                     | 10 / minute         |
| `POST /api/v1/ingest`                          | Agent metrics (agent token)          | 120 / minute        |
| `/api/v1/probe/*`                              | Probe work and results (probe token) | 240 / minute        |
| `/api/v1/heartbeat/{token}`                    | Cron heartbeats                      | 120 / minute        |
| `/install/{token}`, `/install/agent/{arch}`    | Install script and binary            | 30 / minute         |
| `/wallboard/{token}`, `/api/wallboard/{token}` | Wallboard (TV)                       | 60 and 120 / minute |
| `/status/{slug}`                               | Public status pages                  | 60 / minute         |
| Sign-in, two-factor challenge                  |                                      | 5 / minute          |

Wallboards and status pages may be shown inside a frame on another site (TVs, embedded status widgets); every other page refuses to be framed by other sites. The first-run setup wizard (`/setup`) is open until the installation is finished: complete it right after deploying, and it is closed afterwards.

## Browser hardening

Phare sends `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, a minimal `Permissions-Policy`, `X-Frame-Options: SAMEORIGIN` (except wallboards and status pages) and, over HTTPS, `Strict-Transport-Security`.

There is **no Content-Security-Policy** yet. The interface relies on Inertia page data embedded in the HTML and on the Vite development server, which a strict policy would break or force to be loosened to the point of little value. A nonce-based policy is a good future improvement.

## Activity log

Admins can see **who did what, when and from which IP** under _Activity log_. It records sign-ins (including failed ones), sign-outs, password and two-factor changes, creation / edition / deletion of users (with role changes), monitors, groups, servers, notification channels, wallboards, status pages, maintenance windows, API tokens and renewals, and every vault creation, edition, deletion and **reveal**.

It stores the names of things at the time (so deleted items stay readable) and, for edits, only the **names** of the fields that changed, never their values. Passwords, tokens and vault contents are never recorded. Changes made by the system itself (agent check-ins, schedulers, automatic renewal scans) are not logged. Entries are deleted after 365 days by default (`PHARE_AUDIT_RETENTION_DAYS`, pruned daily).

## Recommended hardening

1. Serve the hub over **HTTPS** only and redirect HTTP.
2. Turn on **two-factor authentication** for every admin (Settings → Security). Passkeys are supported too.
3. Use strong passwords (12+ characters in production) and give people the lowest role they need (viewer, tech, admin).
4. Set `APP_ENV=production` and `APP_DEBUG=false`.
5. Keep `.env` readable only by the web user; keep `APP_KEY` and `VAULT_MASTER_KEY` out of git and back them up **separately** from the database.
6. Back up the database regularly and test a restore. `scripts/update.sh` makes a backup before each update.
7. Firewall the server: only 80/443 (and SSH from trusted addresses) should be open. Database and Redis must not be exposed.
8. Keep Phare and the agents up to date; watch the update banner shown to admins.
9. Review the activity log from time to time, and revoke servers or tokens you no longer use.

## Threat model in short

Phare defends against: a curious reader of the database or a leaked backup (vault, channel settings and 2FA secrets are encrypted; tokens are hashed), a stolen session on the vault (re-authentication per reveal, audit trail), token guessing on public endpoints (long random tokens, throttling), and mistakes by lower-privileged users (roles and policies on every action).

It does **not** defend against: an attacker with both the database and the server's `.env` (they hold the keys), a compromised admin account without 2FA, a compromised hub server (it can read secrets when they are revealed), or a malicious server administrator. There is no telemetry: the hub makes no outgoing call except your checks, your notifications and the daily GitHub release check (`PHARE_UPDATE_CHECK=false` turns it off).
