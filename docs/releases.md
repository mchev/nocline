# Releases and upgrades

Product version lives in [`VERSION`](https://github.com/mchev/phare/blob/main/VERSION). It drives hub update checks (`phare:update:check`) and **`phare-agent` downloads** (`vX.Y.Z` = `VERSION`).

Official agent repo: **`mchev/phare`** (`PHARE_OFFICIAL_REPOSITORY`). Forks: set `PHARE_REPOSITORY` for banners.

## Versioning

| Item              | Format                                               | Example    |
| ----------------- | ---------------------------------------------------- | ---------- |
| `VERSION` file    | `X.Y.Z`                                              | `0.1.4`    |
| Git tag / release | `vX.Y.Z`                                             | `v0.1.4`   |
| Assets            | `phare-agent-linux-amd64`, `phare-agent-linux-arm64` | on the tag |

Tag must match `VERSION` (CI).

## Publish (maintainers)

1. Green CI, bump `VERSION`, push `main`.
2. `git tag vX.Y.Z && git push origin vX.Y.Z` — workflow attaches agent binaries.

Do not create empty releases in the GitHub UI. Recovery: re-run workflow or `scripts/publish-agent-assets.sh vX.Y.Z`.

## Upgrade hub

```bash
cd /path/to/phare && scripts/update.sh    # or scripts/update.sh v0.1.4
```

Forge: `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `migrate --force`, `queue:restart`.

`PHARE_UPDATE_CHECK=false` disables daily GitHub checks.

## Upgrade agents

Re-run the install or update line from the server page after the hub matches a new `VERSION`. Servers need HTTPS to `github.com` and the hub.

See [deploy/hub](deploy/hub), [installation](user/installation), [servers and agent](user/servers-and-agent).
