# Translations (app and documentation)

## Application UI

Nocline is translated with one JSON file per locale: `lang/<code>.json`.
Keys are **English text** (same convention as Laravel); used in PHP (`__('…')`) and the front end (`t('…')`).

### Add a UI language

1. Copy `lang/fr.json` to `lang/<code>.json` (e.g. `de.json`, `pt_BR.json`).
2. Set `"_language"` to the language name **in that language** (e.g. `"Deutsch"`).
3. Translate values (keep placeholders `:name`, `:count`, …).
4. Check: `php artisan nocline:lang:missing <code>` lists missing strings (`--fix` adds empty keys).
5. Open a pull request. The locale appears in setup and the user menu automatically.

### For developers

- Vue: `import { t } from '@/lib/i18n'` or `$t('…')` in templates.
- PHP: `__('Some text')`.
- Always use English keys; never build keys by concatenation.
- Locale order: user preference → session → `APP_LOCALE`.

## Documentation (VitePress)

The docs site uses [VitePress i18n](https://vitepress.dev/guide/i18n):

| Locale                | Sources                                 | URL                                                    |
| --------------------- | --------------------------------------- | ------------------------------------------------------ |
| **English** (default) | `docs/` root — `user/`, `deploy/`, etc. | `https://mchev.github.io/nocline/user/installation`    |
| **Français**          | `docs/fr/`                              | `https://mchev.github.io/nocline/fr/user/installation` |

Shared pages (`api.md`, `contributing/`) live at the root and are linked from both locales.

### Add or update a page

1. **English**: edit `docs/user/…` or `docs/deploy/…`; add sidebar entries under `locales.root` in `docs/.vitepress/config.ts`.
2. **French**: mirror under `docs/fr/` and update `locales.fr` sidebar.
3. Use absolute paths in links (`/user/installation`, `/fr/user/surveiller`).
4. Run `npm run docs:build` before merging.

### Another documentation language

Add a `locales.xx` block in `config.ts` and a `docs/xx/` tree mirroring `docs/user/`.

Contributors are welcome to extend English coverage or open new locales; partial translation is fine — the index pages explain what is available.
