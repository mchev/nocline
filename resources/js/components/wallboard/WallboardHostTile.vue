<script setup lang="ts">
import { computed } from 'vue';
import Sparkline from '@/components/ui-kit/Sparkline.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { hostMetricTone } from '@/lib/hostMetricTone';
import { t } from '@/lib/i18n';
import type { HostMetrics } from '@/components/HostCard.vue';

const props = defineProps<{
    host: {
        id: number;
        name: string;
        online: boolean;
        metrics: HostMetrics;
    };
}>();

const status = computed(() => (props.host.online ? 'up' as const : 'down' as const));

const pct = (v: number | null) => (v === null ? '—' : `${Math.round(v)}%`);

const freeDisk = computed(() =>
    props.host.metrics.disk_now === null
        ? null
        : Math.max(0, 100 - props.host.metrics.disk_now),
);

const rows = computed(() => [
    {
        key: 'cpu',
        label: t('CPU'),
        now: props.host.metrics.cpu_now,
        series: props.host.metrics.cpu,
        display: pct(props.host.metrics.cpu_now),
        invertTone: false as const,
        toneNow: props.host.metrics.cpu_now,
    },
    {
        key: 'mem',
        label: t('RAM'),
        now: props.host.metrics.mem_now,
        series: props.host.metrics.mem,
        display: pct(props.host.metrics.mem_now),
        invertTone: false as const,
        toneNow: props.host.metrics.mem_now,
    },
    {
        key: 'disk',
        label: t('Disk'),
        now: props.host.metrics.disk_now,
        series: props.host.metrics.disk.map((v) =>
            v === null ? null : Math.max(0, 100 - v),
        ),
        display:
            freeDisk.value === null ? '—' : `${Math.round(freeDisk.value)}%`,
        invertTone: true as const,
        toneNow: freeDisk.value,
    },
]);
</script>

<template>
    <li
        class="wb-host-card"
        :class="host.online ? 'wb-host-card--up' : 'wb-host-card--down'"
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
                v-for="row in rows"
                :key="row.key"
                class="wb-host-metric-row"
            >
                <span class="wb-host-metric-label">{{ row.label }}</span>
                <Sparkline
                    subtle
                    class="wb-host-spark"
                    :values="row.series"
                    :tone="
                        hostMetricTone(
                            row.invertTone
                                ? (row.toneNow ?? null)
                                : row.now,
                            row.invertTone,
                        )
                    "
                    :label="`${row.label} ${row.display}`"
                />
                <span class="wb-host-metric-val numeric">{{ row.display }}</span>
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