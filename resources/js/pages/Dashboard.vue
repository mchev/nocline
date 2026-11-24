<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BellRing,
    CheckCircle2,
    Globe,
    HeartPulse,
    Hourglass,
    Plus,
    Server,
    ShieldAlert,
    TriangleAlert,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import HostCard, { type HostMetrics } from '@/components/HostCard.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { Button } from '@/components/ui/button';
import { useGridFit, useListFit } from '@/composables/useFitCapacity';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
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
    }[];
    servers: {
        id: number;
        name: string;
        online: boolean;
        last_seen_at: string | null;
        agent_version: string | null;
        metrics: HostMetrics;
    }[];
    heartbeats: {
        id: number;
        name: string;
        status: string;
        since_at: string | null;
        in_maintenance: boolean;
        enabled: boolean;
        grace_sec: number;
    }[];
    renewals: { id: number; name: string; kind: string; expires_on: string | null }[];
};

const props = defineProps<{
    monitors_total: number;
    channels_count: number;
    problems: { id: number; name: string; group: string | null; since_at: string | null; error: string | null }[];
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

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

const addUrl = (kind: string) => `/monitor/new?kind=${kind}`;
const since = (iso: string | null) => (iso ? new Date(iso).toLocaleString() : '');

const relativeAgo = (iso: string | null) => {
    if (!iso) {
        return t('never');
    }

    const sec = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));

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
    () => props.problems.length === 0 && props.checks.down === 0 && props.checks.up === 0,
);
const isHealthy = computed(() => props.problems.length === 0 && props.checks.down === 0);

type DotStatus = 'unknown' | 'up' | 'down' | 'maintenance' | 'warning';

const asDot = (status: string, enabled: boolean, inMaintenance: boolean): DotStatus => {
    if (!enabled) {
        return 'unknown';
    }

    if (inMaintenance) {
        return 'maintenance';
    }

    if (status === 'up' || status === 'down' || status === 'unknown' || status === 'maintenance' || status === 'warning') {
        return status;
    }

    return 'unknown';
};

const { el: sitesEl, count: sitesCount } = useListFit({ itemHeight: 52, max: 40 });
const { el: heartbeatsEl, count: heartbeatsCount } = useListFit({ itemHeight: 44, max: 40 });
const { el: renewalsEl, count: renewalsCount } = useListFit({ itemHeight: 40, max: 20 });
const { el: problemsEl, count: problemsCountFit } = useListFit({ itemHeight: 56, max: 8, min: 1 });
const {
    el: serversEl,
    cols: serverCols,
    capacity: serversCapacity,
} = useGridFit({ itemMinWidth: 220, itemMinHeight: 148, gap: 12, max: 40, min: 1 });

const visibleSites = computed(() => props.overview.sites.slice(0, sitesCount.value));
const visibleHeartbeats = computed(() => props.overview.heartbeats.slice(0, heartbeatsCount.value));
const visibleRenewals = computed(() => props.overview.renewals.slice(0, renewalsCount.value));
const visibleProblems = computed(() => props.problems.slice(0, problemsCountFit.value));
const visibleServers = computed(() => props.overview.servers.slice(0, serversCapacity.value));

const hiddenSites = computed(() => Math.max(0, props.overview.sites.length - visibleSites.value.length));
const hiddenHeartbeats = computed(() => Math.max(0, props.overview.heartbeats.length - visibleHeartbeats.value.length));
const hiddenRenewals = computed(() => Math.max(0, props.overview.renewals.length - visibleRenewals.value.length));
const hiddenServers = computed(() => Math.max(0, props.overview.servers.length - visibleServers.value.length));
const hiddenProblems = computed(() => Math.max(0, props.problems.length - visibleProblems.value.length));

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
        <section class="surface-hero reveal space-y-2 px-5 py-6">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-balance md:text-3xl">
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
                class="surface group flex min-h-11 flex-col gap-2.5 p-4 transition hover:border-primary/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none reveal"
                :class="`reveal-delay-${i + 1}`"
            >
                <span class="grid size-9 place-items-center rounded-md bg-primary/10 text-primary">
                    <component :is="s.icon" class="size-4" aria-hidden="true" />
                </span>
                <span class="font-display text-base font-semibold">{{ t(s.title) }}</span>
                <span class="text-sm text-pretty text-muted-foreground">{{ t(s.text) }}</span>
                <ArrowRight
                    class="mt-auto size-4 text-primary transition motion-safe:group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </Link>
        </div>
    </PageContainer>

    <!-- Ops board: fills remaining viewport, no page scroll -->
    <div v-else class="dashboard-board flex min-h-0 flex-1 flex-col gap-3 overflow-hidden p-3 md:gap-3 md:p-4">
        <header class="board-top shrink-0 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                        {{ t('Dashboard') }}
                    </p>
                    <h1 class="font-display text-xl font-semibold tracking-tight md:text-2xl">{{ t('Overview') }}</h1>
                </div>
                <Button as-child size="sm">
                    <Link href="/monitor/new"><Plus class="size-4" /> {{ t('Add a monitor') }}</Link>
                </Button>
            </div>

            <section
                class="surface-hero grid items-center gap-3 p-3 sm:grid-cols-[auto_minmax(0,1fr)_auto]"
                role="status"
                aria-live="polite"
            >
                <template v-if="isWaiting">
                    <span class="grid size-9 place-items-center rounded-md bg-muted text-muted-foreground">
                        <Hourglass class="size-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-display text-lg font-semibold">{{ t('Waiting for the first results') }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ t('Checks run every minute. The first results appear shortly.') }}
                        </p>
                    </div>
                </template>
                <template v-else-if="isHealthy">
                    <span class="grid size-9 place-items-center rounded-md bg-success/15 text-success">
                        <CheckCircle2 class="size-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-display text-lg font-semibold">{{ t('Everything is running smoothly') }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ t(':count things are being watched.', { count: monitors_total }) }}
                        </p>
                    </div>
                    <p class="numeric text-3xl font-semibold text-success" aria-hidden="true">{{ checks.up }}</p>
                </template>
                <template v-else>
                    <span class="grid size-9 place-items-center rounded-md bg-danger/15 text-danger">
                        <TriangleAlert class="size-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-display text-lg font-semibold">
                            {{ t(':count thing(s) need your attention', { count: problemCount }) }}
                        </p>
                    </div>
                    <p class="numeric text-3xl font-semibold text-danger" aria-hidden="true">{{ problemCount }}</p>
                </template>
            </section>

            <nav class="board-stats flex flex-wrap gap-2" :aria-label="t('Quick stats')">
                <Link :href="checksIndex()" class="chip chip-success gap-1.5 px-3 py-1.5 hover:opacity-90">
                    <CheckCircle2 class="size-3.5" aria-hidden="true" />
                    {{ t('Working') }} · {{ checks.up }}
                </Link>
                <Link
                    :href="incidentsIndex()"
                    class="chip gap-1.5 px-3 py-1.5 hover:opacity-90"
                    :class="incidents_open ? 'chip-danger' : 'chip-success'"
                >
                    <ShieldAlert class="size-3.5" aria-hidden="true" />
                    {{ t('Incidents') }} · {{ incidents_open }}
                </Link>
                <Link :href="hostsIndex()" class="chip chip-info gap-1.5 px-3 py-1.5 hover:opacity-90">
                    <Server class="size-3.5" aria-hidden="true" />
                    {{ t('Servers') }} · {{ hosts_online }}/{{ hosts_total }}
                </Link>
                <Link
                    :href="renewalsIndex()"
                    class="chip gap-1.5 px-3 py-1.5 hover:opacity-90"
                    :class="renewals_soon ? 'chip-warning' : ''"
                >
                    <Hourglass class="size-3.5" aria-hidden="true" />
                    {{ t('Renewals') }} · {{ renewals_soon }}
                </Link>
            </nav>

            <aside
                v-if="channels_count === 0"
                class="surface flex flex-wrap items-center gap-3 p-3"
            >
                <BellRing class="size-4 shrink-0 text-warning" aria-hidden="true" />
                <p class="min-w-0 flex-1 text-sm">{{ t('Do you want to be warned when something breaks?') }}</p>
                <Button as-child size="sm">
                    <Link :href="channelsIndex()">{{ t('Set up alerts') }}</Link>
                </Button>
            </aside>

            <aside
                v-if="maintenance.length"
                class="flex items-start gap-2 rounded-md border border-info/30 bg-info/10 px-3 py-2 text-sm"
            >
                <Wrench class="mt-0.5 size-4 shrink-0 text-info" aria-hidden="true" />
                <p>
                    <span class="font-medium">{{ t(':count thing(s) in maintenance', { count: checks.maintenance }) }}</span>
                    — {{ maintenance.map((m) => m.name).join(', ') }}
                </p>
            </aside>
        </header>

        <section
            v-if="problems.length"
            class="surface flex min-h-0 shrink-0 flex-col overflow-hidden"
            :style="{ maxHeight: 'min(22cqh, 11rem)' }"
            :aria-label="t('Problems')"
        >
            <header class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2">
                <h2 class="font-display text-sm font-semibold text-danger">{{ t('Problems') }}</h2>
                <span v-if="hiddenProblems" class="text-xs text-muted-foreground">+{{ hiddenProblems }}</span>
            </header>
            <ul ref="problemsEl" class="min-h-0 flex-1 divide-y overflow-hidden">
                <li
                    v-for="p in visibleProblems"
                    :key="p.id"
                    class="flex items-start justify-between gap-3 px-3 py-2.5"
                >
                    <div class="flex min-w-0 items-start gap-2">
                        <StatusDot status="down" pulse class="mt-1" :label="t('Down')" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ p.name }}
                                <span v-if="p.group" class="font-normal text-muted-foreground"> · {{ p.group }}</span>
                            </p>
                            <p v-if="p.error" class="truncate text-xs text-muted-foreground">{{ p.error }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-xs text-muted-foreground">{{ since(p.since_at) }}</span>
                </li>
            </ul>
        </section>

        <div class="board-body grid min-h-0 flex-1 gap-3 overflow-hidden">
            <section class="panel surface flex min-h-0 flex-col overflow-hidden" :aria-label="t('Websites')">
                <header class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2">
                    <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                        <Globe class="size-3.5 text-primary" aria-hidden="true" />
                        {{ t('Websites') }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span v-if="hiddenSites" class="text-muted-foreground">+{{ hiddenSites }}</span>
                        <Link :href="checksIndex()" class="text-primary hover:underline">{{ t('See all') }}</Link>
                    </div>
                </header>
                <ul v-if="overview.sites.length" ref="sitesEl" class="min-h-0 flex-1 divide-y overflow-hidden">
                    <li
                        v-for="s in visibleSites"
                        :key="s.id"
                        class="flex h-[52px] items-center justify-between gap-3 px-3"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <StatusDot
                                :status="asDot(s.status, s.enabled, s.in_maintenance)"
                                :pulse="s.enabled && s.status === 'down'"
                            />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ s.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ s.target }}</p>
                            </div>
                        </div>
                        <p
                            v-if="s.latency_ms !== null"
                            class="numeric shrink-0 text-sm font-semibold"
                            :class="s.latency_ms > 1500 ? 'text-warning' : ''"
                        >
                            {{ s.latency_ms }}<span class="ml-0.5 text-xs font-normal text-muted-foreground">ms</span>
                        </p>
                    </li>
                </ul>
                <p v-else class="px-3 py-4 text-sm text-muted-foreground">{{ t('No websites yet.') }}</p>
            </section>

            <section class="panel surface flex min-h-0 flex-col overflow-hidden" :aria-label="t('Heartbeats')">
                <header class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2">
                    <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                        <HeartPulse class="size-3.5 text-primary" aria-hidden="true" />
                        {{ t('Heartbeats') }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span v-if="hiddenHeartbeats" class="text-muted-foreground">+{{ hiddenHeartbeats }}</span>
                        <Link :href="checksIndex()" class="text-primary hover:underline">{{ t('See all') }}</Link>
                    </div>
                </header>
                <ul
                    v-if="overview.heartbeats.length"
                    ref="heartbeatsEl"
                    class="min-h-0 flex-1 divide-y overflow-hidden"
                >
                    <li
                        v-for="h in visibleHeartbeats"
                        :key="h.id"
                        class="flex h-[44px] items-center justify-between gap-3 px-3"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <StatusDot
                                :status="asDot(h.status, h.enabled, h.in_maintenance)"
                                :pulse="h.enabled && h.status === 'down'"
                            />
                            <p class="truncate text-sm font-medium">{{ h.name }}</p>
                        </div>
                        <p class="shrink-0 text-xs text-muted-foreground">{{ relativeAgo(h.since_at) }}</p>
                    </li>
                </ul>
                <p v-else class="px-3 py-4 text-sm text-muted-foreground">{{ t('No heartbeats yet.') }}</p>
            </section>

            <section
                v-if="overview.renewals.length"
                class="panel surface flex min-h-0 flex-col overflow-hidden"
                :aria-label="t('Renewals')"
            >
                <header class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2">
                    <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                        <Hourglass class="size-3.5 text-warning" aria-hidden="true" />
                        {{ t('Renewals') }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span v-if="hiddenRenewals" class="text-muted-foreground">+{{ hiddenRenewals }}</span>
                        <Link :href="renewalsIndex()" class="text-primary hover:underline">{{ t('See all') }}</Link>
                    </div>
                </header>
                <ul ref="renewalsEl" class="min-h-0 flex-1 divide-y overflow-hidden">
                    <li
                        v-for="r in visibleRenewals"
                        :key="r.id"
                        class="flex h-[40px] items-center justify-between gap-3 px-3 text-sm"
                    >
                        <span class="truncate font-medium">{{ r.name }}</span>
                        <span class="shrink-0 text-xs text-muted-foreground">{{ r.expires_on }}</span>
                    </li>
                </ul>
            </section>

            <section class="servers-panel flex min-h-0 flex-col gap-2 overflow-hidden" :aria-label="t('Servers')">
                <header class="flex shrink-0 items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                        <Server class="size-3.5 text-primary" aria-hidden="true" />
                        {{ t('Servers') }}
                        <span class="chip">{{ hosts_online }}/{{ hosts_total }}</span>
                    </h2>
                    <div class="flex items-center gap-2 text-xs">
                        <span v-if="hiddenServers" class="text-muted-foreground">+{{ hiddenServers }}</span>
                        <Link :href="hostsIndex()" class="text-primary hover:underline">{{ t('See all') }}</Link>
                    </div>
                </header>
                <div
                    v-if="overview.servers.length"
                    ref="serversEl"
                    class="host-grid min-h-0 flex-1 overflow-hidden"
                    :style="{ gridTemplateColumns: `repeat(${serverCols}, minmax(0, 1fr))` }"
                >
                    <HostCard
                        v-for="server in visibleServers"
                        :key="server.id"
                        compact
                        :name="server.name"
                        :online="server.online"
                        :enrolled="true"
                        :metrics="server.metrics"
                        :agent-version="server.agent_version"
                        :last-seen-at="server.last_seen_at"
                    />
                </div>
                <p v-else class="surface px-3 py-4 text-sm text-muted-foreground">{{ t('No servers enrolled yet.') }}</p>
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-template-rows: minmax(0, 1fr) minmax(0, 1.35fr);
    grid-template-areas:
        'sites heartbeats renewals'
        'servers servers servers';
}

.board-body > .panel:nth-child(1) {
    grid-area: sites;
}

.board-body > .panel:nth-child(2) {
    grid-area: heartbeats;
}

.board-body > .panel:nth-child(3) {
    grid-area: renewals;
}

.servers-panel {
    grid-area: servers;
    content-visibility: auto;
    contain-intrinsic-size: auto 12rem;
}

.panel {
    content-visibility: auto;
    contain-intrinsic-size: auto 8rem;
}

.host-grid {
    display: grid;
    gap: 0.75rem;
    align-content: start;
}

@supports not (content-visibility: auto) {
    .panel,
    .servers-panel {
        contain: layout style paint;
    }
}

@container dashboard (max-width: 56rem) {
    .board-body {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: minmax(0, 0.9fr) minmax(0, 0.9fr) minmax(0, 1.3fr);
        grid-template-areas:
            'sites heartbeats'
            'renewals renewals'
            'servers servers';
    }
}

@container dashboard (max-width: 36rem) {
    .board-body {
        grid-template-columns: 1fr;
        grid-template-rows: repeat(3, minmax(0, 0.75fr)) minmax(0, 1.2fr);
        grid-template-areas:
            'sites'
            'heartbeats'
            'renewals'
            'servers';
    }
}

/* When renewals panel is absent, let sites/heartbeats share the top row fully */
.board-body:not(:has(> .panel:nth-child(3))) {
    grid-template-areas:
        'sites heartbeats heartbeats'
        'servers servers servers';
}
</style>
