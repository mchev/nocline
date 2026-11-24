<script setup lang="ts">
import { Globe } from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        url?: string | null;
        label: string;
        sizeClass?: string;
    }>(),
    { sizeClass: 'size-5' },
);
</script>

<template>
    <img
        v-if="url"
        :src="url"
        :alt="label"
        :class="[
            props.sizeClass,
            'shrink-0 rounded-sm bg-muted object-contain',
        ]"
        loading="lazy"
        decoding="async"
    />
    <Globe
        v-else
        :class="[props.sizeClass, 'shrink-0 text-muted-foreground']"
        aria-hidden="true"
    />
</template>
