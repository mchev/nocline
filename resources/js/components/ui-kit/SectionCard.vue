<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        padded?: boolean;
        glass?: boolean;
    }>(),
    { padded: true, glass: false },
);
</script>

<template>
    <section
        :class="[
            glass ? 'surface-glass' : 'surface',
            'overflow-hidden',
            padded ? '' : '',
        ]"
    >
        <header
            v-if="title || $slots.actions"
            class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4"
        >
            <div class="min-w-0 space-y-0.5">
                <h2 v-if="title" class="font-display text-lg font-semibold">
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="text-sm text-pretty text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <div :class="padded ? 'p-5' : ''">
            <slot />
        </div>
    </section>
</template>
