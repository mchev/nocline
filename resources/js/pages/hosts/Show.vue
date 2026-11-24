<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Link2, Server, Unplug, Unlink } from '@lucide/vue';
import { computed, ref } from 'vue';
import HostController from '@/actions/App/Modules/Hosts/Http/Controllers/HostController';
import GroupSelect from '@/components/GroupSelect.vue';
import HostCard, { type HostMetrics } from '@/components/HostCard.vue';
import HostMetaChips from '@/components/HostMetaChips.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { checkTypeLabel } from '@/lib/checks';
import { DEFAULT_GROUP_COLOR } from '@/lib/groups';
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

type LinkedCheck = {
    id: number;
    name: string;
    type: string;
    target: string;
    status: string;
    enabled: boolean;
};

type AttachableCheck = { id: number; name: string; type: string };

type HostPayload = {
    id: number;
    name: string;
    group_id: number | null;
    tags: string[];
    color: string | null;
    enrolled: boolean;
    online: boolean;
    last_seen_at: string | null;
    agent_version: string | null;
    agent_outdated: boolean;
    metrics: HostMetrics;
    agent_check: {
        id: number;
        status: string;
        interval_sec: number;
        timeout_sec: number;
        failure_threshold: number;
    } | null;
    hostname: string | null;
    public_ipv4: string | null;
    distro: string | null;
    provider: string | null;
    provider_label: string | null;
    provider_region: string | null;
};

const canManage = computed(
    () =>
        (usePage().props.auth as { user: { role: string } }).user.role !==
        'viewer',
);

const props = defineProps<{
    host: HostPayload;
    linked_checks: LinkedCheck[];
    attachable_checks: AttachableCheck[];
    groups: GroupOption[];
}>();

const tagsInput = ref(props.host.tags.join(', '));
const attachId = ref('' as string | number);

const form = useForm({
    name: props.host.name,
    group_id: props.host.group_id ?? ('' as string | number),
    tags: props.host.tags,
    color: props.host.color ?? '',
    interval_sec: props.host.agent_check?.interval_sec ?? 60,
    timeout_sec: props.host.agent_check?.timeout_sec ?? 10,
    failure_threshold: props.host.agent_check?.failure_threshold ?? 2,
});

const canSave = computed(() => !form.processing);

function save() {
    form.tags = tagsInput.value
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean);
    form.transform((d) => ({
        ...d,
        group_id: d.group_id === '' ? null : d.group_id,
        color: d.color || null,
    })).patch(HostController.update.url(props.host.id), {
        preserveScroll: true,
    });
}

function attach() {
    if (attachId.value === '') {
        return;
    }

    router.post(
        HostController.attachCheck.url(props.host.id),
        { check_id: attachId.value },
        { preserveScroll: true, onSuccess: () => (attachId.value = '') },
    );
}

function detach(check: LinkedCheck) {
    if (confirm(t('Unlink “:name” from this server?', { name: check.name }))) {
        router.delete(
            HostController.detachCheck.url({
                host: props.host.id,
                check: check.id,
            }),
            { preserveScroll: true },
        );
    }
}

function newToken() {
    router.post(
        HostController.enrollmentToken.url(props.host.id),
        {},
        { preserveScroll: true },
    );
}

function revoke() {
    if (
        confirm(
            t('Revoke the agent token of ":name"?', { name: props.host.name }),
        )
    ) {
        router.delete(HostController.revoke.url(props.host.id), {
            preserveScroll: true,
        });
    }
}

const installHref = computed(() => `/monitor/install/${props.host.id}`);
</script>

<template>
    <Head :title="host.name" />

    <PageContainer size="wide">
        <PageHeader
            :eyebrow="t('Server')"
            :title="host.name"
            :description="
                t(
                    'Agent metrics, monitoring settings, and services hosted on this machine.',
                )
            "
        >
            <Link v-if="!host.enrolled" :href="installHref">
                <Button variant="outline" type="button">{{
                    t('Install agent')
                }}</Button>
            </Link>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]">
            <div class="space-y-3">
                <HostCard
                    :name="host.name"
                    :online="host.online"
                    :enrolled="host.enrolled"
                    :metrics="host.metrics"
                    :agent-version="host.agent_version"
                    :agent-outdated="host.agent_outdated"
                    :host-id="host.id"
                    :update-command-enabled="canManage && host.enrolled"
                    :last-seen-at="host.last_seen_at"
                    :accent-color="host.color"
                    :public-ipv4="host.public_ipv4"
                    :provider-label="host.provider_label"
                >
                    <template #actions>
                        <Button
                            size="icon"
                            variant="ghost"
                            :title="t('New enrollment token')"
                            :aria-label="t('New enrollment token')"
                            @click="newToken"
                        >
                            <KeyRound class="size-4" />
                        </Button>
                        <Button
                            v-if="host.enrolled"
                            size="icon"
                            variant="ghost"
                            :title="t('Revoke agent')"
                            :aria-label="t('Revoke agent')"
                            @click="revoke"
                        >
                            <Unplug class="size-4" />
                        </Button>
                    </template>
                </HostCard>
                <HostMetaChips
                    :public-ipv4="host.public_ipv4"
                    :hostname="host.hostname"
                    :distro="host.distro"
                    :provider-label="host.provider_label"
                    :provider-region="host.provider_region"
                />
            </div>

            <form
                class="grid gap-4 surface p-5 sm:grid-cols-2"
                @submit.prevent="save"
            >
                <h2 class="font-display text-sm font-semibold sm:col-span-2">
                    {{ t('Server settings') }}
                </h2>
                <div class="field-stack sm:col-span-2">
                    <Label for="host-name">{{ t('Name') }}</Label>
                    <Input id="host-name" v-model="form.name" required />
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
                    <Label for="host-color">{{ t('Accent color') }}</Label>
                    <div class="flex items-center gap-2">
                        <input
                            id="host-color"
                            type="color"
                            class="size-10 cursor-pointer rounded-md border bg-transparent"
                            :value="form.color || DEFAULT_GROUP_COLOR"
                            @input="
                                form.color = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                        <Button
                            v-if="form.color"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="form.color = ''"
                        >
                            {{ t('Reset') }}
                        </Button>
                    </div>
                </div>
                <div class="field-stack sm:col-span-2">
                    <Label for="host-tags">{{
                        t('Tags (comma separated)')
                    }}</Label>
                    <Input
                        id="host-tags"
                        v-model="tagsInput"
                        :placeholder="t('prod, wordpress, eu')"
                    />
                </div>
                <template v-if="host.agent_check">
                    <h3
                        class="text-sm font-medium text-muted-foreground sm:col-span-2"
                    >
                        {{ t('Agent check') }}
                    </h3>
                    <div class="field-stack">
                        <Label for="interval">{{
                            t('Interval (seconds)')
                        }}</Label>
                        <Input
                            id="interval"
                            v-model.number="form.interval_sec"
                            type="number"
                            min="30"
                        />
                        <InputError :message="form.errors.interval_sec" />
                    </div>
                    <div class="field-stack">
                        <Label for="timeout">{{
                            t('Timeout (seconds)')
                        }}</Label>
                        <Input
                            id="timeout"
                            v-model.number="form.timeout_sec"
                            type="number"
                            min="1"
                            max="60"
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
                        />
                        <InputError :message="form.errors.failure_threshold" />
                    </div>
                </template>
                <div class="flex justify-end sm:col-span-2">
                    <Button type="submit" :disabled="!canSave">{{
                        t('Save')
                    }}</Button>
                </div>
            </form>
        </div>

        <section class="space-y-3">
            <header class="flex flex-wrap items-center justify-between gap-2">
                <h2
                    class="flex items-center gap-2 font-display text-sm font-semibold"
                >
                    <Server class="size-4 text-primary" aria-hidden="true" />
                    {{ t('Services on this server') }}
                    <span class="chip">{{ linked_checks.length }}</span>
                </h2>
                <div
                    v-if="attachable_checks.length"
                    class="flex flex-wrap items-center gap-2"
                >
                    <select
                        v-model="attachId"
                        class="h-10 rounded-md border bg-transparent px-3 text-sm"
                        :aria-label="t('Link a monitor')"
                    >
                        <option value="">{{ t('Link a monitor…') }}</option>
                        <option
                            v-for="c in attachable_checks"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }} ({{ checkTypeLabel(c.type) }})
                        </option>
                    </select>
                    <Button
                        variant="secondary"
                        :disabled="attachId === ''"
                        @click="attach"
                    >
                        <Link2 class="size-4" /> {{ t('Link') }}
                    </Button>
                </div>
            </header>

            <ul
                v-if="linked_checks.length"
                class="divide-y surface"
                role="list"
            >
                <li
                    v-for="check in linked_checks"
                    :key="check.id"
                    class="flex flex-wrap items-center gap-3 px-4 py-3"
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
                                · {{ check.target }}</template
                            >
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        :title="t('Unlink')"
                        :aria-label="t('Unlink')"
                        @click="detach(check)"
                    >
                        <Unlink class="size-4" />
                    </Button>
                </li>
            </ul>
            <p v-else class="surface px-4 py-6 text-sm text-muted-foreground">
                {{
                    t(
                        'No services linked yet. Associate a website or port check from the list above, or set “Runs on server” when editing a monitor.',
                    )
                }}
            </p>
        </section>
    </PageContainer>
</template>
