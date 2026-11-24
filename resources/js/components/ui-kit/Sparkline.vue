<script setup lang="ts">
import { computed, useId } from 'vue';
import type { MetricSparklineTone } from '@/lib/hostMetricTone';

const props = withDefaults(
    defineProps<{
        values: (number | null)[];
        max?: number;
        tone?: MetricSparklineTone | 'success' | 'primary';
        label?: string;
        /** Lighter track (server cards). */
        subtle?: boolean;
    }>(),
    {
        max: 100,
        tone: 'default',
        label: undefined,
        subtle: false,
    },
);

const width = 120;
const height = 28;
const padX = 2;
const padY = 3;
const uid = useId();
const gradId = `sparkline-fill-${uid}`;

const points = computed(() => {
    const n = Math.max(props.values.length - 1, 1);
    const max = props.max || 100;

    return props.values
        .map((v, i) =>
            v === null || Number.isNaN(v)
                ? null
                : {
                      i,
                      x: padX + (i / n) * (width - padX * 2),
                      y:
                          height -
                          padY -
                          (Math.min(max, Math.max(0, v)) / max) *
                              (height - padY * 2),
                  },
        )
        .filter((p): p is { i: number; x: number; y: number } => p !== null);
});

const linePath = computed(() => {
    const pts = points.value;

    if (pts.length < 2) {
        return '';
    }

    return pts
        .map(
            (p, idx) =>
                `${idx === 0 ? 'M' : 'L'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`,
        )
        .join(' ');
});

const areaPath = computed(() => {
    const pts = points.value;
    const line = linePath.value;

    if (!line || pts.length < 2) {
        return '';
    }

    const first = pts[0];
    const last = pts[pts.length - 1];
    const base = height - padY;

    return `${line} L ${last.x.toFixed(1)} ${base} L ${first.x.toFixed(1)} ${base} Z`;
});

const stroke = computed(
    () =>
        ({
            default: 'var(--color-muted-foreground)',
            neutral: 'var(--color-chart-2)',
            success: 'var(--color-success)',
            warning: 'var(--color-warning)',
            danger: 'var(--color-danger)',
            primary: 'var(--color-primary)',
        })[props.tone],
);

const hasSeries = computed(() => linePath.value !== '');

const fillOpacityTop = computed(() => (props.subtle ? 0.18 : 0.38));
const fillOpacityMid = computed(() => (props.subtle ? 0.04 : 0.06));
const trackClass = computed(() =>
    props.subtle
        ? 'border border-border/25 bg-transparent'
        : 'bg-muted/35 dark:bg-muted/25',
);
</script>

<template>
    <div
        class="sparkline-track relative h-7 w-full overflow-hidden rounded-sm"
        :class="trackClass"
        role="img"
        :aria-label="label || undefined"
        :aria-hidden="label ? undefined : true"
    >
        <svg
            class="absolute inset-0 h-full w-full"
            :viewBox="`0 0 ${width} ${height}`"
            preserveAspectRatio="none"
        >
            <defs>
                <linearGradient :id="gradId" x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        :stop-color="stroke"
                        :stop-opacity="fillOpacityTop"
                    />
                    <stop
                        offset="85%"
                        :stop-color="stroke"
                        :stop-opacity="fillOpacityMid"
                    />
                    <stop offset="100%" :stop-color="stroke" stop-opacity="0" />
                </linearGradient>
            </defs>
            <line
                :x1="padX"
                :x2="width - padX"
                :y1="height - padY"
                :y2="height - padY"
                stroke="var(--color-border)"
                stroke-width="1"
                vector-effect="non-scaling-stroke"
                :opacity="subtle ? 0.3 : 0.55"
            />
            <path
                v-if="areaPath && !subtle"
                :d="areaPath"
                :fill="`url(#${gradId})`"
                stroke="none"
            />
            <path
                v-if="hasSeries"
                :d="linePath"
                fill="none"
                :stroke="stroke"
                :stroke-width="subtle ? 1.35 : 1.75"
                stroke-linecap="round"
                stroke-linejoin="round"
                vector-effect="non-scaling-stroke"
            />
            <line
                v-else
                :x1="padX"
                :x2="width - padX"
                :y1="height / 2"
                :y2="height / 2"
                :stroke="stroke"
                stroke-width="1.25"
                stroke-dasharray="3 4"
                stroke-linecap="round"
                opacity="0.45"
                vector-effect="non-scaling-stroke"
            />
        </svg>
    </div>
</template>
