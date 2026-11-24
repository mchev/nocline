# Changelog

Format : [Keep a Changelog](https://keepachangelog.com), versions [SemVer](https://semver.org).

## [Unreleased]

### Added
- Parcours « Add a monitor » (site, serveur, autre) et installation serveur en une ligne.
- Groupes imbriqués (couleur, disposition liste/cartes/compact, repliage) à la place des clients.
- Renouvellements automatiques (domaine via RDAP, certificat SSL).
- Import Uptime Kuma, adaptateurs de notification, interface en anglais et français.
- Vérification quotidienne des releases GitHub, bannière de mise à jour, `scripts/update.sh` et `php artisan phare:update`.

### Changed
- Les clients sont remplacés par des groupes (migration automatique : chaque client devient un groupe).
