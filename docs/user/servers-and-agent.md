# Servers and agent

**Groups** (see [Add monitors](surveiller#groups)) are folders for servers and checks.

## Enroll a server

1. **Servers** → name (+ optional group) → _Add_. An enrollment token is shown **once** (valid 24h, single use). **Token** generates a new one. The binary comes from the **official GitHub release** matching your hub version — the server must reach `github.com` over HTTPS.
2. On the server: `nocline-agent enroll --hub https://your-hub --token <token>`. The permanent token is written to `/etc/nocline-agent/config.yaml` (mode `0600`).
3. Run `nocline-agent` (systemd recommended). The server shows **online** after the first push; **offline** after ~3 minutes without data.

_Revoke_ invalidates the permanent token (the agent gets 401). No inbound port is required: the agent pushes to the hub over HTTPS.

## Delete a server (admin)

**Servers** list or server page → **Delete server** (modal). On the hub: record and API token removed, metric history cleared; linked monitors stay but are detached from this server. The agent **keeps running** on the machine until you stop it — the modal shows systemd commands to remove the service, binary (`/usr/local/bin/nocline-agent`), and config (`/etc/nocline-agent/`).

## Update the agent

### Automatic updates (recommended)

Per server: **Servers → server page → nocline-agent section** — _Automatic updates_ (on by default). The hub offers updates via ingest only when this is enabled and `NOCLINE_AGENT_AUTO_UPDATE` is not `false` in `.env`.

The agent accepts updates by default (`auto_update: false` in `/etc/nocline-agent/config.yaml` opts out). Downloads are verified (SHA256), with at most one attempt every 6 hours.

### Manual update

On an **already enrolled** server, use the _update available_ badge command (or `curl -fsSL https://your-hub/install/agent-update.sh | sudo sh`). **Do not** re-run the full install line on production: it re-enrolls, invalidates the old token while the old process still runs → 401 and “offline” until you restart manually.

## Reboot after kernel updates

About **once per hour** the agent reports whether the OS wants a reboot (`/var/run/reboot-required` on Debian/Ubuntu, or `needs-restarting -r` on RHEL/Alma). The hub shows a **Reboot pending** banner on the server list and detail page; channels get an alert on first detection, plus a **daily digest at 08:05** while reboot is still pending (`nocline:hosts:reboot-remind`). Disable with `NOCLINE_REBOOT_REQUIRED_ALERTS=false` and/or `NOCLINE_REBOOT_REQUIRED_DAILY_REMIND=false`.

## Metrics

The server page shows **CPU, RAM, disk** over **24h**, **7 days**, or **30 days** (1 min, 5 min, or 1h rollups).

## Load alerts

By default Nocline opens an incident if **CPU, RAM, or disk** stays above **90%** for **5 consecutive minutes** (per-minute average). Per server: **Servers → Load alerts** (thresholds, duration, optional 1 min load). Sparklines use the same thresholds for colors.

Every 30s the agent sends reduced aggregates; the hub stores rollups only (7d @ 1 min, 90d @ 5 min, 2y @ 1h). Building the agent: `agent/README.md`.
