# Hosts et agent

Les **groupes** (voir [Surveiller](surveiller.md#ranger-dans-des-groupes)) permettent de ranger les hosts et checks dans des dossiers.

## Enrôler un host

1. Menu **Hosts** > nom (+ groupe) > _Ajouter_. Un jeton d'enrôlement est affiché **une seule fois** (valable 24 h, usage unique). Le bouton _Jeton_ en génère un nouveau. Le binaire vient de la **release GitHub officielle** (même version que le hub) : le serveur surveillé doit pouvoir joindre `github.com` en HTTPS.
2. Sur le serveur : `phare-agent enroll --hub https://votre-hub --token <jeton>`. Le jeton permanent est écrit dans `/etc/phare-agent/config.yaml` (mode 0600).
3. Lancez `phare-agent` (service systemd recommandé). Le host passe « en ligne » dès le premier envoi ; il est « hors ligne » après 3 minutes sans nouvelle.

_Révoquer_ invalide le jeton permanent (l'agent reçoit 401). Aucun port entrant n'est nécessaire : l'agent envoie vers le hub (HTTPS).

## Mettre à jour l'agent

Sur un serveur **déjà enrôlé**, utilisez la commande du badge _mise à jour disponible_ (ou `curl -fsSL https://votre-hub/install/agent-update.sh | sudo sh`) : téléchargement du binaire + `systemctl restart`. **Ne relancez pas** la ligne d'installation complète sur un serveur en prod : elle ré-enrôle, invalide l'ancien jeton pendant que l'ancien processus tourne encore → 401 et host « hors ligne » jusqu'à un restart manuel.

## Métriques

Toutes les 30 s l'agent envoie des agrégats réduits (min/max/moyenne/p95) de `cpu.usage_pct`, `mem.used_bytes`, `mem.used_pct`, `disk.used_pct`, `load.1m`. Le hub ne conserve que des rollups : 1 min pendant 7 jours, 5 min pendant 90 jours, 1 h pendant 2 ans. Compilation de l'agent : voir `agent/README.md`.
