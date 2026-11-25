<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppWordmark from '@/components/AppWordmark.vue';
import { home } from '@/routes';
import { t } from '@/lib/i18n';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col justify-center px-4 py-8 sm:px-6 md:px-10"
    >
        <div
            class="mx-auto w-full max-w-[min(80vw,40rem)] space-y-8 lg:max-w-[min(80vw,48rem)]"
        >
            <Link
                :href="home()"
                class="flex w-fit items-center gap-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-4" />
                </span>
                <AppWordmark class="h-[1.125rem]" />
            </Link>

            <header v-if="title" class="space-y-1.5">
                <h1
                    class="font-display text-2xl font-semibold tracking-tight text-balance md:text-3xl"
                >
                    {{ t(title) }}
                </h1>
                <p
                    v-if="description"
                    class="text-sm text-pretty text-muted-foreground"
                >
                    {{ t(description) }}
                </p>
            </header>

            <slot />
        </div>
    </div>
</template>
