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
    <div class="flex min-h-svh flex-col justify-center px-6 py-10 md:px-12">
        <div class="w-full max-w-sm space-y-6">
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
            </Link>

            <div class="space-y-6">
                <header v-if="title" class="space-y-1.5">
                    <h1
                        class="font-display text-2xl font-semibold tracking-tight text-balance"
                    >
                        {{ $t(title) }}
                    </h1>
                    <p
                        v-if="description"
                        class="text-sm text-pretty text-muted-foreground"
                    >
                        {{ $t(description) }}
                    </p>
                </header>
                <slot />
            </div>
        </div>
    </div>
</template>
