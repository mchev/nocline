# Roadmap Phare

Alignée sur [`.cursor/skills/phare/SKILL.md`](../.cursor/skills/phare/SKILL.md).

## Phase 1 — MVP

- [x] Auth Fortify + rôles `admin` / `tech` / `viewer`
- [x] Module **Groups** (dossiers imbriquables, options d'affichage par groupe : liste/grille/compact, couleur, replié)
- [x] Module **Hosts** + enrôlement agent
- [x] Module **Checks** + planification
- [x] Module **Incidents** (events up/down, ack)
- [x] Module **Alertes** (mail + 1 canal)
- [x] API ingest métriques (`POST /api/v1/ingest`)
- [x] Agent Go : collecte CPU/RAM/disk minimale (vet/test/build OK)
- [x] **First setup** (assistant au premier déploiement) : choix BDD SQLite/MySQL (PostgreSQL recommandé en prod), test de connexion + migrations, création du premier admin, langue (fr/en), nom d'instance / URL du hub / fuseau horaire, config mail ; verrouillé une fois installé

## Phase 1.5

- [x] **Wallboard** Inertia (TV / kiosque, API token)
- [x] **Sondes** (hub + agent Go validés) `role=probe` + quorum
- [x] API wallboard agrégée + cache Redis

## Phase 2

- [x] **Coffre** (libsodium, audit, re-auth)
- [x] **Journal d'activité** (qui a fait quoi, admin, rétention 365 j) + en-têtes de sécurité + SECURITY/CONTRIBUTING
- [x] **Renewals** automatiques (SSL, domaines via RDAP, rappels), page en lecture seule

## Phase 3

- [ ] **WordPress** (plugin minimal)
- [ ] Intégrations hébergeurs (API)
- [x] **Maintenance planifiée** (fenêtres ponctuelles / quotidiennes / hebdomadaires, portée tout / groupe / sonde : pas d'incident ni d'alerte, badge « Maintenance »)
- [x] **Pages de statut publiques** par client (`/status/{slug}`, marque, disponibilité 90 j, incidents 14 j, aucune donnée technique)

## Transverse

- [ ] Doc utilisateur `docs/user/` tenue à jour à chaque feature (en cours)
- [x] i18n ouverte aux contributeurs (`lang/<code>.json`, EN par défaut, FR ; `phare:lang:missing`)
- [x] Assistant d'installation pas à pas (langue d'abord)
- [x] Organisation des checks : groupe / catégorie / tags, groupement, filtres, actions groupées, édition
- [x] Détection automatique à l'ajout d'un site (Laravel / WordPress, page `/up`, garde SSRF)
- [x] API REST v1 (tokens personnels hachés, `docs/api.md` + OpenAPI), heartbeat / tâches planifiées (dead man's switch), package `phare/laravel` (non publié)
- [x] Pas d'inscription publique ; gestion des utilisateurs par l'admin
- [x] Mises à jour : check des releases GitHub, bannière admin, `scripts/update.sh` + `phare:update` (sauvegarde, migrations), release automatisée (agent Linux)
- [ ] Plus tard : image Docker officielle (non prioritaire), mise à jour de l'agent en un clic
