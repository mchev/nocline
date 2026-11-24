<script setup lang="ts">
import { computed } from 'vue';
import Sparkline from '@/components/ui-kit/Sparkline.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { t } from '@/lib/i18n';

export type HostMetrics = {
    cpu: (number | null)[];
    mem: (number | null)[];
    disk: (number | null)[];
    cpu_now: number | null;
    mem_now: number | null;
    disk_now: number | null;
};

const props = withDefaults(
    defineProps<{
        name: string;
        online: boolean;
        enrolled?: boolean;
        metrics: HostMetrics;
        agentVersion?: string | null;
        agentOutdated?: boolean;
        lastSeenAt?: string | null;
        compact?: boolean;
    }>(),
    { compact: false },
);

const status = computed(() => {
    if (props.enrolled === false) {
        return 'unknown' as const;
    }

    return props.online ? ('up' as const) : ('down' as const);
});

const statusLabel = computed(() => {
    if (props.enrolled === false) {
        return t('Not enrolled');
    }

    return props.online ? t('Online') : t('Offline');
});

const toneFor = (v: number | null, invert = false) => {
    if (v === null) {
        return 'default' as const;
    }

    if (invert) {
        if (v <= 10) {
            return 'danger' as const;
        }

        if (v <= 25) {
            return 'warning' as const;
        }

        return 'primary' as const;
    }

    if (v >= 90) {
        return 'danger' as const;
    }

    if (v >= 75) {
        return 'warning' as const;
    }

    return 'primary' as const;
};

const pct = (v: number | null) => (v === null ? '—' : `${Math.round(v)}%`);

const freeDisk = computed(() =>
    props.metrics.disk_now === null
        ? null
        : Math.max(0, 100 - props.metrics.disk_now),
);

const rows = computed(() => [
    {
        key: 'cpu',
        label: t('CPU'),
        now: props.metrics.cpu_now,
        series: props.metrics.cpu,
        display: pct(props.metrics.cpu_now),
    },
    {
        key: 'mem',
        label: t('RAM'),
        now: props.metrics.mem_now,
        series: props.metrics.mem,
        display: pct(props.metrics.mem_now),
    },
    {
        key: 'disk',
        label: t('Disk free'),
        now: props.metrics.disk_now,
        series: props.metrics.disk.map((v) =>
            v === null ? null : Math.max(0, 100 - v),
        ),
        display:
            freeDisk.value === null ? '—' : `${Math.round(freeDisk.value)}%`,
        toneNow: freeDisk.value,
        invertTone: true,
    },
]);
</script>

<template>
    <article
        class="host-card flex flex-col surface"
        :class="{
            'gap-3 p-4': !compact,
            'gap-2 p-3': compact,
            'border-danger/40': enrolled !== false && !online,
            'opacity-70': enrolled === false,
        }"
    >
        <header class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h3
                    class="flex items-center gap-2 truncate font-display font-semibold"
                    :class="compact ? 'text-sm' : 'text-base'"
                >
                    <StatusDot
                        :status="status"
                        :pulse="enrolled !== false && !online"
                    />
                    <span class="truncate">{{ name }}</span>
                </h3>
                <p
                    v-if="agentVersion && !compact"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    agent {{ agentVersion }}
                    <span v-if="agentOutdated" class="ml-1 chip chip-warning">{{
                        t('update available')
                    }}</span>
                </p>
            </div>
            <span
                class="chip shrink-0"
                :class="{
                    'chip-success': enrolled !== false && online,
                    'chip-danger': enrolled !== false && !online,
                }"
            >
                {{ statusLabel }}
            </span>
        </header>

        <ul class="grid gap-2" role="list">
            <li
                v-for="row in rows"
                :key="row.key"
                class="grid grid-cols-[3.5rem_1fr_2.5rem] items-center gap-2"
            >
                <span class="text-xs font-medium text-muted-foreground">{{
                    row.label
                }}</span>
                <Sparkline
                    :values="row.series"
                    :tone="
                        toneFor(
                            row.invertTone ? (row.toneNow ?? null) : row.now,
                            !!row.invertTone,
                        )
                    "
                    :label="`${row.label} ${row.display}`"
                />
                <span
                    class="text-right numeric text-sm font-semibold tabular-nums"
                    :class="{
                        'text-danger': row.invertTone
                            ? (row.toneNow ?? 100) <= 10
                            : (row.now ?? 0) >= 90,
                        'text-warning': row.invertTone
                            ? (row.toneNow ?? 100) <= 25 &&
                              (row.toneNow ?? 100) > 10
                            : (row.now ?? 0) >= 75 && (row.now ?? 0) < 90,
                    }"
                >
                    {{ row.display }}
                </span>
            </li>
        </ul>

        <footer
            v-if="$slots.actions"
            class="mt-auto -mb-1 flex flex-wrap items-center gap-1 border-t pt-2"
        >
            <slot name="actions" />
        </footer>
    </article>
</template>

<style scoped>
.host-card {
    container: host-card / inline-size;
}
</style>
