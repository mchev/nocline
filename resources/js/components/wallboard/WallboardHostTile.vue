<script setup lang="ts">
import { computed } from 'vue';
import Sparkline from '@/components/ui-kit/Sparkline.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import {
    hostMetricTone,
    metricThresholdsFromAlert,
} from '@/lib/hostMetricTone';
import { hostDiskCaption } from '@/lib/hostDiskCaption';
import { t } from '@/lib/i18n';
import type { HostMetrics } from '@/components/HostCard.vue';

const props = defineProps<{
    host: {
        id: number;
        name: string;
        online: boolean;
        metrics: HostMetrics;
        metric_alerts?: {
            cpu_pct: number;
            mem_pct: number;
            disk_pct: number;
        };
        resource_alert?: { active: boolean; message: string | null };
    };
}>();

const cpuThresholds = computed(() =>
    props.host.metric_alerts
        ? metricThresholdsFromAlert(props.host.metric_alerts.cpu_pct)
        : undefined,
);
const memThresholds = computed(() =>
    props.host.metric_alerts
        ? metricThresholdsFromAlert(props.host.metric_alerts.mem_pct)
        : undefined,
);
const diskThresholds = computed(() =>
    props.host.metric_alerts
        ? metricThresholdsFromAlert(props.host.metric_alerts.disk_pct)
        : undefined,
);

function rowTone(key: 'cpu' | 'mem', now: number | null) {
    const custom = key === 'cpu' ? cpuThresholds.value : memThresholds.value;

    return hostMetricTone(now, false, custom);
}

const status = computed(() =>
    props.host.online ? ('up' as const) : ('down' as const),
);

const pct = (v: number | null) => (v === null ? '—' : `${Math.round(v)}%`);

const sparkRows = computed(() => [
    {
        key: 'cpu',
        label: t('CPU'),
        now: props.host.metrics.cpu_now,
        series: props.host.metrics.cpu,
        display: pct(props.host.metrics.cpu_now),
    },
    {
        key: 'mem',
        label: t('RAM'),
        now: props.host.metrics.mem_now,
        series: props.host.metrics.mem,
        display: pct(props.host.metrics.mem_now),
    },
]);

const diskCaption = computed(() => hostDiskCaption(props.host.metrics));

const diskTone = computed(() =>
    hostMetricTone(props.host.metrics.disk_now, false, diskThresholds.value),
);
</script>

<template>
    <li
        class="wb-host-card"
        :class="{
            'wb-host-card--up': host.online && !host.resource_alert?.active,
            'wb-host-card--down': !host.online,
            'wb-host-card--warn': host.online && host.resource_alert?.active,
        }"
    >
        <header class="wb-host-head">
            <StatusDot
                :status="status"
                :pulse="!host.online"
                class="shrink-0 scale-125"
            />
            <h3 class="wb-host-name">{{ host.name }}</h3>
        </header>

        <ul v-if="host.online" class="wb-host-metrics" role="list">
            <li
                v-for="row in sparkRows"
                :key="row.key"
                class="wb-host-metric-row"
            >
                <span class="wb-host-metric-label">{{ row.label }}</span>
                <Sparkline
                    subtle
                    class="wb-host-spark"
                    :values="row.series"
                    :tone="rowTone(row.key as 'cpu' | 'mem', row.now)"
                    :label="`${row.label} ${row.display}`"
                />
                <span class="wb-host-metric-val numeric">{{
                    row.display
                }}</span>
            </li>
            <li class="wb-host-disk-row">
                <span class="wb-host-metric-label">{{ t('Disk') }}</span>
                <span
                    class="wb-host-disk-cap numeric"
                    :class="{
                        'text-danger': diskTone === 'danger',
                        'text-warning': diskTone === 'warning',
                    }"
                >
                    {{ diskCaption }}
                </span>
            </li>
        </ul>
        <p v-else class="wb-host-offline">{{ t('Offline') }}</p>
    </li>
</template>

<style scoped>
.wb-host-card {
    display: flex;
    flex-direction: column;
    gap: 0.35em;
    min-height: 100%;
    padding: 0.45em 0.55em;
    list-style: none;
    background: color-mix(in oklch, var(--card) 92%, transparent);
    border: 1px solid var(--border);
    border-radius: 0.35rem;
}

.wb-host-card--down {
    background: color-mix(in oklch, var(--danger) 22%, var(--card));
    border-color: var(--danger);
    animation: wb-pulse 1.5s ease-in-out infinite;
}

.wb-host-card--warn {
    background: color-mix(in oklch, var(--warning) 18%, var(--card));
    border-color: color-mix(in oklch, var(--warning) 65%, var(--border));
}

.wb-host-head {
    display: flex;
    gap: 0.35em;
    align-items: center;
    min-width: 0;
}

.wb-host-name {
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: var(--wb-host-name, 0.95rem);
    font-weight: 700;
    white-space: nowrap;
}

.wb-host-metrics {
    display: grid;
    gap: 0.25em;
    margin: 0;
    padding: 0;
    list-style: none;
}

.wb-host-metric-row {
    display: grid;
    grid-template-columns: 2.5em 1fr 2.75em;
    gap: 0.35em;
    align-items: center;
}

.wb-host-disk-row {
    display: grid;
    grid-template-columns: 2.5em 1fr;
    gap: 0.35em;
    align-items: center;
    padding-top: 0.1em;
    border-top: 1px solid color-mix(in oklch, var(--border) 70%, transparent);
}

.wb-host-metric-label {
    font-size: calc(var(--wb-host-name, 0.95rem) * 0.72);
    font-weight: 600;
    color: var(--muted-foreground);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.wb-host-metric-val {
    font-size: calc(var(--wb-host-name, 0.95rem) * 0.85);
    font-weight: 700;
    text-align: right;
}

.wb-host-disk-cap {
    overflow: hidden;
    font-size: calc(var(--wb-host-name, 0.95rem) * 0.82);
    font-weight: 700;
    line-height: 1.15;
    text-overflow: ellipsis;
    text-align: right;
    white-space: nowrap;
}

.wb-host-offline {
    margin: 0.25em 0 0;
    font-size: var(--wb-host-name, 0.95rem);
    font-weight: 700;
    color: var(--danger);
}

:deep(.wb-host-spark) {
    height: clamp(1.35rem, 3.5vmin, 2rem);
}

@keyframes wb-pulse {
    0%,
    100% {
        filter: brightness(1);
    }

    50% {
        filter: brightness(1.12);
    }
}
</style>
