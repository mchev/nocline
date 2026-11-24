<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const name = usePage().props.name;
</script>

<template>
    <div class="flex min-h-svh flex-col justify-center px-6 py-10 md:px-12">
        <div class="w-full max-w-sm space-y-7">
            <div class="space-y-4">
                <Link
                    :href="home()"
                    class="flex w-fit items-center gap-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-4 fill-current" />
                    </span>
                    <span class="font-display text-lg font-semibold tracking-tight">{{
                        name
                    }}</span>
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
