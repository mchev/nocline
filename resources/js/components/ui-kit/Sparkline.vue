<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        values: (number | null)[];
        max?: number;
        tone?: 'default' | 'success' | 'warning' | 'danger' | 'primary';
        label?: string;
    }>(),
    {
        max: 100,
        tone: 'default',
        label: undefined,
    },
);

const width = 120;
const height = 32;
const pad = 1;

const path = computed(() => {
    const pts = props.values
        .map((v, i) => (v === null || Number.isNaN(v) ? null : { i, v: Math.min(props.max, Math.max(0, v)) }))
        .filter((p): p is { i: number; v: number } => p !== null);

    if (pts.length < 2) {
        return '';
    }

    const n = Math.max(props.values.length - 1, 1);
    const max = props.max || 100;

    return pts
        .map((p, idx) => {
            const x = pad + (p.i / n) * (width - pad * 2);
            const y = height - pad - (p.v / max) * (height - pad * 2);

            return `${idx === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .join(' ');
});

const stroke = computed(
    () =>
        ({
            default: 'var(--color-muted-foreground)',
            success: 'var(--color-success)',
            warning: 'var(--color-warning)',
            danger: 'var(--color-danger)',
            primary: 'var(--color-primary)',
        })[props.tone],
);
</script>

<template>
    <svg
        class="block h-8 w-full overflow-visible"
        :viewBox="`0 0 ${width} ${height}`"
        preserveAspectRatio="none"
        role="img"
        :aria-label="label || undefined"
        :aria-hidden="label ? undefined : true"
    >
        <path
            v-if="path"
            :d="path"
            fill="none"
            :stroke="stroke"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
        <line
            v-else
            :x1="pad"
            :x2="width - pad"
            :y1="height / 2"
            :y2="height / 2"
            :stroke="stroke"
            stroke-width="1"
            stroke-dasharray="2 3"
            opacity="0.4"
        />
    </svg>
</template>
