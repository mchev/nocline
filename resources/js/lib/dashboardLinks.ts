import CheckController from '@/actions/App/Modules/Checks/Http/Controllers/CheckController';
import { index as checksIndex } from '@/routes/checks';

/** Detail page for a monitored target (dashboard, lists). */
export function checkMonitorHref(checkId: number): string {
    return CheckController.show.url(checkId);
}

export function checksFilteredHref(query: Record<string, string>): string {
    return checksIndex.url({ query });
}
