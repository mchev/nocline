<script setup lang="ts">
import { computed } from 'vue';
import HostMetaChips from '@/components/HostMetaChips.vue';
import HostStackChips from '@/components/HostStackChips.vue';
import Sparkline from '@/components/ui-kit/Sparkline.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import {
    hostMetricTone,
    metricThresholdsFromAlert,
} from '@/lib/hostMetricTone';
import { hostDiskParts } from '@/lib/hostDiskCaption';
import { t } from '@/lib/i18n';
import type { HostMetrics } from '@/components/HostCard.vue';

const props = defineProps<{
    host: {
        id: number;
        name: string;
        online: boolean;
        agent_version?: string | null;
        agent_outdated?: boolean;
        public_ipv4?: string | null;
        provider_label?: string | null;
        provider_region?: string | null;
        stack?: Record<string, string> | null;
        stack_warnings?: Record<string, string> | null;
        reboot_required?: boolean;
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

const resourceAlert = computed(
    () => props.host.resource_alert?.active === true,
);
const rebootRequired = computed(() => props.host.reboot_required === true);

const hostWarn = computed(
    () => props.host.online && (resourceAlert.value || rebootRequired.value),
);

const statusDot = computed(() => {
    if (!props.host.online) {
        return 'down' as const;
    }

    if (resourceAlert.value) {
        return 'degraded' as const;
    }

    if (rebootRequired.value) {
        return 'maintenance' as const;
    }

    return 'up' as const;
});

const statusLabel = computed(() => {
    if (!props.host.online) {
        return t('Offline');
    }

    const parts: string[] = [];

    if (resourceAlert.value) {
        parts.push(t('High load'));
    }

    if (rebootRequired.value) {
        parts.push(t('Reboot required'));
    }

    if (parts.length) {
        return parts.join(' · ');
    }

    return t('Online');
});

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

const diskParts = computed(() => hostDiskParts(props.host.metrics));

const diskTone = computed(() =>
    hostMetricTone(props.host.metrics.disk_now, false, diskThresholds.value),
);
</script>

<template>
    <li
        class="wb-host-card"
        :class="{
            'wb-host-card--up': host.online && !hostWarn,
            'wb-host-card--down': !host.online,
            'wb-host-card--warn': hostWarn,
        }"
    >
        <header class="wb-host-head">
            <StatusDot
                :status="statusDot"
                :pulse="!host.online"
                class="shrink-0 scale-125"
            />
            <h3 class="wb-host-name">{{ host.name }}</h3>
            <span
                class="wb-host-chip"
                :class="{
                    'wb-host-chip--ok': host.online && !hostWarn,
                    'wb-host-chip--down': !host.online,
                    'wb-host-chip--warn': hostWarn,
                }"
            >
                {{ statusLabel }}
            </span>
        </header>

        <HostMetaChips
            v-if="host.public_ipv4 || host.provider_label"
            compact
            class="wb-host-meta"
            :public-ipv4="host.public_ipv4"
            :provider-label="host.provider_label"
            :provider-region="host.provider_region"
        />

        <HostStackChips
            v-if="host.stack && host.online"
            class="wb-host-stack"
            :stack="host.stack"
            :stack-warnings="host.stack_warnings"
        />

        <p
            v-if="host.agent_version && host.online"
            class="wb-host-agent numeric"
            :class="{ 'wb-host-agent--old': host.agent_outdated }"
        >
            {{ t('Agent') }} v{{ host.agent_version }}
        </p>

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
                <span class="wb-host-disk-bytes numeric">
                    {{ diskParts.bytes ?? '' }}
                </span>
                <span
                    class="wb-host-metric-val numeric"
                    :class="{
                        'text-danger': diskTone === 'danger',
                        'text-warning': diskTone === 'warning',
                    }"
                >
                    {{ diskParts.percent }}
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
    gap: 0.3em;
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
    flex: 1;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: var(--wb-host-name, 0.95rem);
    font-weight: 700;
    white-space: nowrap;
}

.wb-host-chip {
    flex-shrink: 0;
    padding: 0.1em 0.4em;
    font-size: calc(var(--wb-host-name, 0.95rem) * 0.65);
    font-weight: 800;
    line-height: 1.2;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-radius: 0.2rem;
}

.wb-host-chip--ok {
    color: color-mix(in oklch, var(--success) 25%, white);
    background: color-mix(in oklch, var(--success) 55%, transparent);
}

.wb-host-chip--down {
    color: white;
    background: var(--danger);
}

.wb-host-chip--warn {
    color: color-mix(in oklch, var(--warning) 15%, #111);
    background: var(--warning);
}

.wb-host-meta {
    font-size: calc(var(--wb-host-name, 0.95rem) * 0.68);
}

.wb-host-meta :deep(.chip) {
    padding: 0.08em 0.35em;
    font-size: inherit;
}

.wb-host-stack {
    font-size: calc(var(--wb-host-name, 0.95rem) * 0.62);
}

.wb-host-stack :deep(.chip) {
    padding: 0.06em 0.3em;
}

.wb-host-agent {
    margin: 0;
    font-size: calc(var(--wb-host-name, 0.95rem) * 0.65);
    color: var(--muted-foreground);
}

.wb-host-agent--old {
    font-weight: 700;
    color: var(--warning);
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
    grid-template-columns: 2.5em 1fr 2.75em;
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

.wb-host-disk-bytes {
    overflow: hidden;
    font-size: calc(var(--wb-host-name, 0.95rem) * 0.82);
    font-weight: 400;
    line-height: 1.15;
    color: var(--muted-foreground);
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
