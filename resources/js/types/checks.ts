export type CheckStatus = 'unknown' | 'up' | 'down';

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
    host_online?: boolean | null;
    status: CheckStatus;
    since_at: string | null;
    latency_ms: number | null;
    last_error: string | null;
    in_maintenance: boolean;
    metrics?: CheckMetrics | null;
};
