<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Copy, KeyRound, Pencil, Plus, Server, Trash2, Unplug } from '@lucide/vue';
import { computed, ref } from 'vue';
import HostController from '@/actions/App/Modules/Hosts/Http/Controllers/HostController';
import GroupSelect from '@/components/GroupSelect.vue';
import HostCard, { type HostMetrics } from '@/components/HostCard.vue';
import InputError from '@/components/InputError.vue';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { index } from '@/routes/hosts';
import { groupWithDescendantIds } from '@/lib/groups';
import type { GroupOption } from '@/types/groups';

type Host = {
    id: number;
    name: string;
    group_id: number | null;
    tags: string[];
    enrolled: boolean;
    online: boolean;
    last_seen_at: string | null;
    agent_version: string | null;
    agent_outdated: boolean;
    metrics: HostMetrics;
};

const props = defineProps<{
    hosts: Host[];
    groups: GroupOption[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Servers', href: index() }] },
});

const page = usePage();
const role = computed(() => page.props.auth.user.role);
const canManage = computed(() => role.value !== 'viewer');
const isAdmin = computed(() => role.value === 'admin');
const hubUrl = typeof window === 'undefined' ? '' : window.location.origin;
const enrollment = computed(
    () => page.props.enrollment as { host: string; token: string } | null,
);
const enrollCommand = computed(() =>
    enrollment.value
        ? `phare-agent enroll --hub ${hubUrl} --token ${enrollment.value.token}`
        : '',
);
const select = 'h-10 rounded-md border bg-transparent px-3 text-sm';

const q = ref('');
const groupFilter = ref('all');
const groupByGroup = ref(true);
const groupById = computed(() => new Map(props.groups.map((g) => [g.id, g])));
const pathOf = (h: Host) =>
    h.group_id === null ? '' : (groupById.value.get(h.group_id)?.path ?? '');

const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();

    return props.hosts.filter((h) => {
        if (groupFilter.value === 'none' && h.group_id !== null) {
            return false;
        }

        if (
            !['all', 'none'].includes(groupFilter.value) &&
            (h.group_id === null ||
                !groupWithDescendantIds(props.groups, Number(groupFilter.value)).includes(h.group_id))
        ) {
            return false;
        }

        return (
            !term ||
            [h.name, pathOf(h), ...h.tags]
                .join(' ')
                .toLowerCase()
                .includes(term)
        );
    });
});

const sections = computed(() => {
    const map = new Map<string, Host[]>();

    for (const h of filtered.value) {
        const key = groupByGroup.value
            ? (pathOf(h) || t('Ungrouped'))
            : t('All hosts');
        map.set(key, [...(map.get(key) ?? []), h]);
    }

    /* tree order: follow the order of `groups`; ungrouped last */
    const order = (label: string) => {
        const i = props.groups.findIndex((g) => g.path === label);

        return i === -1 ? Number.MAX_SAFE_INTEGER : i;
    };

    return [...map.entries()].sort((a, b) => order(a[0]) - order(b[0]));
});

const dialogOpen = ref(false);
const editing = ref<Host | null>(null);
const tagsInput = ref('');
const form = useForm({ name: '', group_id: '' as string | number, tags: [] as string[] });

function openCreate() {
    router.visit('/monitor/new?kind=server');
}

function openEdit(h: Host) {
    editing.value = h;
    form.clearErrors();
    form.defaults({ name: h.name, group_id: h.group_id ?? '', tags: h.tags }).reset();
    tagsInput.value = h.tags.join(', ');
    dialogOpen.value = true;
}

function save() {
    form.tags = tagsInput.value
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean);
    form.transform((d) => ({
        ...d,
        group_id: d.group_id === '' ? null : d.group_id,
    }));
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        form.patch(HostController.update.url(editing.value.id), options);
    } else {
        form.post(HostController.store.url(), options);
    }
}

function copyCommand() {
    navigator.clipboard?.writeText(enrollCommand.value);
}

function newToken(h: Host) {
    router.post(HostController.enrollmentToken.url(h.id), {}, { preserveScroll: true });
}

function revoke(h: Host) {
    if (confirm(t('Revoke the agent token of ":name"?', { name: h.name }))) {
        router.delete(HostController.revoke.url(h.id), { preserveScroll: true });
    }
}

function remove(h: Host) {
    if (confirm(t('Delete the host ":name"?', { name: h.name }))) {
        router.delete(HostController.destroy.url(h.id), { preserveScroll: true });
    }
}

const emptyMetrics = (): HostMetrics => ({
    cpu: [],
    mem: [],
    disk: [],
    cpu_now: null,
    mem_now: null,
    disk_now: null,
});
</script>

<template>
    <Head :title="t('Servers')" />

    <PageContainer size="wide">
        <PageHeader
            :eyebrow="t('Servers')"
            :title="t('Servers')"
            :description="t('Servers running phare-agent and pushing metrics to the hub.')"
        >
            <Button v-if="canManage" @click="openCreate">
                <Plus class="size-4" /> {{ t('Add a server') }}
            </Button>
        </PageHeader>

        <div
            v-if="enrollment"
            class="surface border-warning/40 bg-warning/10 p-5 text-sm"
            role="status"
        >
            <p class="font-medium">
                {{
                    t(
                        'Enrollment token for :host (shown once, valid for 24 h). Run this on the server:',
                        { host: enrollment.host },
                    )
                }}
            </p>
            <div class="mt-3 flex items-start gap-2">
                <code class="surface-muted block flex-1 break-all p-3 select-all">{{ enrollCommand }}</code>
                <Button size="icon" variant="outline" :title="t('Copy')" :aria-label="t('Copy')" @click="copyCommand">
                    <Copy class="size-4" />
                </Button>
            </div>
        </div>

        <search v-if="hosts.length" class="surface p-4">
            <form class="flex flex-wrap items-center gap-2" role="search" @submit.prevent>
                <Input
                    v-model="q"
                    class="w-56"
                    type="search"
                    :placeholder="t('Search name, tag…')"
                    :aria-label="t('Search')"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                />
                <select v-model="groupFilter" :class="select" :aria-label="t('Group')">
                    <option value="all">{{ t('All groups') }}</option>
                    <option value="none">{{ t('No group') }}</option>
                    <option v-for="g in groups" :key="g.id" :value="String(g.id)">
                        {{ g.path }}
                    </option>
                </select>
                <label class="ml-auto flex min-h-10 items-center gap-2 text-sm text-muted-foreground">
                    <input v-model="groupByGroup" type="checkbox" class="size-4 accent-primary" />
                    {{ t('Show by group') }}
                </label>
            </form>
        </search>

        <EmptyState
            v-if="!hosts.length"
            :icon="Server"
            :title="t('No hosts yet')"
            :description="
                t(
                    'A host is a server running the phare-agent, which pushes its metrics to the hub.',
                )
            "
        >
            <Button v-if="canManage" @click="openCreate">{{ t('Add your first server') }}</Button>
        </EmptyState>
        <EmptyState
            v-else-if="!filtered.length"
            :title="t('No host matches these filters.')"
        />

        <section
            v-for="[label, items] in sections"
            :key="label"
            class="space-y-3"
        >
            <header
                v-if="groupByGroup"
                class="flex items-center gap-2"
            >
                <h2 class="font-display text-sm font-semibold">{{ label }}</h2>
                <span class="chip">{{ items.length }}</span>
            </header>
            <div class="host-grid grid gap-3">
                <HostCard
                    v-for="h in items"
                    :key="h.id"
                    :name="h.name"
                    :online="h.online"
                    :enrolled="h.enrolled"
                    :metrics="h.metrics ?? emptyMetrics()"
                    :agent-version="h.agent_version"
                    :agent-outdated="h.agent_outdated"
                    :last-seen-at="h.last_seen_at"
                >
                    <template v-if="canManage" #actions>
                        <Button size="icon" variant="ghost" :title="t('New enrollment token')" :aria-label="t('New enrollment token')" @click="newToken(h)">
                            <KeyRound class="size-4" />
                        </Button>
                        <Button v-if="h.enrolled" size="icon" variant="ghost" :title="t('Revoke agent')" :aria-label="t('Revoke agent')" @click="revoke(h)">
                            <Unplug class="size-4" />
                        </Button>
                        <Button size="icon" variant="ghost" :title="t('Edit')" :aria-label="t('Edit')" @click="openEdit(h)">
                            <Pencil class="size-4" />
                        </Button>
                        <Button v-if="isAdmin" size="icon" variant="ghost" :title="t('Delete')" :aria-label="t('Delete')" @click="remove(h)">
                            <Trash2 class="size-4" />
                        </Button>
                    </template>
                </HostCard>
            </div>
        </section>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t('Edit server') }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="save">
                    <div class="field-stack">
                        <Label for="host-name">{{ t('Name') }}</Label>
                        <Input id="host-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="field-stack">
                        <Label for="host-group">{{ t('Group') }}</Label>
                        <GroupSelect id="host-group" v-model="form.group_id" :groups="groups" />
                        <InputError :message="form.errors.group_id" />
                    </div>
                    <div class="field-stack">
                        <Label for="host-tags">{{ t('Tags (comma separated)') }}</Label>
                        <Input id="host-tags" v-model="tagsInput" :placeholder="t('prod, wordpress, eu')" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" @click="dialogOpen = false">{{ t('Cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ t('Save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </PageContainer>
</template>

<style scoped>
.host-grid {
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 17rem), 1fr));
}
</style>
