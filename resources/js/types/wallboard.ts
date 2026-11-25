import type { UptimeHistoryDot } from '@/types/checks';

export type WallboardSite = {
    id: number;
    name: string;
    target?: string;
    health_check_id?: number;
    companion_of?: number;
    is_health_companion?: boolean;
    status: string;
    latency_ms: number | null;
    in_maintenance: boolean;
    enabled: boolean;
    history?: UptimeHistoryDot[] | null;
    timeline?:
        | {
              s: UptimeHistoryDot;
              at: string;
              error?: string | null;
          }[]
        | null;
    uptime_24h_pct?: number | null;
    favicon_url?: string | null;
    ssl_status?: 'ok' | 'warning' | 'expired' | 'error' | 'unknown' | null;
    ssl_days_remaining?: number | null;
    site_framework?: string | null;
    wordpress_version?: string | null;
};
