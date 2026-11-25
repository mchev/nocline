export type StatusMonitor = {
    name: string;
    status: 'operational' | 'down' | 'degraded' | 'maintenance' | 'unknown';
    uptime: number | null;
};

export type StatusNode = {
    name: string;
    color: string | null;
    monitors: StatusMonitor[];
    groups: StatusNode[];
};
