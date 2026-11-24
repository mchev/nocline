# Déploiement hub Phare

## Requis (production)

- PHP 8.3+
- **PostgreSQL** (recommandé — jsonb, rollups)
- **Redis** (queues, cache wallboard, rate limits)
- Supervisor : `php artisan queue:work --tries=3` (alertes) + scheduler (`php artisan schedule:work` ou cron `* * * * * php artisan schedule:run`) — **indispensable** : exécute les checks, rollups métriques, rappels de renouvellement, scan SSL
- Horizon : optionnel, non installé par défaut (dépendance à valider)

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

1. Site PHP, PHP 8.3, deploy script : `composer install`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan queue:restart`
2. Daemon `queue:work` + scheduler
3. Base Postgres + Redis (addon ou serveur dédié)

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
- **Agents** : la page Serveurs signale un agent plus ancien que le hub ; relancer la ligne d'installation le met à jour (elle télécharge le binaire de la release : `PHARE_AGENT_DOWNLOAD_URL` pointe par défaut vers les binaires publiés sur GitHub, sans Go sur le hub).
- **Publier une release** (mainteneur) : incrémenter `VERSION`, `git tag vX.Y.Z && git push --tags` ; l'action `release.yml` compile l'agent Linux (amd64/arm64) et crée la release GitHub.
