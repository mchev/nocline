# Monitor something

Use **Add a monitor** (top of the dashboard and sidebar). Three choices:

1. **A website** — enter the address (`example.com` is enough). Phare checks that it responds, and automatically tracks domain and SSL certificate expiry (Renewals page, no manual entry).
2. **A server** — give it a name. Phare shows **one line** to paste in a terminal on the server:

    ```sh
    curl -fsSL https://your-hub/install/<token> | sudo sh
    ```

    The script installs the agent, enrolls it, and starts it (systemd). The page updates on its own once the server is connected. The link is single-use and expires after 24h (“Generate a new command” for another).

3. **Something else** — **object storage (S3)** (OVH, AWS, Scaleway…), mail server, port/app, machine (ping), domain name (DNS), scheduled task.

### Object storage (S3)

Under **Something else** → **Object storage (S3)**:

| Mode | Meaning |
| ---- | ------- |
| **Storage service answers** | TCP on the provider S3 endpoint (port 443) — good first step for OVH `s3.<region>.io.cloud.ovh.net`. |
| **Public file in my bucket** | HTTP on `https://<bucket>.s3.<region>…/<file>` — put a small public `health.txt` (or similar) in the bucket. |
| **Backup job still runs** | Heartbeat — ping Phare after your `aws s3` / `rclone` job succeeds. |

Providers: OVH, **Hetzner**, **Laravel Cloud** (R2 endpoint from the bucket settings), AWS, Scaleway, Wasabi, or custom MinIO hostname. No cloud API keys in Phare for this wizard (phase 1).

For each choice you can optionally put the item in a group (existing or created on the fly).

## Automatic detection

When you enter a site URL, Phare inspects it (a few GET requests, 5s max each) and suggests suitable monitoring, without jargon:

- it checks the site responds and follows redirects to the same site (e.g. `http` → `https`);
- it detects **Laravel** or **WordPress** (tag on the check);
- for Laravel, if `/up` really exists, it offers (checked by default) a companion health check: “site name — health”, useful when the homepage is cached but the app is down;
- SSL expiry is already tracked automatically.

Detection is optional: if it fails, you can add the site normally.

The **favicon** is fetched on add (then refreshed weekly). If it changes, the `favicon-changed` tag appears on the check.

**Security.** Because the hub performs these requests, it blocks private addresses (LAN, `localhost`, cloud metadata, local IPv6, decimal/hex-encoded IPs), verifies each hop’s IP, and only allows ports 80, 443, 8080, 8443. To monitor sites on your own network (`*.test`, `192.168.x.x`), set `PHARE_DETECT_ALLOW_PRIVATE=true` in `.env` — only if everyone who can add sites is trusted.

## Groups

A **group** is a folder for servers, sites, and other items (project, team, provider…). Groups can contain **sub-groups**, as deep as you need.

Each group has display options: **list**, **grid**, or **compact** (dots); **colour**; **collapsed** (shows only aggregate state).

A wallboard can target one group (sub-groups included). Importing from Uptime Kuma recreates Kuma groups as nested groups. Deleting a group does not delete its content: sub-groups move up and items are detached.

## Agent: official GitHub releases

Monitored servers download `phare-agent` from the **official** release (`PHARE_OFFICIAL_REPOSITORY`, default `mchev/phare`), tag `vX.Y.Z` = hub `VERSION` file. No Go on the production hub.

Maintainers publish binaries with `git tag vX.Y.Z && git push --tags` (`release.yml`) or `scripts/build-agent.sh`. `APP_URL` is the hub URL for enrollment only.
