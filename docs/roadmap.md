# Roadmap

## Phase 1 — MVP

- [x] Fortify auth + `admin` / `tech` / `viewer` roles
- [x] **Groups** (nested folders, list/grid/compact, colour, collapsed)
- [x] **Servers** + agent enrollment
- [x] **Checks** + scheduling
- [x] **Incidents** (up/down events, ack)
- [x] **Alerts** (mail + pluggable channels)
- [x] Metrics ingest API (`POST /api/v1/ingest`)
- [x] Go agent: CPU/RAM/disk
- [x] **First setup** wizard (DB, admin, locale, URL, mail) — locked after install

## Phase 1.5

- [x] **Wallboard** (TV / kiosk, token URL)
- [x] **Probes** (`role=probe`) + quorum
- [x] Wallboard aggregate API + Redis cache

## Phase 2

- [x] **Vault** (libsodium, audit, re-auth)
- [x] **Activity log** + security headers + SECURITY/CONTRIBUTING
- [x] **Renewals** (SSL, domain via RDAP, reminders)

## Phase 3

- [ ] **WordPress** (minimal plugin)
- [ ] Hosting provider integrations
- [x] **Scheduled maintenance**
- [x] **Public status pages** (`/status/{slug}`)

## Cross-cutting

- [x] User documentation (`docs/`, EN default + FR)
- [x] i18n (`lang/*.json`, EN default)
- [x] Setup wizard (language first)
- [x] Check organisation (group, category, tags, bulk actions)
- [x] Auto-detection on website add (Laravel/WordPress, SSRF-safe)
- [x] REST API v1 + heartbeats + `nocline/laravel` package (unpublished)
- [x] No public registration
- [x] GitHub release checks + `scripts/update.sh` + `nocline:update`
- [x] Official **Docker Compose** stack
- [ ] One-click agent upgrade from UI (later)
