<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    LoaderCircle,
    Pause,
    Pencil,
    Play,
    Trash2,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import CheckController from '@/actions/App/Modules/Checks/Http/Controllers/CheckController';
import CheckMonitorResponseChart, {
    type DownInterval,
    type LatencyPoint,
} from '@/components/CheckMonitorResponseChart.vue';
import CheckSettingsForm from '@/components/CheckSettingsForm.vue';
import MonitorIdentityBadges from '@/components/MonitorIdentityBadges.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import UptimeHistoryDots from '@/components/ui-kit/UptimeHistoryDots.vue';
import { Button } from '@/components/ui/button';
import { monitorUptimeHistoryProps } from '@/lib/monitorHistory';
import { t } from '@/lib/i18n';
import { siteHealthCompanionLabel } from '@/lib/siteHealthCompanion';
import { index as checksIndex } from '@/routes/checks';
import type { UptimeHistoryDot } from '@/types/checks';
import type { GroupOption } from '@/types/groups';

type CheckDetail = {
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
    status: string;
    since_at: string | null;
    latency_ms: number | null;
    last_error: string | null;
    in_maintenance: boolean;
    group_id: number | null;
    host_id: number | null;
    favicon_url?: string | null;
    ssl_status?: string | null;
    ssl_days_remaining?: number | null;
    site_framework?: string | null;
    health_check_id?: number;
    companion_of?: number;
    is_health_companion?: boolean;
};

const props = defineProps<{
    check: CheckDetail;
    recent: {
        history: UptimeHistoryDot[];
        timeline: { s: UptimeHistoryDot; at: string; error?: string | null }[];
        uptime_24h_pct: number | null;
    };
    stats: {
        latency_avg_24h: number | null;
        uptime_24h: number | null;
        uptime_30d: number | null;
        uptime_365d: number | null;
    };
    renewals: {
        ssl: { expires_on: string; days: number | null } | null;
        domain: {
            label: string;
            expires_on: string;
            days: number | null;
        } | null;
    };
    chart: {
        hours: number;
        points: LatencyPoint[];
        down_intervals: DownInterval[];
    };
    groups: GroupOption[];
    hosts: { id: number; name: string }[];
    types: string[];
    categories: string[];
}>();

const role = computed(() => usePage().props.auth.user.role);
const canManage = computed(() => role.value !== 'viewer');
const isAdmin = computed(() => role.value === 'admin');

const settingsOpen = ref(false);
const chartHours = ref(props.chart.hours);
const chartPoints = ref<LatencyPoint[]>([...props.chart.points]);
const chartDown = ref<DownInterval[]>([...props.chart.down_intervals]);
const chartLoading = ref(false);

const title = computed(() =>
    props.check.is_health_companion
        ? siteHealthCompanionLabel()
        : props.check.name,
);

const dotStatus = computed(() => {
    if (!props.check.enabled || props.check.in_maintenance) {
        return 'unknown' as const;
    }

    return props.check.status as 'up' | 'down' | 'unknown' | 'maintenance';
});

const statusLabel = computed(() => {
    if (!props.check.enabled) {
        return t('Paused');
    }

    if (props.check.in_maintenance) {
        return t('Maintenance');
    }

    return (
        { up: t('Up'), down: t('Down'), unknown: t('Unknown') }[
            props.check.status
        ] ?? props.check.status
    );
});

const statusChipClass = computed(() => {
    if (!props.check.enabled || props.check.in_maintenance) {
        return 'chip';
    }

    if (props.check.status === 'up') {
        return 'chip chip-success';
    }

    if (props.check.status === 'down') {
        return 'chip chip-danger';
    }

    return 'chip';
});

const recentHistory = computed(() =>
    monitorUptimeHistoryProps({
        history: props.recent.history,
        timeline: props.recent.timeline,
        uptime_24h_pct: props.recent.uptime_24h_pct,
    }),
);

const pct = (v: number | null) =>
    v === null ? '—' : `${v % 1 === 0 ? v.toFixed(0) : v.toFixed(1)}%`;

const intervalLabel = computed(() => {
    const s = props.check.interval_sec;
    if (s < 60) {
        return t('Checked every :n seconds', { n: s });
    }

    const m = Math.round(s / 60);

    return t('Checked every :minutes min (:sec s)', { minutes: m, sec: s });
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('edit') === '1') {
        settingsOpen.value = true;
    }
});

function togglePause() {
    router.patch(
        CheckController.update.url(props.check.id),
        {
            name: props.check.name,
            type: props.check.type,
            target: props.check.target,
            host_id: props.check.host_id,
            enabled: !props.check.enabled,
        },
        { preserveScroll: true },
    );
}

function remove() {
    if (!confirm(t('Delete the check ":name"?', { name: props.check.name }))) {
        return;
    }

    router.delete(CheckController.destroy.url(props.check.id));
}

async function loadChart(hours: number) {
    chartHours.value = hours;
    chartLoading.value = true;

    try {
        const url = new URL(
            CheckController.chart.url(props.check.id),
            window.location.origin,
        );
        url.searchParams.set('hours', String(hours));

        const res = await fetch(url.toString(), {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            throw new Error(String(res.status));
        }

        const data = (await res.json()) as typeof props.chart;
        chartPoints.value = data.points;
        chartDown.value = data.down_intervals;
    } finally {
        chartLoading.value = false;
    }
}
</script>

<template>
    <Head :title="title" />

    <PageContainer class="max-w-5xl">
        <div class="mb-4">
            <Link
                :href="checksIndex()"
                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('Monitor') }}
            </Link>
        </div>

        <header class="space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 space-y-2">
                    <h1
                        class="font-display text-2xl font-semibold tracking-tight"
                    >
                        {{ title }}
                    </h1>
                    <a
                        v-if="check.target && check.type === 'http'"
                        :href="check.target"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-primary hover:underline"
                    >
                        {{ check.target }}
                    </a>
                    <p
                        v-else-if="check.target"
                        class="text-sm text-muted-foreground"
                    >
                        {{ check.target }}
                    </p>
                </div>
                <div v-if="canManage" class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="togglePause"
                    >
                        <Pause v-if="check.enabled" class="mr-1.5 size-4" />
                        <Play v-else class="mr-1.5 size-4" />
                        {{ check.enabled ? t('Pause') : t('Resume') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="settingsOpen = !settingsOpen"
                    >
                        <Pencil class="mr-1.5 size-4" />
                        {{ t('Edit') }}
                    </Button>
                    <Button
                        v-if="isAdmin"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-danger hover:text-danger"
                        @click="remove"
                    >
                        <Trash2 class="mr-1.5 size-4" />
                        {{ t('Delete') }}
                    </Button>
                </div>
            </div>
        </header>

        <section class="mt-6 surface p-5">
            <div
                class="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <div
                        v-if="check.category === 'website'"
                        class="flex items-center gap-2"
                    >
                        <MonitorIdentityBadges
                            :name="check.name"
                            :favicon-url="check.favicon_url"
                            :ssl-status="check.ssl_status"
                            :ssl-days-remaining="check.ssl_days_remaining"
                            :site-framework="check.site_framework"
                        />
                    </div>
                    <UptimeHistoryDots
                        v-bind="recentHistory"
                        show-axis-labels
                        show-percent
                        variant="board"
                        class="max-w-full"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ intervalLabel }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1">
                    <StatusDot
                        :status="dotStatus"
                        :pulse="check.enabled && check.status === 'down'"
                    />
                    <span
                        class="px-4 py-2 text-sm font-semibold"
                        :class="statusChipClass"
                    >
                        {{ statusLabel }}
                    </span>
                </div>
            </div>

            <dl
                class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('Response time (now)') }}
                    </dt>
                    <dd class="numeric text-lg font-semibold tabular-nums">
                        {{
                            check.latency_ms !== null
                                ? `${check.latency_ms} ms`
                                : '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('Avg response (24 h)') }}
                    </dt>
                    <dd class="numeric text-lg font-semibold tabular-nums">
                        {{
                            stats.latency_avg_24h !== null
                                ? `${stats.latency_avg_24h} ms`
                                : '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('Uptime (24 h)') }}
                    </dt>
                    <dd class="numeric text-lg font-semibold tabular-nums">
                        {{ pct(stats.uptime_24h) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('Uptime (30 d)') }}
                    </dt>
                    <dd class="numeric text-lg font-semibold tabular-nums">
                        {{ pct(stats.uptime_30d) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('Uptime (1 y)') }}
                    </dt>
                    <dd class="numeric text-lg font-semibold tabular-nums">
                        {{ pct(stats.uptime_365d) }}
                    </dd>
                </div>
                <div v-if="renewals.ssl">
                    <dt class="text-muted-foreground">
                        {{ t('SSL certificate expiry') }}
                    </dt>
                    <dd class="text-base font-medium">
                        {{
                            renewals.ssl.days !== null
                                ? t(':days d', { days: renewals.ssl.days })
                                : '—'
                        }}
                        <span class="block text-xs text-muted-foreground">{{
                            renewals.ssl.expires_on
                        }}</span>
                    </dd>
                </div>
                <div v-if="renewals.domain">
                    <dt class="text-muted-foreground">
                        {{ t('Domain expiry') }}
                    </dt>
                    <dd class="text-base font-medium">
                        {{ t(':days d', { days: renewals.domain.days }) }}
                        <span class="block text-xs text-muted-foreground">{{
                            renewals.domain.expires_on
                        }}</span>
                    </dd>
                </div>
            </dl>

            <div class="mt-6 border-t pt-5">
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-2"
                >
                    <h2 class="text-sm font-semibold">
                        {{ t('Response time (ms)') }}
                    </h2>
                    <label class="flex items-center gap-2 text-sm">
                        <span class="text-muted-foreground">{{
                            t('Range')
                        }}</span>
                        <select
                            class="h-9 rounded-md border bg-transparent px-2 text-sm"
                            :value="chartHours"
                            :disabled="chartLoading"
                            @change="
                                loadChart(
                                    Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    ),
                                )
                            "
                        >
                            <option :value="3">{{ t('Recent (3 h)') }}</option>
                            <option :value="24">{{ t('24 hours') }}</option>
                            <option :value="168">{{ t('7 days') }}</option>
                        </select>
                    </label>
                </div>
                <div v-if="chartLoading" class="flex justify-center py-8">
                    <LoaderCircle
                        class="size-8 animate-spin text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <CheckMonitorResponseChart
                    v-else
                    :points="chartPoints"
                    :down-intervals="chartDown"
                    :hours="chartHours"
                />
                <p
                    v-if="!chartLoading && chartPoints.length === 0"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    {{
                        t(
                            'No response samples yet — data appears after the next runs.',
                        )
                    }}
                </p>
            </div>

            <div
                v-if="check.health_check_id || check.companion_of"
                class="mt-4 flex flex-wrap gap-3 text-sm"
            >
                <Link
                    v-if="check.health_check_id"
                    :href="CheckController.show.url(check.health_check_id)"
                    class="text-primary hover:underline"
                >
                    {{ siteHealthCompanionLabel() }}
                </Link>
                <Link
                    v-else-if="check.companion_of"
                    :href="CheckController.show.url(check.companion_of)"
                    class="text-primary hover:underline"
                >
                    {{ t('Main site check') }}
                </Link>
            </div>
        </section>

        <section
            v-if="settingsOpen && canManage"
            class="mt-6 surface p-5"
            :aria-label="t('Edit check')"
        >
            <h2 class="mb-4 font-display text-lg font-semibold">
                {{ t('Edit check') }}
            </h2>
            <CheckSettingsForm
                :check="check"
                :groups="groups"
                :hosts="hosts"
                :types="types"
                :categories="categories"
                @saved="settingsOpen = false"
                @cancel="settingsOpen = false"
            />
        </section>

        <p
            v-if="check.last_error"
            class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger"
        >
            {{ check.last_error }}
        </p>
    </PageContainer>
</template>
