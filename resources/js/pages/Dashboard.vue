<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BellRing,
    CheckCircle2,
    Globe,
    HeartPulse,
    Hourglass,
    Server,
    ShieldAlert,
    TriangleAlert,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import MonitorIdentityBadges from '@/components/MonitorIdentityBadges.vue';
import HostController from '@/actions/App/Modules/Hosts/Http/Controllers/HostController';
import HostCard, { type HostMetrics } from '@/components/HostCard.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import UptimeHistoryDots from '@/components/ui-kit/UptimeHistoryDots.vue';
import type { UptimeHistoryDot } from '@/types/checks';
import { useListFit } from '@/composables/useFitCapacity';

import {
    MONITOR_HISTORY_MINUTES,
    MONITOR_HISTORY_SLOTS,
    monitorUptimeHistoryProps,
} from '@/lib/monitorHistory';
import { checkMonitorHref, checksFilteredHref } from '@/lib/dashboardLinks';
import { t } from '@/lib/i18n';
import { index as channelsIndex } from '@/routes/alertes/channels';
import { index as checksIndex } from '@/routes/checks';
import { index as hostsIndex } from '@/routes/hosts';
import { index as incidentsIndex } from '@/routes/incidents';
import { index as renewalsIndex } from '@/routes/renewals';

type Overview = {
    sites: {
        id: number;
        name: string;
        target: string;
        status: string;
        latency_ms: number | null;
        in_maintenance: boolean;
        enabled: boolean;
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
    }[];
    servers: {
        id: number;
        name: string;
        online: boolean;
        last_seen_at: string | null;
        agent_version: string | null;
        agent_outdated?: boolean;
        metrics: HostMetrics;
        public_ipv4?: string | null;
        provider_label?: string | null;
    }[];
    heartbeats: {
        id: number;
        name: string;
        status: string;
        since_at: string | null;
        in_maintenance: boolean;
        enabled: boolean;
        grace_sec: number;
        history?: UptimeHistoryDot[];
        timeline?: {
            s: UptimeHistoryDot;
            at: string;
            error?: string | null;
        }[];
        uptime_24h_pct?: number | null;
    }[];
    renewals: {
        id: number;
        name: string;
        kind: string;
        expires_on: string | null;
    }[];
};

const props = defineProps<{
    monitors_total: number;
    channels_count: number;
    problems: {
        id: number;
        name: string;
        group: string | null;
        since_at: string | null;
        error: string | null;
    }[];
    maintenance: { id: number; name: string; group: string | null }[];
    checks: { up: number; down: number; unknown: number; maintenance: number };
    incidents_open: number;
    incidents_unacknowledged: number;
    hosts_online: number;
    hosts_total: number;
    probes_online: number;
    renewals_soon: number;
    overview: Overview;
}>();

const page = usePage();
const canManageHosts = computed(
    () =>
        (page.props.auth as { user: { role: string } }).user.role !== 'viewer',
);

const addUrl = (kind: string) => `/monitor/new?kind=${kind}`;
const since = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString() : '';

const relativeAgo = (iso: string | null) => {
    if (!iso) {
        return t('never');
    }

    const sec = Math.max(
        0,
        Math.round((Date.now() - new Date(iso).getTime()) / 1000),
    );

    if (sec < 60) {
        return t(':n s ago', { n: sec });
    }

    if (sec < 3600) {
        return t(':n min ago', { n: Math.floor(sec / 60) });
    }

    return t(':n h ago', { n: Math.floor(sec / 3600) });
};

const problemCount = computed(() => props.problems.length || props.checks.down);
const isWaiting = computed(
    () =>
        props.problems.length === 0 &&
        props.checks.down === 0 &&
        props.checks.up === 0,
);
const isHealthy = computed(
    () => props.problems.length === 0 && props.checks.down === 0,
);

type DotStatus = 'unknown' | 'up' | 'down' | 'maintenance' | 'warning';

const asDot = (
    status: string,
    enabled: boolean,
    inMaintenance: boolean,
): DotStatus => {
    if (!enabled) {
        return 'unknown';
    }

    if (inMaintenance) {
        return 'maintenance';
    }

    if (
        status === 'up' ||
        status === 'down' ||
        status === 'unknown' ||
        status === 'maintenance' ||
        status === 'warning'
    ) {
        return status;
    }

    return 'unknown';
};

const { el: sitesEl, count: sitesCount } = useListFit({
    itemHeight: 52,
    max: 40,
});
const { el: heartbeatsEl, count: heartbeatsCount } = useListFit({
    itemHeight: 44,
    max: 40,
});
const { el: renewalsEl, count: renewalsCount } = useListFit({
    itemHeight: 40,
    max: 20,
});
const { el: serversEl, count: serverRows } = useListFit({
    itemHeight: 148,
    gap: 12,
    max: 8,
    min: 1,
});

const hasSites = computed(() => props.overview.sites.length > 0);
const hasHeartbeats = computed(() => props.overview.heartbeats.length > 0);
const hasRenewals = computed(() => props.overview.renewals.length > 0);
const hasServers = computed(() => props.overview.servers.length > 0);

const visibleSites = computed(() =>
    props.overview.sites.slice(0, sitesCount.value),
);
const visibleHeartbeats = computed(() =>
    props.overview.heartbeats.slice(0, heartbeatsCount.value),
);
const visibleRenewals = computed(() =>
    props.overview.renewals.slice(0, renewalsCount.value),
);
const serverSlots = computed(() => Math.max(1, serverRows.value) * 2);

const visibleServers = computed(() =>
    props.overview.servers.slice(0, serverSlots.value),
);

const serverGridCols = computed(() => {
    const n = visibleServers.value.length;

    if (n <= 1) {
        return 1;
    }

    return 2;
});

const hiddenSites = computed(() =>
    Math.max(0, props.overview.sites.length - visibleSites.value.length),
);
const hiddenHeartbeats = computed(() =>
    Math.max(
        0,
        props.overview.heartbeats.length - visibleHeartbeats.value.length,
    ),
);
const hiddenRenewals = computed(() =>
    Math.max(0, props.overview.renewals.length - visibleRenewals.value.length),
);
const hiddenServers = computed(() =>
    Math.max(0, props.overview.servers.length - visibleServers.value.length),
);
const primaryProblem = computed(() => props.problems[0] ?? null);

const problemBannerHref = computed(() =>
    primaryProblem.value
        ? checkMonitorHref(primaryProblem.value.id)
        : checksFilteredHref({ status: 'down' }),
);

const healthLine = computed(() => {
    if (isWaiting.value) {
        return t('Waiting for the first results');
    }

    if (isHealthy.value) {
        return t('Everything is running smoothly');
    }

    if (props.problems.length > 0) {
        return t(':count open issue(s)', { count: props.problems.length });
    }

    return t(':count thing(s) need your attention', {
        count: problemCount.value,
    });
});

const historyPanelHint = computed(() =>
    t('Last :minutes minutes', { minutes: MONITOR_HISTORY_MINUTES }),
);

const renewalsInStrip = computed(
    () => hasRenewals.value && props.overview.renewals.length <= 4,
);

const hasRenewalsPanel = computed(
    () => hasRenewals.value && !renewalsInStrip.value,
);

const visiblePanelCount = computed(
    () =>
        [
            hasSites.value,
            hasHeartbeats.value,
            hasRenewalsPanel.value,
            hasServers.value,
        ].filter(Boolean).length,
);

const boardBodyClass = computed(() => ({
    'board-body--servers-tall': hasServers.value && visiblePanelCount.value > 2,
    'board-body--panels-1': visiblePanelCount.value === 1,
    'board-body--panels-2': visiblePanelCount.value === 2,
    'board-body--three-panels': visiblePanelCount.value === 3,
}));

function rowProblemClass(
    status: string,
    enabled: boolean,
    inMaintenance: boolean,
): string {
    if (!enabled || inMaintenance) {
        return '';
    }

    if (status === 'down') {
        return 'board-row--down';
    }

    if (status === 'unknown') {
        return 'board-row--unknown';
    }

    return '';
}

function latencyClass(ms: number | null): string {
    if (ms === null) {
        return '';
    }

    if (ms > 1500) {
        return 'text-warning font-semibold';
    }

    return 'text-muted-foreground font-normal';
}

const healthTone = computed(() => {
    if (isWaiting.value) {
        return 'text-muted-foreground';
    }

    if (isHealthy.value) {
        return 'text-success';
    }

    return 'text-danger';
});

const starts = [
    {
        kind: 'website',
        icon: Globe,
        title: 'A website',
        text: 'Warns you if it goes down or if its domain or certificate is about to expire.',
    },
    {
        kind: 'server',
        icon: Server,
        title: 'A server',
        text: 'Follows its health with a single line to paste on it.',
    },
    {
        kind: 'service',
        icon: Wrench,
        title: 'Something else',
        text: 'A mail server, an application, a machine, a domain name…',
    },
];
</script>

<template>
    <Head :title="t('Dashboard')" />

    <!-- Empty install: normal scrollable onboarding -->
    <PageContainer v-if="monitors_total === 0" size="full">
        <section class="reveal space-y-2 surface-hero px-5 py-6">
            <h1
                class="font-display text-2xl font-semibold tracking-tight text-balance md:text-3xl"
            >
                {{ t('Welcome! What do you want to monitor?') }}
            </h1>
            <p class="max-w-prose text-sm text-pretty text-muted-foreground">
                {{ t('Pick one to start. It takes less than a minute.') }}
            </p>
        </section>
        <div class="grid gap-3 md:grid-cols-3">
            <Link
                v-for="(s, i) in starts"
                :key="s.kind"
                :href="addUrl(s.kind)"
                class="group reveal flex min-h-11 flex-col gap-2.5 surface p-4 transition hover:border-primary/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                :class="`reveal-delay-${i + 1}`"
            >
                <span
                    class="grid size-9 place-items-center rounded-md bg-primary/10 text-primary"
                >
                    <component :is="s.icon" class="size-4" aria-hidden="true" />
                </span>
                <span class="font-display text-base font-semibold">{{
                    t(s.title)
                }}</span>
                <span class="text-sm text-pretty text-muted-foreground">{{
                    t(s.text)
                }}</span>
                <ArrowRight
                    class="mt-auto size-4 text-primary transition motion-safe:group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </Link>
        </div>
    </PageContainer>

    <!-- Ops board: fills remaining viewport, no page scroll -->
    <div
        v-else
        class="dashboard-board flex min-h-0 flex-1 flex-col gap-3 overflow-hidden p-3 md:gap-3 md:p-4"
    >
        <header class="board-top shrink-0">
            <div
                class="board-top-bar flex flex-wrap items-center gap-x-3 gap-y-2 surface px-3 py-2"
                role="status"
                aria-live="polite"
            >
                <div
                    class="flex max-w-full min-w-0 shrink-0 items-center gap-2 sm:max-w-[14rem]"
                >
                    <Hourglass
                        v-if="isWaiting"
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <CheckCircle2
                        v-else-if="isHealthy"
                        class="size-4 shrink-0 text-success"
                        aria-hidden="true"
                    />
                    <TriangleAlert
                        v-else
                        class="size-4 shrink-0 text-danger"
                        aria-hidden="true"
                    />
                    <p
                        class="truncate font-display text-sm font-semibold"
                        :class="healthTone"
                    >
                        {{ healthLine }}
                    </p>
                </div>

                <nav
                    class="board-stats flex min-w-0 flex-1 flex-wrap items-center gap-1.5 border-border sm:border-l sm:pl-3"
                    :aria-label="t('Quick stats')"
                >
                    <Link
                        :href="checksIndex()"
                        class="chip gap-1 px-2 py-0.5 text-xs chip-success hover:opacity-90"
                    >
                        <CheckCircle2 class="size-3" aria-hidden="true" />
                        {{ t('Working') }} · {{ checks.up }}
                    </Link>
                    <Link
                        :href="incidentsIndex()"
                        class="chip gap-1 px-2 py-0.5 text-xs hover:opacity-90"
                        :class="incidents_open ? 'chip-danger' : 'chip-success'"
                    >
                        <ShieldAlert class="size-3" aria-hidden="true" />
                        {{ t('Incidents') }} · {{ incidents_open }}
                    </Link>
                    <Link
                        :href="hostsIndex()"
                        class="chip gap-1 px-2 py-0.5 text-xs chip-info hover:opacity-90"
                    >
                        <Server class="size-3" aria-hidden="true" />
                        {{ t('Servers') }} · {{ hosts_online }}/{{
                            hosts_total
                        }}
                    </Link>
                    <Link
                        :href="renewalsIndex()"
                        class="chip gap-1 px-2 py-0.5 text-xs hover:opacity-90"
                        :class="renewals_soon ? 'chip-warning' : ''"
                    >
                        <Hourglass class="size-3" aria-hidden="true" />
                        {{ t('Renewals') }} · {{ renewals_soon }}
                    </Link>
                </nav>
            </div>

            <Link
                v-if="primaryProblem"
                :href="problemBannerHref"
                class="problem-banner mt-2 flex min-h-11 w-full items-start gap-3 rounded-md border border-danger/30 bg-danger/8 px-3 py-2.5 text-left transition hover:bg-danger/12 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                :title="primaryProblem.error ?? primaryProblem.name"
            >
                <StatusDot
                    status="down"
                    pulse
                    class="mt-0.5 shrink-0"
                    :label="t('Down')"
                />
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-foreground">
                        {{ primaryProblem.name }}
                        <span
                            v-if="primaryProblem.group"
                            class="font-normal text-muted-foreground"
                        >
                            · {{ primaryProblem.group }}</span
                        >
                    </span>
                    <span
                        v-if="primaryProblem.error"
                        class="mt-0.5 line-clamp-2 text-sm text-pretty text-muted-foreground"
                        >{{ primaryProblem.error }}</span
                    >
                    <span
                        v-else-if="primaryProblem.since_at"
                        class="mt-0.5 block text-xs text-muted-foreground"
                    >
                        {{ since(primaryProblem.since_at) }}
                    </span>
                </span>
                <span
                    v-if="problems.length > 1"
                    class="chip shrink-0 px-1.5 py-0.5 numeric text-[0.65rem] chip-danger"
                >
                    +{{ problems.length - 1 }}
                </span>
                <ArrowRight
                    class="mt-1 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>

            <div
                v-if="channels_count === 0 || maintenance.length"
                class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 px-1 text-xs text-muted-foreground"
            >
                <p
                    v-if="channels_count === 0"
                    class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1"
                >
                    <BellRing
                        class="size-3.5 shrink-0 text-warning"
                        aria-hidden="true"
                    />
                    <span>{{
                        t('Do you want to be warned when something breaks?')
                    }}</span>
                    <Link
                        :href="channelsIndex()"
                        class="text-primary hover:underline"
                        >{{ t('Set up alerts') }}</Link
                    >
                </p>
                <p
                    v-if="maintenance.length"
                    class="flex min-w-0 items-center gap-1.5"
                >
                    <Wrench
                        class="size-3.5 shrink-0 text-info"
                        aria-hidden="true"
                    />
                    <span class="truncate">
                        {{
                            t(':count thing(s) in maintenance', {
                                count: checks.maintenance,
                            })
                        }}
                        — {{ maintenance.map((m) => m.name).join(', ') }}
                    </span>
                </p>
            </div>
        </header>

        <section
            v-if="renewalsInStrip"
            class="renewals-strip flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 surface px-3 py-2"
            :aria-label="t('Renewals')"
        >
            <h2
                class="flex items-center gap-2 font-display text-sm font-semibold"
            >
                <Hourglass class="size-3.5 text-warning" aria-hidden="true" />
                {{ t('Renewals') }}
            </h2>
            <ul
                class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1"
                role="list"
            >
                <li
                    v-for="r in overview.renewals"
                    :key="r.id"
                    class="flex max-w-xs min-w-0 items-baseline gap-2 text-sm"
                >
                    <span class="truncate font-medium">{{ r.name }}</span>
                    <span
                        class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >{{ r.expires_on }}</span
                    >
                </li>
            </ul>
            <Link
                :href="renewalsIndex()"
                class="shrink-0 text-xs text-primary hover:underline"
                >{{ t('See all') }}</Link
            >
        </section>

        <div
            class="board-body grid min-h-0 min-w-0 flex-1 gap-3 overflow-hidden"
            :class="boardBodyClass"
        >
            <section
                v-if="hasSites"
                class="panel flex min-h-0 min-w-0 flex-col overflow-visible surface"
                :aria-label="t('Websites')"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2"
                >
                    <h2
                        class="flex items-center gap-2 font-display text-sm font-semibold"
                    >
                        <Globe
                            class="size-3.5 text-primary"
                            aria-hidden="true"
                        />
                        {{ t('Websites') }}
                    </h2>
                    <div
                        class="flex flex-wrap items-center justify-end gap-x-2 gap-y-0.5 text-xs"
                    >
                        <span class="hidden text-muted-foreground sm:inline">{{
                            historyPanelHint
                        }}</span>
                        <span v-if="hiddenSites" class="text-muted-foreground"
                            >+{{ hiddenSites }}</span
                        >
                        <Link
                            :href="checksIndex()"
                            class="text-primary hover:underline"
                            >{{ t('See all') }}</Link
                        >
                    </div>
                </header>
                <ul
                    ref="sitesEl"
                    class="min-h-0 flex-1 divide-y overflow-x-visible overflow-y-auto py-0.5"
                >
                    <li
                        v-for="s in visibleSites"
                        :key="s.id"
                        class="relative z-0 focus-within:z-20 hover:z-20"
                    >
                        <Link
                            :href="checkMonitorHref(s.id)"
                            class="board-row board-row-monitor min-h-[3.75rem] px-3 py-2.5 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            :class="
                                rowProblemClass(
                                    s.status,
                                    s.enabled,
                                    s.in_maintenance,
                                )
                            "
                            :aria-label="s.name"
                        >
                            <StatusDot
                                class="shrink-0"
                                :status="
                                    asDot(s.status, s.enabled, s.in_maintenance)
                                "
                                :pulse="s.enabled && s.status === 'down'"
                            />
                            <span
                                class="board-row-main flex min-w-0 items-center gap-2"
                            >
                                <MonitorIdentityBadges
                                    class="board-row-identity"
                                    :name="s.name"
                                    :favicon-url="s.favicon_url"
                                    :ssl-status="s.ssl_status"
                                    :ssl-days-remaining="s.ssl_days_remaining"
                                    :site-framework="s.site_framework"
                                />
                                <span
                                    class="min-w-0 truncate text-sm font-semibold text-foreground"
                                    :title="s.name"
                                >
                                    {{ s.name }}
                                </span>
                            </span>
                            <UptimeHistoryDots
                                v-bind="monitorUptimeHistoryProps(s)"
                                class="col-history"
                            />
                            <p
                                v-if="s.latency_ms !== null"
                                class="justify-self-end text-right numeric text-xs tabular-nums"
                                :class="latencyClass(s.latency_ms)"
                            >
                                {{ s.latency_ms }}<span class="ml-0.5">ms</span>
                            </p>
                            <span
                                v-else
                                class="justify-self-end text-xs text-muted-foreground"
                                >—</span
                            >
                        </Link>
                    </li>
                </ul>
            </section>

            <section
                v-if="hasHeartbeats"
                class="panel flex min-h-0 min-w-0 flex-col overflow-hidden surface"
                :aria-label="t('Heartbeats')"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2"
                >
                    <h2
                        class="flex items-center gap-2 font-display text-sm font-semibold"
                    >
                        <HeartPulse
                            class="size-3.5 text-primary"
                            aria-hidden="true"
                        />
                        {{ t('Heartbeats') }}
                    </h2>
                    <div
                        class="flex flex-wrap items-center justify-end gap-x-2 gap-y-0.5 text-xs"
                    >
                        <span class="hidden text-muted-foreground sm:inline">{{
                            historyPanelHint
                        }}</span>
                        <span
                            v-if="hiddenHeartbeats"
                            class="text-muted-foreground"
                            >+{{ hiddenHeartbeats }}</span
                        >
                        <Link
                            :href="checksIndex()"
                            class="text-primary hover:underline"
                            >{{ t('See all') }}</Link
                        >
                    </div>
                </header>
                <ul
                    ref="heartbeatsEl"
                    class="min-h-0 flex-1 divide-y overflow-x-visible overflow-y-auto py-0.5"
                >
                    <li v-for="h in visibleHeartbeats" :key="h.id">
                        <Link
                            :href="checkMonitorHref(h.id)"
                            class="board-row board-row-monitor min-h-[2.75rem] px-3 py-2 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            :class="
                                rowProblemClass(
                                    h.status,
                                    h.enabled,
                                    h.in_maintenance,
                                )
                            "
                            :aria-label="h.name"
                        >
                            <StatusDot
                                class="shrink-0"
                                :status="
                                    asDot(h.status, h.enabled, h.in_maintenance)
                                "
                                :pulse="h.enabled && h.status === 'down'"
                            />
                            <span
                                class="board-row-main min-w-0 truncate text-sm font-semibold text-foreground"
                                :title="h.name"
                            >
                                {{ h.name }}
                            </span>
                            <UptimeHistoryDots
                                v-bind="monitorUptimeHistoryProps(h)"
                                class="col-history"
                            />
                            <p
                                class="justify-self-end text-right text-xs text-muted-foreground tabular-nums"
                            >
                                {{ relativeAgo(h.since_at) }}
                            </p>
                        </Link>
                    </li>
                </ul>
            </section>

            <section
                v-if="hasRenewalsPanel"
                class="panel flex min-h-0 min-w-0 flex-col overflow-hidden surface"
                :aria-label="t('Renewals')"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2"
                >
                    <h2
                        class="flex items-center gap-2 font-display text-sm font-semibold"
                    >
                        <Hourglass
                            class="size-3.5 text-warning"
                            aria-hidden="true"
                        />
                        {{ t('Renewals') }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span
                            v-if="hiddenRenewals"
                            class="text-muted-foreground"
                            >+{{ hiddenRenewals }}</span
                        >
                        <Link
                            :href="renewalsIndex()"
                            class="text-primary hover:underline"
                            >{{ t('See all') }}</Link
                        >
                    </div>
                </header>
                <ul
                    ref="renewalsEl"
                    class="min-h-0 flex-1 divide-y overflow-hidden"
                >
                    <li
                        v-for="r in visibleRenewals"
                        :key="r.id"
                        class="flex h-[40px] items-center justify-between gap-3 px-3 text-sm"
                    >
                        <span class="truncate font-medium">{{ r.name }}</span>
                        <span class="shrink-0 text-xs text-muted-foreground">{{
                            r.expires_on
                        }}</span>
                    </li>
                </ul>
            </section>

            <section
                v-if="hasServers"
                class="servers-panel panel flex min-h-0 min-w-0 flex-col overflow-hidden surface"
                :aria-label="t('Servers')"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2"
                >
                    <h2
                        class="flex items-center gap-2 font-display text-sm font-semibold"
                    >
                        <Server
                            class="size-3.5 text-primary"
                            aria-hidden="true"
                        />
                        {{ t('Servers') }}
                        <span class="chip"
                            >{{ hosts_online }}/{{ hosts_total }}</span
                        >
                    </h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span v-if="hiddenServers" class="text-muted-foreground"
                            >+{{ hiddenServers }}</span
                        >
                        <Link
                            :href="hostsIndex()"
                            class="text-primary hover:underline"
                            >{{ t('See all') }}</Link
                        >
                    </div>
                </header>
                <div
                    ref="serversEl"
                    class="host-grid min-h-0 flex-1 overflow-y-auto p-3"
                    :style="{
                        gridTemplateColumns: `repeat(${serverGridCols}, minmax(0, 1fr))`,
                    }"
                >
                    <HostCard
                        v-for="server in visibleServers"
                        :key="server.id"
                        class="min-w-0"
                        compact
                        :name="server.name"
                        :online="server.online"
                        :enrolled="true"
                        :metrics="server.metrics"
                        :agent-version="server.agent_version"
                        :agent-outdated="server.agent_outdated"
                        :host-id="server.id"
                        :update-command-enabled="canManageHosts"
                        :last-seen-at="server.last_seen_at"
                        :detail-href="HostController.show.url(server.id)"
                        :public-ipv4="server.public_ipv4"
                        :provider-label="server.provider_label"
                    />
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.dashboard-board {
    container: dashboard / size;
    width: 100%;
}

.board-body {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-rows: minmax(0, 1fr) minmax(0, 1fr);
    align-content: stretch;
}

.board-body--panels-1 {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: minmax(0, 1fr);
}

.board-body--panels-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-rows: minmax(0, 1fr);
}

.board-body--servers-tall {
    grid-template-rows: minmax(0, 1.2fr) minmax(0, 1.35fr);
}

.board-body--three-panels {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-rows: minmax(0, 1fr) minmax(0, 1fr);
}

.renewals-strip {
    border-radius: var(--radius);
}

.board-body > .panel,
.board-body > .servers-panel {
    min-height: 0;
    min-width: 0;
}

.servers-panel {
    content-visibility: auto;
    contain-intrinsic-size: auto 12rem;
}

.panel {
    content-visibility: auto;
    contain-intrinsic-size: auto 8rem;
}

/* status · name/identity · history · trailing */
.board-row {
    display: grid;
    align-items: center;
    column-gap: 0.5rem;
    text-decoration: none;
    color: inherit;
    transition: background-color 0.12s ease;
}

.board-row-monitor {
    grid-template-columns:
        auto
        minmax(0, 1.05fr)
        minmax(5.5rem, 1.85fr)
        3.5rem;
    overflow: visible;
}

.board-row-monitor .col-history :deep(.uptime-dots-track) {
    max-width: 12rem;
}

.board-row:hover {
    background: color-mix(in oklab, var(--muted) 55%, transparent);
}

.board-row--down {
    border-left: 3px solid color-mix(in oklab, var(--danger) 70%, transparent);
    background: color-mix(in oklab, var(--danger) 9%, transparent);
}

.board-row--down:hover {
    background: color-mix(in oklab, var(--danger) 14%, transparent);
}

.board-row--unknown {
    border-left: 3px solid
        color-mix(in oklab, var(--muted-foreground) 35%, transparent);
}

.col-history {
    width: 100%;
    min-width: 0;
}

.board-row-identity :deep(img),
.board-row-identity :deep(svg) {
    flex-shrink: 0;
}

.host-grid {
    display: grid;
    gap: 0.75rem;
    align-content: start;
}

.servers-panel .host-card {
    height: 100%;
    min-width: 0;
}

@supports not (content-visibility: auto) {
    .panel,
    .servers-panel {
        contain: layout style paint;
    }
}

@container dashboard (max-width: 40rem) {
    .board-body {
        grid-template-columns: 1fr;
        grid-template-rows: none;
        grid-auto-rows: minmax(0, 12rem);
    }

    .board-body--panels-1 {
        grid-auto-rows: minmax(0, 1fr);
        grid-template-rows: minmax(0, 1fr);
    }

    .board-body--panels-2 {
        grid-template-rows: repeat(2, minmax(0, 1fr));
        grid-auto-rows: minmax(0, 1fr);
    }

    .board-top-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .board-stats {
        border-left: 0 !important;
        padding-left: 0 !important;
    }

    .board-row-monitor {
        grid-template-columns: auto minmax(0, 1fr);
        grid-template-rows: auto auto;
        row-gap: 0.35rem;
    }

    .board-row-monitor .col-history {
        grid-column: 1 / -1;
        justify-self: stretch;
    }

    .board-row-monitor .col-history :deep(.uptime-dots-track) {
        max-width: min(100%, 11rem);
    }

    .board-row-monitor > :last-child {
        justify-self: end;
        grid-column: 2;
        grid-row: 1;
    }
}
</style>
