import { t } from '@/lib/i18n';

export const SITE_HEALTH_NAME_SUFFIX = ' — health';

export type SiteMonitorLinkable = {
    id: number;
    name: string;
    target?: string;
    health_check_id?: number | null;
    companion_of?: number | null;
    config?: { health_check_id?: number; companion_of?: number } | null;
};

export type SiteMonitorGroup<T extends SiteMonitorLinkable> = {
    primary: T;
    companion: T | null;
};

export type MonitorListEntry<T extends SiteMonitorLinkable> =
    | { kind: 'single'; check: T }
    | { kind: 'site-group'; group: SiteMonitorGroup<T> };

function healthCheckId(site: SiteMonitorLinkable): number | null {
    return site.health_check_id ?? site.config?.health_check_id ?? null;
}

function companionOf(check: SiteMonitorLinkable): number | null {
    return check.companion_of ?? check.config?.companion_of ?? null;
}

export function isSiteHealthCompanion(check: SiteMonitorLinkable): boolean {
    if (companionOf(check) !== null) {
        return true;
    }

    return (
        check.name.endsWith(SITE_HEALTH_NAME_SUFFIX) &&
        (check.target?.endsWith('/up') ?? false)
    );
}

export function siteHealthCompanionLabel(): string {
    return t('Health (/up)');
}

export function findSiteHealthCompanion<T extends SiteMonitorLinkable>(
    primary: T,
    items: T[],
    byId: Map<number, T>,
): T | null {
    const linkedId = healthCheckId(primary);

    if (linkedId !== null && byId.has(linkedId)) {
        return byId.get(linkedId) ?? null;
    }

    const byConfig = items.find((c) => companionOf(c) === primary.id);

    if (byConfig) {
        return byConfig;
    }

    const expectedName = `${primary.name}${SITE_HEALTH_NAME_SUFFIX}`;

    return (
        items.find(
            (c) => c.name === expectedName && isSiteHealthCompanion(c),
        ) ?? null
    );
}

/**
 * One visual row per site; Laravel /up companions nest under the main site.
 */
export function groupWebsiteMonitors<T extends SiteMonitorLinkable>(
    items: T[],
): SiteMonitorGroup<T>[] {
    const byId = new Map(items.map((item) => [item.id, item]));
    const usedCompanionIds = new Set<number>();
    const groups: SiteMonitorGroup<T>[] = [];

    for (const item of items) {
        if (usedCompanionIds.has(item.id)) {
            continue;
        }

        if (isSiteHealthCompanion(item)) {
            const hasPrimary = items.some(
                (p) =>
                    healthCheckId(p) === item.id ||
                    companionOf(item) === p.id ||
                    item.name === `${p.name}${SITE_HEALTH_NAME_SUFFIX}`,
            );

            if (hasPrimary) {
                continue;
            }
        }

        const companion = findSiteHealthCompanion(item, items, byId);

        if (companion) {
            usedCompanionIds.add(companion.id);
        }

        groups.push({ primary: item, companion });
    }

    return groups;
}

/** Preserve list order; nest health companions under their site when category is website. */
export function orderedWebsiteMonitorEntries<
    T extends SiteMonitorLinkable & { category?: string },
>(items: T[]): MonitorListEntry<T>[] {
    const websiteItems = items.filter((c) => c.category === 'website');
    const groups = groupWebsiteMonitors(websiteItems);
    const companionIds = new Set(
        groups
            .map((g) => g.companion?.id)
            .filter((id): id is number => id !== undefined),
    );
    const groupByPrimary = new Map(groups.map((g) => [g.primary.id, g]));
    const entries: MonitorListEntry<T>[] = [];

    for (const check of items) {
        if (check.category !== 'website') {
            entries.push({ kind: 'single', check });
            continue;
        }

        if (companionIds.has(check.id)) {
            continue;
        }

        const group = groupByPrimary.get(check.id);
        if (group?.companion) {
            entries.push({ kind: 'site-group', group });
        } else {
            entries.push({ kind: 'single', check });
        }
    }

    return entries;
}
