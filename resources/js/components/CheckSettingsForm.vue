<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CheckController from '@/actions/App/Modules/Checks/Http/Controllers/CheckController';
import GroupSelect from '@/components/GroupSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { checkTypeLabel } from '@/lib/checks';
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

export type CheckSettings = {
    id: number;
    name: string;
    type: string;
    target: string;
    group_id: number | null;
    host_id: number | null;
    category: string;
    tags: string[];
    interval_sec: number;
    timeout_sec: number;
    failure_threshold: number;
    execution: string;
    quorum: string;
    enabled: boolean;
    config?: {
        latency_alert_ms?: number | null;
        latency_sustain?: number | null;
    };
};

const props = defineProps<{
    check: CheckSettings;
    groups: GroupOption[];
    hosts: { id: number; name: string }[];
    types: string[];
    categories: string[];
}>();

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const select = 'h-10 w-full rounded-md border bg-transparent px-3 text-sm';
const tagsInput = ref(props.check.tags.join(', '));

const form = useForm({
    name: props.check.name,
    type: props.check.type,
    target: props.check.target,
    group_id: props.check.group_id ?? ('' as string | number),
    host_id: props.check.host_id ?? ('' as string | number),
    category: props.check.category,
    tags: [...props.check.tags],
    interval_sec: props.check.interval_sec,
    timeout_sec: props.check.timeout_sec,
    failure_threshold: props.check.failure_threshold,
    execution: props.check.execution,
    quorum: props.check.quorum,
    enabled: props.check.enabled,
    config: {
        latency_alert_ms:
            props.check.config?.latency_alert_ms ?? ('' as number | ''),
        latency_sustain:
            props.check.config?.latency_sustain ?? ('' as number | ''),
    },
});

watch(
    () => props.check,
    (c) => {
        form.defaults({
            name: c.name,
            type: c.type,
            target: c.target,
            group_id: c.group_id ?? '',
            host_id: c.host_id ?? '',
            category: c.category,
            tags: c.tags,
            interval_sec: c.interval_sec,
            timeout_sec: c.timeout_sec,
            failure_threshold: c.failure_threshold,
            execution: c.execution,
            quorum: c.quorum,
            enabled: c.enabled,
            config: {
                latency_alert_ms: c.config?.latency_alert_ms ?? '',
                latency_sustain: c.config?.latency_sustain ?? '',
            },
        }).reset();
        tagsInput.value = c.tags.join(', ');
    },
);

const categoryLabel = (c: string) =>
    (
        {
            website: t('Websites'),
            server: t('Servers'),
            mail: t('Mail'),
            dns: t('DNS'),
            network: t('Network'),
            other: t('Other'),
        } as Record<string, string>
    )[c] ?? c;

const parseTags = (s: string) => [
    ...new Set(
        s
            .split(',')
            .map((x) => x.trim())
            .filter(Boolean),
    ),
];

function save() {
    form.tags = parseTags(tagsInput.value);
    form.transform((d) => ({
        ...d,
        group_id: d.group_id === '' ? null : d.group_id,
        host_id:
            d.type === 'heartbeat' ? null : d.host_id !== '' ? d.host_id : null,
        config: {
            latency_alert_ms:
                d.config.latency_alert_ms === '' ||
                d.config.latency_alert_ms === null
                    ? null
                    : Number(d.config.latency_alert_ms),
            latency_sustain:
                d.config.latency_sustain === '' ||
                d.config.latency_sustain === null
                    ? null
                    : Number(d.config.latency_sustain),
        },
    }));

    form.patch(CheckController.update.url(props.check.id), {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
        <div class="field-stack sm:col-span-2">
            <Label for="check-name">{{ t('Name') }}</Label>
            <Input id="check-name" v-model="form.name" required />
            <InputError :message="form.errors.name" />
        </div>
        <div class="field-stack">
            <Label for="check-type">{{ t('Type') }}</Label>
            <select id="check-type" v-model="form.type" :class="select">
                <option v-for="ty in types" :key="ty" :value="ty">
                    {{ checkTypeLabel(ty) }}
                </option>
            </select>
        </div>
        <div v-if="form.type !== 'heartbeat'" class="field-stack">
            <Label for="check-host">{{
                form.type === 'agent' ? t('Host') : t('Runs on server')
            }}</Label>
            <select id="check-host" v-model="form.host_id" :class="select">
                <option value="">—</option>
                <option v-for="h in hosts" :key="h.id" :value="h.id">
                    {{ h.name }}
                </option>
            </select>
            <InputError :message="form.errors.host_id" />
        </div>
        <div
            v-if="form.type !== 'agent' && form.type !== 'heartbeat'"
            class="field-stack sm:col-span-2"
        >
            <Label for="check-target">{{
                t('Target (URL, host:port, domain)')
            }}</Label>
            <Input id="check-target" v-model="form.target" />
            <InputError :message="form.errors.target" />
        </div>
        <div class="field-stack">
            <Label for="check-group">{{ t('Group') }}</Label>
            <GroupSelect
                id="check-group"
                v-model="form.group_id"
                :groups="groups"
            />
            <InputError :message="form.errors.group_id" />
        </div>
        <div class="field-stack">
            <Label for="check-category">{{ t('Category') }}</Label>
            <select id="check-category" v-model="form.category" :class="select">
                <option v-for="c in categories" :key="c" :value="c">
                    {{ categoryLabel(c) }}
                </option>
            </select>
        </div>
        <div class="field-stack sm:col-span-2">
            <Label for="check-tags">{{ t('Tags (comma separated)') }}</Label>
            <Input
                id="check-tags"
                v-model="tagsInput"
                :placeholder="t('prod, wordpress, eu')"
            />
        </div>
        <div class="field-stack">
            <Label for="check-interval">{{ t('Interval (seconds)') }}</Label>
            <Input
                id="check-interval"
                v-model.number="form.interval_sec"
                type="number"
                min="30"
            />
            <InputError :message="form.errors.interval_sec" />
        </div>
        <div class="field-stack">
            <Label for="check-timeout">{{ t('Timeout (seconds)') }}</Label>
            <Input
                id="check-timeout"
                v-model.number="form.timeout_sec"
                type="number"
                min="1"
            />
        </div>
        <template v-if="form.type === 'http'">
            <div class="field-stack sm:col-span-2">
                <Label for="check-latency-ms">{{
                    t('Slow response alert (ms)')
                }}</Label>
                <Input
                    id="check-latency-ms"
                    v-model.number="form.config.latency_alert_ms"
                    type="number"
                    min="100"
                    :placeholder="t('Optional — e.g. 2000')"
                />
                <p class="text-xs text-muted-foreground">
                    {{
                        t(
                            'Opens an incident when response time stays above this limit.',
                        )
                    }}
                </p>
            </div>
            <div class="field-stack">
                <Label for="check-latency-sustain">{{
                    t('Slow streak before alert')
                }}</Label>
                <Input
                    id="check-latency-sustain"
                    v-model.number="form.config.latency_sustain"
                    type="number"
                    min="1"
                    max="20"
                    :placeholder="String(form.failure_threshold)"
                />
            </div>
        </template>
        <div class="field-stack">
            <Label for="check-threshold">{{
                t('Failures before alert')
            }}</Label>
            <Input
                id="check-threshold"
                v-model.number="form.failure_threshold"
                type="number"
                min="1"
                max="10"
            />
        </div>
        <div class="field-stack">
            <Label for="check-execution">{{ t('Run from') }}</Label>
            <select
                id="check-execution"
                v-model="form.execution"
                :class="select"
            >
                <option value="hub">{{ t('The hub') }}</option>
                <option value="probe">{{ t('Remote probes') }}</option>
            </select>
        </div>
        <div v-if="form.execution === 'probe'" class="field-stack">
            <Label for="check-quorum">{{ t('Quorum') }}</Label>
            <select id="check-quorum" v-model="form.quorum" :class="select">
                <option value="majority">
                    {{ t('Majority of probes') }}
                </option>
                <option value="any">{{ t('Any probe') }}</option>
                <option value="all">{{ t('All probes') }}</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-2 sm:col-span-2">
            <Button type="submit" :disabled="form.processing">
                {{ t('Save') }}
            </Button>
            <Button type="button" variant="ghost" @click="emit('cancel')">
                {{ t('Cancel') }}
            </Button>
        </div>
    </form>
</template>
