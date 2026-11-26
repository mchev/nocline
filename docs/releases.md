# Releases and upgrades

Product version lives in [`VERSION`](https://github.com/mchev/nocline/blob/main/VERSION). It drives hub update checks (`nocline:update:check`) and **`nocline-agent` downloads** (`vX.Y.Z` = `VERSION`).

Official agent repo: **`mchev/nocline`** (`NOCLINE_OFFICIAL_REPOSITORY`). Forks: set `NOCLINE_REPOSITORY` for banners.

## Versioning

| Item              | Format                                                   | Example    |
| ----------------- | -------------------------------------------------------- | ---------- |
| `VERSION` file    | `X.Y.Z`                                                  | `0.1.4`    |
| Git tag / release | `vX.Y.Z`                                                 | `v0.1.4`   |
| Assets            | `nocline-agent-linux-amd64`, `nocline-agent-linux-arm64` | on the tag |

Tag must match `VERSION` (CI).

## Upgrade hub

```bash
cd /path/to/nocline && scripts/update.sh    # or scripts/update.sh v0.1.4
```

Forge: `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `migrate --force`, `queue:restart`.

`NOCLINE_UPDATE_CHECK=false` disables daily GitHub checks.

## Upgrade agents

Re-run the install or update line from the server page after the hub matches a new `VERSION`. Servers need HTTPS to `github.com` and the hub.

See [deploy/hub](deploy/hub), [installation](user/installation), [servers and agent](user/servers-and-agent).
