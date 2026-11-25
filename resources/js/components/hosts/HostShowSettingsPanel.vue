<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import GroupSelect from '@/components/GroupSelect.vue';
import HostAgentSection from '@/components/HostAgentSection.vue';
import HostMetricAlertsSection, {
    type MetricAlertsConfig,
} from '@/components/HostMetricAlertsSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DEFAULT_GROUP_COLOR } from '@/lib/groups';
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

type AgentCheckForm = {
    interval_sec: number;
    timeout_sec: number;
    failure_threshold: number;
};

const tagsInput = defineModel<string>('tagsInput', { required: true });

defineProps<{
    groups: GroupOption[];
    form: InertiaForm<
        {
            name: string;
            group_id: string | number;
            tags: string[];
            color: string;
        } & AgentCheckForm
    >;
    hasAgentCheck: boolean;
    canSave: boolean;
    host: {
        id: number;
        name: string;
        enrolled: boolean;
        agent_auto_update: boolean;
        agent_version: string | null;
        agent_outdated: boolean;
        hub_version: string;
        metric_alerts: MetricAlertsConfig;
        resource_alert: { active: boolean; message: string | null };
    };
    canManage: boolean;
}>();

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-5">
        <form class="space-y-4 surface p-5" @submit.prevent="emit('submit')">
            <h2 class="text-sm font-semibold">
                {{ t('Server settings') }}
            </h2>
            <div class="field-stack">
                <Label for="host-name">{{ t('Name') }}</Label>
                <Input
                    id="host-name"
                    v-model="form.name"
                    required
                    :disabled="!canManage"
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="field-stack">
                <Label for="host-group">{{ t('Group') }}</Label>
                <GroupSelect
                    id="host-group"
                    v-model="form.group_id"
                    :groups="groups"
                />
                <InputError :message="form.errors.group_id" />
            </div>
            <div class="field-stack">
                <Label for="host-tags">{{ t('Tags (comma separated)') }}</Label>
                <Input
                    id="host-tags"
                    v-model="tagsInput"
                    :placeholder="t('prod, wordpress, eu')"
                    :disabled="!canManage"
                />
            </div>
            <div class="field-stack">
                <Label for="host-color">{{ t('Accent color') }}</Label>
                <div class="flex items-center gap-2">
                    <input
                        id="host-color"
                        type="color"
                        class="size-10 cursor-pointer rounded-md border bg-transparent"
                        :value="form.color || DEFAULT_GROUP_COLOR"
                        :disabled="!canManage"
                        @input="
                            form.color = (
                                $event.target as HTMLInputElement
                            ).value
                        "
                    />
                    <Button
                        v-if="form.color && canManage"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="form.color = ''"
                    >
                        {{ t('Reset') }}
                    </Button>
                </div>
            </div>
            <template v-if="hasAgentCheck">
                <h3
                    class="border-t pt-4 text-sm font-medium text-muted-foreground"
                >
                    {{ t('Agent check') }}
                </h3>
                <div class="field-stack">
                    <Label for="interval">{{ t('Interval (seconds)') }}</Label>
                    <Input
                        id="interval"
                        v-model.number="form.interval_sec"
                        type="number"
                        min="30"
                        :disabled="!canManage"
                    />
                    <InputError :message="form.errors.interval_sec" />
                </div>
                <div class="field-stack">
                    <Label for="timeout">{{ t('Timeout (seconds)') }}</Label>
                    <Input
                        id="timeout"
                        v-model.number="form.timeout_sec"
                        type="number"
                        min="1"
                        max="60"
                        :disabled="!canManage"
                    />
                    <InputError :message="form.errors.timeout_sec" />
                </div>
                <div class="field-stack">
                    <Label for="threshold">{{
                        t('Failures before alert')
                    }}</Label>
                    <Input
                        id="threshold"
                        v-model.number="form.failure_threshold"
                        type="number"
                        min="1"
                        max="10"
                        :disabled="!canManage"
                    />
                    <InputError :message="form.errors.failure_threshold" />
                </div>
            </template>
            <div v-if="canManage" class="flex justify-end border-t pt-4">
                <Button type="submit" :disabled="!canSave">
                    {{ t('Save') }}
                </Button>
            </div>
        </form>

        <HostAgentSection
            :agent-auto-update="host.agent_auto_update"
            :host-name="host.name"
            :host-id="host.id"
            :enrolled="host.enrolled"
            :agent-version="host.agent_version"
            :agent-outdated="host.agent_outdated"
            :hub-version="host.hub_version"
            :can-manage="canManage"
        />

        <HostMetricAlertsSection
            v-if="host.enrolled"
            :host-id="host.id"
            :host-name="host.name"
            :metric-alerts="host.metric_alerts"
            :resource-alert="host.resource_alert"
            :can-manage="canManage"
        />
    </div>
</template>
