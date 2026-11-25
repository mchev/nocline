# Modules Laravel (domaines Nocline)

Un module = `Models`, `Http/Controllers`, `Jobs`, `Policies`, `routes` (inclus depuis `routes/web.php` ou fichier dédié).

Couplage faible via **Events** Laravel entre modules.

| Module    | Dossier (prévu) | Phase |
| --------- | --------------- | ----- |
| Groups    | `Groups/`       | 1     |
| Hosts     | `Hosts/`        | 1     |
| Checks    | `Checks/`       | 1     |
| Metrics   | `Metrics/`      | 1     |
| Incidents | `Incidents/`    | 1     |
| Alertes   | `Alertes/`      | 1     |
| Wallboard | `Wallboard/`    | 1.5   |
| Coffre    | `Coffre/`       | 2     |
| Renewals  | `Renewals/`     | 2     |
| WordPress | `WordPress/`    | 3     |

Créer chaque dossier au début d’implémentation du module (éviter le code mort vide).
