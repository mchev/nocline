# Tâches planifiées, API et jetons

## Surveiller une tâche planifiée (heartbeat)

Pour une sauvegarde, un cron ou le scheduler Laravel : c'est **la tâche qui prévient Nocline**. Si le signal n'arrive pas à temps, Nocline vous alerte.

1. **Ajouter une surveillance → Autre chose → Une tâche planifiée**, donnez un nom et la fréquence attendue (chaque minute, heure, jour…).
2. Nocline affiche une adresse secrète et des exemples prêts à copier (cron, script de sauvegarde, Laravel). La page se met à jour dès le premier signal reçu.
3. Un délai de tolérance (par défaut 10 % de la fréquence, au moins 60 s) évite les fausses alertes.

Dans une application Laravel, le paquet `nocline/laravel` (dossier `packages/nocline-laravel`) ajoute `->pingNoclineOnSuccess('jeton')` et `->pingNoclineOnFailure('jeton')` à vos tâches planifiées ; il n'ralentit jamais l'application si Nocline est injoignable.

## Jetons d'API

**Mon profil → API tokens** : créez un jeton (lecture seule ou lecture/écriture, expiration facultative). Il n'est affiché **qu'une fois** : copiez-le tout de suite. Un jeton a au plus les droits de son propriétaire et se révoque en un clic. Détails, exemples `curl` et schéma OpenAPI : `docs/api.md`.

## Détection automatique d'un site

Lors de l'ajout d'un site, Nocline reconnaît Laravel ou WordPress et propose de surveiller aussi la page de santé `/up`. Voir `surveiller.md`.
