<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ChevronDown,
    ChevronRight,
    Pause,
    Play,
    Plus,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import CheckController from '@/actions/App/Modules/Checks/Http/Controllers/CheckController';
import GroupController from '@/actions/App/Modules/Groups/Http/Controllers/GroupController';
import CheckEntry from '@/components/CheckEntry.vue';
import GroupSelect from '@/components/GroupSelect.vue';
import InputError from '@/components/InputError.vue';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatTile from '@/components/ui-kit/StatTile.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
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
import { checkTypeLabel } from '@/lib/checks';
import { MONITOR_HISTORY_MINUTES } from '@/lib/monitorHistory';
import { orderedWebsiteMonitorEntries } from '@/lib/siteHealthCompanion';
import { groupWithDescendantIds } from '@/lib/groups';
import { t } from '@/lib/i18n';
import type { Check, CheckStatus } from '@/types/checks';
import type { GroupLayout, GroupOption } from '@/types/groups';

const props = defineProps<{
    checks: Check[];
    groups: GroupOption[];
    hosts: { id: number; name: string }[];
    types: string[];
    categories: string[];
}>();

const role = computed(() => usePage().props.auth.user.role);
const canManage = computed(() => role.value !== 'viewer');
const isAdmin = computed(() => role.value === 'admin');
const select = 'h-10 rounded-md border bg-transparent px-3 text-sm';

const categoryLabel = (c: string) =>
    ({
        website: t('Websites'),
        server: t('Servers'),
        mail: t('Mail'),
        dns: t('DNS'),
        network: t('Network'),
        other: t('Other'),
    })[c] ?? c;
/** A monitor under planned maintenance is not counted as down. */
const isDown = (c: Check) => c.status === 'down' && !c.in_maintenance;
const statusLabel = (s: string) =>
    ({ up: t('Up'), down: t('Down'), unknown: t('Unknown') })[s] ?? s;

/* ---------- view state (persisted) ---------- */
type GroupBy = 'group' | 'category' | 'status' | 'none';
type LayoutChoice = 'auto' | GroupLayout;
type View = {
    groupFilter: string;
    category: string;
    status: string;
    groupBy: GroupBy;
    layout: LayoutChoice;
    sort: 'status' | 'name' | 'latency';
    q: string;
};

const VIEW_KEY = 'phare.checks.view';
const COLLAPSED_KEY = 'phare.checks.collapsed';

function readStorage<T>(key: string): Partial<T> {
    try {
        const value = JSON.parse(localStorage.getItem(key) ?? '{}');

        return value && typeof value === 'object' ? value : {};
    } catch {
        return {};
    }
}

const pick = <T extends string>(
    value: unknown,
    allowed: T[],
    fallback: T,
): T => (allowed.includes(value as T) ? (value as T) : fallback);

function loadView(): View {
    const s = readStorage<Record<string, unknown>>(VIEW_KEY);

    return {
        groupFilter: typeof s.groupFilter === 'string' ? s.groupFilter : 'all',
        category: typeof s.category === 'string' ? s.category : 'all',
        status: pick(s.status, ['all', 'up', 'down', 'unknown'], 'all'),
        groupBy: pick(
            s.groupBy,
            ['group', 'category', 'status', 'none'],
            'group',
        ),
        layout: pick(s.layout, ['auto', 'list', 'grid', 'compact'], 'auto'),
        sort: pick(s.sort, ['status', 'name', 'latency'], 'status'),
        q: '',
    };
}

const view = ref<View>(loadView());

function applyUrlViewHints(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const status = params.get('status');

    if (status === 'up' || status === 'down' || status === 'unknown') {
        view.value.status = status;
    }

    const checkId = params.get('check');

    if (!checkId) {
        return;
    }

    const match = props.checks.find((c) => c.id === Number(checkId));

    if (match) {
        view.value.q = match.name;
        view.value.status = 'all';
        view.value.groupBy = 'none';
    }
}

onMounted(applyUrlViewHints);

/* a stale saved filter (deleted group / category) must not hide everything */
if (
    !['all', 'none'].includes(view.value.groupFilter) &&
    !props.groups.some((g) => String(g.id) === view.value.groupFilter)
) {
    view.value.groupFilter = 'all';
}

if (
    view.value.category !== 'all' &&
    !props.categories.includes(view.value.category)
) {
    view.value.category = 'all';
}

watch(
    view,
    (v) => {
        const { q: _q, ...saved } = v;
        localStorage.setItem(VIEW_KEY, JSON.stringify(saved));
    },
    { deep: true },
);

/** Per-user fold state, keyed by section key; absent = use the default. */
const foldState = ref<Record<string, boolean>>(
    readStorage<Record<string, boolean>>(COLLAPSED_KEY) as Record<
        string,
        boolean
    >,
);
watch(
    foldState,
    (v) => localStorage.setItem(COLLAPSED_KEY, JSON.stringify(v)),
    {
        deep: true,
    },
);

const selected = ref<number[]>([]);
const statusRank: Record<CheckStatus, number> = { down: 0, unknown: 1, up: 2 };

const groupById = computed(() => new Map(props.groups.map((g) => [g.id, g])));
const filtersActive = computed(
    () =>
        view.value.q.trim() !== '' ||
        view.value.groupFilter !== 'all' ||
        view.value.category !== 'all' ||
        view.value.status !== 'all',
);

const filtered = computed(() => {
    const q = view.value.q.trim().toLowerCase();
    const gf = view.value.groupFilter;
    const allowedGroupIds =
        gf === 'all' || gf === 'none'
            ? null
            : new Set(groupWithDescendantIds(props.groups, Number(gf)));

    return props.checks
        .filter((c) => {
            if (gf === 'none' && c.group_id !== null) {
                return false;
            }

            if (
                allowedGroupIds &&
                (c.group_id === null || !allowedGroupIds.has(c.group_id))
            ) {
                return false;
            }

            if (
                view.value.category !== 'all' &&
                c.category !== view.value.category
            ) {
                return false;
            }

            if (view.value.status !== 'all' && c.status !== view.value.status) {
                return false;
            }

            return (
                !q ||
                [
                    c.name,
                    c.target,
                    c.group_id !== null
                        ? (groupById.value.get(c.group_id)?.path ?? '')
                        : '',
                    ...c.tags,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(q)
            );
        })
        .sort((a, b) => {
            if (view.value.sort === 'status') {
                return (
                    statusRank[a.status] - statusRank[b.status] ||
                    a.name.localeCompare(b.name)
                );
            }

            if (view.value.sort === 'latency') {
                return (b.latency_ms ?? -1) - (a.latency_ms ?? -1);
            }

            return a.name.localeCompare(b.name);
        });
});

type Section = {
    key: string;
    label: string;
    color: string | null;
    depth: number;
    group: GroupOption | null;
    items: Check[];
    total: number;
    up: number;
    down: number;
    defaultCollapsed: boolean;
    showHeader: boolean;
};

const isCollapsed = (s: Section) =>
    view.value.q.trim() !== ''
        ? false
        : (foldState.value[s.key] ?? s.defaultCollapsed);

function toggleSection(s: Section) {
    foldState.value[s.key] = !isCollapsed(s);
}

const sections = computed<Section[]>(() => {
    const mode = view.value.groupBy;

    if (mode === 'group') {
        const own = new Map<number | null, Check[]>();
        const total = new Map<number, number>();
        const down = new Map<number, number>();
        const up = new Map<number, number>();

        for (const c of filtered.value) {
            own.set(c.group_id, [...(own.get(c.group_id) ?? []), c]);

            let id = c.group_id;
            const seen = new Set<number>();

            while (id !== null && !seen.has(id)) {
                seen.add(id);
                total.set(id, (total.get(id) ?? 0) + 1);
                down.set(id, (down.get(id) ?? 0) + (isDown(c) ? 1 : 0));
                up.set(id, (up.get(id) ?? 0) + (c.status === 'up' ? 1 : 0));
                id = groupById.value.get(id)?.parent_id ?? null;
            }
        }

        const result: Section[] = [];
        let skipBelow: number | null = null;

        for (const g of props.groups) {
            const count = total.get(g.id) ?? 0;

            if (filtersActive.value && count === 0) {
                continue;
            }

            if (skipBelow !== null && g.depth > skipBelow) {
                continue;
            }

            skipBelow = null;

            const section: Section = {
                key: `g:${g.id}`,
                label: g.name,
                color: g.color,
                depth: g.depth,
                group: g,
                items: own.get(g.id) ?? [],
                total: count,
                up: up.get(g.id) ?? 0,
                down: down.get(g.id) ?? 0,
                defaultCollapsed: g.collapsed,
                showHeader: true,
            };
            result.push(section);

            if (isCollapsed(section)) {
                skipBelow = g.depth;
            }
        }

        const loose = own.get(null) ?? [];

        if (loose.length) {
            result.push({
                key: 'g:none',
                label: t('Ungrouped'),
                color: null,
                depth: 0,
                group: null,
                items: loose,
                total: loose.length,
                up: loose.filter((c) => c.status === 'up').length,
                down: loose.filter(isDown).length,
                defaultCollapsed: false,
                showHeader: true,
            });
        }

        return result;
    }

    const map = new Map<string, { label: string; items: Check[] }>();

    for (const c of filtered.value) {
        let key = 'all';
        let label = t('All checks');

        if (mode === 'category') {
            key = c.category;
            label = categoryLabel(c.category);
        } else if (mode === 'status') {
            key = c.status;
            label = statusLabel(c.status);
        }

        if (!map.has(key)) {
            map.set(key, { label, items: [] });
        }

        map.get(key)!.items.push(c);
    }

    return [...map.entries()]
        .map(([key, g]) => ({
            key: `${mode}:${key}`,
            label: g.label,
            color: null,
            depth: 0,
            group: null,
            items: g.items,
            total: g.items.length,
            up: g.items.filter((c) => c.status === 'up').length,
            down: g.items.filter(isDown).length,
            defaultCollapsed: false,
            showHeader: mode !== 'none',
        }))
        .sort((a, b) => b.down - a.down || a.label.localeCompare(b.label));
});

const layoutOf = (s: Section): GroupLayout =>
    view.value.layout !== 'auto'
        ? view.value.layout
        : (s.group?.layout ?? 'list');

const totals = computed(() => ({
    down: props.checks.filter(isDown).length,
    up: props.checks.filter((c) => c.status === 'up').length,
    all: props.checks.length,
}));

const layoutOptions = computed<{ value: GroupLayout; label: string }[]>(() => [
    { value: 'list', label: t('List') },
    { value: 'grid', label: t('Cards') },
    { value: 'compact', label: t('Compact') },
]);

function setGroupLayout(g: GroupOption, layout: GroupLayout) {
    router.patch(
        GroupController.update.url(g.id),
        { name: g.name, layout },
        { preserveScroll: true },
    );
}

function toggleSelect(ids: number[], on: boolean) {
    selected.value = on
        ? [...new Set([...selected.value, ...ids])]
        : selected.value.filter((id) => !ids.includes(id));
}

/* ---------- create / edit ---------- */
const dialogOpen = ref(false);
const editing = ref<Check | null>(null);
const tagsInput = ref('');

const form = useForm({
    name: '',
    type: 'http',
    target: '',
    group_id: '' as string | number,
    host_id: '' as string | number,
    category: 'website',
    tags: [] as string[],
    interval_sec: 60,
    timeout_sec: 10,
    failure_threshold: 2,
    execution: 'hub',
    quorum: 'majority',
    enabled: true,
});

function openCreate() {
    router.visit('/monitor/new');
}

function openEdit(c: Check) {
    router.visit(CheckController.show.url(c.id, { query: { edit: '1' } }));
}

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
    }));
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        form.patch(CheckController.update.url(editing.value.id), options);
    } else {
        form.post(CheckController.store.url(), options);
    }
}

function togglePause(c: Check) {
    router.patch(
        CheckController.update.url(c.id),
        {
            name: c.name,
            type: c.type,
            target: c.target,
            host_id: c.host_id,
            enabled: !c.enabled,
        },
        { preserveScroll: true },
    );
}

function remove(c: Check) {
    if (confirm(t('Delete the check ":name"?', { name: c.name }))) {
        router.delete(CheckController.destroy.url(c.id), {
            preserveScroll: true,
        });
    }
}

/* ---------- Uptime Kuma import ---------- */
type KumaResult = {
    created: string[];
    skipped: { name: string; reason: string }[];
    dry_run: boolean;
};
const importOpen = ref(false);
const importForm = useForm({ file: null as File | null, dry_run: true });
const kumaResult = computed(
    () => usePage().props.kumaImport as KumaResult | null,
);

function runImport(dryRun: boolean) {
    importForm.dry_run = dryRun;
    importForm.post(CheckController.importKuma.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (!dryRun) {
                importOpen.value = false;
                importForm.reset();
            }
        },
    });
}

/* ---------- bulk ---------- */
const bulk = ref({ group_id: '', category: '', tags: '' });

function bulkAction(action: string, extra: Record<string, unknown> = {}) {
    router.post(
        CheckController.bulk.url(),
        { ids: selected.value, action, ...extra },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                bulk.value = { group_id: '', category: '', tags: '' };
            },
        },
    );
}

function bulkAssign() {
    const extra: Record<string, unknown> = {};

    if (bulk.value.group_id !== '') {
        extra.group_id =
            bulk.value.group_id === 'none' ? null : bulk.value.group_id;
    }

    if (bulk.value.category) {
        extra.category = bulk.value.category;
    }

    if (bulk.value.tags) {
        extra.add_tags = parseTags(bulk.value.tags);
    }

    if (Object.keys(extra).length) {
        bulkAction('assign', extra);
    }
}

function bulkDelete() {
    if (confirm(t('Delete :count checks?', { count: selected.value.length }))) {
        bulkAction('delete');
    }
}
</script>

<template>
    <Head :title="t('Monitors')" />

    <PageContainer size="wide">
        <PageHeader
            :eyebrow="t('Monitors')"
            :title="t('Monitors')"
            :description="
                t(':up up · :down down · :all total', {
                    up: totals.up,
                    down: totals.down,
                    all: totals.all,
                })
            "
        >
            <template v-if="canManage">
                <Button variant="outline" @click="importOpen = true">
                    <Upload class="size-4" /> {{ t('Import from Uptime Kuma') }}
                </Button>
                <Button @click="openCreate">
                    <Plus class="size-4" /> {{ t('Add a monitor') }}
                </Button>
            </template>
        </PageHeader>

        <div v-if="checks.length" class="grid gap-4 sm:grid-cols-3">
            <StatTile
                :label="t('Up')"
                :value="totals.up"
                tone="success"
                class="reveal"
            />
            <StatTile
                :label="t('Down')"
                :value="totals.down"
                :tone="totals.down ? 'danger' : 'default'"
                class="reveal reveal-delay-1"
            />
            <StatTile
                :label="t('Total')"
                :value="totals.all"
                class="reveal reveal-delay-2"
            />
        </div>

        <!-- toolbar -->
        <search v-if="checks.length" class="surface p-4">
            <form
                class="flex flex-wrap items-center gap-2"
                role="search"
                @submit.prevent
            >
                <Input
                    v-model="view.q"
                    class="w-56"
                    type="search"
                    :placeholder="t('Search name, target, group, tag…')"
                    :aria-label="t('Search')"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                />
                <select
                    v-model="view.groupFilter"
                    :class="select"
                    :aria-label="t('Group')"
                >
                    <option value="all">{{ t('All groups') }}</option>
                    <option value="none">{{ t('No group') }}</option>
                    <option
                        v-for="g in groups"
                        :key="g.id"
                        :value="String(g.id)"
                    >
                        {{ g.path }}
                    </option>
                </select>
                <select
                    v-model="view.category"
                    :class="select"
                    :aria-label="t('Category')"
                >
                    <option value="all">{{ t('All categories') }}</option>
                    <option v-for="c in categories" :key="c" :value="c">
                        {{ categoryLabel(c) }}
                    </option>
                </select>
                <select
                    v-model="view.status"
                    :class="select"
                    :aria-label="t('Status')"
                >
                    <option value="all">{{ t('All statuses') }}</option>
                    <option value="down">{{ t('Down') }}</option>
                    <option value="up">{{ t('Up') }}</option>
                    <option value="unknown">{{ t('Unknown') }}</option>
                </select>
                <label
                    class="ml-auto flex items-center gap-2 text-sm text-muted-foreground"
                >
                    {{ t('Group by') }}
                    <select v-model="view.groupBy" :class="select">
                        <option value="group">{{ t('Group tree') }}</option>
                        <option value="category">{{ t('Category') }}</option>
                        <option value="status">{{ t('Status') }}</option>
                        <option value="none">{{ t('No grouping') }}</option>
                    </select>
                </label>
                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    {{ t('Display') }}
                    <select v-model="view.layout" :class="select">
                        <option value="auto">
                            {{ t("Use each group's layout") }}
                        </option>
                        <option
                            v-for="l in layoutOptions"
                            :key="l.value"
                            :value="l.value"
                        >
                            {{ l.label }}
                        </option>
                    </select>
                </label>
                <select
                    v-model="view.sort"
                    :class="select"
                    :aria-label="t('Sort')"
                >
                    <option value="status">{{ t('Sort: status') }}</option>
                    <option value="name">{{ t('Sort: name') }}</option>
                    <option value="latency">{{ t('Sort: latency') }}</option>
                </select>
            </form>
        </search>

        <!-- bulk bar -->
        <div
            v-if="selected.length && canManage"
            class="flex flex-wrap items-center gap-2 surface p-4 text-sm"
        >
            <strong class="font-display">{{
                t(':count selected', { count: selected.length })
            }}</strong>
            <select
                v-model="bulk.group_id"
                :class="select"
                :aria-label="t('Group')"
            >
                <option value="">{{ t('Group…') }}</option>
                <option value="none">{{ t('No group') }}</option>
                <option v-for="g in groups" :key="g.id" :value="String(g.id)">
                    {{ g.path }}
                </option>
            </select>
            <select
                v-model="bulk.category"
                :class="select"
                :aria-label="t('Category')"
            >
                <option value="">{{ t('Category…') }}</option>
                <option v-for="c in categories" :key="c" :value="c">
                    {{ categoryLabel(c) }}
                </option>
            </select>
            <Input
                v-model="bulk.tags"
                class="w-40"
                :placeholder="t('Add tags (a, b)')"
            />
            <Button size="sm" @click="bulkAssign">{{ t('Apply') }}</Button>
            <span class="mx-2 h-5 border-l" aria-hidden="true" />
            <Button size="sm" variant="outline" @click="bulkAction('disable')">
                <Pause class="size-3" /> {{ t('Pause') }}
            </Button>
            <Button size="sm" variant="outline" @click="bulkAction('enable')">
                <Play class="size-3" /> {{ t('Resume') }}
            </Button>
            <Button
                v-if="isAdmin"
                size="sm"
                variant="destructive"
                @click="bulkDelete"
            >
                <Trash2 class="size-3" /> {{ t('Delete') }}
            </Button>
            <Button size="sm" variant="ghost" @click="selected = []">
                {{ t('Clear selection') }}
            </Button>
        </div>

        <!-- empty states -->
        <EmptyState
            v-if="!checks.length"
            :icon="Activity"
            :title="t('No checks yet')"
            :description="
                t(
                    'A check monitors a website, a port or a server and alerts you when it goes down.',
                )
            "
        >
            <Button v-if="canManage" @click="openCreate">
                {{ t('Add your first monitor') }}
            </Button>
        </EmptyState>
        <EmptyState
            v-else-if="!filtered.length"
            :title="t('No check matches these filters.')"
        />

        <!-- sections (group tree is flattened depth-first, indented by depth) -->
        <section
            v-for="s in sections"
            :key="s.key"
            class="overflow-hidden surface"
            :style="{ marginLeft: `${s.depth * 1.25}rem` }"
        >
            <header
                v-if="s.showHeader"
                class="flex flex-wrap items-center gap-2 border-b border-l-4 bg-muted/30 px-4 py-3"
                :style="{ borderLeftColor: s.color ?? 'transparent' }"
            >
                <label
                    v-if="canManage && s.items.length"
                    class="inline-flex size-6 items-center justify-center"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        :checked="s.items.every((c) => selected.includes(c.id))"
                        :aria-label="
                            t('Select all in :name', { name: s.label })
                        "
                        @change="
                            toggleSelect(
                                s.items.map((c) => c.id),
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                </label>
                <h2 class="min-w-0 flex-1 text-sm">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 text-left"
                        :aria-expanded="!isCollapsed(s)"
                        :aria-controls="`section-${s.key}`"
                        @click="toggleSection(s)"
                    >
                        <component
                            :is="isCollapsed(s) ? ChevronRight : ChevronDown"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="truncate font-display font-semibold">{{
                            s.label
                        }}</span>
                        <span class="chip">{{ s.total }}</span>
                        <span
                            v-if="s.down"
                            class="ml-1 chip inline-flex items-center gap-1.5 chip-danger"
                        >
                            <StatusDot status="down" />
                            {{ s.down }} {{ t('down') }}
                        </span>
                        <span
                            v-else-if="s.total > 0 && s.up === s.total"
                            class="ml-1 chip inline-flex items-center gap-1.5 chip-success"
                        >
                            <StatusDot status="up" />
                            {{ t('all up') }}
                        </span>
                    </button>
                </h2>
                <select
                    v-if="s.group && canManage && view.layout === 'auto'"
                    :value="s.group.layout"
                    :class="[select, 'h-9 text-xs']"
                    :aria-label="t('Display of :name', { name: s.label })"
                    @change="
                        setGroupLayout(
                            s.group!,
                            ($event.target as HTMLSelectElement)
                                .value as GroupLayout,
                        )
                    "
                >
                    <option
                        v-for="l in layoutOptions"
                        :key="l.value"
                        :value="l.value"
                    >
                        {{ l.label }}
                    </option>
                </select>
            </header>

            <div
                v-if="!s.showHeader || !isCollapsed(s)"
                :id="`section-${s.key}`"
            >
                <table
                    v-if="s.items.length && layoutOf(s) === 'list'"
                    class="table-clean"
                >
                    <thead
                        class="border-b text-left text-[0.65rem] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th v-if="canManage" class="w-10 py-2 pl-4">
                                <span class="sr-only">{{ t('Select') }}</span>
                            </th>
                            <th class="w-36 py-2">{{ t('Status') }}</th>
                            <th class="py-2">{{ t('Name') }}</th>
                            <th class="hidden w-48 py-2 md:table-cell">
                                {{
                                    t('Last :minutes minutes', {
                                        minutes: MONITOR_HISTORY_MINUTES,
                                    })
                                }}
                            </th>
                            <th class="hidden py-2 md:table-cell">
                                {{ t('Tags') }}
                            </th>
                            <th class="w-24 py-2 text-right">
                                {{ t('Metric') }}
                            </th>
                            <th
                                v-if="canManage"
                                class="w-36 py-2 pr-3 text-right"
                            >
                                {{ t('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="entry in orderedWebsiteMonitorEntries(
                                s.items,
                            )"
                            :key="
                                entry.kind === 'site-group'
                                    ? `g-${entry.group.primary.id}`
                                    : entry.check.id
                            "
                        >
                            <template v-if="entry.kind === 'site-group'">
                                <CheckEntry
                                    :check="entry.group.primary"
                                    layout="list"
                                    :selected="
                                        selected.includes(
                                            entry.group.primary.id,
                                        )
                                    "
                                    :can-manage="canManage"
                                    :is-admin="isAdmin"
                                    :status-text="
                                        statusLabel(entry.group.primary.status)
                                    "
                                    :category-text="
                                        view.groupBy !== 'category'
                                            ? categoryLabel(
                                                  entry.group.primary.category,
                                              )
                                            : null
                                    "
                                    @select="
                                        toggleSelect(
                                            [entry.group.primary.id],
                                            $event,
                                        )
                                    "
                                    @pause="togglePause(entry.group.primary)"
                                    @edit="openEdit(entry.group.primary)"
                                    @remove="remove(entry.group.primary)"
                                    @tag="view.q = $event"
                                />
                                <CheckEntry
                                    :check="entry.group.companion!"
                                    layout="list"
                                    companion-row
                                    :selected="
                                        selected.includes(
                                            entry.group.companion!.id,
                                        )
                                    "
                                    :can-manage="canManage"
                                    :is-admin="isAdmin"
                                    :status-text="
                                        statusLabel(
                                            entry.group.companion!.status,
                                        )
                                    "
                                    :category-text="
                                        view.groupBy !== 'category'
                                            ? categoryLabel(
                                                  entry.group.companion!
                                                      .category,
                                              )
                                            : null
                                    "
                                    @select="
                                        toggleSelect(
                                            [entry.group.companion!.id],
                                            $event,
                                        )
                                    "
                                    @pause="togglePause(entry.group.companion!)"
                                    @edit="openEdit(entry.group.companion!)"
                                    @remove="remove(entry.group.companion!)"
                                    @tag="view.q = $event"
                                />
                            </template>
                            <CheckEntry
                                v-else
                                :check="entry.check"
                                layout="list"
                                :selected="selected.includes(entry.check.id)"
                                :can-manage="canManage"
                                :is-admin="isAdmin"
                                :status-text="statusLabel(entry.check.status)"
                                :category-text="
                                    view.groupBy !== 'category'
                                        ? categoryLabel(entry.check.category)
                                        : null
                                "
                                @select="toggleSelect([entry.check.id], $event)"
                                @pause="togglePause(entry.check)"
                                @edit="openEdit(entry.check)"
                                @remove="remove(entry.check)"
                                @tag="view.q = $event"
                            />
                        </template>
                    </tbody>
                </table>
                <div
                    v-else-if="s.items.length"
                    :class="
                        layoutOf(s) === 'grid'
                            ? 'grid grid-cols-[repeat(auto-fill,minmax(16rem,1fr))] gap-3 p-4'
                            : 'flex flex-wrap gap-2 p-4'
                    "
                >
                    <CheckEntry
                        v-for="c in s.items"
                        :key="c.id"
                        :check="c"
                        :layout="layoutOf(s)"
                        :selected="selected.includes(c.id)"
                        :can-manage="canManage"
                        :is-admin="isAdmin"
                        :status-text="statusLabel(c.status)"
                        :category-text="null"
                        @select="toggleSelect([c.id], $event)"
                        @pause="togglePause(c)"
                        @edit="openEdit(c)"
                        @remove="remove(c)"
                        @tag="view.q = $event"
                    />
                </div>
                <p
                    v-else-if="s.group && s.total === 0"
                    class="px-5 py-4 text-xs text-muted-foreground"
                >
                    {{ t('Nothing in this group yet.') }}
                </p>
            </div>
        </section>

        <Dialog v-model:open="importOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('Import from Uptime Kuma')
                    }}</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Upload a Kuma JSON backup (Settings → Backup) or the kuma.db file (data/kuma.db). HTTP, keyword, TCP port, ping and DNS monitors are imported; notification channels are not.',
                        )
                    }}
                </p>
                <input
                    type="file"
                    accept=".json,.db,.sqlite,.sqlite3"
                    class="text-sm"
                    @change="
                        importForm.file =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                />
                <InputError :message="importForm.errors.file" />
                <div
                    v-if="kumaResult"
                    class="max-h-60 overflow-y-auto surface-muted p-4 text-sm"
                >
                    <p class="font-medium">
                        {{
                            kumaResult.dry_run
                                ? t(':count monitors will be imported', {
                                      count: kumaResult.created.length,
                                  })
                                : t(':count monitors imported.', {
                                      count: kumaResult.created.length,
                                  })
                        }}
                    </p>
                    <ul
                        v-if="kumaResult.skipped.length"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        <li v-for="s in kumaResult.skipped" :key="s.name">
                            {{ s.name }} — {{ t(s.reason) }}
                        </li>
                    </ul>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="!importForm.file || importForm.processing"
                        @click="runImport(true)"
                    >
                        {{ t('Preview') }}
                    </Button>
                    <Button
                        :disabled="!importForm.file || importForm.processing"
                        @click="runImport(false)"
                    >
                        {{ t('Import') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? t('Edit check') : t('New check') }}
                    </DialogTitle>
                </DialogHeader>

                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <div class="field-stack sm:col-span-2">
                        <Label for="name">{{ t('Name') }}</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="field-stack">
                        <Label for="type">{{ t('Type') }}</Label>
                        <select id="type" v-model="form.type" :class="select">
                            <option v-for="ty in types" :key="ty" :value="ty">
                                {{ checkTypeLabel(ty) }}
                            </option>
                        </select>
                    </div>
                    <div v-if="form.type !== 'heartbeat'" class="field-stack">
                        <Label for="host_id">{{
                            form.type === 'agent'
                                ? t('Host')
                                : t('Runs on server')
                        }}</Label>
                        <select
                            id="host_id"
                            v-model="form.host_id"
                            :class="select"
                            :required="form.type === 'agent'"
                        >
                            <option value="">—</option>
                            <option
                                v-for="h in hosts"
                                :key="h.id"
                                :value="h.id"
                            >
                                {{ h.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.host_id" />
                    </div>
                    <div
                        v-if="
                            form.type !== 'agent' && form.type !== 'heartbeat'
                        "
                        class="field-stack"
                    >
                        <Label for="target">{{
                            t('Target (URL, host:port, domain)')
                        }}</Label>
                        <Input id="target" v-model="form.target" />
                        <InputError :message="form.errors.target" />
                    </div>
                    <div class="field-stack">
                        <Label for="group_id">{{ t('Group') }}</Label>
                        <GroupSelect
                            id="group_id"
                            v-model="form.group_id"
                            :groups="groups"
                        />
                        <InputError :message="form.errors.group_id" />
                    </div>
                    <div class="field-stack">
                        <Label for="category">{{ t('Category') }}</Label>
                        <select
                            id="category"
                            v-model="form.category"
                            :class="select"
                        >
                            <option v-for="c in categories" :key="c" :value="c">
                                {{ categoryLabel(c) }}
                            </option>
                        </select>
                    </div>
                    <div class="field-stack sm:col-span-2">
                        <Label for="tags">{{
                            t('Tags (comma separated)')
                        }}</Label>
                        <Input
                            id="tags"
                            v-model="tagsInput"
                            :placeholder="t('prod, wordpress, eu')"
                        />
                    </div>
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
                    </div>
                    <div class="field-stack">
                        <Label for="execution">{{ t('Run from') }}</Label>
                        <select
                            id="execution"
                            v-model="form.execution"
                            :class="select"
                        >
                            <option value="hub">{{ t('The hub') }}</option>
                            <option value="probe">
                                {{ t('Remote probes') }}
                            </option>
                        </select>
                    </div>
                    <div v-if="form.execution === 'probe'" class="field-stack">
                        <Label for="quorum">{{ t('Quorum') }}</Label>
                        <select
                            id="quorum"
                            v-model="form.quorum"
                            :class="select"
                        >
                            <option value="majority">
                                {{ t('Majority of probes') }}
                            </option>
                            <option value="any">{{ t('Any probe') }}</option>
                            <option value="all">{{ t('All probes') }}</option>
                        </select>
                    </div>
                    <DialogFooter class="sm:col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="dialogOpen = false"
                        >
                            {{ t('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ t('Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </PageContainer>
</template>
