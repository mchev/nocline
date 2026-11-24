import { usePage } from '@inertiajs/vue3';

/**
 * Translate an English source string. Keys are the English text itself (same convention
 * as Laravel's JSON translations, so lang/<code>.json serves both PHP `__()` and this).
 * Placeholders use `:name` like Laravel: t('Welcome back, :user', { user }).
 */
export function t(
    key: string,
    params: Record<string, string | number> = {},
): string {
    const translations = (usePage().props.translations ?? {}) as Record<
        string,
        string
    >;
    let text = translations[key] ?? key;

    for (const [name, value] of Object.entries(params)) {
        text = text.replaceAll(`:${name}`, String(value));
    }

    return text;
}
