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

Menu **Wallboards** (admin) : créer un écran (optionnellement limité à un groupe, sous-groupes compris). L'URL `…/wallboard/wb_…` est affichée **une seule fois** à la création (ou après _Nouvelle URL_) — conservez-la ; elle sert de secret. **Nouvelle URL** régénère le jeton si vous l'avez perdu ; l'ancien lien cesse de fonctionner tout de suite. **Révoquer** = supprimer l'écran. Ouvrez l'URL en plein écran sur la TV : aucune connexion n'est nécessaire. Sur une vieille Raspberry (Chromium < 111), ajoutez **`/compat`** à la fin de l'URL : CSS en hexadécimal, sans Tailwind ni oklch.

- Rafraîchissement toutes les 15 s ; la page lit une API agrégée mise en cache 10 s (`/api/wallboard/<jeton>`).
- Si plus aucune donnée n'arrive pendant 90 s, un bandeau **« Données périmées »** s'affiche et les dernières valeurs restent visibles.
- Contenu : état global, compteurs (OK / en panne / inconnus / hosts en ligne), incidents non résolus, **sites web** (pastille, favicon, historique 24 min, latence), pannes en cours, CPU/RAM/disque des serveurs (moyenne 5 min).

Pour de meilleures performances en production, utilisez Redis comme cache (`CACHE_STORE=redis`).
