<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';

export type UptimeHistoryDot = 'up' | 'down' | 'unknown' | 'maintenance';

export type UptimeTimelineBucket = {
    s: UptimeHistoryDot;
    at: string;
    error?: string | null;
};

const props = withDefaults(
    defineProps<{
        history?: UptimeHistoryDot[] | null;
        timeline?: UptimeTimelineBucket[] | null;
        uptimePct?: number | null;
        showPercent?: boolean;
        showAxisLabels?: boolean;
        windowMinutes?: number | null;
        dense?: boolean;
        hours?: number;
        fixedSlots?: number | null;
        variant?: 'default' | 'board' | 'wallboard';
    }>(),
    {
        history: () => [],
        timeline: () => [],
        uptimePct: null,
        showPercent: false,
        showAxisLabels: false,
        windowMinutes: null,
        dense: false,
        hours: 24,
        fixedSlots: null,
        variant: 'default',
    },
);

const rawBuckets = computed((): UptimeTimelineBucket[] => {
    if (props.timeline?.length) {
        return props.timeline;
    }

    return (props.history ?? []).map((s) => ({
        s,
        at: '',
        error: null,
    }));
});

const slotTarget = computed(() => props.fixedSlots ?? rawBuckets.value.length);

const slots = computed((): UptimeTimelineBucket[] => {
    const h = rawBuckets.value;
    const n = slotTarget.value;

    if (n < 1) {
        return h;
    }

    const empty = (s: UptimeHistoryDot): UptimeTimelineBucket => ({
        s,
        at: '',
        error: null,
    });

    if (h.length >= n) {
        return h.slice(-n);
    }

    return [
        ...Array.from({ length: n - h.length }, () => empty('unknown')),
        ...h,
    ];
});

const observed = computed(() => slots.value.filter((b) => b.s !== 'unknown'));

const pctLabel = computed(() => {
    if (props.uptimePct !== null) {
        return `${props.uptimePct % 1 === 0 ? props.uptimePct.toFixed(0) : props.uptimePct.toFixed(1)}%`;
    }

    if (observed.value.length === 0) {
        return null;
    }

    const good = observed.value.filter(
        (b) => b.s === 'up' || b.s === 'maintenance',
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
        unknown: 'bg-muted-foreground/40',
    })[d];

const statusTitle = (d: UptimeHistoryDot) =>
    ({
        up: t('UP'),
        down: t('DOWN'),
        maintenance: t('MAINTENANCE'),
        unknown: t('Unknown'),
    })[d];

const statusTitleClass = (d: UptimeHistoryDot) =>
    ({
        up: 'text-success',
        down: 'text-danger',
        maintenance: 'text-info',
        unknown: 'text-muted-foreground',
    })[d];

const formatBucketAt = (iso: string) => {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return d.toLocaleString(undefined, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });
};

const rangeMinutes = computed(() => {
    if (props.windowMinutes !== null && props.windowMinutes > 0) {
        return props.windowMinutes;
    }

    return Math.max(1, Math.round(props.hours * 60));
});

const rangeStartLabel = computed(() => `${rangeMinutes.value}m`);

const summary = computed(() => {
    if (!observed.value.length) {
        if (rangeMinutes.value < 60) {
            return t('No history for the last :minutes minutes yet', {
                minutes: rangeMinutes.value,
            });
        }

        return t('No history for the last :hours hours yet', {
            hours: props.hours,
        });
    }

    const down = observed.value.filter((b) => b.s === 'down').length;

    if (down === 0 && pctLabel.value !== null) {
        return t(':percent uptime over the last :minutes minutes', {
            percent: pctLabel.value,
            minutes: rangeMinutes.value,
        });
    }

    return t(
        ':percent uptime over the last :minutes minutes · :down down period(s)',
        {
            percent: pctLabel.value ?? '—',
            minutes: rangeMinutes.value,
            down,
        },
    );
});

const slotCount = computed(() => slotTarget.value);

const isBoard = computed(
    () => props.variant === 'board' || props.variant === 'wallboard',
);
const isWallboard = computed(() => props.variant === 'wallboard');

const trackStyle = computed(() => ({
    gridTemplateColumns: `repeat(${slotCount.value}, minmax(0, 1fr))`,
}));
</script>

<template>
    <div
        v-if="slotCount"
        class="uptime-history flex min-w-0 items-center gap-2"
        :class="isWallboard ? 'uptime-history--wallboard' : ''"
        role="img"
        :aria-label="summary"
    >
        <span
            v-if="showPercent && pctLabel"
            class="chip shrink-0 px-2 py-0.5 text-center numeric font-semibold"
            :class="[isWallboard ? 'w-14 text-sm' : 'w-11 text-xs', pctTone]"
        >
            {{ pctLabel }}
        </span>
        <div class="min-w-0 flex-1">
            <div
                class="uptime-dots-track grid w-full min-w-0 gap-px"
                :class="isBoard ? '' : dense ? 'max-w-[9rem]' : 'max-w-[12rem]'"
                :style="trackStyle"
            >
                <div
                    v-for="(bucket, i) in slots"
                    :key="`d-${i}`"
                    class="group/dot relative flex min-w-0 justify-center"
                >
                    <span
                        class="uptime-dot block w-full max-w-full rounded-full"
                        :class="dotClass(bucket.s)"
                        :title="
                            !isBoard
                                ? `${statusTitle(bucket.s)} · ${formatBucketAt(bucket.at)}`
                                : undefined
                        "
                        tabindex="0"
                    />
                    <div
                        v-if="isBoard"
                        class="timeline-popover pointer-events-none absolute top-[calc(100%+0.35rem)] left-1/2 z-[120] w-max max-w-[14rem] -translate-x-1/2 opacity-0 transition-opacity duration-150 group-focus-within/dot:opacity-100 group-hover/dot:opacity-100"
                        role="tooltip"
                    >
                        <p
                            class="text-xs font-bold tracking-wide uppercase"
                            :class="statusTitleClass(bucket.s)"
                        >
                            {{ statusTitle(bucket.s) }}
                        </p>
                        <p
                            v-if="bucket.at"
                            class="mt-1 font-mono text-[0.65rem] text-muted-foreground"
                        >
                            {{ formatBucketAt(bucket.at) }}
                        </p>
                        <template v-if="bucket.error && bucket.s === 'down'">
                            <hr class="timeline-popover-rule" />
                            <p
                                class="line-clamp-3 text-xs leading-snug text-foreground/90"
                            >
                                {{ bucket.error }}
                            </p>
                        </template>
                    </div>
                </div>
            </div>
            <div
                v-if="isBoard && showAxisLabels"
                class="mt-1 flex justify-between text-[0.65rem] text-muted-foreground"
                aria-hidden="true"
            >
                <span>{{ rangeStartLabel }}</span>
                <span>{{ t('Now') }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.uptime-dots-track {
    align-items: center;
}

.uptime-dot {
    aspect-ratio: 1;
}

.timeline-popover {
    border-radius: 0.375rem;
    border: 1px solid var(--color-border);
    background: var(--color-popover);
    color: var(--color-popover-foreground);
    padding: 0.5rem 0.65rem;
    box-shadow:
        0 4px 16px rgb(0 0 0 / 0.35),
        0 0 0 1px rgb(255 255 255 / 0.04);
}

.timeline-popover::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: 100%;
    margin-left: -5px;
    border: 5px solid transparent;
    border-bottom-color: var(--color-border);
}

.timeline-popover-rule {
    margin: 0.4rem 0;
    border: 0;
    border-top: 1px solid var(--color-border);
}

.uptime-history--wallboard .uptime-dot {
    min-height: 0.55rem;
}
</style>
