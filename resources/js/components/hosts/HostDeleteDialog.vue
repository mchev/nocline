<script setup lang="ts">
import { ref, watch } from 'vue';
import CopyBlock from '@/components/CopyBlock.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';

const AGENT_CLEANUP_SCRIPT = `sudo systemctl stop nocline-agent 2>/dev/null || true
sudo systemctl disable nocline-agent 2>/dev/null || true
sudo rm -f /etc/systemd/system/nocline-agent.service
sudo systemctl daemon-reload
sudo rm -f /usr/local/bin/nocline-agent
sudo rm -rf /etc/nocline-agent`;

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    hostName: string;
    enrolled: boolean;
    processing?: boolean;
}>();

const emit = defineEmits<{
    confirm: [];
}>();

const showCleanup = ref(true);

watch(open, (isOpen) => {
    if (isOpen) {
        showCleanup.value = props.enrolled;
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ t('Delete server “:name”?', { name: hostName }) }}
                </DialogTitle>
                <DialogDescription class="text-left text-pretty">
                    {{
                        t(
                            'The server is removed from Nocline. Linked monitors stay in the hub but are no longer tied to this machine.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                <li>
                    {{
                        t(
                            'The API token is destroyed — the agent can no longer send metrics to this hub.',
                        )
                    }}
                </li>
                <li>
                    {{
                        t(
                            'Metric history for this server is deleted from the hub.',
                        )
                    }}
                </li>
                <li v-if="enrolled">
                    {{
                        t(
                            'The agent binary is not removed from the machine automatically.',
                        )
                    }}
                </li>
            </ul>

            <section
                v-if="enrolled && showCleanup"
                class="space-y-2 rounded-md border border-border/80 bg-muted/30 p-3"
            >
                <p class="text-sm font-medium">
                    {{ t('Clean up the agent on the server (optional)') }}
                </p>
                <p class="text-xs text-pretty text-muted-foreground">
                    {{
                        t(
                            'Run on the Linux host after deleting it in Nocline (systemd install).',
                        )
                    }}
                </p>
                <CopyBlock
                    :text="AGENT_CLEANUP_SCRIPT"
                    :label="t('Agent cleanup commands')"
                />
            </section>

            <DialogFooter class="gap-2 sm:justify-between">
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="processing"
                    @click="open = false"
                >
                    {{ t('Cancel') }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ t('Delete server') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
