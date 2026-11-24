<script setup lang="ts">
withDefaults(
    defineProps<{
        status: 'up' | 'down' | 'warning' | 'unknown' | 'maintenance';
        pulse?: boolean;
        label?: string;
    }>(),
    { pulse: false },
);
</script>

<template>
    <span
        class="inline-block size-2.5 shrink-0 rounded-full"
        :class="[
            {
                'bg-success text-success': status === 'up',
                'bg-danger text-danger': status === 'down',
                'bg-warning text-warning': status === 'warning',
                'bg-info text-info': status === 'maintenance',
                'bg-muted-foreground/40 text-muted-foreground': status === 'unknown',
            },
            pulse && status === 'down'
                ? 'motion-safe:animate-[pulse-ring_1.6s_ease-out_infinite]'
                : '',
        ]"
        :role="label ? 'img' : undefined"
        :aria-label="label"
        :aria-hidden="label ? undefined : true"
    />
</template>
