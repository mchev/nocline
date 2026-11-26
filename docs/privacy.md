# Privacy and vault data (self-hosted)

Nocline is **self-hosted**: the instance you operate makes **you** the data controller (GDPR sense).

## Data processed

| Data                        | Purpose                   | Typical retention                            |
| --------------------------- | ------------------------- | -------------------------------------------- |
| User accounts (email, name) | Console access            | While account is active                      |
| Vault access logs           | Security audit            | 12–24 months (configurable)                  |
| Metrics and checks          | Infrastructure monitoring | Rollups (state + events, not raw heartbeats) |
| Vault secrets               | Operations (panels, SSH…) | Until manual deletion                        |

## Vault

- Secrets **encrypted** at rest; master key `VAULT_MASTER_KEY` **outside** git.
- **Losing the master key = irreversible loss** of vault contents.
- Per-group export and erasure rights: ship with vault module expectations.

## Backups

- Treat database dumps as **sensitive** (encrypt at rest).
- Never run `APP_DEBUG=true` in production; do not log request bodies on vault or ingest routes.
