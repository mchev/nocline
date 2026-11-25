import { formatGiB } from '@/lib/formatBytes';

export type HostDiskMetrics = {
    disk_now: number | null;
    disk_used_bytes?: number | null;
    disk_total_bytes?: number | null;
};

export type HostDiskParts = {
    percent: string;
    bytes: string | null;
};

/** Percent on the right; capacity `used / total` beside it (not bold in UI). */
export function hostDiskParts(metrics: HostDiskMetrics): HostDiskParts {
    const usedPct = metrics.disk_now;
    const used = metrics.disk_used_bytes ?? null;
    const total = metrics.disk_total_bytes ?? null;

    if (usedPct === null) {
        return { percent: '—', bytes: null };
    }

    const percent = `${Math.round(usedPct)}%`;

    if (used !== null && total !== null && total > 0) {
        return {
            percent,
            bytes: `${formatGiB(used)} / ${formatGiB(total)}`,
        };
    }

    return { percent, bytes: null };
}

/** Single-line caption (feeds, aria, etc.). */
export function hostDiskCaption(metrics: HostDiskMetrics): string {
    const { percent, bytes } = hostDiskParts(metrics);

    if (bytes !== null) {
        return `${percent} · ${bytes}`;
    }

    return percent;
}
