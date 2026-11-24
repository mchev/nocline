<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';

export type LatencyPoint = {
    at: string;
    latency_ms: number | null;
    ok: boolean;
};

export type DownInterval = {
    start: string;
    end: string;
};

const props = defineProps<{
    points: LatencyPoint[];
    downIntervals: DownInterval[];
    hours: number;
}>();

const width = 880;
const height = 240;
const pad = { top: 16, right: 16, bottom: 32, left: 52 };
const plotW = width - pad.left - pad.right;
const plotH = height - pad.top - pad.bottom;

const timeExtent = computed(() => {
    const end = Date.now();
    const start = end - props.hours * 3600 * 1000;

    return { start, end };
});

const maxLatency = computed(() => {
    const values = props.points
        .map((p) => p.latency_ms)
        .filter((v): v is number => v !== null && v > 0);

    if (values.length === 0) {
        return 500;
    }

    const m = Math.max(...values);

    return Math.ceil(m / 100) * 100 + 100;
});

function xAt(iso: string): number {
    const t = Date.parse(iso);
    const { start, end } = timeExtent.value;
    const ratio = (t - start) / Math.max(1, end - start);

    return pad.left + ratio * plotW;
}

function yAt(ms: number | null): number {
    if (ms === null || ms < 0) {
        return pad.top + plotH;
    }

    const ratio = Math.min(1, ms / maxLatency.value);

    return pad.top + (1 - ratio) * plotH;
}

const linePath = computed(() => {
    const segments: string[] = [];

    for (const p of props.points) {
        if (p.latency_ms === null || !p.ok) {
            continue;
        }

        const x = xAt(p.at);
        const y = yAt(p.latency_ms);
        segments.push(
            `${segments.length === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`,
        );
    }

    return segments.join(' ');
});

const downRects = computed(() =>
    props.downIntervals.map((iv) => {
        const x1 = xAt(iv.start);
        const x2 = xAt(iv.end);

        return {
            x: Math.min(x1, x2),
            w: Math.max(2, Math.abs(x2 - x1)),
        };
    }),
);

const yTicks = computed(() => {
    const step = maxLatency.value <= 400 ? 100 : 200;
    const ticks: number[] = [];

    for (let v = 0; v <= maxLatency.value; v += step) {
        ticks.push(v);
    }

    return ticks;
});

const xLabels = computed(() => {
    const { start, end } = timeExtent.value;
    const n = 5;

    return Array.from({ length: n + 1 }, (_, i) => {
        const t = start + ((end - start) * i) / n;

        return {
            x: pad.left + (i / n) * plotW,
            label: new Date(t).toLocaleTimeString(undefined, {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            }),
        };
    });
});
</script>

<template>
    <div class="w-full overflow-x-auto">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="h-auto w-full min-w-[36rem] text-muted-foreground"
            role="img"
            :aria-label="t('Response time (ms)')"
        >
            <rect
                :x="pad.left"
                :y="pad.top"
                :width="plotW"
                :height="plotH"
                class="fill-muted/30"
                rx="4"
            />
            <rect
                v-for="(r, i) in downRects"
                :key="`down-${i}`"
                :x="r.x"
                :y="pad.top"
                :width="r.w"
                :height="plotH"
                class="fill-danger/25"
            />
            <g aria-hidden="true">
                <line
                    v-for="tick in yTicks"
                    :key="`y-${tick}`"
                    :x1="pad.left"
                    :x2="pad.left + plotW"
                    :y1="yAt(tick)"
                    :y2="yAt(tick)"
                    class="stroke-border/60"
                    stroke-width="1"
                />
                <text
                    v-for="tick in yTicks"
                    :key="`yl-${tick}`"
                    :x="pad.left - 8"
                    :y="yAt(tick) + 4"
                    text-anchor="end"
                    class="fill-current text-[10px]"
                >
                    {{ tick }}
                </text>
            </g>
            <path
                v-if="linePath"
                :d="linePath"
                fill="none"
                class="stroke-success"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
            <g aria-hidden="true">
                <text
                    v-for="(lbl, i) in xLabels"
                    :key="`x-${i}`"
                    :x="lbl.x"
                    :y="height - 8"
                    text-anchor="middle"
                    class="fill-current text-[10px]"
                >
                    {{ lbl.label }}
                </text>
            </g>
        </svg>
    </div>
</template>
