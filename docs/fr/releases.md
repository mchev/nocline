# Versions et mises à jour

La version produit est dans le fichier [`VERSION`](https://github.com/mchev/nocline/blob/main/VERSION). Elle alimente la vérification des mises à jour (`nocline:update:check`) et le téléchargement de **`nocline-agent`** (`vX.Y.Z` = contenu de `VERSION`).

Repo officiel des binaires : **`mchev/nocline`** (`NOCLINE_OFFICIAL_REPOSITORY`). Sur un fork, définir `NOCLINE_REPOSITORY` pour les bannières de mise à jour.

## Numérotation

| Élément           | Format                                                   | Exemple    |
| ----------------- | -------------------------------------------------------- | ---------- |
| Fichier `VERSION` | `X.Y.Z`                                                  | `0.1.4`    |
| Tag / release     | `vX.Y.Z`                                                 | `v0.1.4`   |
| Assets agent      | `nocline-agent-linux-amd64`, `nocline-agent-linux-arm64` | sur le tag |

Le tag doit correspondre à `VERSION` (CI).

## Mettre à jour le hub

```bash
cd /chemin/vers/nocline && scripts/update.sh    # ou scripts/update.sh v0.1.4
```

Forge : `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `migrate --force`, `queue:restart`.

`NOCLINE_UPDATE_CHECK=false` désactive la vérification quotidienne sur GitHub.

## Mettre à jour les agents

Relancez la ligne d’installation depuis la fiche serveur après qu’une nouvelle `VERSION` est déployée sur le hub. Accès HTTPS vers `github.com` et le hub requis.

Voir [déploiement hub](deploy/hub), [installation](/fr/user/installation), [serveurs & agent](/fr/user/hosts-agent).
