# Checks, incidents, alertes

## Checks

Types : `http` (statut, mot-clé optionnel), `tcp` (`hôte:port`), `agent` (le host a-t-il été vu récemment ?), `icmp` et `dns` (réservés aux sondes, voir plus tard). Les checks http/tcp/agent sont exécutés par le hub chaque minute selon leur intervalle.

Un check passe **down** après N échecs consécutifs (2 par défaut), évitant les fausses alertes ; il repasse **up** au premier succès. Seuls les changements d'état sont historisés.

### Organiser ses checks

Chaque check peut avoir un **groupe**, une **catégorie** (sites web, serveurs, mail, DNS, réseau, autre) et des **tags** libres (ex. `prod`, `wordpress`).

Sur la page Checks :

- **Grouper par** groupe, catégorie, statut, type ou rien ; chaque groupe est repliable et affiche son nombre de checks en panne (les groupes en panne remontent en premier).
- **Filtrer** par groupe, catégorie, statut, ou rechercher par nom / cible / tag (cliquer un tag le filtre). La vue (groupement, tri, filtres) est mémorisée dans le navigateur.
- **Actions groupées** : cocher des checks, puis assigner un groupe / une catégorie / ajouter des tags, mettre en pause, reprendre ou supprimer (admin).
- Chaque check peut être modifié, mis en pause ou supprimé depuis sa ligne.

La page Hosts propose le même groupement par groupe, la recherche, les tags et l'édition.

## Incidents

Une panne ouvre un incident (un seul par check tant qu'il dure) ; le retour à la normale le résout. Un `tech` ou `admin` peut l'**acquitter** depuis le menu _Incidents_.

## Alertes

Menu **Alertes** (admin) : canaux **e-mail (SMTP)**, **Slack**, **Discord**, **Brevo** (API) et **webhook** JSON générique.

### Canaux par groupe

Sur **Groupes → modifier un groupe** : cochez les **canaux d’alerte** à utiliser pour les checks de ce groupe. **Aucune case** = comportement historique (tous les canaux actifs). Utile pour envoyer le Slack « client Acme » uniquement aux incidents de ce dossier.

### Snooze

Sur la fiche d’un check ou depuis **Incidents** : **Snooze 4 h** coupe les notifications (incident toujours visible). À l’expiration, si l’incident est encore ouvert et n’avait pas été notifié, l’envoi repart (`nocline:alerts:release-snoozes` chaque minute).

### Lenteur HTTP

Sur un check **HTTP** (édition) : **Slow response alert (ms)** — après N mesures lentes consécutives (_Slow streak_, défaut = seuil d’échecs), le check passe **down** avec un incident (« Slow response… ») et les alertes habituelles. Chaque canal a un bouton **Envoyer un test** ; les secrets (clés API, URL de webhook) ne sont jamais ré-affichés (laisser vide à l'édition les conserve). Besoin d'un autre système (WhatsApp, Telegram…) ? Un adapter se crée en un fichier : voir [contributing/notification-adapters.md](/contributing/notification-adapters). Chaque canal actif reçoit une notification à l'ouverture et à la résolution, une seule fois par incident (déduplication). Un échec d'envoi n'interrompt pas les autres canaux. La configuration des canaux est chiffrée en base.
