# Translations (app and documentation)

## Application UI

Phare is translated with one JSON file per locale: `lang/<code>.json`.
Keys are **English text** (same convention as Laravel); used in PHP (`__('…')`) and the front end (`t('…')`).

### Add a UI language

1. Copy `lang/fr.json` to `lang/<code>.json` (e.g. `de.json`, `pt_BR.json`).
2. Set `"_language"` to the language name **in that language** (e.g. `"Deutsch"`).
3. Translate values (keep placeholders `:name`, `:count`, …).
4. Check: `php artisan phare:lang:missing <code>` lists missing strings (`--fix` adds empty keys).
5. Open a pull request. The locale appears in setup and the user menu automatically.

### For developers

- Vue: `import { t } from '@/lib/i18n'` or `$t('…')` in templates.
- PHP: `__('Some text')`.
- Always use English keys; never build keys by concatenation.
- Locale order: user preference → session → `APP_LOCALE`.

## Documentation (VitePress)

The public docs site supports **French** (default URLs) and **English** (`/en/…`), via [VitePress i18n](https://vitepress.dev/guide/i18n).

| Locale   | Source paths                      | Public URL (GitHub Pages)           |
| -------- | --------------------------------- | ----------------------------------- |
| Français | `docs/` (root), except `docs/en/` | `https://mchev.github.io/phare/`    |
| English  | `docs/en/` mirroring structure    | `https://mchev.github.io/phare/en/` |

### Add or update an English page

1. Add or edit the Markdown file under `docs/en/` (e.g. `docs/en/user/installation.md`).
2. If you create a **new section**, add sidebar entries in `docs/.vitepress/config.ts` under `locales.en.themeConfig.sidebar` (and the French sidebar if the page is French-only at root).
3. Keep links **locale-relative**: in English pages use `/en/user/…`; cross-links to French use absolute paths like `/user/installation`.
4. Pages that are already written in English at the repo root (`api.md`, `security.md`, parts of `guideline/`) are linked from both sidebars without duplication until a localized copy is needed.
5. Run `npm run docs:build` before merging.

### Add another documentation language later

1. Add a new locale block in `docs/.vitepress/config.ts` (e.g. `de: { label: 'Deutsch', link: '/de/', … }`).
2. Create `docs/de/` with the same folder layout as `docs/en/`.
3. Document the locale in this file (or a short `docs/de/user/uebersetzungen.md`).

Contributors are welcome to extend English coverage or open new locales; partial translation is fine — the index pages explain what is available.
