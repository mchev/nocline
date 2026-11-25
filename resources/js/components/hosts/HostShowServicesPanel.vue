<script setup lang="ts">
import { Link2, Server, Unlink } from '@lucide/vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { Button } from '@/components/ui/button';
import { checkTypeLabel } from '@/lib/checks';
import { t } from '@/lib/i18n';

export type LinkedCheckRow = {
    id: number;
    name: string;
    type: string;
    target: string;
    status: string;
    enabled: boolean;
};

export type AttachableCheckRow = {
    id: number;
    name: string;
    type: string;
};

const attachId = defineModel<string | number>('attachId', { required: true });

defineProps<{
    linkedChecks: LinkedCheckRow[];
    attachableChecks: AttachableCheckRow[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    attach: [];
    detach: [check: LinkedCheckRow];
}>();
</script>

<template>
    <section class="surface p-5" aria-labelledby="host-services-heading">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2
                id="host-services-heading"
                class="flex items-center gap-2 text-sm font-semibold"
            >
                <Server class="size-4 text-primary" aria-hidden="true" />
                {{ t('Services on this server') }}
                <span class="chip">{{ linkedChecks.length }}</span>
            </h2>
            <div
                v-if="canManage && attachableChecks.length"
                class="flex flex-wrap items-center gap-2"
            >
                <select
                    v-model="attachId"
                    class="h-9 max-w-[14rem] rounded-md border bg-transparent px-2 text-sm"
                    :aria-label="t('Link a monitor')"
                >
                    <option value="">{{ t('Link a monitor…') }}</option>
                    <option
                        v-for="c in attachableChecks"
                        :key="c.id"
                        :value="c.id"
                    >
                        {{ c.name }} ({{ checkTypeLabel(c.type) }})
                    </option>
                </select>
                <Button
                    variant="secondary"
                    size="sm"
                    :disabled="attachId === ''"
                    @click="emit('attach')"
                >
                    <Link2 class="size-4" aria-hidden="true" />
                    {{ t('Link') }}
                </Button>
            </div>
        </header>

        <ul
            v-if="linkedChecks.length"
            class="divide-y divide-border"
            role="list"
        >
            <li
                v-for="check in linkedChecks"
                :key="check.id"
                class="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0"
            >
                <StatusDot
                    :status="
                        !check.enabled
                            ? 'unknown'
                            : (check.status as 'up' | 'down' | 'unknown')
                    "
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ check.name }}</p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ checkTypeLabel(check.type) }}
                        <template v-if="check.target">
                            · {{ check.target }}
                        </template>
                    </p>
                </div>
                <Button
                    v-if="canManage"
                    size="icon"
                    variant="ghost"
                    :title="t('Unlink')"
                    :aria-label="t('Unlink')"
                    @click="emit('detach', check)"
                >
                    <Unlink class="size-4" />
                </Button>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            {{
                t(
                    'No services linked yet. Associate a website or port check from the list above, or set “Runs on server” when editing a monitor.',
                )
            }}
        </p>
    </section>
</template>
