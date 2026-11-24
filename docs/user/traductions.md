# Traductions (contribuer une langue)

Phare est traduit via un seul fichier JSON par langue : `lang/<code>.json`.
La clé est le **texte anglais** (même convention que Laravel) ; elle sert au PHP (`__('…')`) et au front (`t('…')`).

## Ajouter une langue

1. Copier `lang/fr.json` vers `lang/<code>.json` (ex. `de.json`, `pt_BR.json`).
2. Renseigner `"_language"` avec le nom de la langue **dans sa propre langue** (ex. `"Deutsch"`).
3. Traduire les valeurs (garder les placeholders `:name`, `:count`… intacts).
4. Vérifier : `php artisan phare:lang:missing <code>` liste les textes non traduits (`--fix` ajoute les clés manquantes vides).
5. Ouvrir une pull request. La langue apparaît automatiquement dans l'assistant d'installation et le menu utilisateur — aucun code à modifier.

## Pour les développeurs

- Vue : `import { t } from '@/lib/i18n'` puis `t('Some text')` ou `t('Hello :name', { name })` ; dans un template, `$t('…')` est aussi disponible.
- PHP : `__('Some text')`.
- Toujours écrire la clé en anglais ; ne jamais construire une clé par concaténation.
- La langue est choisie par : préférence de l'utilisateur → session (installation / invité) → `APP_LOCALE`.
