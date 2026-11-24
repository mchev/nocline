<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { Check, Loader2 } from '@lucide/vue';
import { computed } from 'vue';
import MonitorController from '@/actions/App/Modules/Checks/Http/Controllers/MonitorController';
import CopyBlock from '@/components/CopyBlock.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';

const props = defineProps<{
    host: { id: number; name: string; connected: boolean; online: boolean };
    install_command: string | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Add a monitor', href: '/monitor/new' }] },
});

const { stop } = usePoll(3000, { only: ['host'] }, { autoStart: true });
const ready = computed(() => props.host.connected);
const unreachable = computed(() =>
    /\/\/(localhost|127\.|\[::1\]|[^/]+\.(test|local)\b)/.test(props.install_command ?? ''),
);

if (props.host.connected) {
    stop();
}

function regenerate() {
    router.post(MonitorController.regenerate.url(props.host.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('Connect your server')" />

    <PageContainer size="narrow">
        <PageHeader
            :eyebrow="t('Server')"
            :title="t('Connect “:name”', { name: host.name })"
            :description="t('Paste one line on the server. This page updates when it connects.')"
        />

        <ol v-if="!ready" class="grid gap-4">
            <li class="surface flex gap-4 p-5">
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-md bg-primary/10 font-display text-sm font-semibold text-primary"
                    aria-hidden="true"
                >
                    1
                </span>
                <div>
                    <p class="font-display font-semibold">{{ t('Open a terminal on your server') }}</p>
                    <p class="mt-1 text-sm text-pretty text-muted-foreground">
                        {{ t('Usually with SSH: ssh your-user@your-server. You need administrator rights (sudo).') }}
                    </p>
                </div>
            </li>
            <li class="surface flex gap-4 p-5">
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-md bg-primary/10 font-display text-sm font-semibold text-primary"
                    aria-hidden="true"
                >
                    2
                </span>
                <div class="grid min-w-0 flex-1 gap-3">
                    <p class="font-display font-semibold">{{ t('Paste this line and press Enter') }}</p>
                    <template v-if="install_command">
                        <CopyBlock :text="install_command" :label="t('install command')" />
                        <p
                            v-if="unreachable"
                            class="surface border-warning/40 bg-warning/10 p-4 text-sm"
                            role="status"
                        >
                            {{ t('Warning: this address (localhost or .test) cannot be reached from another machine. Set the public address of Phare (APP_URL) first.') }}
                        </p>
                        <p class="field-hint">
                            {{ t('This link works once and expires in 24 hours.') }}
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-sm text-muted-foreground">
                            {{ t('The command is no longer displayed for security reasons.') }}
                        </p>
                        <Button class="w-fit" variant="outline" @click="regenerate">
                            {{ t('Generate a new command') }}
                        </Button>
                    </template>
                </div>
            </li>
            <li class="surface flex gap-4 p-5">
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-md bg-primary/10 font-display text-sm font-semibold text-primary"
                    aria-hidden="true"
                >
                    3
                </span>
                <div>
                    <p class="flex items-center gap-2 font-display font-semibold">
                        <Loader2 class="size-4 animate-spin" aria-hidden="true" />
                        {{ t('Waiting for your server…') }}
                    </p>
                    <p class="mt-1 text-sm text-pretty text-muted-foreground" role="status" aria-live="polite">
                        {{ t('This page updates by itself as soon as the server is connected.') }}
                    </p>
                </div>
            </li>
        </ol>

        <div v-else class="surface-hero flex flex-col gap-4 p-6 md:p-8" role="status">
            <p class="flex items-center gap-2 font-display text-lg font-semibold">
                <span class="grid size-10 place-items-center rounded-md bg-success/15 text-success">
                    <Check class="size-5" aria-hidden="true" />
                </span>
                {{ t('Connected!') }}
            </p>
            <p class="text-sm text-pretty text-muted-foreground">
                {{ t('Your server is now monitored. Its first measurements appear within a minute.') }}
            </p>
            <Button class="w-fit" @click="router.visit(dashboard())">{{ t('Go to the dashboard') }}</Button>
        </div>
    </PageContainer>
</template>
