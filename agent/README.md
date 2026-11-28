# nocline-agent

Binaire unique, deux rôles :

| Rôle    | Comportement                                                          |
| ------- | --------------------------------------------------------------------- |
| `host`  | Push métriques + heartbeat vers le hub (aucun port entrant)           |
| `probe` | Pull travaux de checks, exécute ICMP/HTTP/TCP/SSL/DNS, push résultats |

## Spec complète

Voir [`.cursor/skills/nocline/reference.md`](../.cursor/skills/nocline/reference.md) (structure, config, protocole hub ↔ probe).

## Build (local)

```bash
cd agent
go build -o bin/nocline-agent ./cmd/nocline-agent
```

## Usage (role `host`)

```bash
nocline-agent enroll --hub https://ops.example.com --token <TOKEN_ENROLLEMENT> [--config /etc/nocline-agent/config.yaml]
nocline-agent [-config /etc/nocline-agent/config.yaml]   # boucle de collecte + push
```

`enroll` : `POST /api/v1/enroll` (token à usage unique) → écrit la config en mode `0600` avec le token permanent.

## Config

`/etc/nocline-agent/config.yaml` (YAML plat, parser maison, stdlib uniquement) :

```yaml
hub_url: https://ops.example.com
role: host # host | probe
token: '***'
interval_sec: 30 # défaut 30, min 5
auto_update: true # défaut : applique agent_update du hub (mettre false pour refuser sur la machine)
```

## Mise à jour automatique

Sur chaque push accepté (202), le hub peut renvoyer `agent_update` lorsque le serveur est autorisé dans Nocline (version + URL + SHA256). Par défaut l'agent applique l'offre (`auto_update` absent ou `true`). Vérif SHA256, `.prev`, `systemctl restart nocline-agent`, cooldown **6 h**. Refus : décocher sur la fiche serveur, `NOCLINE_AGENT_AUTO_UPDATE=false` sur le hub, ou `auto_update: false` dans la config agent.

## Fonctionnement

- Collecte Linux (`/proc/stat`, `/proc/meminfo`, `/proc/loadavg`, `statfs /`) toutes les 5 s : `cpu.usage_pct`, `mem.used_bytes`, `mem.used_pct`, `disk.used_pct`, `disk.used_bytes`, `disk.total_bytes`, `load.1m`. Hors Linux : stub (aucune métrique).
- **Inventaire logiciel** (dans `host_meta.stack`, ~1×/h + à l'enrôlement) : exécution de binaires connus en lecture seule (`php`, `node`, `mysql`, …) — pas de shell, pas de connexion aux bases. Désactiver : `stack_probe: false` dans la config.
- **Redémarrage requis** (dans `host_meta`, ~1×/h) : lecture de `/var/run/reboot-required` (Debian/Ubuntu) ou `needs-restarting -r` (RHEL/Alma). Aucune commande d'installation ou de reboot depuis l'agent.
- Chaque `interval_sec`, réduction en `{key,min,max,avg,p95}` puis `POST /api/v1/ingest` (`Bearer`, attend 202). Aucun port entrant.
- Échec : retry exponentiel (1 s → 30 s, 4 essais) ; lots gardés en mémoire (≤ 10 Mo / 120 lots, les plus anciens abandonnés). 401 : log + nouvel essai toutes les 5 min. 4xx (autres) : lot abandonné.

## Rôle `probe`

```bash
nocline-agent enroll --hub https://ops.example.com --token prb_...   # écrit role: probe depuis la réponse du hub
nocline-agent -config /etc/nocline-agent/config.yaml
```

```yaml
hub_url: https://ops.example.com
role: probe
token: '***'
interval_sec: 15 # défaut 15 pour une probe, min 5
capabilities: [http, tcp, dns] # défaut http,tcp,dns ; ajouter icmp pour le ping
```

- Poll `GET /api/v1/probe/work?capabilities=…` (Bearer), exécution concurrente (pool de 8, `timeout_sec` respecté), puis `POST /api/v1/probe/results` (≤ 200 résultats par requête, attend 202, retry exponentiel 1 s → 30 s). 401 : log + nouvel essai toutes les 5 min.
- `http` : GET sans suivi de redirection ; ok si statut == `expected_status` (sinon < 400) et mot-clé présent (lecture ≤ 1 Mio) ; métadonnées `http_status`, `ssl_days_remaining` (https). `tcp` : dial `host:port`. `dns` : `LookupHost`. `icmp` : echo request sur socket raw `ip4:icmp`.
- `icmp` n'est annoncé que s'il est listé dans `capabilities` **et** qu'une socket raw peut être ouverte au démarrage (sinon avertissement et capability retirée). Il faut `CAP_NET_RAW` :
    - binaire : `sudo setcap cap_net_raw+ep /usr/local/bin/nocline-agent`
    - Docker : `cap_add: [NET_RAW]` (`--cap-add NET_RAW`)
- Aucun port entrant ; mémoire bornée (corps HTTP lus ≤ 1 Mio, pool fixe).

## Tests

```bash
go vet ./... && go test ./... && GOOS=linux GOARCH=amd64 go build ./...
```

## État

Rôles `host` et `probe` implémentés (`go vet`, `go test`, build linux/amd64 OK).
