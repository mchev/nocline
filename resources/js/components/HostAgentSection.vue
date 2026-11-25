<script setup lang="ts">
import { router, useHttp, usePage } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import HostController from '@/actions/App/Modules/Hosts/Http/Controllers/HostController';
import CopyBlock from '@/components/CopyBlock.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
    hostId: number;
    hostName: string;
    agentAutoUpdate: boolean;
    enrolled: boolean;
    agentVersion: string | null;
    agentOutdated: boolean;
    hubVersion: string;
    canManage: boolean;
}>();

const agentAutoUpdate = ref(props.agentAutoUpdate);
const autoUpdateSaving = ref(false);

watch(
    () => props.agentAutoUpdate,
    (value) => {
        agentAutoUpdate.value = value;
    },
);

const hubVersionPage = computed(() => usePage().props.version as string);
const hubVersion = computed(() => props.hubVersion || hubVersionPage.value);

const http = useHttp();
const manualCommand = ref<string | null>(null);
const manualLoading = ref(false);
const manualError = ref<string | null>(null);

const showManual = computed(
    () =>
        props.canManage &&
        props.enrolled &&
        props.agentOutdated &&
        !agentAutoUpdate.value,
);

const statusChip = computed(() => {
    if (!props.enrolled) {
        return { label: t('Not enrolled'), class: 'chip' as const };
    }
    if (!props.agentOutdated) {
        return { label: t('Up to date'), class: 'chip-success' as const };
    }

    return { label: t('Update available'), class: 'chip-warning' as const };
});

async function loadManualCommand() {
    if (!showManual.value || manualCommand.value !== null) {
        return;
    }

    manualLoading.value = true;
    manualError.value = null;

    try {
        const data = (await http.submit(
            HostController.agentUpdateCommand.post(props.hostId),
        )) as { command: string };

        manualCommand.value = data.command;
    } catch {
        manualError.value = t('Could not prepare the update command.');
    } finally {
        manualLoading.value = false;
    }
}

onMounted(() => {
    void loadManualCommand();
});

watch(showManual, (visible) => {
    if (visible) {
        void loadManualCommand();
    } else {
        manualCommand.value = null;
    }
});

function onAgentAutoUpdateChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const next = input.checked;
    const previous = props.agentAutoUpdate;

    if (next === previous || autoUpdateSaving.value) {
        agentAutoUpdate.value = previous;
        input.checked = previous;

        return;
    }

    agentAutoUpdate.value = next;
    autoUpdateSaving.value = true;

    router.patch(
        HostController.update.url(props.hostId),
        {
            name: props.hostName,
            agent_auto_update: next,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    next
                        ? t('Automatic agent updates enabled.')
                        : t('Automatic agent updates disabled.'),
                );
            },
            onError: () => {
                agentAutoUpdate.value = previous;
                input.checked = previous;
                toast.error(t('Could not save agent update preference.'));
            },
            onFinish: () => {
                autoUpdateSaving.value = false;
            },
        },
    );
}
</script>

<template>
    <section
        class="min-w-0 space-y-2.5 overflow-visible surface p-3"
        aria-labelledby="host-agent-heading"
    >
        <header class="space-y-1.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2
                    id="host-agent-heading"
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('phare-agent') }}
                </h2>
                <span class="chip text-xs" :class="statusChip.class">{{
                    statusChip.label
                }}</span>
            </div>
            <p
                v-if="enrolled && (agentVersion || hubVersion)"
                class="font-mono text-xs text-muted-foreground tabular-nums"
            >
                <template v-if="agentVersion"
                    >{{ t('Agent') }} {{ agentVersion }}</template
                >
                <span v-if="agentVersion"> · </span>
                hub {{ hubVersion }}
            </p>
        </header>

        <template v-if="enrolled">
            <label
                v-if="canManage"
                class="flex cursor-pointer items-start gap-2 text-sm leading-snug"
            >
                <input
                    :checked="agentAutoUpdate"
                    type="checkbox"
                    class="mt-0.5 size-4 shrink-0 rounded border-input"
                    :disabled="autoUpdateSaving"
                    :aria-busy="autoUpdateSaving"
                    @change="onAgentAutoUpdateChange"
                />
                <span class="text-pretty">{{
                    t('Automatic agent updates')
                }}</span>
            </label>

            <div
                v-if="showManual"
                class="min-w-0 space-y-2 overflow-visible border-t border-border/60 pt-3"
            >
                <p class="text-xs leading-snug font-medium">
                    {{ t('Manual update (SSH)') }}
                </p>
                <div
                    v-if="manualLoading"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                    role="status"
                >
                    <Loader2 class="size-3.5 animate-spin" aria-hidden="true" />
                    {{ t('Preparing command…') }}
                </div>
                <p
                    v-else-if="manualError"
                    class="text-xs text-danger"
                    role="alert"
                >
                    {{ manualError }}
                </p>
                <CopyBlock
                    v-else-if="manualCommand"
                    stacked
                    :text="manualCommand"
                    :label="t('agent update command')"
                />
            </div>

            <p
                v-else-if="agentAutoUpdate && agentOutdated"
                class="text-xs text-pretty text-muted-foreground"
            >
                {{
                    t(
                        'The agent should update on its own after the next metrics push.',
                    )
                }}
            </p>
        </template>

        <p v-else class="text-xs text-muted-foreground">
            {{ t('Install the agent to start receiving metrics.') }}
        </p>
    </section>
</template>
