<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{ title: string; description?: string; icon?: Component }>();
</script>

<template>
    <div
        class="surface-muted flex flex-col items-start gap-2 border-dashed px-5 py-8"
    >
        <span
            v-if="icon"
            class="grid size-9 place-items-center rounded-md bg-primary/10 text-primary"
        >
            <component :is="icon" class="size-4" aria-hidden="true" />
        </span>
        <h2 class="font-display text-base font-semibold">{{ title }}</h2>
        <p
            v-if="description"
            class="max-w-prose text-sm text-pretty text-muted-foreground"
        >
            {{ description }}
        </p>
        <div
            v-if="$slots.default"
            class="mt-2 flex flex-wrap gap-2"
        >
            <slot />
        </div>
    </div>
</template>
