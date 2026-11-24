<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div
        class="grid gap-2 sm:grid-cols-3"
        role="group"
        :aria-label="$t('Appearance settings')"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            class="group flex min-h-20 flex-col items-start justify-center gap-2 rounded-md border bg-card p-3 text-sm font-medium text-muted-foreground transition hover:border-primary/40 hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none aria-pressed:border-primary aria-pressed:bg-primary/10 aria-pressed:text-foreground"
            @click="updateAppearance(value)"
        >
            <component :is="Icon" class="size-4" aria-hidden="true" />
            {{ $t(label) }}
        </button>
    </div>
</template>
