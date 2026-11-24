<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Activity, BellRing, ShieldCheck } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const points = [
    { icon: Activity, text: 'Know when a site or server goes down' },
    { icon: BellRing, text: 'Get warned before certificates expire' },
    { icon: ShieldCheck, text: 'Self-hosted: your data stays with you' },
];
</script>

<template>
    <div
        class="grid min-h-svh bg-background lg:grid-cols-[minmax(16rem,22rem)_minmax(0,1fr)]"
    >
        <aside
            class="relative hidden flex-col justify-between border-r bg-card p-8 lg:flex xl:p-10"
        >
            <Link
                :href="home()"
                class="relative flex w-fit items-center gap-2.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5" />
                </span>
                <span
                    class="font-display text-lg font-semibold tracking-tight lowercase"
                >
                    phare<span class="text-primary">.</span>
                </span>
            </Link>

            <div class="relative space-y-6">
                <p
                    class="font-display text-3xl leading-tight font-semibold tracking-tight text-balance xl:text-4xl"
                >
                    {{ $t('Keep watch, so you can sleep.') }}
                </p>
                <ul class="space-y-3 text-sm text-muted-foreground">
                    <li
                        v-for="p in points"
                        :key="p.text"
                        class="flex items-start gap-2.5"
                    >
                        <component
                            :is="p.icon"
                            class="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        {{ $t(p.text) }}
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-muted-foreground">phare.</p>
        </aside>

        <main class="flex flex-col justify-center px-6 py-10 md:px-12 lg:px-16">
            <div class="w-full max-w-sm space-y-7">
                <Link
                    :href="home()"
                    class="flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:hidden"
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

                <header v-if="title" class="space-y-1.5">
                    <h1
                        class="font-display text-2xl font-semibold tracking-tight text-balance md:text-3xl"
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
        </main>
    </div>
</template>
