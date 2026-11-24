import { index as checksIndex } from '@/routes/checks';

/** Deep-link into Monitoring for a single check (wizard / dashboard rows). */
export function checkMonitorHref(checkId: number): string {
    return checksIndex.url({ query: { check: String(checkId) } });
}

export function checksFilteredHref(query: Record<string, string>): string {
    return checksIndex.url({ query });
}
