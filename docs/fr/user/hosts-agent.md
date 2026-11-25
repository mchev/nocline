# Hosts et agent

Les **groupes** (voir [Surveiller](surveiller.md#ranger-dans-des-groupes)) permettent de ranger les hosts et checks dans des dossiers.

## Enrôler un host

1. Menu **Hosts** > nom (+ groupe) > _Ajouter_. Un jeton d'enrôlement est affiché **une seule fois** (valable 24 h, usage unique). Le bouton _Jeton_ en génère un nouveau. Le binaire vient de la **release GitHub officielle** (même version que le hub) : le serveur surveillé doit pouvoir joindre `github.com` en HTTPS.
2. Sur le serveur : `nocline-agent enroll --hub https://votre-hub --token <jeton>`. Le jeton permanent est écrit dans `/etc/nocline-agent/config.yaml` (mode 0600).
3. Lancez `nocline-agent` (service systemd recommandé). Le host passe « en ligne » dès le premier envoi ; il est « hors ligne » après 3 minutes sans nouvelle.

_Révoquer_ invalide le jeton permanent (l'agent reçoit 401). Aucun port entrant n'est nécessaire : l'agent envoie vers le hub (HTTPS).

## Supprimer un serveur (admin)

Liste **Hosts** ou fiche serveur → **Supprimer le serveur** (modale). Effets côté hub : enregistrement et jeton API supprimés, historique métriques effacé ; les moniteurs liés restent mais ne sont plus rattachés à ce serveur. L'agent **continue de tourner** sur la machine tant que vous ne l'arrêtez pas : la modale propose des commandes `systemd` pour désinstaller le service, le binaire (`/usr/local/bin/nocline-agent`) et la config (`/etc/nocline-agent/`).

## Mettre à jour l'agent

### Mise à jour automatique (recommandée)

Pour chaque serveur : **Hosts → fiche → section nocline-agent** — case _Mises à jour automatiques_ (**activée par défaut** ; décocher pour forcer une mise à jour manuelle). Le hub n’envoie `agent_update` dans `POST /api/v1/ingest` que si cette option est cochée **et** si `NOCLINE_AGENT_AUTO_UPDATE` n’est pas à `false` dans `.env`.

L’agent accepte ces mises à jour par défaut (opt-out serveur possible : `auto_update: false` dans `/etc/nocline-agent/config.yaml`). Chaque offre inclut version cible, URL GitHub officielle et **SHA256** : téléchargement vérifié, sauvegarde `.prev`, `systemctl restart nocline-agent`, au plus une tentative toutes les 6 h. Si l’auto est désactivée dans Nocline : commande manuelle sur la fiche serveur.

### Mise à jour manuelle

Sur un serveur **déjà enrôlé**, utilisez la commande du badge _mise à jour disponible_ (ou `curl -fsSL https://votre-hub/install/agent-update.sh | sudo sh`) : téléchargement du binaire + `systemctl restart` + `auto_update: true` si absent. **Ne relancez pas** la ligne d'installation complète sur un serveur en prod : elle ré-enrôle, invalide l'ancien jeton pendant que l'ancien processus tourne encore → 401 et host « hors ligne » jusqu'à un restart manuel.

## Métriques

Sur la **fiche serveur**, le graphique **Charge serveur (CPU, RAM, disque)** affiche l’évolution sur **24 h**, **7 jours** ou **30 jours** (rollups 1 min, 5 min ou 1 h selon la période). Utile pour remonter un pic ou une saturation autour d’un incident.

## Alertes de charge (CPU, RAM, disque)

Par défaut, Nocline ouvre un incident (et envoie vos canaux d’alerte) si **CPU, RAM ou disque** dépassent **90 %** pendant **5 minutes** consécutives (moyenne par minute). Réglage par serveur : fiche **Hosts → Alertes de charge** (seuils, durée, charge 1 min optionnelle). Les sparklines utilisent ces seuils pour le code couleur.

Toutes les 30 s l'agent envoie des agrégats réduits (min/max/moyenne/p95) de `cpu.usage_pct`, `mem.used_bytes`, `mem.used_pct`, `disk.used_pct`, `disk.used_bytes`, `disk.total_bytes`, `load.1m`. Le hub ne conserve que des rollups : 1 min pendant 7 jours, 5 min pendant 90 jours, 1 h pendant 2 ans. Compilation de l'agent : voir `agent/README.md`.
