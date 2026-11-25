# Maintenance planifiée et pages de statut

## Maintenance planifiée : « pas d'alerte pendant une intervention »

Vous allez redémarrer un serveur ou mettre à jour un site ? Annoncez-le à Phare et personne ne recevra de fausse alerte.

1. Ouvrez **Maintenance** dans le menu.
2. Cliquez sur **Planifier une maintenance** (ou sur **Démarrer une maintenance maintenant pour 1 heure** si vous commencez tout de suite).
3. Choisissez **ce qui est concerné** :
    - **Tout** ;
    - **Un groupe** (ses sous-groupes sont inclus) ;
    - **Une sonde** précise.
4. Indiquez le début et la fin (dans le fuseau horaire de votre navigateur).
5. Si l'intervention revient régulièrement (par exemple chaque nuit), choisissez **Chaque jour** ou **Chaque semaine** : elle se répète à la même heure locale. Une maintenance qui se répète doit être plus courte que sa répétition.

Pendant la maintenance :

- les sondes continuent de tourner et d'enregistrer leurs résultats ;
- **aucun incident n'est ouvert et aucune alerte n'est envoyée** ;
- l'état réel reste exact, mais l'écran affiche un badge **Maintenance** au lieu de **En panne** (page Sondes, tableau de bord, wallboard) et ces sondes ne comptent pas comme des pannes ;
- si un incident était déjà ouvert avant le début de la maintenance, il reste tel quel (pas de nouvelle alerte) ; s'il se résout pendant la maintenance, il est clôturé normalement ;
- si une sonde est encore en panne à la fin de la maintenance, l'incident est ouvert dans la minute qui suit (et l'alerte part à ce moment-là).

Les rôles **admin** et **technicien** créent et modifient les maintenances ; les **lecteurs** les consultent.

## Pages de statut publiques

Donnez à chaque client une adresse unique qui montre si ses sites et serveurs fonctionnent, sans connexion, à vos couleurs.

1. Ouvrez **Pages de statut** puis **Nouvelle page de statut**.
2. Remplissez le **titre** ; l'adresse se crée toute seule à partir du titre (vous pouvez la modifier : lettres minuscules, chiffres et tirets).
3. Choisissez **ce qu'il faut montrer** : tout, ou un groupe (avec ses sous-groupes, affichés comme des sections).
4. Ajoutez si vous le souhaitez un message, une **couleur** et un **logo** (PNG, JPG ou WebP, 1 Mo maximum ; le SVG est refusé par sécurité).
5. Copiez l'adresse avec le bouton prévu et envoyez-la à votre client : `https://votre-phare/status/nom-du-client`.

La page affiche, en langage simple : un bandeau global (« Tous les systèmes fonctionnent », « Certains systèmes ont un problème », « Maintenance planifiée en cours »), chaque sonde avec son état (**Opérationnel**, **En panne**, **Maintenance**, **Inconnu**) et sa disponibilité sur 90 jours, les maintenances en cours ou à venir et, si vous le laissez activé, les incidents des 14 derniers jours.

**Rien de technique n'est publié** : ni adresses, ni IP, ni messages d'erreur, ni noms de serveurs. Seul le **nom** de la sonde apparaît : renommez-la dans Phare si vous voulez un nom plus parlant pour votre client. La page n'est pas référencée par les moteurs de recherche et se rafraîchit toutes les 30 secondes environ. Un flux JSON (mêmes données) est disponible sur `/status/nom-du-client/feed`.

Désactivez la case **La page est publique** pour la retirer sans la supprimer. Supprimer un groupe supprime aussi les maintenances et pages de statut qui lui étaient rattachées.

### Installation : logos

Les logos sont stockés sur le disque `public`. Une fois, sur le serveur : `php artisan storage:link`.
