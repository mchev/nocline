# Votre première heure avec Phare

Après l’[installation](installation), suivez ce parcours une fois — il reprend le vocabulaire de l’interface.

## 1. Connexion

Compte administrateur créé dans `/setup`. **Pas d’inscription publique** — voir [comptes et rôles](utilisateurs).

## 2. Ajouter une surveillance

**Add a monitor** (tableau de bord ou menu) :

| Vous avez…       | Choix…                              | Résultat                            |
| ---------------- | ----------------------------------- | ----------------------------------- |
| Un site web      | **A website**                       | HTTP + suivi domaine / SSL          |
| Un serveur Linux | **A server**                        | Ligne d’installation agent à coller |
| Une tâche cron   | **Something else → Scheduled task** | URL secrète de heartbeat            |
| Mail, DNS, port… | **Something else**                  | Type adapté                         |

Détails : [Surveiller](surveiller).

## 3. Activer les alertes

Menu **Alertes** (admin) → au moins un canal (e-mail, Slack…) + **test**. En cas de panne : [incidents](checks-incidents-alertes).

## 4. Options utiles

- **Wallboard** TV → [Sondes et wallboard](sondes-wallboard)
- **Page de statut** client → [Maintenance et statut](maintenance-statut)
- **Uptime Kuma** → [Migration](/fr/migration-kuma)

## Processus en arrière-plan

Checks, SSL, renouvellements et rollups nécessitent un **worker** et le **scheduler** (inclus dans Docker). → [Déploiement hub](../deploy/hub).
