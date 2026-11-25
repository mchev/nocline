# Déploiement hub Phare

## Requis

**PHP 8.3+** obligatoire. Le reste dépend du volume :

|         | Petit NOC / essai        | Production                          |
| ------- | ------------------------ | ----------------------------------- |
| Base    | SQLite ou MySQL          | **PostgreSQL** (jsonb, rollups)     |
| Redis   | optionnel                | recommandé (files, cache wallboard) |
| Workers | `queue:work` + scheduler | daemons Supervisor                  |

SQLite convient aux essais ; Postgres + Redis quand les checks et le wallboard grossissent. Stack prête à l’emploi : **[Docker](../../deploy/docker)**.

## Variables d’environnement (en plus du standard Laravel)

```env
APP_NAME=Phare

# Coffre — généré par l'assistant /setup ou `phare:install` ; à sauvegarder séparément de la base
# VAULT_MASTER_KEY=
CACHE_STORE=redis   # wallboard (cache 10 s)
QUEUE_CONNECTION=redis

# Agents / sondes — tokens par host/probe en BDD ; URL publique :
# PHARE_PUBLIC_URL=https://phare.example.com
```

Premier déploiement : ouvrir l'URL (assistant `/setup`) ou lancer `php artisan phare:install` — voir `docs/user/installation.md`.

## Sauvegardes

Sauvegarder la base **et** `VAULT_MASTER_KEY` séparément (voir `docs/privacy.md`). Ne jamais lancer `migrate:fresh` en production.

## Forge (résumé)

1. Site PHP, PHP 8.3, deploy script : `composer install`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan queue:restart` (aucun Go sur le hub : les agents se téléchargent depuis la **release GitHub officielle** correspondant au fichier `VERSION`).
2. Daemon `queue:work` + scheduler
3. Postgres ou MySQL + Redis (addons Forge)

## Sondes

Installer `phare-agent` en `role=probe` sur un VPS **externe** pour la vue Internet ; ICMP nécessite `CAP_NET_RAW` (voir `reference.md`).

## Mises à jour

Phare ne se met pas à jour tout seul (pas de code modifié à l'insu de l'administrateur), mais il vous prévient et rend la mise à jour triviale.

- **Prévenir** : une fois par jour (`phare:update:check`, via le scheduler), le hub lit la dernière release GitHub (`PHARE_REPOSITORY`, défaut `mchev/phare`). Si elle est plus récente que le fichier `VERSION`, les **administrateurs** voient une bannière avec les notes de version. Désactivable : `PHARE_UPDATE_CHECK=false` (aucun appel sortant).
- **Appliquer** (installation par `git clone`) :

    ```sh
    scripts/update.sh          # dernière release ; ou scripts/update.sh v0.3.0
    ```

    Le script récupère le tag, lance `composer install --no-dev` et `npm run build`, puis `php artisan phare:update`, qui **sauvegarde la base** (copie du fichier SQLite dans `storage/app/backups`, ou demande confirmation pour une autre base), passe en maintenance, applique les migrations, rafraîchit les caches, redémarre les workers et rouvre. Il ne réinitialise jamais les données.

- **Agents** : la ligne d'installation télécharge le binaire sur **GitHub** (`PHARE_OFFICIAL_REPOSITORY`, défaut `mchev/phare`), release `vX.Y.Z` = fichier `VERSION` du hub. Relancer la commande met à jour l'agent. Override réservé aux tests : `PHARE_AGENT_DOWNLOAD_URL`.
- **Publier une release** / mettre à jour hub et agents : voir **[releases.md](../releases.md)** (checklist, tag, CI, `scripts/update.sh`, agents).
