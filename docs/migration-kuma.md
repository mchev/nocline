# Migration depuis Uptime Kuma & wallboard

## Wallboard HTML actuel

Référence conservée dans [`legacy/wallboard/`](../legacy/wallboard/) :

- UX dense, bandeau *stale*, couleurs ok/down/warn
- À remplacer par une page Inertia alimentée par `GET /api/wallboard/{token}` (spec dans `reference.md`)

## Importer ses monitors Kuma

Deux sources acceptées : la **sauvegarde JSON** (Kuma 1.x : Paramètres → Sauvegarde) ou directement le fichier **`kuma.db`** (SQLite, `data/kuma.db`, utile pour Kuma 2.x qui n'exporte plus en JSON).

- Interface : page **Checks → Importer depuis Uptime Kuma** (aperçu puis import).
- CLI : `php artisan phare:import-kuma chemin/kuma.db --dry-run`, puis sans `--dry-run`.

Correspondance : `http`/`keyword`/`json-query` → http (catégorie sites web, mot-clé conservé) ; `port` → tcp (serveurs) ; `ping` → icmp (réseau, exécuté par les sondes) ; `dns` → dns ; `push` → **tâche planifiée** (heartbeat : Phare génère une nouvelle URL de ping à brancher sur vos tâches). Les tags Kuma deviennent des tags Phare ; les groupes Kuma deviennent des **groupes Phare imbriqués** (la hiérarchie est conservée). Les retries Kuma + 1 deviennent le seuil d'échecs ; les monitors en pause sont importés désactivés. Les autres types (docker, mqtt…) et les canaux de notification ne sont pas importés (liste affichée). L'import est idempotent (même nom + même cible = ignoré).

## Coexistence

Tu peux faire tourner Kuma en parallèle jusqu’à couverture checks + wallboard Phare.