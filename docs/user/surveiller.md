# Surveiller quelque chose

Bouton **Add a monitor** (haut du dashboard et de la barre latérale). Trois choix :

1. **Un site** — saisissez l'adresse (`example.com` suffit). Phare vérifie qu'il répond, et suit automatiquement l'expiration du domaine et du certificat SSL (page Renewals, aucune saisie).
2. **Un serveur** — donnez-lui un nom. Phare affiche **une seule ligne** à coller dans un terminal du serveur :

    ```sh
    curl -fsSL https://votre-hub/install/<jeton> | sudo sh
    ```

    Le script installe l'agent, l'enregistre et le démarre (systemd). La page se met à jour seule dès que le serveur est connecté. Le lien est à usage unique et expire après 24 h (« Generate a new command » pour en obtenir un autre).

3. **Autre chose** — serveur mail, port/application, machine (ping), nom de domaine (DNS).

Pour chaque choix vous pouvez, en option, ranger l'élément dans un groupe (existant ou créé à la volée).

## Détection automatique

Quand vous saisissez l'adresse d'un site, Phare l'examine pour vous (quelques requêtes GET, 5 s maximum chacune) et propose une surveillance adaptée, sans jargon :

- il vérifie que le site répond et suit les redirections vers le même site (par exemple `http` vers `https`) ;
- il reconnaît un site **Laravel** ou **WordPress** (et l'indique par une étiquette sur le check) ;
- pour Laravel, si la page de santé `/up` existe réellement, il propose (case cochée) de la surveiller aussi : cela crée un second check « <nom> — health », utile quand la page d'accueil est servie par un cache alors que l'application est en panne ;
- il rappelle que l'expiration du certificat SSL est déjà suivie automatiquement.

La détection est facultative : si elle échoue, vous pouvez ajouter le site normalement.

**Sécurité.** Comme c'est le hub qui fait ces requêtes, il refuse les adresses privées (réseau local, `localhost`, adresses de métadonnées cloud, IPv6 locales, adresses écrites en décimal ou hexadécimal), vérifie l'adresse IP de chaque étape et n'accepte que les ports 80, 443, 8080 et 8443. Pour détecter un site hébergé sur votre propre réseau (`*.test`, `192.168.x.x`), définissez `PHARE_DETECT_ALLOW_PRIVATE=true` dans `.env` : à n'activer que si tous les utilisateurs qui peuvent ajouter des sites sont de confiance.

## Ranger dans des groupes

Un **groupe** est un dossier qui regroupe serveurs, sites et autres éléments (par exemple un projet, une équipe ou un hébergeur). Les groupes peuvent contenir des **sous-groupes**, autant de niveaux que nécessaire.

Chaque groupe a ses propres options d'affichage :

- **Présentation** : _liste_ (une ligne par élément), _grille_ (cartes) ou _compact_ (pastilles, pour de nombreux éléments) ;
- **Couleur**, pour le repérer d'un coup d'œil ;
- **Replié** : le groupe est fermé par défaut et n'affiche que son état global.

Un wallboard peut afficher un seul groupe : ses sous-groupes sont inclus. Importer depuis Uptime Kuma recrée les groupes Kuma sous forme de groupes imbriqués. Supprimer un groupe ne supprime pas son contenu : les sous-groupes remontent d'un niveau et les éléments sont simplement détachés.

## Côté hub : fournir l'agent aux serveurs

Le script télécharge le binaire depuis le hub. Construisez-le une fois (Go requis) :

```sh
scripts/build-agent.sh      # produit storage/app/agent/phare-agent-linux-{amd64,arm64}
```

Ou, sans Go sur le hub, définissez `PHARE_AGENT_DOWNLOAD_URL` (ex. `https://…/phare-agent-linux-{arch}`). `APP_URL` doit être l'adresse publique du hub, sinon les serveurs ne pourront pas l'atteindre.
