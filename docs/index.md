# Documentation Phare

## Produit

- **Guideline (git, source de vérité)** : [guideline/SKILL.md](guideline/SKILL.md)
- **Référence technique** : [guideline/reference.md](guideline/reference.md)
- **Copie Cursor (non versionnée par défaut)** : `/.cursor/skills/phare/`
- **Roadmap** : [roadmap.md](roadmap.md)
- **Documentation utilisateur** : [user/README.md](user/README.md)
- **Écrire un adapter de notification** : [contributing/notification-adapters.md](contributing/notification-adapters.md)
- **API REST v1 (tokens personnels, heartbeat)** : [api.md](api.md) · spec OpenAPI [openapi.yaml](openapi.yaml)
- **Traductions** : [user/traductions.md](user/traductions.md)
- **Maintenance planifiée et pages de statut** : [user/maintenance-statut.md](user/maintenance-statut.md)
- **RGPD self-hosted (coffre)** : [privacy.md](privacy.md)
- **Sécurité (coffre, jetons, durcissement, journal d'activité)** : [security.md](security.md) — signalement : [../SECURITY.md](../SECURITY.md)

## Code

- **Hub Laravel** : racine du repo (`app/`, `routes/`, `resources/js/`)
- **Agent / sonde** : [`../agent/README.md`](../agent/README.md)
- **Modules métier** : [`../app/Modules/README.md`](../app/Modules/README.md)
- **Package client Laravel `phare/laravel`** : [`../packages/phare-laravel/README.md`](../packages/phare-laravel/README.md)

## Legacy

- Wallboard Kuma statique (inspiration UX) : [`../legacy/wallboard/`](../legacy/wallboard/)
- Migration Kuma / wallboard : [migration-kuma.md](migration-kuma.md)

## Déploiement

- [deploy/hub.md](deploy/hub.md) — Forge / VPS, Postgres, Redis, Horizon