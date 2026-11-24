<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        hint?: string;
        icon?: Component;
        tone?: 'default' | 'success' | 'warning' | 'danger' | 'info' | 'primary';
        variant?: 'default' | 'hero';
    }>(),
    { tone: 'default', variant: 'default' },
);
</script>

<template>
    <div
        class="surface relative flex h-full min-h-[8.25rem] flex-col overflow-hidden p-4"
        :class="variant === 'hero' ? 'ring-1 ring-primary/20' : ''"
    >
        <div
            v-if="variant === 'hero'"
            aria-hidden="true"
            class="pointer-events-none absolute -top-10 -right-8 size-36 rounded-full opacity-30 blur-2xl"
            :class="{
                'bg-primary/40': tone === 'primary' || tone === 'default',
                'bg-success/40': tone === 'success',
                'bg-warning/40': tone === 'warning',
                'bg-danger/40': tone === 'danger',
                'bg-info/40': tone === 'info',
            }"
        />
        <div class="relative flex items-start justify-between gap-3">
            <p class="text-sm leading-5 text-muted-foreground">{{ label }}</p>
            <span
                v-if="icon"
                class="grid size-8 shrink-0 place-items-center rounded-md"
                :class="{
                    'bg-muted text-muted-foreground': tone === 'default',
                    'bg-success/15 text-success': tone === 'success',
                    'bg-warning/20 text-warning': tone === 'warning',
                    'bg-danger/15 text-danger': tone === 'danger',
                    'bg-info/15 text-info': tone === 'info',
                    'bg-primary/15 text-primary': tone === 'primary',
                }"
            >
                <component :is="icon" class="size-4" aria-hidden="true" />
            </span>
        </div>
        <p class="numeric relative mt-3 text-4xl leading-none font-semibold tracking-tight">
            {{ value }}
        </p>
        <p
            class="relative mt-auto pt-3 text-xs leading-4 text-muted-foreground"
            :class="hint ? '' : 'invisible'"
            :aria-hidden="hint ? undefined : true"
        >
            {{ hint || '\u00a0' }}
        </p>
        <slot />
    </div>
</template>
