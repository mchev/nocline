export type DashboardSection = 'sites' | 'servers' | 'heartbeats' | 'renewals';

export type DashboardLayoutPrefs = Record<DashboardSection, boolean>;

const STORAGE_KEY = 'phare.dashboard.layout';

export const defaultDashboardLayout = (): DashboardLayoutPrefs => ({
    sites: true,
    servers: true,
    heartbeats: true,
    renewals: true,
});

export function loadDashboardLayout(): DashboardLayoutPrefs {
    const base = defaultDashboardLayout();

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return base;
        }

        const parsed = JSON.parse(raw) as Partial<DashboardLayoutPrefs>;

        if (!parsed || typeof parsed !== 'object') {
            return base;
        }

        return {
            sites: parsed.sites !== false,
            servers: parsed.servers !== false,
            heartbeats: parsed.heartbeats !== false,
            renewals: parsed.renewals !== false,
        };
    } catch {
        return base;
    }
}

export function saveDashboardLayout(prefs: DashboardLayoutPrefs): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
}
