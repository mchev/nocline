# Surveiller quelque chose

Bouton **Add a monitor** (haut du dashboard et de la barre latérale). Trois choix :

1. **Un site** — saisissez l'adresse (`example.com` suffit). Nocline vérifie qu'il répond, et suit automatiquement l'expiration du domaine et du certificat SSL (page Renewals, aucune saisie).
2. **Un serveur** — donnez-lui un nom. Nocline affiche **une seule ligne** à coller dans un terminal du serveur :

    ```sh
    curl -fsSL https://votre-hub/install/<jeton> | sudo sh
    ```

    Le script installe l'agent, l'enregistre et le démarre (systemd). La page se met à jour seule dès que le serveur est connecté. Le lien est à usage unique et expire après 24 h (« Generate a new command » pour en obtenir un autre).

3. **Autre chose** — **stockage objet (S3)** (OVH, AWS, Scaleway…), mail, port/application, ping, DNS, tâche planifiée.

### Stockage objet (S3)

**Autre chose** → **Object storage (S3)** :

| Mode                               | Effet                                                                      |
| ---------------------------------- | -------------------------------------------------------------------------- |
| **Le service répond**              | TCP 443 sur l’endpoint S3 du provider (ex. OVH `s3.gra.io.cloud.ovh.net`). |
| **Fichier public dans mon bucket** | HTTP vers `health.txt` (ou autre) en lecture publique.                     |
| **Ma sauvegarde tourne encore**    | Heartbeat après un upload S3 réussi (`aws` / `rclone`).                    |

Providers : OVH, **Hetzner**, **Laravel Cloud** (endpoint R2 depuis le bucket), AWS, Scaleway, Wasabi, MinIO / autre. Pas de clés S3 dans Nocline (phase 1).

Pour chaque choix vous pouvez, en option, ranger l'élément dans un groupe (existant ou créé à la volée).

## Détection automatique

Quand vous saisissez l'adresse d'un site, Nocline l'examine pour vous (quelques requêtes GET, 5 s maximum chacune) et propose une surveillance adaptée, sans jargon :

- il vérifie que le site répond et suit les redirections vers le même site (par exemple `http` vers `https`) ;
- il reconnaît un site **Laravel** ou **WordPress** (étiquette sur le check) ; pour WordPress il tente de lire la **version du noyau** (balise generator, URLs d’assets, ou flux RSS) et l’affiche dans les listes et le dashboard ;
- pour Laravel, si la page de santé `/up` existe réellement, il propose (case cochée) de la surveiller aussi : cela crée un second check « nom du site — health », utile quand la page d'accueil est servie par un cache alors que l'application est en panne ;
- il rappelle que l'expiration du certificat SSL est déjà suivie automatiquement.

La détection est facultative : si elle échoue, vous pouvez ajouter le site normalement.

L’**icône du site** (favicon) est récupérée automatiquement à l’ajout (puis vérifiée chaque semaine). Si elle change, le tag `favicon-changed` apparaît sur le check — utile pour repérer un rebranding ou une page remplacée.

**Sécurité.** Comme c'est le hub qui fait ces requêtes, il refuse les adresses privées (réseau local, `localhost`, adresses de métadonnées cloud, IPv6 locales, adresses écrites en décimal ou hexadécimal), vérifie l'adresse IP de chaque étape et n'accepte que les ports 80, 443, 8080 et 8443. Pour détecter un site hébergé sur votre propre réseau (`*.test`, `192.168.x.x`), définissez `NOCLINE_DETECT_ALLOW_PRIVATE=true` dans `.env` : à n'activer que si tous les utilisateurs qui peuvent ajouter des sites sont de confiance.

## Ranger dans des groupes

Un **groupe** est un dossier qui regroupe serveurs, sites et autres éléments (par exemple un projet, une équipe ou un hébergeur). Les groupes peuvent contenir des **sous-groupes**, autant de niveaux que nécessaire.

Chaque groupe a ses propres options d'affichage :

- **Présentation** : _liste_ (une ligne par élément), _grille_ (cartes) ou _compact_ (pastilles, pour de nombreux éléments) ;
- **Couleur**, pour le repérer d'un coup d'œil ;
- **Replié** : le groupe est fermé par défaut et n'affiche que son état global.

Un wallboard peut afficher un seul groupe : ses sous-groupes sont inclus. Importer depuis Uptime Kuma recrée les groupes Kuma sous forme de groupes imbriqués. Supprimer un groupe ne supprime pas son contenu : les sous-groupes remontent d'un niveau et les éléments sont simplement détachés.

## Agent : releases GitHub officielles

Les serveurs surveillés téléchargent `nocline-agent` depuis la release **officielle** (`NOCLINE_OFFICIAL_REPOSITORY`, défaut `mchev/nocline`), tag `vX.Y.Z` = fichier `VERSION` du hub. Aucun Go sur le hub en production.

Chaque release GitHub taguée `vX.Y.Z` (même numéro que le fichier `VERSION` du hub) inclut les binaires `nocline-agent` pour Linux amd64 et arm64. `APP_URL` reste l'adresse du hub pour l'enrôlement uniquement.
