# Security policy

Phare stores credentials (vault), talks to your servers (agents) and sends alerts: we take security reports seriously.

## Supported versions

Phare is young. Only the **latest release** receives security fixes. Please update (`scripts/update.sh`) before reporting, if you can.

| Version | Supported |
|---------|-----------|
| Latest release | Yes |
| Older releases | No |

## Reporting a vulnerability

**Please do not open a public issue.**

1. Preferred: use GitHub's private reporting: **Security → Report a vulnerability** on the repository page.
2. Or write to **martin@pegase.io**.

Please include what you found, how to reproduce it, the Phare version, and the impact you think it has. Do not include real production secrets.

## What to expect

| Step | Target |
|------|--------|
| Acknowledgement | within 3 working days |
| First assessment (accepted / need more info / not a vulnerability) | within 10 working days |
| Fix or mitigation for confirmed issues | depends on severity; critical issues first |
| Public disclosure | after a fix is released, coordinated with you; credit if you wish |

These are targets from a small volunteer team, not guarantees.

## Scope

In scope: the hub (Laravel app), the `phare-agent` binary, the install script served by the hub, the public endpoints (agent enrollment and ingest, probe API, wallboards, status pages, heartbeats) and the vault.

Out of scope: vulnerabilities in your own infrastructure or third-party services Phare talks to, missing hardening of a deployment that ignores `docs/security.md`, denial of service by volume, social engineering, and reports from automated scanners without a demonstrated impact.

## Safe harbour

If you act in good faith, avoid privacy violations, data destruction and service disruption, only test against your own instance, and give us reasonable time to fix before disclosing, we will not pursue or support legal action against you.

## What the project does for security

- Passwords are hashed (bcrypt); agent, probe, wallboard and enrollment tokens are stored as SHA-256 hashes and shown once.
- Vault secrets are encrypted with XChaCha20-Poly1305 (libsodium), one key per entry wrapped by `VAULT_MASTER_KEY`; every reveal needs the current password and is logged.
- Notification channel settings are encrypted at rest with `APP_KEY`.
- Two-factor authentication (TOTP) and passkeys are available; sensitive actions are throttled and audited.
- No telemetry. The only outgoing call of the hub itself is the daily GitHub release check, which can be disabled (`PHARE_UPDATE_CHECK=false`).

Details: [docs/security.md](docs/security.md).
