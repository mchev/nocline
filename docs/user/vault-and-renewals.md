# Vault and renewals

## Vault (`admin` and `tech`)

Store credentials tied to a group (hosting panel, FTP/SSH, admin URLs…).

- **Encryption**: libsodium (XChaCha20-Poly1305). Each entry has its own data key, wrapped by `VAULT_MASTER_KEY` in `.env` — **never** in the database, distinct from `APP_KEY`. The setup wizard generates it.
- **Losing the master key = secrets lost forever.** Back it up separately from database backups. A DB dump alone cannot decrypt entries.
- Create or edit requires your **password** again. **Reveal** requires it each time; secrets hide after 30s. _Copy_ clears the clipboard after 30s.
- List views never send secrets to the browser.
- **Audit** (admin, _Vault → Audit log_): create, view, copy, update, delete with user, time, IP — never content. Append-only; survives entry deletion.
- `viewer` has no access. Only admins delete entries.

Master key rotation is not automated yet.

## Renewals

**Renewals** menu: **fully automatic**, read-only. Add an HTTP check for a site — domain and SSL expiry appear here.

- **Summary**: “N items expire in the next 30 days”. Grouped by urgency: _Expired_, _Within 30 days_, _Later_.
- **Domains**: nightly RDAP lookup per active HTTP check (one row per domain). Unsupported TLDs, IPs, `localhost`, `.test`, `.local` are skipped.
- **SSL**: nightly certificate read for each `https` check.
- **New site**: domain + SSL refresh in the background when the check is created.
- **Refresh now** (admin/tech): re-run all lookups (max 3× per minute) — needs a queue worker.
- Manual renewal rows from older versions remain read-only.
- **Reminders** at 30, 14, 7, and 1 day(s) on all active alert channels (one message per threshold). Past-due items show `EXPIRED`.
