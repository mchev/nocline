<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarClock, Pencil, Play, Plus, Trash2, Wrench } from '@lucide/vue';
import { computed, ref } from 'vue';
import MaintenanceController from '@/actions/App/Modules/Maintenance/Http/Controllers/MaintenanceController';
import GroupSelect from '@/components/GroupSelect.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

type Recurrence = 'none' | 'daily' | 'weekly';
type Scope = 'all' | 'group' | 'check';
type Win = {
    id: number;
    name: string;
    group_id: number | null;
    check_id: number | null;
    scope: string | null;
    starts_at: string;
    ends_at: string;
    recurrence: Recurrence;
    state: 'active' | 'upcoming' | 'past';
    next_starts_at: string;
    next_ends_at: string;
};

const props = defineProps<{
    windows: Win[];
    groups: GroupOption[];
    checks: { id: number; name: string }[];
}>();

const canManage = computed(() => usePage().props.auth.user.role !== 'viewer');
const select =
    'h-10 rounded-md border bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const active = computed(() =>
    props.windows.filter((w) => w.state === 'active'),
);
const upcoming = computed(() =>
    props.windows.filter((w) => w.state === 'upcoming'),
);
const past = computed(() => props.windows.filter((w) => w.state === 'past'));

const recurrenceLabel = (r: Recurrence) =>
    ({ none: t('Once'), daily: t('Every day'), weekly: t('Every week') })[r];
const scopeLabel = (w: Win) =>
    w.check_id !== null
        ? t('Monitor: :name', { name: w.scope ?? '' })
        : w.group_id !== null
          ? t('Group: :name', { name: w.scope ?? '' })
          : t('Everything');
const when = (iso: string) =>
    new Date(iso).toLocaleString([], {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

/** ISO instant -> value of a datetime-local input (browser time zone). */
function toInput(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

type Fields = {
    name: string;
    scope: Scope;
    group_id: string | number;
    check_id: string | number;
    starts_at: string;
    ends_at: string;
    recurrence: Recurrence;
};

const form = useForm<Fields>({
    name: '',
    scope: 'all',
    group_id: '',
    check_id: '',
    starts_at: '',
    ends_at: '',
    recurrence: 'none',
});
const open = ref(false);
const editingId = ref<number | null>(null);

function startCreate(minutes: number | null = null) {
    const now = new Date();
    const start = minutes === null ? new Date(now.getTime() + 3600_000) : now;
    const end = new Date(start.getTime() + (minutes ?? 60) * 60_000);

    editingId.value = null;
    form.clearErrors();
    form.defaults({
        name: minutes === null ? '' : t('Maintenance'),
        scope: 'all',
        group_id: '',
        check_id: '',
        starts_at: toInput(start),
        ends_at: toInput(end),
        recurrence: 'none',
    }).reset();
    open.value = true;
}

function startEdit(w: Win) {
    editingId.value = w.id;
    form.clearErrors();
    form.defaults({
        name: w.name,
        scope:
            w.check_id !== null
                ? 'check'
                : w.group_id !== null
                  ? 'group'
                  : 'all',
        group_id: w.group_id ?? '',
        check_id: w.check_id ?? '',
        starts_at: toInput(new Date(w.starts_at)),
        ends_at: toInput(new Date(w.ends_at)),
        recurrence: w.recurrence,
    }).reset();
    open.value = true;
}

function save() {
    form.transform((d) => ({
        name: d.name,
        group_id: d.scope === 'group' && d.group_id !== '' ? d.group_id : null,
        check_id: d.scope === 'check' && d.check_id !== '' ? d.check_id : null,
        starts_at: d.starts_at ? new Date(d.starts_at).toISOString() : '',
        ends_at: d.ends_at ? new Date(d.ends_at).toISOString() : '',
        recurrence: d.recurrence,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editingId.value === null) {
        form.post(MaintenanceController.store.url(), options);
    } else {
        form.patch(MaintenanceController.update.url(editingId.value), options);
    }
}

function remove(w: Win) {
    if (confirm(t('Delete the maintenance ":name"?', { name: w.name }))) {
        router.delete(MaintenanceController.destroy.url(w.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="t('Planned maintenance')" />

    <PageContainer>
        <PageHeader
            :title="t('Planned maintenance')"
            :description="
                t(
                    `Announce an intervention and we won't alert you during this time. Monitors keep running and show “Maintenance” instead of “Down”.`,
                )
            "
        >
            <template v-if="canManage">
                <Button variant="outline" @click="startCreate(60)">
                    <Play class="size-4" />
                    {{ t('Start maintenance now for 1 hour') }}
                </Button>
                <Button @click="startCreate()"
                    ><Plus class="size-4" />
                    {{ t('Plan a maintenance') }}</Button
                >
            </template>
        </PageHeader>

        <EmptyState
            v-if="windows.length === 0"
            :icon="Wrench"
            :title="t('No maintenance planned')"
            :description="
                t(
                    'Going to restart a server or update a site? Plan it here so nobody gets a false alarm.',
                )
            "
        >
            <Button v-if="canManage" @click="startCreate()">{{
                t('Plan a maintenance')
            }}</Button>
        </EmptyState>

        <template
            v-for="section in [
                { key: 'active', title: t('In progress'), items: active },
                { key: 'upcoming', title: t('Upcoming'), items: upcoming },
                { key: 'past', title: t('Past'), items: past },
            ]"
            :key="section.key"
        >
            <section
                v-if="section.items.length"
                :aria-labelledby="`h-${section.key}`"
            >
                <h2
                    :id="`h-${section.key}`"
                    class="mb-3 text-sm font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ section.title }}
                </h2>
                <ul class="divide-y overflow-hidden surface">
                    <li
                        v-for="w in section.items"
                        :key="w.id"
                        class="flex flex-wrap items-center gap-3 p-4 transition-colors hover:bg-muted/50"
                    >
                        <CalendarClock
                            class="size-5 shrink-0"
                            :class="
                                w.state === 'active'
                                    ? 'text-info'
                                    : 'text-muted-foreground'
                            "
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                {{ w.name }}
                                <Badge
                                    v-if="w.state === 'active'"
                                    variant="info"
                                    class="ml-1"
                                    >{{ t('Maintenance') }}</Badge
                                >
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ when(w.next_starts_at) }} →
                                {{ when(w.next_ends_at) }} ·
                                {{ recurrenceLabel(w.recurrence) }} ·
                                {{ scopeLabel(w) }}
                            </p>
                        </div>
                        <div v-if="canManage" class="flex">
                            <Button
                                size="icon"
                                variant="ghost"
                                :title="t('Edit')"
                                :aria-label="t('Edit')"
                                @click="startEdit(w)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                :title="t('Delete')"
                                :aria-label="t('Delete')"
                                @click="remove(w)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>
        </template>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editingId === null
                            ? t('Plan a maintenance')
                            : t('Edit maintenance')
                    }}</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            "Planned maintenance — we won't alert you during this time.",
                        )
                    }}
                </p>

                <form class="grid gap-4" @submit.prevent="save">
                    <div class="field-stack">
                        <Label for="m-name" class="field-label">{{
                            t('Name')
                        }}</Label>
                        <Input
                            id="m-name"
                            v-model="form.name"
                            :placeholder="t('Server update')"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="field-label mb-1">
                            {{ t('What is affected?') }}
                        </legend>
                        <label class="flex min-h-10 items-center gap-2 text-sm">
                            <input
                                v-model="form.scope"
                                type="radio"
                                name="scope"
                                value="all"
                                class="accent-primary"
                            />
                            {{ t('Everything') }}
                        </label>
                        <label
                            class="flex min-h-10 flex-wrap items-center gap-2 text-sm"
                        >
                            <input
                                v-model="form.scope"
                                type="radio"
                                name="scope"
                                value="group"
                                class="accent-primary"
                            />
                            {{ t('A group (and its sub-groups)') }}
                            <GroupSelect
                                v-if="form.scope === 'group'"
                                v-model="form.group_id"
                                :groups="groups"
                                :empty-label="t('Choose a group')"
                                :aria-label="t('Group')"
                            />
                        </label>
                        <label
                            class="flex min-h-10 flex-wrap items-center gap-2 text-sm"
                        >
                            <input
                                v-model="form.scope"
                                type="radio"
                                name="scope"
                                value="check"
                                class="accent-primary"
                            />
                            {{ t('One monitor') }}
                            <select
                                v-if="form.scope === 'check'"
                                v-model="form.check_id"
                                :class="select"
                                :aria-label="t('Monitor')"
                            >
                                <option value="">
                                    {{ t('Choose a monitor') }}
                                </option>
                                <option
                                    v-for="c in checks"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                        </label>
                        <InputError
                            :message="
                                form.errors.group_id || form.errors.check_id
                            "
                        />
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="field-stack">
                            <Label for="m-start" class="field-label">{{
                                t('Starts')
                            }}</Label>
                            <Input
                                id="m-start"
                                v-model="form.starts_at"
                                type="datetime-local"
                                required
                            />
                            <InputError :message="form.errors.starts_at" />
                        </div>
                        <div class="field-stack">
                            <Label for="m-end" class="field-label">{{
                                t('Ends')
                            }}</Label>
                            <Input
                                id="m-end"
                                v-model="form.ends_at"
                                type="datetime-local"
                                required
                            />
                            <InputError :message="form.errors.ends_at" />
                        </div>
                    </div>
                    <p class="field-hint -mt-2">
                        {{
                            t('Times are in your time zone (:zone).', {
                                zone: timeZone,
                            })
                        }}
                    </p>

                    <div class="field-stack">
                        <Label for="m-rec" class="field-label">{{
                            t('Repeat')
                        }}</Label>
                        <select
                            id="m-rec"
                            v-model="form.recurrence"
                            :class="select"
                        >
                            <option value="none">{{ t('Once') }}</option>
                            <option value="daily">{{ t('Every day') }}</option>
                            <option value="weekly">
                                {{ t('Every week') }}
                            </option>
                        </select>
                        <InputError :message="form.errors.recurrence" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >{{ t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            t('Save')
                        }}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </PageContainer>
</template>
