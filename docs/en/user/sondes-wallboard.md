# Probes and wallboard

## Probes

A probe is the same `phare-agent` binary with `role: probe`. It runs checks from another network point (e.g. an external VPS): http, tcp, dns, and icmp (icmp needs `CAP_NET_RAW`: `setcap cap_net_raw+ep` or Docker `cap_add: NET_RAW`). The hub never runs ICMP itself.

1. **Probes** menu (admin) → name + role (`primary`: same network as the hub, `external`: Internet view) → _Add_.
2. On the machine: `phare-agent enroll --hub https://your-hub --token <prb_… token>`, then run `phare-agent`.
3. On a check, choose **Execution: probes** (required for icmp/dns) and a **quorum**:
    - _majority_ (default): down if more than half of probes see a failure;
    - _any_: down as soon as one probe sees a failure (fast detection);
    - _all_: down only if every probe sees a failure.

Only active probes that responded recently count in the quorum. Each probe keeps only its latest result per check; history remains “state + events”.

## Wallboard

**Wallboards** menu (admin): create a screen (optionally scoped to one group, sub-groups included). The `…/wallboard/wb_…` URL is shown **once** on create (or after _New URL_) — keep it; it acts as the secret. **New URL** regenerates the token if you lost it; the old link stops immediately. **Revoke** deletes the screen. Open the URL fullscreen on a TV or Raspberry Pi — no login (recent Chromium recommended for kiosk).

On a touch kiosk, hide the system cursor (`unclutter -idle 0.01 -root`) and tweak Chromium’s `--force-device-scale-factor` if needed.

- Refreshes every 15s; the page reads a cached aggregate API (10s, `/api/wallboard/<token>`). After a deploy (new front build or `VERSION` bump), the API exposes `hub_revision`; when it changes, the screen **reloads itself** (kiosk included). Optional: `PHARE_DEPLOY_REVISION` in `.env` to force the marker.
- If no data arrives for 90s, a **stale data** banner appears and the last values stay visible.
- Content: **ALL GOOD / ATTENTION** header, problem list when needed, website rows with 24-minute history, server band at the bottom — layout adapts from small screens to TV.

Use Redis for cache in production (`CACHE_STORE=redis`).
