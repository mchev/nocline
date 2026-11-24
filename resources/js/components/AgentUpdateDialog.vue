<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import HostController from '@/actions/App/Modules/Hosts/Http/Controllers/HostController';
import CopyBlock from '@/components/CopyBlock.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';

const props = defineProps<{
    hostId: number;
    hostName: string;
    agentVersion: string | null;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const hubVersion = computed(() => usePage().props.version as string);

const http = useHttp();
const loading = ref(false);
const command = ref<string | null>(null);
const error = ref<string | null>(null);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            command.value = null;
            error.value = null;

            return;
        }

        loading.value = true;
        error.value = null;
        command.value = null;

        try {
            const data = (await http.submit(
                HostController.agentUpdateCommand.post(props.hostId),
            )) as { command: string; hub_version: string };

            command.value = data.command;
        } catch {
            error.value = t('Could not prepare the update command.');
        } finally {
            loading.value = false;
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[90dvh] gap-0 overflow-y-auto p-0 sm:max-w-xl"
        >
            <div class="grid gap-4 p-6 pr-12">
                <DialogHeader class="gap-2 text-left">
                    <DialogTitle>{{
                        t('Update agent on “:name”', { name: hostName })
                    }}</DialogTitle>
                    <DialogDescription class="text-pretty">
                        {{
                            t(
                                'Hub version :hub — your server reports agent :agent. Paste this line in an SSH session on the server (with sudo). It downloads the latest binary and restarts the service without changing your API token.',
                                {
                                    hub: hubVersion,
                                    agent: agentVersion ?? '—',
                                },
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="loading"
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                    role="status"
                >
                    <Loader2 class="size-4 animate-spin" aria-hidden="true" />
                    {{ t('Preparing command…') }}
                </div>

                <p v-else-if="error" class="text-sm text-danger" role="alert">
                    {{ error }}
                </p>

                <CopyBlock
                    v-else-if="command"
                    stacked
                    :text="command"
                    :label="t('agent update command')"
                />

                <p
                    class="border-t border-border/60 pt-3 text-xs text-pretty text-muted-foreground"
                >
                    {{
                    t(
                        'The server must reach this Phare hub and GitHub over HTTPS. Existing config in /etc/phare-agent/config.yaml is kept.',
                    )
                    }}
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
