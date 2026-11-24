# Phare — référence technique

## Estimation volume (ordre de grandeur)

Hypothèses : 50 sites, 200 checks, 30 agents, poll check 60 s, métriques agent 30 s × 8 series.

| Donnée                        | Mauvais modèle (tout en rows)     | Modèle cible                                    |
| ----------------------------- | --------------------------------- | ----------------------------------------------- |
| Heartbeat check 60 s          | ~200 × 525600 ≈ **100 M rows/an** | **~quelques milliers** events down/up + rollups |
| Métriques 30 s × 8 × 30 hosts | ~**25 M points/an**               | ~**500k** rows rollup (1 min/5 min/1 h)         |

Objectif BDD métier : **&lt; 5 Go** à 2 ans hors coffre ; métriques rollup **&lt; 2 Go** ; pas de croissance exponentielle.

## Schéma conceptuel (groupes)

```
groups
  id, parent_id? (auto-référence, imbriquable), name, color?, layout (list|grid|compact), collapsed bool, position
```

`group_id` (nullable) remplace l'ancien `client_id` sur hosts, checks, wallboards, vault_entries, renewals. Un wallboard ciblant un groupe inclut ses sous-groupes. L'import Kuma mappe les groupes Kuma vers des groupes Phare imbriqués.

## Schéma conceptuel (checks)

```
checks
  id, host_id?, group_id?, type, target, interval_sec, ...

check_current
  check_id PK, status, since_at, last_run_at, last_latency_ms, last_error

check_events
  id, check_id, from_status, to_status, at, metadata jsonb

check_rollups
  check_id, bucket_start, bucket_size (1m|5m|1h), up_seconds, down_seconds, probe_count

probes
  id, name, role (primary|external|…), last_seen_at, capabilities (icmp|http|tcp|dns|ssl), labels jsonb

check_probe_assignments
  check_id, probe_id, weight   # quelles sondes exécutent ce check

check_results_recent (optionnel, TTL court ~24h)
  check_id, probe_id, status, latency_ms, at, metadata jsonb   # debug / graph latence multi-sonde
```

Reconstruction uptime 24 h : lire rollups 1 m (1440 points max par check) — acceptable en une requête indexée.

## Schéma conceptuel (métriques)

```
metric_definitions
  key (cpu.usage), unit, host_id

metric_rollups
  host_id, metric_key, bucket_start, bucket_size, min, max, avg, p95, sample_count
```

Ingestion API `POST /api/v1/ingest` :

```json
{
    "host_id": "…",
    "window_sec": 30,
    "metrics": [
        {
            "key": "cpu.usage_pct",
            "min": 2.1,
            "max": 41.0,
            "avg": 12.3,
            "p95": 38.0
        },
        { "key": "mem.used_bytes", "avg": 4294967296 }
    ]
}
```

## Schéma conceptuel (coffre) — phase 2

```
vault_entries
  id, group_id?, label, username_enc, secret_enc, url, notes_enc, host_id?
  dek_wrapped bytea   # DEK chiffrée par MEK (jamais DEK en clair persistée)
vault_access_logs
  id, vault_entry_id, user_id, action (view|copy|edit|delete), at, ip, user_agent
```

**Confiance absolue (exigences)** :

- `VAULT_MASTER_KEY` : 32 octets aléatoires, **distinct** de `APP_KEY`, hors dépôt / hors `.env` committé.
- Algorithme : libsodium `crypto_secretbox` / XChaCha20-Poly1305 (ou AEAD équivalent audité).
- Révélation secret : endpoint dédié, policy stricte, **re-auth** ou 2FA (Fortify + confirm password).
- UI : affichage temporaire, copie clipboard avec timeout ; pas de secret dans Inertia props globales ni dans `localStorage`.
- Sauvegardes BDD : traiter comme **données chiffrées** ; doc « perte de MEK = perte irréversible des secrets ».
- Tests : assert qu'aucun `secret`/`password` n'apparaît dans logs (canal dédié sans dump request).
- RGPD self-hosted : registre minimal dans `docs/privacy.md` (finalité, durée, export JSON par `group_id`, droit effacement).

Rotation MEK (v2) : re-wrap des DEK sans exposer les secrets en clair dans un job long — documenter avant implémentation.

## Agent Go — structure v1

```
cmd/phare-agent/
internal/
  collect/   # cpu, mem, disk, load (host role)
  probe/     # icmp, http, tcp, dns, ssl (probe role)
  transport/ # batch POST, retry, backoff
  enroll/    # one-time token
```

Configuration `/etc/phare-agent/config.yaml` :

```yaml
hub_url: https://ops.example.com
role: host # host | probe
token: '***'
interval_sec: 30
# role=host
metrics: [cpu, memory, disk, load]
# role=probe
capabilities: [icmp, http, tcp, ssl, dns]
```

Install sonde avec ICMP (ex. Docker) : `cap_add: [NET_RAW]` ; binaire non-root avec file capabilities `cap_net_raw+ep` si bare metal.

## Checks — types MVP

| Type  | Méthode                                                                        |
| ----- | ------------------------------------------------------------------------------ |
| icmp  | **sonde** avec `NET_RAW` uniquement                                            |
| http  | GET, status, keyword, ssl_expiry_days                                          |
| tcp   | connexion + timeout                                                            |
| dns   | résolution + latence (sonde)                                                   |
| agent | « host agent vu &lt; N min » (complète checks réseau, pas un simple ping vide) |

SSL : extraire `notAfter` ; alimenter module **Renewals** sans stocker chaque scan en historique — seulement dernier + event si &lt; 30 j.

## Alerting

```
alert_policies
  group_id?, rules json (ex: down &gt; 2 min, cpu p95 &gt; 90)

notification_channels
  type (email|slack|webhook), config_enc

alert_deliveries
  incident_id, channel_id, sent_at, dedupe_key
```

**Dedupe** : une clé `incident_id + channel + severity` par fenêtre 15 min.

## Wallboard API (lecture seule)

`GET /api/wallboard/{token}`

Réponse agrégée (une requête) :

- `summary` : counts up/down/warn
- `active_incidents[]`
- `hosts[]` : status, cpu_avg_5m, mem_pct, disk_pct_warn
- `checks_failing[]` : name, since, latency

Cache Redis TTL 10 s ; stale si &gt; 90 s sans refresh réussi.

## Sondes — protocole hub ↔ probe (résumé)

1. Hub : job `DispatchChecks` assigne `(check_id, probe_id)` via queue Redis.
2. Probe : **long poll** ou interval court `GET /api/v1/probe/work` (token probe) → exécute checks → `POST /api/v1/probe/results` (batch).
3. Hub : `IngestProbeResults` met à jour `check_current`, écrit event si transition, merge rollups.
4. **Quorum** (config par check) :
    - `all` : down si toutes sondes down sur la fenêtre ;
    - `any` : down si une sonde voit down (détection rapide) ;
    - `majority` : défaut recommandé pour HTTP ; ICMP souvent `any` sur sonde **external** seule.
5. Déploiement recommandé Phare (usage interne) :
    - **probe-primary** : même réseau que le hub (checks admin / internes) ;
    - **probe-external-eu** : VPS OVH/Hetzner/etc. (vue client réelle) ;
    - option **probe-home** : désactivé par défaut (faux positifs si box down).

Kuma comparé : Kuma exécute tout depuis le conteneur unique ; nous **séparons** host metrics (push) et checks réseau (pull work sur sondes) pour scale et clarté des points de vue.

## Intégrations futures

| Source              | Approche                                               |
| ------------------- | ------------------------------------------------------ |
| WordPress           | plugin minimal : version, plugins, cron last run → API |
| Hébergeurs sans API | checks HTTP + agent seulement                          |
| Providers avec API  | module `Integrations\{Provider}` ; secrets dans coffre |

## PostgreSQL vs MySQL

**Défaut : PostgreSQL** (jsonb pour metadata checks, meilleurs rollups). MySQL acceptable si contrainte hébergeur — éviter json lourd en historique.

## Déploiement hub

- Forge / VPS : PHP 8.3, Postgres, Redis, Supervisor (workers + scheduler).
- Horizon pour checks et rollup jobs.
- Agent release : GitHub Actions → binaires linux amd64/arm64.

## Migration depuis stack actuelle

- Uptime Kuma : import monitors (`phare:import-kuma` ou UI) — voir [migration-kuma.md](../migration-kuma.md).
- Wallboard : URL jeton + `GET /api/wallboard/{token}` (voir module Wallboards).

## Décisions actées (2026-11)

| Sujet          | Décision                                                                                                                      |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Modèle produit | Usage **interne** maintenant ; cible **OSS self-hosted** (pas SaaS multi-tenant managé en v1).                                |
| ICMP / checks  | **Sondes** (agent `role=probe`), modèle Kuma **enrichi** (résultats structurés, multi-sonde, quorum) ; pas d'ICMP depuis PHP. |
| Coffre         | **Confiance maximale** : MEK hors BDD, libsodium, audit, re-auth/2FA à la révélation, pas de secrets dans logs.               |
| GPU            | **Hors périmètre** jusqu'à nouvel ordre.                                                                                      |
| Nom produit    | **Phare** (repo `phare`, binaires `phare-agent`).                                                                             |

## Pistes v2 (non engagées)

- Sondes communautaires « contrib » (style uptime monitors publics) — seulement si OSS mûr.
- HSM / Vault externe pour MEK entreprise.
- GPU via build tag NVIDIA si besoin réapparaît.
