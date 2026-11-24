<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center px-6 py-10 md:px-12">
        <div class="w-full max-w-sm space-y-7">
            <div class="space-y-4">
                <Link
                    :href="home()"
                    class="flex w-fit items-center gap-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-4" />
                    </span>
                    <span
                        class="font-display text-lg font-semibold tracking-tight lowercase"
                    >
                        phare<span class="text-primary">.</span>
                    </span>
                    <span class="sr-only">{{ title ? $t(title) : '' }}</span>
                </Link>
                <div class="space-y-1.5">
                    <h1
                        class="font-display text-2xl font-semibold tracking-tight text-balance"
                    >
                        {{ title ? $t(title) : '' }}
                    </h1>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{ description ? $t(description) : '' }}
                    </p>
                </div>
            </div>
            <slot />
        </div>
    </div>
</template>
