export type CheckStatus = 'unknown' | 'up' | 'down';

export type UptimeHistoryDot = 'up' | 'down' | 'unknown' | 'maintenance';

export type CheckMetrics = {
    cpu: (number | null)[];
    mem: (number | null)[];
    disk: (number | null)[];
    cpu_now: number | null;
    mem_now: number | null;
    disk_now: number | null;
};

export type Check = {
    id: number;
    name: string;
    type: string;
    category: string;
    tags: string[];
    target: string;
    interval_sec: number;
    timeout_sec: number;
    failure_threshold: number;
    execution: string;
    quorum: string;
    enabled: boolean;
    group_id: number | null;
    host_id: number | null;
    host_name?: string | null;
    host_online?: boolean | null;
    status: CheckStatus;
    since_at: string | null;
    latency_ms: number | null;
    last_error: string | null;
    in_maintenance: boolean;
    metrics?: CheckMetrics | null;
    history?: UptimeHistoryDot[];
    timeline?: {
        s: UptimeHistoryDot;
        at: string;
        error?: string | null;
    }[];
    uptime_24h_pct?: number | null;
    favicon_url?: string | null;
    ssl_status?: 'ok' | 'warning' | 'expired' | 'error' | 'unknown' | null;
    ssl_days_remaining?: number | null;
    site_framework?: string | null;
    wordpress_version?: string | null;
    health_check_id?: number;
    companion_of?: number;
    is_health_companion?: boolean;
};
