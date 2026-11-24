<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, CircleHelp, TriangleAlert, Wrench } from '@lucide/vue';
import { computed } from 'vue';
import StatusGroup from '@/components/StatusGroup.vue';
import { t } from '@/lib/i18n';
import type { StatusMonitor, StatusNode } from '@/types/status';

const props = defineProps<{
    title: string;
    description: string | null;
    logo_url: string | null;
    accent_color: string | null;
    generated_at: string;
    overall: 'operational' | 'degraded' | 'maintenance' | 'unknown';
    monitors: StatusMonitor[];
    groups: StatusNode[];
    maintenance: {
        name: string;
        starts_at: string;
        ends_at: string;
        active: boolean;
    }[];
    incidents: {
        monitor: string;
        started_at: string;
        resolved_at: string | null;
    }[];
    show_incidents: boolean;
    uptime_days: number;
}>();

const banner = computed(
    () =>
        ({
            operational: {
                text: t('All systems operational'),
                icon: CheckCircle2,
                cls: 'border-success/40 bg-success/15 text-success',
            },
            degraded: {
                text: t('Some systems are having problems'),
                icon: TriangleAlert,
                cls: 'border-danger/40 bg-danger/15 text-danger',
            },
            maintenance: {
                text: t('Planned maintenance in progress'),
                icon: Wrench,
                cls: 'border-info/40 bg-info/15 text-info',
            },
            unknown: {
                text: t('Waiting for the first results'),
                icon: CircleHelp,
                cls: 'border-muted-foreground/30 bg-muted text-muted-foreground',
            },
        })[props.overall],
);

const when = (iso: string) =>
    new Date(iso).toLocaleString([], {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
const accentStyle = computed(() =>
    props.accent_color ? { '--accent-color': props.accent_color } : {},
);
</script>

<template>
    <Head :title="title">
        <meta name="robots" content="noindex" />
        <meta name="color-scheme" content="light dark" />
    </Head>

    <div
        class="relative min-h-svh bg-background text-foreground"
        :style="accentStyle"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-96 opacity-70"
            :style="{
                background:
                    'radial-gradient(50rem 24rem at 50% -6rem, color-mix(in oklab, var(--accent-color, var(--primary)) 32%, transparent), transparent 70%)',
            }"
            aria-hidden="true"
        />

        <header class="relative mx-auto max-w-3xl px-6 pt-14 pb-4">
            <div class="flex flex-col items-start gap-5">
                <img
                    v-if="logo_url"
                    :src="logo_url"
                    :alt="title"
                    class="max-h-14 max-w-44 object-contain"
                />
                <div class="space-y-2">
                    <h1
                        class="font-display text-4xl font-semibold tracking-tight text-balance md:text-5xl"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="max-w-prose text-base text-pretty whitespace-pre-line text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
        </header>

        <main class="relative mx-auto flex max-w-3xl flex-col gap-8 p-6 pb-20">
            <div
                class="reveal flex items-center gap-4 surface-glass border p-6"
                :class="banner.cls"
                role="status"
            >
                <span
                    class="grid size-12 shrink-0 place-items-center rounded-md bg-current/15"
                >
                    <component
                        :is="banner.icon"
                        class="size-6"
                        aria-hidden="true"
                    />
                </span>
                <p
                    class="font-display text-xl font-semibold text-balance md:text-2xl"
                >
                    {{ banner.text }}
                </p>
            </div>

            <section
                v-if="maintenance.length"
                class="reveal reveal-delay-1"
                aria-labelledby="sp-maintenance"
            >
                <h2
                    id="sp-maintenance"
                    class="mb-3 text-sm font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('Planned maintenance') }}
                </h2>
                <ul class="divide-y overflow-hidden surface text-sm">
                    <li
                        v-for="(m, i) in maintenance"
                        :key="i"
                        class="flex flex-wrap items-center justify-between gap-2 p-4"
                    >
                        <span class="font-medium">{{ m.name }}</span>
                        <span class="text-muted-foreground">
                            {{ when(m.starts_at) }} → {{ when(m.ends_at) }}
                            <span v-if="m.active" class="ml-1 chip chip-info">
                                {{ t('In progress') }}
                            </span>
                        </span>
                    </li>
                </ul>
            </section>

            <div class="reveal reveal-delay-2 flex flex-col gap-6">
                <StatusGroup
                    v-if="monitors.length"
                    :monitors="monitors"
                    :uptime-days="uptime_days"
                />

                <StatusGroup
                    v-for="(g, i) in groups"
                    :key="i"
                    :node="g"
                    :uptime-days="uptime_days"
                />
            </div>

            <section
                v-if="show_incidents"
                class="reveal reveal-delay-3"
                aria-labelledby="sp-incidents"
            >
                <h2
                    id="sp-incidents"
                    class="mb-3 text-sm font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('Recent incidents') }}
                </h2>
                <p
                    v-if="incidents.length === 0"
                    class="surface-muted p-5 text-sm text-muted-foreground"
                >
                    {{ t('No incident in the last 14 days.') }}
                </p>
                <ul v-else class="divide-y overflow-hidden surface text-sm">
                    <li
                        v-for="(i, k) in incidents"
                        :key="k"
                        class="flex flex-wrap justify-between gap-2 p-4"
                    >
                        <span class="font-medium">{{
                            t(':name had a problem', { name: i.monitor })
                        }}</span>
                        <span class="text-muted-foreground">
                            {{ when(i.started_at) }} —
                            {{
                                i.resolved_at
                                    ? t('resolved :date', {
                                          date: when(i.resolved_at),
                                      })
                                    : t('ongoing')
                            }}
                        </span>
                    </li>
                </ul>
            </section>

            <p class="text-center text-xs text-muted-foreground">
                {{ t('Last updated :date', { date: when(generated_at) }) }}
            </p>
        </main>
    </div>
</template>
