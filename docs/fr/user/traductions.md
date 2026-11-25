# Traductions (contribuer une langue)

Nocline est traduit via un seul fichier JSON par langue : `lang/<code>.json`.
La clé est le **texte anglais** (même convention que Laravel) ; elle sert au PHP (`__('…')`) et au front (`t('…')`).

## Ajouter une langue

1. Copier `lang/fr.json` vers `lang/<code>.json` (ex. `de.json`, `pt_BR.json`).
2. Renseigner `"_language"` avec le nom de la langue **dans sa propre langue** (ex. `"Deutsch"`).
3. Traduire les valeurs (garder les placeholders `:name`, `:count`… intacts).
4. Vérifier : `php artisan nocline:lang:missing <code>` liste les textes non traduits (`--fix` ajoute les clés manquantes vides).
5. Ouvrir une pull request. La langue apparaît automatiquement dans l'assistant d'installation et le menu utilisateur — aucun code à modifier.

## Pour les développeurs

- Vue : `import { t } from '@/lib/i18n'` puis `t('Some text')` ou `t('Hello :name', { name })` ; dans un template, `$t('…')` est aussi disponible.
- PHP : `__('Some text')`.
- Toujours écrire la clé en anglais ; ne jamais construire une clé par concaténation.
- La langue est choisie par : préférence de l'utilisateur → session (installation / invité) → `APP_LOCALE`.

## Documentation (site VitePress)

Le site public est **bilingue** : **français** (URLs à la racine, ex. `/user/installation`) et **anglais** (`/en/…`), comme l'application. VitePress affiche un sélecteur de langue en haut à droite.

| Langue   | Fichiers sources               | URL (GitHub Pages)                                             |
| -------- | ------------------------------ | -------------------------------------------------------------- |
| Français | `docs/` (hors `docs/en/`)      | [mchev.github.io/nocline/](https://mchev.github.io/nocline/)       |
| English  | `docs/en/` (même arborescence) | [mchev.github.io/nocline/en/](https://mchev.github.io/nocline/en/) |

### Contribuer en anglais (ou autre langue doc)

1. Ajouter ou modifier un fichier sous `docs/en/` (ex. `docs/en/user/surveiller.md`).
2. **Nouvelle section** : entrées dans `docs/.vitepress/config.ts` (`locales.en.themeConfig.sidebar` et, si besoin, la sidebar française).
3. Liens **relatifs à la locale** : dans une page EN, préférer `/en/user/…` ; renvoi vers le français : `/user/…`.
4. Certaines pages techniques restent en anglais à la racine (`api.md`, `security.md`, …) et sont liées depuis les deux sidebars.
5. Vérifier : `npm run docs:build`.

Pour une **troisième langue** plus tard : nouveau bloc `locales` dans `config.ts` + dossier `docs/<code>/` (voir le détail côté anglais dans [user/translations.md](/user/translations)).
