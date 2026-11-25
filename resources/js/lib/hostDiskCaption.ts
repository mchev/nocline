import { formatGiB } from '@/lib/formatBytes';

export type HostDiskMetrics = {
    disk_now: number | null;
    disk_used_bytes?: number | null;
    disk_total_bytes?: number | null;
};

/** Wallboard + HostCard: `68% · 5,2 Go / 250 Go` when bytes exist, else used %. */
export function hostDiskCaption(metrics: HostDiskMetrics): string {
    const usedPct = metrics.disk_now;
    const used = metrics.disk_used_bytes ?? null;
    const total = metrics.disk_total_bytes ?? null;

    if (usedPct === null) {
        return '—';
    }

    const head = `${Math.round(usedPct)}%`;

    if (used !== null && total !== null && total > 0) {
        return `${head} · ${formatGiB(used)} / ${formatGiB(total)}`;
    }

    return head;
}
