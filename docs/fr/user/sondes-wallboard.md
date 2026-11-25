# Sondes et wallboard

## Sondes

Une sonde est le même binaire `phare-agent` avec `role: probe`. Elle exécute les checks depuis un autre point du réseau (ex. un VPS externe) : http, tcp, dns et icmp (icmp nécessite `CAP_NET_RAW` : `setcap cap_net_raw+ep` ou `cap_add: NET_RAW` sous Docker). Le hub ne fait jamais d'ICMP lui-même.

1. Menu **Sondes** (admin) > nom + rôle (`primary` : même réseau que le hub, `external` : vue Internet) > _Ajouter_.
2. Sur la machine : `phare-agent enroll --hub https://votre-hub --token <jeton prb_…>`, puis lancez `phare-agent`.
3. Dans un check, choisissez **Exécution : sondes** (obligatoire pour icmp/dns) et un **quorum** :
    - _majorité_ (défaut) : down si plus de la moitié des sondes voient une panne ;
    - _une seule_ : down dès qu'une sonde voit une panne (détection rapide) ;
    - _toutes_ : down seulement si toutes les sondes voient une panne.

Seules les sondes actives, ayant répondu récemment, comptent dans le quorum. Chaque sonde ne garde que son dernier résultat par check ; l'historique reste « état + événements ».

## Wallboard

Menu **Wallboards** (admin) : créer un écran (optionnellement limité à un groupe, sous-groupes compris). L'URL `…/wallboard/wb_…` est affichée **une seule fois** à la création (ou après _Nouvelle URL_) — conservez-la ; elle sert de secret. **Nouvelle URL** régénère le jeton si vous l'avez perdu ; l'ancien lien cesse de fonctionner tout de suite. **Révoquer** = supprimer l'écran. Ouvrez l'URL en plein écran sur la TV ou Raspberry : aucune connexion n'est nécessaire (navigateur Chromium récent conseillé pour le kiosk).

Sur une borne tactile, masquez le curseur système (`unclutter -idle 0.01 -root`) et, si besoin, ajustez `--force-device-scale-factor` dans le lanceur Chromium.

- Rafraîchissement toutes les 15 s ; la page lit une API agrégée mise en cache 10 s (`/api/wallboard/<jeton>`). Après un déploiement (nouveau build front ou bump `VERSION`), l'API expose `hub_revision` : si elle change, l'écran se **recharge tout seul** (kiosk inclus). Optionnel : `PHARE_DEPLOY_REVISION` dans `.env` pour forcer la valeur au déploiement.
- Si plus aucune donnée n'arrive pendant 90 s, un bandeau **« Données périmées »** s'affiche et les dernières valeurs restent visibles.
- Contenu : bandeau **TOUT VA BIEN / ATTENTION** (pulsation si problème), **liste des noms en panne** en gros quand il y a une alerte, grille de tuiles **sites** (jusqu’à ~60, favicon inclus) et bande **serveurs** en bas — typo et colonnes adaptées à la taille de l’écran (10″ → TV). Les icônes de sites sont servies via l’URL du wallboard (`/wallboard/<jeton>/favicon/<id>`), pas via la console connectée.

Pour de meilleures performances en production, utilisez Redis comme cache (`CACHE_STORE=redis`).
