<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0 space-y-1">
            <p
                v-if="eyebrow"
                class="text-[0.65rem] font-medium tracking-[0.12em] text-muted-foreground uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1
                class="font-display text-2xl font-semibold tracking-tight text-balance md:text-[1.75rem]"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="max-w-prose text-sm text-pretty text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </header>
</template>
