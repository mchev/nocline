# Phare

Self-hosted NOC : checks (sondes type Kuma enrichies), métriques (agent Go), incidents, alertes, wallboard, coffre & renewals (phases ultérieures).

**Identité UI :** wordmark **phare.** (minuscules, point Signal `#F4512A`), palette Signal / Nuit `#252B24` / Brume `#F7F6F2` — détail dans [`docs/guideline/SKILL.md`](docs/guideline/SKILL.md#identité-visuelle-2026).

**Stack hub :** Laravel 13, Inertia, Vue 3, Fortify, PostgreSQL (prod), Redis + Horizon (à brancher).

**Stack agent :** Go (`agent/`), binaire `phare-agent` — rôles `host` (métriques) et `probe` (checks réseau).

## Documentation

| Fichier                                                      | Contenu                                            |
| ------------------------------------------------------------ | -------------------------------------------------- |
| [`docs/guideline/SKILL.md`](docs/guideline/SKILL.md)         | Guideline produit & technique (**versionnée git**) |
| [`docs/guideline/reference.md`](docs/guideline/reference.md) | Schémas, APIs, stockage, sondes                    |
| [`.cursor/skills/phare/`](.cursor/skills/phare/)             | Même contenu pour Cursor (local)                   |
| [`docs/index.md`](docs/index.md)                             | Index doc + liens                                  |
| [`docs/roadmap.md`](docs/roadmap.md)                         | Phases MVP → v3                                    |
| [`AGENTS.md`](AGENTS.md)                                     | Règles Cursor / Boost + Phare                      |

## Arborescence cible

```
phare/
├── app/Modules/          # domaines Laravel (Checks, Hosts, …)
├── agent/                # phare-agent (Go)
├── docs/
└── resources/js/         # console Inertia
```

## Développement local

```bash
composer run setup   # ou composer install && cp .env.example .env && key:generate && migrate && npm i && npm run build
composer run dev     # serveur, queue, vite
```

## Sécurité

Voir [docs/security.md](docs/security.md) (fonctionnement, durcissement) et [SECURITY.md](SECURITY.md) (signaler une vulnérabilité). Contribuer : [CONTRIBUTING.md](CONTRIBUTING.md).

## Licence

[Elastic License 2.0](LICENSE) : code source ouvert, utilisation, modification et redistribution libres, y compris en entreprise pour ses propres besoins. Il est interdit de proposer Phare à des tiers comme service hébergé ou managé (SaaS), et de contourner la clé de licence éventuelle. Besoin d'un usage hébergé ? Contactez martin@pegase.io.
