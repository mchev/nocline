# Your first hour with Nocline

After [installation](installation), follow this path once. It matches the in-app wizard vocabulary.

## 1. Sign in

Use the administrator account from `/setup`. There is **no public sign-up** — [accounts and roles](accounts-and-roles) explains who can do what.

## 2. Add something to watch

**Add a monitor** (dashboard or sidebar):

| You have…        | Choose…                             | What happens                                     |
| ---------------- | ----------------------------------- | ------------------------------------------------ |
| A website        | **A website**                       | Nocline checks HTTP, tracks domain + SSL expiry  |
| A Linux server   | **A server**                        | You get a one-line install command for the agent |
| A cron job       | **Something else → Scheduled task** | Nocline gives you a secret ping URL              |
| Mail, DNS, port… | **Something else**                  | Pick the matching type                           |

Details: [Add monitors](surveiller).

## 3. Turn on alerts (optional but recommended)

**Alerts** (admin) → add at least one channel (email, Slack, Discord…). Send a **test** from each channel.

When something goes down, Nocline opens an [incident](checks-incidents-alerts) and notifies you once per incident (deduplicated).

## 4. Nice extras

- **Wallboard** — fullscreen TV view without login → [Probes and wallboard](sondes-wallboard)
- **Status page** for a client → [Maintenance and status pages](maintenance-and-status)
- **Coming from Uptime Kuma?** → [Migration guide](../migration-kuma)

## What runs in the background

Checks, SSL/domain scans, renewal reminders, and metric rollups need a **queue worker** and the **scheduler** (Docker Compose includes them; on a VPS use Supervisor or systemd). If nothing seems to run on a schedule, verify those two processes first → [Production checklist](../deploy/hub).
