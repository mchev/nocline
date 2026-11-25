# Données personnelles & coffre (self-hosted)

Nocline est **self-hosted** : l’instance que tu déploies fait de toi le **responsable de traitement** au sens RGPD.

## Données traitées

| Donnée                            | Finalité                                       | Durée indicative              |
| --------------------------------- | ---------------------------------------------- | ----------------------------- |
| Comptes utilisateurs (email, nom) | Accès console                                  | Tant que le compte est actif  |
| Journaux d’accès coffre           | Audit sécurité                                 | 12–24 mois (configurable)     |
| Métriques & checks                | Supervision infra                              | Rollups (voir `reference.md`) |
| Secrets coffre                    | Exploitation (accès hébergeurs, back-offices…) | Jusqu’à suppression manuelle  |

## Coffre

- Secrets **chiffrés** en base ; clé maître `VAULT_MASTER_KEY` **hors** dépôt git.
- **Perte de la clé maître = perte irréversible** des secrets.
- Export JSON par **groupe** et droit à l’effacement : à implémenter avec le module Coffre (phase 2).

## Sauvegardes

- Traiter les dumps BDD comme **données sensibles** (chiffrement au repos recommandé).
- Ne pas activer `APP_DEBUG=true` en production ; ne pas logger le corps des requêtes sur les routes coffre / ingest.
