<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';

export type HostMetricPoint = {
    at: string;
    cpu: number | null;
    mem: number | null;
    disk: number | null;
};

const props = defineProps<{
    points: HostMetricPoint[];
    hours: number;
    endAt: string;
}>();

const width = 880;
const height = 260;
const pad = { top: 28, right: 16, bottom: 36, left: 44 };
const plotW = width - pad.left - pad.right;
const plotH = height - pad.top - pad.bottom;

const timeExtent = computed(() => {
    const end = Date.parse(props.endAt);
    const start = end - props.hours * 3600 * 1000;

    return { start, end };
});

function xAt(iso: string): number {
    const ts = Date.parse(iso);
    const { start, end } = timeExtent.value;
    const ratio = (ts - start) / Math.max(1, end - start);

    return pad.left + ratio * plotW;
}

function yAt(pct: number): number {
    const ratio = Math.min(1, Math.max(0, pct / 100));

    return pad.top + (1 - ratio) * plotH;
}

function lineFor(series: 'cpu' | 'mem' | 'disk'): string {
    const segments: string[] = [];

    for (const p of props.points) {
        const v = p[series];

        if (v === null) {
            continue;
        }

        const x = xAt(p.at);
        const y = yAt(v);
        segments.push(
            `${segments.length === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`,
        );
    }

    return segments.join(' ');
}

const cpuPath = computed(() => lineFor('cpu'));
const memPath = computed(() => lineFor('mem'));
const diskPath = computed(() => lineFor('disk'));

const yTicks = [0, 25, 50, 75, 100];

const xLabels = computed(() => {
    const { start, end } = timeExtent.value;
    const n = 5;
    const longRange = props.hours > 48;

    return Array.from({ length: n + 1 }, (_, i) => {
        const ts = start + ((end - start) * i) / n;
        const d = new Date(ts);

        return {
            x: pad.left + (i / n) * plotW,
            label: longRange
                ? d.toLocaleDateString(undefined, {
                      month: 'short',
                      day: 'numeric',
                  })
                : d.toLocaleTimeString(undefined, {
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
        <div
            class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            aria-hidden="true"
        >
            <span class="inline-flex items-center gap-1.5">
                <span
                    class="inline-block size-2.5 rounded-full bg-primary"
                />{{ t('CPU') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span
                    class="inline-block size-2.5 rounded-full bg-success"
                />{{ t('RAM') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span
                    class="inline-block size-2.5 rounded-full bg-warning"
                />{{ t('Disk') }}
            </span>
        </div>
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="h-auto w-full min-w-[36rem] text-muted-foreground"
            role="img"
            :aria-label="t('Server load (CPU, RAM, disk)')"
        >
            <rect
                :x="pad.left"
                :y="pad.top"
                :width="plotW"
                :height="plotH"
                class="fill-muted/30"
                rx="4"
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
                    {{ tick }}%
                </text>
            </g>
            <path
                v-if="cpuPath"
                :d="cpuPath"
                fill="none"
                class="stroke-primary"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
            <path
                v-if="memPath"
                :d="memPath"
                fill="none"
                class="stroke-success"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
            <path
                v-if="diskPath"
                :d="diskPath"
                fill="none"
                class="stroke-warning"
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