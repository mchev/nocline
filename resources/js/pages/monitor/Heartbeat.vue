<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { Check, Loader2 } from '@lucide/vue';
import { computed } from 'vue';
import CopyBlock from '@/components/CopyBlock.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SectionCard from '@/components/ui-kit/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { frequencyLabel } from '@/lib/checks';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';

const props = defineProps<{
    check: {
        id: number;
        name: string;
        interval_sec: number;
        grace_sec: number;
        status: string;
        pinged: boolean;
    };
    url: string;
}>();

const { stop } = usePoll(3000, { only: ['check'] }, { autoStart: true });

if (props.check.pinged) {
    stop();
}

const token = computed(() => props.url.split('/').pop() ?? '');
const unreachable = computed(() =>
    /\/\/(localhost|127\.|\[::1\]|[^/]+\.(test|local)\b)/.test(props.url),
);
const curl = computed(
    () => `curl -fsS -m 10 --retry 3 -o /dev/null ${props.url}`,
);

const snippets = computed(() => [
    {
        key: 'cron',
        title: t('In a crontab, after your command'),
        text: `0 3 * * * /path/to/your-script.sh && ${curl.value}`,
    },
    {
        key: 'script',
        title: t('At the end of a backup script'),
        text: curl.value,
    },
    {
        key: 'fail',
        title: t('Also tell Nocline when the task fails'),
        text: `/path/to/your-script.sh && ${curl.value} || ${curl.value.replace(props.url, `${props.url}/fail`)}`,
    },
    {
        key: 'laravel',
        title: t('Laravel scheduler (package nocline/laravel)'),
        text: `// .env: NOCLINE_URL=${props.url.split('/api/')[0]}\n$schedule->command('backup:run')->daily()\n    ->pingNoclineOnSuccess('${token.value}')\n    ->pingNoclineOnFailure('${token.value}');`,
    },
]);
</script>

<template>
    <Head :title="t('Connect your scheduled task')" />

    <PageContainer size="narrow">
        <PageHeader
            :eyebrow="t('Scheduled task')"
            :title="t('Connect “:name”', { name: check.name })"
            :description="
                t(
                    ':frequency, your task must visit the address below. If Nocline hears nothing for :minutes minutes longer than expected, we warn you.',
                    {
                        frequency: frequencyLabel(check.interval_sec),
                        minutes: Math.max(1, Math.round(check.grace_sec / 60)),
                    },
                )
            "
        />

        <div
            v-if="check.pinged"
            class="flex flex-col gap-4 surface-hero p-6 md:p-8"
            role="status"
        >
            <p
                class="flex items-center gap-2 font-display text-lg font-semibold"
            >
                <span
                    class="grid size-10 place-items-center rounded-md bg-success/15 text-success"
                >
                    <Check class="size-5" aria-hidden="true" />
                </span>
                {{ t('First signal received!') }}
            </p>
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    t(
                        'Your task is now watched. We warn you if it stops reporting.',
                    )
                }}
            </p>
            <Button class="w-fit" @click="router.visit(dashboard())">{{
                t('Go to the dashboard')
            }}</Button>
        </div>

        <template v-else>
            <SectionCard :title="t('1. The address of your task')">
                <div class="grid gap-3">
                    <CopyBlock :text="url" :label="t('address of the task')" />
                    <p
                        v-if="unreachable"
                        class="surface border-warning/40 bg-warning/10 p-4 text-sm"
                        role="status"
                    >
                        {{
                            t(
                                'Warning: this address (localhost or .test) cannot be reached from another machine. Set the public address of Nocline (APP_URL) first.',
                            )
                        }}
                    </p>
                    <p class="field-hint">
                        {{
                            t(
                                'Keep this address private: anyone who has it can send signals for this task.',
                            )
                        }}
                    </p>
                </div>
            </SectionCard>

            <SectionCard
                :title="t('2. Make your task visit it')"
                :description="t('Pick the one that fits, and paste it.')"
            >
                <div class="grid gap-5">
                    <div v-for="s in snippets" :key="s.key" class="grid gap-2">
                        <h3 class="text-sm font-medium">{{ s.title }}</h3>
                        <CopyBlock :text="s.text" :label="s.title" />
                    </div>
                </div>
            </SectionCard>

            <p
                class="flex items-center gap-2 text-sm text-muted-foreground"
                role="status"
                aria-live="polite"
            >
                <Loader2 class="size-4 animate-spin" aria-hidden="true" />
                {{
                    t(
                        'Waiting for the first signal… This page updates by itself.',
                    )
                }}
            </p>
        </template>
    </PageContainer>
</template>
