# Installation et premier démarrage

## Prérequis

|                   | Essai / petite équipe        | Production                           |
| ----------------- | ---------------------------- | ------------------------------------ |
| **PHP**           | 8.3+                         | 8.3+                                 |
| **Base**          | **SQLite** (fichier)         | PostgreSQL ou MySQL (recommandé)     |
| **Files / cache** | pilote `database` ou fichier | **Redis** + worker `queue:work`      |
| **Scheduler**     | `schedule:work` ou cron      | indispensable (checks, SSL, rollups) |

PostgreSQL et Redis ne sont **pas** obligatoires pour découvrir Nocline. Piste la plus simple : **[Docker](../../deploy/docker)**.

Composer et Node servent au build des assets (ou utilisez l’image Docker qui les inclut déjà).

## Assistant de premier démarrage

À la première visite, Nocline redirige vers `/setup`. Six étapes (indicateur de progression, validation à chaque étape, retour possible) :

0. **Langue** (en premier, anglais par défaut ; le reste de l'assistant bascule immédiatement). Voir [traductions.md](traductions.md) pour en ajouter.
1. **Base de données** : SQLite (essai, petite instance), MySQL ou PostgreSQL (recommandé en production). Un bouton « Tester la connexion » est proposé ; la connexion est aussi testée avant de passer à l'étape suivante ; les migrations sont appliquées sans jamais effacer de données existantes.
2. **Administrateur** : nom, e-mail, mot de passe (12 caractères minimum, majuscules, minuscules, chiffres, symboles).
3. **Instance** : nom, URL publique du hub (reprise dans les commandes d'enrôlement), fuseau horaire.
4. **E-mail (optionnel, ignorable)** : serveur SMTP utilisé pour les alertes.
5. **Récapitulatif** puis installation.

Une fois terminé, l'assistant est **verrouillé** (`/setup` renvoie 404) et vous êtes redirigé vers la connexion. Les réglages sont écrits dans `.env` ; le marqueur d'installation est `storage/app/nocline-installed` (gardez `storage/` persistant, notamment sous Docker).

## Installation non interactive (Docker, Forge)

```bash
php artisan nocline:install --db=sqlite \
  --admin-name="Admin" --admin-email=admin@example.com --admin-password='ChangeMe-Secret1!' \
  --name="Mon NOC" --url=http://localhost:8080 --locale=fr --timezone=Europe/Paris
```

La commande refuse de s'exécuter si l'instance est déjà installée. `NOCLINE_FORCE_INSTALLED=true` désactive l'assistant (déploiements préprovisionnés).

Il n'y a **pas d'inscription publique** : les comptes sont créés par un administrateur.
