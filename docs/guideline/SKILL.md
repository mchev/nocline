---
name: nocline
description: >-
    Conçoit et implémente Nocline — plateforme ops self-hosted OSS (uptime, perf,
    alertes, coffre, renewals, wallboard) pour ~50 sites multi-providers. Utiliser
    quand l'utilisateur dit Nocline, remplace Kuma/Netdata, agent nocline, sonde,
    wallboard NOC, métriques, Laravel Inertia Vue, ou coffre secrets.
---

# Nocline — guideline produit & technique

**Nocline** : console + wallboard + agents (métaphore du phare — voir l'état de l'infra de loin).

| Identité            | Valeur                                                                    |
| ------------------- | ------------------------------------------------------------------------- |
| Nom affiché (UI)    | **NOCLINE** (wordmark caps, symbole « champ de veille »)                  |
| Nom produit / légal | Nocline                                                                   |
| Repo OSS visé       | `nocline/nocline` (ou org perso + nom `nocline`)                          |
| Binaires            | `nocline-agent`, `nocline-probe` (même binaire, rôle config)              |
| App Laravel         | namespace / Nocline                                                       |
| Tagline             | _« Self-hosted NOC : checks, métriques, incidents, wallboard. »_          |
| Baseline (FR)       | _Voir l’essentiel. Veiller sur l’invisible._ · _Votre NOC, sans l’équipe_ |

**Source de vérité (git).** Copie Cursor : `.cursor/skills/nocline/SKILL.md` (même contenu).

## Identité visuelle (2026)

| Rôle                            | Token                        | Hex       |
| ------------------------------- | ---------------------------- | --------- |
| **Lien** (accent, CTA, icône)   | `--nocline-lien` / `primary` | `#365CF5` |
| **Réseau** (texte, fond sombre) | `--nocline-reseau`           | `#202536` |
| **Cloud** (fond clair)          | `--nocline-cloud`            | `#F5F6FA` |

- **Symbole / wordmark** : `public/brand/nocline-symbole.svg`, `nocline-wordmark.svg` — `AppLogoIcon`, `AppWordmark`, `AppLogo`.
- **Favicon** : `public/favicon.svg` (fond Lien, symbole blanc).
- **PWA** : `theme_color` Lien, `background_color` Cloud.
- **CSS** : tokens dans `resources/css/app.css` (plus de thème teal « coastal »).
- **Ne pas** réintroduire violet SaaS 2020 ni teal 195° comme primaire sans demande explicite.

Document vivant : l'utilisateur affine par itérations. L'agent suit ces choix par défaut sauf contre-indication explicite.

## Vision

**Une console + un wallboard + des agents**, pas un empilement d'outils.

| Besoin                            | Inspiration               | Notre réponse                                                  |
| --------------------------------- | ------------------------- | -------------------------------------------------------------- |
| Up/down, ping, HTTP, SSL          | Uptime Kuma, Better Stack | Module **Checks** + historique d'états                         |
| CPU/RAM/DISK, graphiques          | Netdata                   | Agent **léger** + rollups côté hub (GPU **hors v1**)           |
| Parc WordPress / MAJ              | ManageWP                  | Module **Sites** (plugins, noyau, backups) — phase 2+          |
| Alerting, incidents, on-call      | Better Stack              | Module **Alertes** (règles, escalade, canaux)                  |
| Ops métier (MDP, renouvellements) | —                         | Module **Coffre** + **Calendrier** (domaines, certs, contrats) |
| Vue d'ensemble TV / bureau        | Uptime Kuma TV            | **Wallboard** read-only, temps réel léger                      |

## Impératifs (non négociables)

1. **Ping & statut up/down** : vérité claire, latence visible, début/fin d'incident.
2. **Performances** : UI rapide, API paginées, wallboard &lt; 2 s au premier paint utile.
3. **Stockage BDD maîtrisé** : pas de série temporelle brute infinie en MySQL/PostgreSQL.
4. **Modulaire** : domaines découplés (packages ou modules Laravel), wallboard et agents consommables sans le reste.
5. **Élégant** : UI sobre, dense, lisible à 3 m (wallboard) et au bureau (console).
6. **Agent** : quasi invisible (CPU/RAM négligeable), **push-only**, installation en une commande, **mTLS** ou clé par serveur.
7. **Accessible à tous** : l'app doit être comprise et utilisable par quelqu'un qui ne connaît rien à l'informatique. Parcours « Surveiller → un site / un serveur / autre → réglages selon le choix », vocabulaire courant (« serveur », « site », pas « host/check/probe » en façade), un seul geste par étape, valeurs par défaut sensées, tout ce qui peut être détecté automatiquement l'est (ex. expiration de domaine/SSL) au lieu d'être saisi, installation serveur = **une seule ligne à coller**. Les options avancées restent disponibles mais jamais sur le chemin principal.

## Stack recommandée (défaut)

| Couche                                          | Choix                                                             | Pourquoi                                                                                              |
| ----------------------------------------------- | ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Hub (UI, API, alertes, coffre, billing interne) | **Laravel 13+**, **Inertia**, **Vue 3**, Tailwind                 | CRUD, files, queues, chiffrement, notifs, écosystème mature ; tu maîtrises déjà le flux.              |
| Temps réel wallboard / console                  | **Laravel Reverb** ou SSE ; WebSockets seulement si besoin prouvé | Moins de complexité qu'un second stack.                                                               |
| Files & queues                                  | **Redis** + Horizon                                               | Checks planifiés, agrégation métriques, alertes.                                                      |
| Agent serveur                                   | **Go** (binaire statique unique)                                  | Faible empreinte, cross-compile, pas de runtime sur les hosts. _Rust_ si besoin ultra-strict mémoire. |
| Checks réseau (ICMP/HTTP/TCP/SSL)               | **Sondes** (modèle Kuma enrichi) — binaire agent en `role=probe`  | Plus qu'un heartbeat : exécution du check, latence, métadonnées, attribution par sonde.               |

**À ne pas faire** : tout le monitoring dans PHP long-running sur les serveurs ; stocker chaque heartbeat 15 s × 50 machines × N métriques en lignes SQL brutes.

Alternatives valides plus tard : hub Laravel inchangé ; métriques chaudes dans **TimescaleDB** ou **VictoriaMetrics** si le volume le justifie — seulement après preuve par les chiffres de rétention (voir [reference.md](reference.md)).

## Architecture logique

```
[Sondes probe] ----pull work / push résultats----+
        ↑ checks (ICMP/HTTP/TCP/SSL/DNS…)         |
[Internet / sites surveillés]                       v
[Agents Go role=host] --HTTPS push/mTLS--------> [Hub Laravel — self-hosted]
                                                    │
                    ┌───────────────────────────────┼───────────────────────────────┐
                    │         PostgreSQL (état, config, incidents, coffre)           │
                    │         Redis (cache état courant, files, rate limits)          │
                    └───────────────────────────────┼───────────────────────────────┘
                                                    │
                    [Wallboard Inertia/Vue]  [Alertes: mail, Slack, …]
```

**Principe agent** : aucun port entrant sur les serveurs ; l'agent sort vers le hub, envoie des **batches** (métriques + heartbeat agent) toutes les 15–60 s (configurable).

## Modèle de données — stockage maîtrisé

### Checks (uptime)

- **État courant** : une ligne par check (`status`, `since`, `last_latency_ms`) — mise à jour en place.
- **Historique** : **événements** `check_state_changed` (up→down, down→up), pas un point toutes les 15 s.
- **Courbe / heatmap 24 h / 30 j** : **rollups pré-calculés** (buckets 1 min → 1 h → 1 j) avec durée **up**/**down** par fenêtre.
- Purge : détail &lt; 48 h si stocké ; rollups 90 j–1 an selon config.

### Métriques (CPU, RAM, disk)

- Agent envoie agrégats **déjà réduits** sur la fenêtre (min/max/avg/p95 pour 15 s ou 60 s).
- Hub persiste rollups **1 min** (rétention ~7 j), **5 min** (~90 j), **1 h** (~2 ans) ; pas de raw &gt; 24 h en SQL.
- Option : séries chaudes en Redis/Timescale pour graphs temps réel uniquement.

### Incidents & alertes

- Incident = agrégat de transitions down non acquittées ; notification idempotente (pas 500 mails pour 500 checks identiques si regroupé).

### Coffre (mots de passe d'équipe)

- **Confiance absolue** : chiffrement **libsodium** (XChaCha20-Poly1305), clé maître **hors BDD** (`VAULT_MASTER_KEY` distinct de `APP_KEY`), enveloppe par secret (DEK).
- Révélation **explicite** (clic + re-auth ou 2FA) ; jamais en clair dans logs, exports, dumps, `APP_DEBUG`.
- Audit **append-only** : view / copy / edit / delete (sans contenu secret).
- Self-hosted RGPD : tu es responsable de traitement ; hébergement et sauvegardes **chez toi** ; doc install pour export / effacement par groupe.

Voir détails chiffrés et estimations volume dans [reference.md](reference.md).

## Modules (boundary Laravel)

| Module        | Responsabilité                                                                                                                  | MVP                     |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------- | ----------------------- |
| **Checks**    | monitors, types (ping, http, tcp, keyword), planification                                                                       | oui                     |
| **Hosts**     | serveurs, agents, versions, groupes, tags                                                                                       | oui                     |
| **Metrics**   | ingest agent, rollups, graphiques                                                                                               | oui (CPU/RAM/DISK)      |
| **Incidents** | timeline, ack, résolution                                                                                                       | oui                     |
| **Alertes**   | politiques, canaux, silence                                                                                                     | oui (mail + 1 autre)    |
| **Wallboard** | vue TV, layout, cible = un groupe (sous-groupes inclus), filtres tag                                                            | oui (phase 1.5)         |
| **Groups**    | dossiers imbriquables (serveurs, sites, autres) ; options d'affichage par groupe : layout liste/grille/compact, couleur, replié | oui                     |
| **Coffre**    | entrées, rotation, liens vers host/site                                                                                         | phase 2                 |
| **Renewals**  | domaines, SSL, contrats, rappels                                                                                                | phase 2                 |
| **WordPress** | MAJ, vulnérabilités, backup status                                                                                              | phase 3 (API/plugin WP) |

Chaque module : `routes`, `policies`, `jobs`, `models` ; pas de dépendances circulaires — **Events** Laravel pour couplage faible.

## Agent serveur (spec résumée)

- Binaire `nocline-agent` : install script `curl | sh` avec token d'enrôlement **à usage unique**.
- Collecte : `/proc`, cgroups, `statvfs` ; **pas** GPU ni eBPF en v1.
- **Rôle probe** (même binaire) : exécute les checks assignés (ICMP si `cap_net_raw`, HTTP, TCP, SSL expiry, DNS…) et renvoie résultats structurés — voir section Sondes.
- Budget : &lt; 0,5 % CPU en continu, &lt; 50 Mo RAM ; intervalle défaut 30 s.
- Sécurité : TLS 1.3, pinning optionnel ; token stocké `0600` ; **auto-update** signé (phase ultérieure).
- Mode dégradé : buffer local disque borné (ex. 10 Mo) si hub injoignable.

## Wallboard

- Mode **kiosque** : pas de chrome inutile, rafraîchissement 15–30 s ou push.
- Panneaux : santé globale, pannes actives, top latences, carte hosts (CPU/RAM), bandeau incidents non ack.
- UX : lisibilité NOC, bandeau _stale_, données via `GET /api/wallboard/{token}`.
- Auth : URL signée longue durée ou Basic Auth / IP allowlist pour écrans.

## UI / UX

- Console : dashboard **dense** type NOC, pas un SaaS marketing.
- Couleurs sémantiques Nocline (ok / down / warn / maint).
- **Test du novice** : avant de livrer un écran, se demander « quelqu'un qui n'y connaît rien sait-il quoi faire ici en 5 secondes ? ». Un état vide propose toujours l'action suivante ; les statuts sont dits en phrases (« Tout fonctionne », « 2 choses demandent votre attention »).
- Mobile : consultation oui ; admin lourd non prioritaire MVP.

## Modèle de déploiement

- **Maintenant** : usage **interne** (ton NOC / tes projets métier).
- **Produit** : **open source, self-hosted** (une instance = une équipe) — **pas** de SaaS multi-tenant managé en v1.
- Dans l'instance : **groupes imbriquables** (`groups` : parent_id, name, color, layout, collapsed, position) + tags pour regrouper hosts, checks, wallboards, coffre et renouvellements (comme des dossiers Kuma ; `group_id` partout), **pas** d'isolation forte type locataire cloud.
- Licence et repo publics quand le code est prêt ; doc « une commande » + Docker Compose optionnel.
- **Pas** d'analytics tiers sur les routes coffre / auth.

## Sondes (checks réseau — modèle Kuma enrichi)

Inspiré d'Uptime Kuma (checks exécutés **depuis** des points de vue réseau), mais **pas** limité à un ping/heartbeat stocké en masse :

1. **Hub** planifie les checks, file d'attente par sonde, agrège les résultats (quorum configurable : _toutes_ / _une_ / _majorité_ down → incident).
2. **Sonde** = agent Go `role=probe`, installée sur **1 hub local** + **1–2 VPS** (autre provider / pays) pour la « vue Internet ».
3. Chaque exécution renvoie : `status`, `latency_ms`, `probe_id`, `checked_at`, **metadata** (code HTTP, erreur TLS, jours SSL restants, body match…).
4. Stockage côté hub : toujours **état courant + events + rollups** (pas un row par tick par sonde sauf fenêtre courte optionnelle debug).
5. **ICMP** : uniquement sur sondes avec `CAP_NET_RAW` (Docker `cap_add: NET_RAW` ou install bare metal) — **pas** d'ICMP depuis workers PHP Laravel.
6. Checks **sans** sonde dédiée : HTTP/TCP/SSL depuis une sonde par défaut « primary » (souvent le VPS le plus proche des sites).

## First setup (premier déploiement)

Assistant d'installation, accessible **uniquement tant que l'instance n'est pas installée** (marqueur `storage/installed` ou ligne en BDD), puis verrouillé (404).

1. **Base de données** : SQLite (essai / petite instance), MySQL, PostgreSQL (recommandé prod) ; test de connexion avant écriture dans `.env` ; migrations ciblées (jamais de `fresh`).
2. **Premier admin** : nom, e-mail, mot de passe (règles prod) ; rôle `admin`.
3. **Instance** : nom, URL publique du hub (utilisée par les commandes d'enrôlement agent), langue par défaut (fr/en), fuseau horaire.
4. **Mail** : SMTP optionnel avec envoi de test (alertes).
5. **Clé coffre** (phase 2) : génération de `VAULT_MASTER_KEY` distincte de `APP_KEY`, avec avertissement « perte = secrets irrécupérables ».
6. Équivalent CLI non interactif (`php artisan nocline:install`) pour Docker / Forge.

Tests : accès verrouillé après install, rejet d'une connexion BDD invalide, création idempotente de l'admin.

## Sécurité

- Utilisateurs : rôles `admin`, `tech`, `viewer` ; politiques Laravel par module.
- API agent/sonde : token par host **ou** par probe, révocable, rate limit.
- Pages statut publiques optionnelles (slug read-only, style Kuma) si besoin.

## Phases de livraison

1. **MVP** : Checks + Hosts + Incidents + Alertes mail + agent métriques minimales + auth + **first setup**.
2. **Wallboard** + **sondes probe** (primary + VPS externe).
3. **Coffre + Renewals** + calendrier.
4. **WordPress** + intégrations provider (API hébergeur si existante).

## Règles pour l'agent Cursor

Quand tu codes ce produit :

1. **Toujours** préférer événements + rollups aux inserts répétitifs.
2. **Toujours** séparer ingest agent (API haute fréquence) et UI (lecture rollups/cache).
3. **Ne pas** ajouter de dépendance lourde (Elastic, full Prometheus stack) sans demande explicite.
4. **Tests** : politique de rétention et transitions d'état des checks en priorité.
5. **Nom de code** : **Nocline** (`nocline-agent`, `nocline:*`) — ne pas réintroduire d'anciens noms de code (Phare, `phare-agent`, etc.).

## Documentation (obligatoire à chaque évolution produit)

Site public : **VitePress** dans `docs/` → GitHub Pages (`npm run docs:dev` / `docs:build`, config `docs/.vitepress/config.ts`).

| Changement                   | Mettre à jour                                                                        |
| ---------------------------- | ------------------------------------------------------------------------------------ |
| Feature visible dans l'UI    | `docs/user/` (FR, où cliquer, rôles, limites)                                        |
| Install / déploiement / env  | `docs/deploy/`, `docs/user/installation.md`, README racine si parcours change        |
| API ou intégrations          | `docs/api.md`, OpenAPI si besoin                                                     |
| Comportement agent / sonde   | `docs/user/servers-and-agent.md`, `docs/user/sondes-wallboard.md`, `agent/README.md` |
| Nouvelle page doc ou section | Entrée dans `docs/.vitepress/config.ts` (sidebar)                                    |

Une PR qui modifie le produit **sans** doc à jour est incomplète. Vérifier `npm run docs:build` si la structure ou les liens changent.

## Checklist avant une feature

- [ ] Impact stockage sur 12 mois estimé ?
- [ ] État courant mis à jour sans grossir l'historique ?
- [ ] Module isolé + events ?
- [ ] Chemin agent = push-only ?
- [ ] Wallboard reste lisible si API lente (stale / cache) ?
- [ ] Compréhensible par un non-informaticien (parcours guidé, vocabulaire simple, rien à saisir si détectable) ?
- [ ] Documentation mise à jour (`docs/user/` + deploy/api/README/sidebar VitePress si pertinent) ?

## Ressources

- Spécification stockage, schémas tables, estimations Go : [reference.md](reference.md)
