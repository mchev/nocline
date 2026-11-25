# Maintenance and status pages

## Scheduled maintenance (“no alerts during work”)

Rebooting a server or deploying a site? Tell Nocline first — no false alarms.

1. Open **Maintenance** in the menu.
2. **Schedule maintenance** (or **Start maintenance now for 1 hour**).
3. **Scope**: everything, one **group** (sub-groups included), or one **probe**.
4. Start and end (browser timezone).
5. For recurring work (nightly jobs): **Daily** or **Weekly** — must be shorter than the repeat interval.

During maintenance:

- Probes keep running and recording;
- **no new incidents or alerts**;
- UI shows **Maintenance** instead of **Down** (checks, dashboard, wallboard);
- incidents open before maintenance stay open (no new alerts); recovery during maintenance closes normally;
- still-down checks after maintenance end open incidents within about a minute.

`admin` and `tech` create and edit; `viewer` can read.

## Public status pages

Give each client a URL that shows if their services are up — no login, your branding.

1. **Status pages** → **New status page**.
2. **Title**; slug auto-generated (lowercase, numbers, hyphens).
3. **Scope**: everything or one group (sections match sub-groups).
4. Optional message, **colour**, **logo** (PNG/JPG/WebP, 1 MB max; SVG blocked).
5. Share `https://your-hub/status/client-name`.

Plain-language banner, per-check status (**Operational**, **Down**, **Maintenance**, **Unknown**), 90-day availability, upcoming maintenance, optional 14-day incident history. **No technical leaks** (no URLs, IPs, or error strings) — only the **monitor name** shown; rename checks for client-friendly labels. Not indexed by search engines; refreshes ~30s. JSON feed: `/status/slug/feed`.

Uncheck **Page is public** to hide without deleting. Deleting a group removes linked maintenance windows and status pages tied to it.

### Logos

Logos use the `public` disk — once on the server: `php artisan storage:link`.
