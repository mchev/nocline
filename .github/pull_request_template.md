## What and why

<!-- What does this change, and what problem does it solve? Link the issue: Fixes # -->

## How to check it

<!-- Steps, screenshots for UI changes. -->

## Checklist

- [ ] Tests added or updated (`php artisan test --compact`)
- [ ] `vendor/bin/pint --dirty` and `vendor/bin/phpstan analyse` pass
- [ ] UI strings are English keys (`__()` / `t()`), `php artisan nocline:lang:missing` reviewed
- [ ] No secret, token or password is logged, stored in clear or put in the activity log
- [ ] Docs updated if behaviour changed
