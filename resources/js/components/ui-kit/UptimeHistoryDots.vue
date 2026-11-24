<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';

export type UptimeHistoryDot = 'up' | 'down' | 'unknown' | 'maintenance';

const props = withDefaults(
    defineProps<{
        history?: UptimeHistoryDot[] | null;
        uptimePct?: number | null;
        showPercent?: boolean;
        dense?: boolean;
        hours?: number;
    }>(),
    {
        history: () => [],
        uptimePct: null,
        showPercent: false,
        dense: false,
        hours: 24,
    },
);

const observed = computed(() =>
    (props.history ?? []).filter((d) => d !== 'unknown'),
);

const pctLabel = computed(() => {
    if (props.uptimePct !== null) {
        return `${props.uptimePct % 1 === 0 ? props.uptimePct.toFixed(0) : props.uptimePct.toFixed(1)}%`;
    }

    if (observed.value.length === 0) {
        return null;
    }

    const good = observed.value.filter(
        (d) => d === 'up' || d === 'maintenance',
    ).length;
    const pct = Math.floor((good / observed.value.length) * 1000) / 10;

    return `${pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1)}%`;
});

const pctTone = computed(() => {
    if (pctLabel.value === null) {
        return '';
    }

    const n = parseFloat(pctLabel.value);

    if (n >= 99.9) {
        return 'chip-success';
    }

    if (n >= 95) {
        return 'chip-warning';
    }

    return 'chip-danger';
});

const dotClass = (d: UptimeHistoryDot) =>
    ({
        up: 'bg-success',
        down: 'bg-danger',
        maintenance: 'bg-info',
        unknown: 'bg-muted/50',
    })[d];

const dotLabel = (d: UptimeHistoryDot) =>
    ({
        up: t('Up'),
        down: t('Down'),
        maintenance: t('Maintenance'),
        unknown: t('Unknown'),
    })[d];

const summary = computed(() => {
    if (!observed.value.length) {
        return t('No history for the last :hours hours yet', {
            hours: props.hours,
        });
    }

    const down = observed.value.filter((d) => d === 'down').length;

    if (down === 0 && pctLabel.value !== null) {
        return t(':percent uptime over the last :hours hours', {
            percent: pctLabel.value,
            hours: props.hours,
        });
    }

    return t(
        ':percent uptime over the last :hours hours · :down down period(s)',
        { percent: pctLabel.value ?? '—', hours: props.hours, down },
    );
});
</script>

<template>
    <div
        v-if="history?.length"
        class="flex min-w-0 items-center gap-2"
        role="img"
        :aria-label="summary"
    >
        <span
            v-if="showPercent && pctLabel"
            class="chip shrink-0 px-2 py-0.5 numeric text-xs font-semibold"
            :class="pctTone"
        >
            {{ pctLabel }}
        </span>
        <div
            class="flex min-w-0 flex-1 items-end gap-px"
            :class="dense ? 'h-4 max-w-[9rem]' : 'h-5 max-w-[12rem]'"
        >
            <span
                v-for="(d, i) in history"
                :key="i"
                class="min-w-0 flex-1 rounded-full"
                :class="[
                    dotClass(d),
                    dense ? 'min-h-[0.5rem]' : 'min-h-[0.65rem]',
                ]"
                :title="dotLabel(d)"
            />
        </div>
    </div>
</template>
