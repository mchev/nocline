# Changelog

Format : [Keep a Changelog](https://keepachangelog.com), versions [SemVer](https://semver.org).

## [0.2.0] - 2025-11-25

### Changed

- **Rebrand:** product renamed **Nocline** (docs, `nocline:*` Artisan commands, `NOCLINE_*` env vars, `nocline-agent` binaries, repo `mchev/nocline`).
- Agents self-migrate on first **0.2.0** start: `/usr/local/bin/nocline-agent`, `/etc/nocline-agent/`, `nocline-agent.service`.

## [Unreleased]

### Removed

- Agent auto-migration and PHP fallbacks for the pre-Nocline product name (paths, env vars, install markers).

### Added

- Parcours « Add a monitor » (site, serveur, autre) et installation serveur en une ligne.
- Groupes imbriqués (couleur, disposition liste/cartes/compact, repliage) à la place des clients.
- Renouvellements automatiques (domaine via RDAP, certificat SSL).
- Import Uptime Kuma, adaptateurs de notification, interface en anglais et français.
- Vérification quotidienne des releases GitHub, bannière de mise à jour, `scripts/update.sh` et `php artisan nocline:update`.

### Changed

- Les clients sont remplacés par des groupes (migration automatique : chaque client devient un groupe).
