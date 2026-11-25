# Checks, incidents, and alerts

## Checks

Types: `http` (status, optional keyword), `tcp` (`host:port`), `agent` (has the server checked in recently?), `icmp` and `dns` (probe-only — see [Probes and wallboard](sondes-wallboard)). HTTP/TCP/agent checks run from the hub every minute according to their interval.

A check goes **down** after N consecutive failures (default 2), then **up** on the first success. Only **state changes** are stored in history.

### Organize monitors

Each check can have a **group**, a **category** (website, server, mail, DNS, network, other), and free **tags** (e.g. `prod`, `wordpress`).

On **Checks**:

- **Group by** group, category, status, type, or none; groups collapse and show down counts (groups with problems float to the top).
- **Filter** by group, category, status, or search name / target / tag (click a tag to filter). View preferences are saved in the browser.
- **Bulk actions**: select checks → assign group / category / tags, pause, resume, or delete (admin).
- Edit, pause, or delete from each row.

**Servers** uses the same grouping, search, tags, and inline edit patterns.

## Incidents

A failure opens one **incident** per check until it recovers. A `tech` or `admin` can **acknowledge** it from **Incidents**.

## Alerts

**Alerts** menu (admin): **email (SMTP)**, **Slack**, **Discord**, **Brevo** (API), and generic **webhook** JSON.

### Channels per group

**Groups → edit group**: tick **alert channels** for checks in that group. **No selection** = legacy behaviour (all active channels). Route “Acme client” Slack only for that folder’s incidents.

### Snooze

On a check or from **Incidents**: **Snooze 4h** stops notifications (incident still visible). When snooze ends, if the incident is still open and was not notified, alerting resumes (`nocline:alerts:release-snoozes` every minute).

### Slow HTTP responses

On an **HTTP** check: **Slow response alert (ms)** — after N slow measurements in a row (_slow streak_, default = failure threshold), the check goes **down** with a “Slow response…” incident. Each channel has **Send test**; secrets are never shown again on edit (leave blank to keep). Custom systems (WhatsApp, Telegram…): [notification adapters](/contributing/notification-adapters). One notification per channel on open and resolve (deduped). Channel settings are encrypted at rest.
