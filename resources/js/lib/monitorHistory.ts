import type { UptimeHistoryDot } from '@/types/checks';

/** Keep in sync with {@see HistoryWindow::dashboard} (24 dots × 1 min). */
export const MONITOR_HISTORY_SLOTS = 24;
export const MONITOR_HISTORY_MINUTES = 24;

export type MonitorHistorySource = {
    history?: UptimeHistoryDot[] | null;
    timeline?:
        | {
              s: UptimeHistoryDot;
              at: string;
              error?: string | null;
          }[]
        | null;
    uptime_24h_pct?: number | null;
};

export function monitorUptimeHistoryProps(
    source: MonitorHistorySource,
    variant: 'board' | 'wallboard' = 'board',
) {
    return {
        history: source.history ?? [],
        timeline: source.timeline ?? [],
        uptimePct: source.uptime_24h_pct ?? null,
        hours: MONITOR_HISTORY_MINUTES / 60,
        windowMinutes: MONITOR_HISTORY_MINUTES,
        fixedSlots: MONITOR_HISTORY_SLOTS,
        variant,
        showPercent: true,
    };
}
