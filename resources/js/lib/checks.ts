import { t } from '@/lib/i18n';

/**
 * Plain-words label of a check type; scheduled tasks are stored as "heartbeat".
 */
export function checkTypeLabel(type: string): string {
    return type === 'heartbeat' ? t('Scheduled task') : type;
}

/**
 * "every 5 minutes", "every day"… for an expected period given in seconds.
 */
export function frequencyLabel(seconds: number): string {
    const units: [number, string, string][] = [
        [86400, 'Every day', 'Every :count days'],
        [3600, 'Every hour', 'Every :count hours'],
        [60, 'Every minute', 'Every :count minutes'],
    ];

    for (const [size, one, many] of units) {
        if (seconds % size === 0) {
            const count = seconds / size;

            return count === 1 ? t(one) : t(many, { count });
        }
    }

    return t('Every :count seconds', { count: seconds });
}
