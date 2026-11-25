<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { FolderPlus, Folders, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import GroupController from '@/actions/App/Modules/Groups/Http/Controllers/GroupController';
import GroupSelect from '@/components/GroupSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { DEFAULT_GROUP_COLOR, groupWithDescendantIds } from '@/lib/groups';
import { t } from '@/lib/i18n';
import type { GroupLayout, GroupOption } from '@/types/groups';

type NotificationChannelOption = {
    id: number;
    name: string;
    enabled: boolean;
};

const props = defineProps<{
    groups: GroupOption[];
    notification_channels: NotificationChannelOption[];
}>();

const role = computed(() => usePage().props.auth.user.role);
const canManage = computed(() => role.value !== 'viewer');
const isAdmin = computed(() => role.value === 'admin');
const select =
    'h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none';

const layouts = computed<{ value: GroupLayout; label: string }[]>(() => [
    { value: 'list', label: t('List') },
    { value: 'grid', label: t('Cards') },
    { value: 'compact', label: t('Compact') },
]);

type GroupFields = {
    name: string;
    parent_id: string | number;
    color: string;
    layout: GroupLayout;
    collapsed: boolean;
    channel_ids: number[];
};

const blank = (): GroupFields => ({
    name: '',
    parent_id: '',
    color: '',
    layout: 'list',
    collapsed: false,
    channel_ids: [],
});

const createForm = useForm<GroupFields>(blank());

function create() {
    createForm
        .transform((d) => ({
            ...d,
            parent_id: d.parent_id === '' ? null : d.parent_id,
            color: d.color || null,
        }))
        .post(GroupController.store.url(), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
}

const editingId = ref<number | null>(null);
const editForm = useForm<GroupFields>(blank());
const editExcluded = computed(() =>
    editingId.value === null
        ? []
        : groupWithDescendantIds(props.groups, editingId.value),
);

function startEdit(g: GroupOption) {
    editingId.value = g.id;
    editForm.clearErrors();
    editForm
        .defaults({
            name: g.name,
            parent_id: g.parent_id ?? '',
            color: g.color ?? '',
            layout: g.layout,
            collapsed: g.collapsed,
            channel_ids: [...(g.channel_ids ?? [])],
        })
        .reset();
}

function toggleChannel(id: number) {
    const set = new Set(editForm.channel_ids);
    if (set.has(id)) {
        set.delete(id);
    } else {
        set.add(id);
    }
    editForm.channel_ids = [...set];
}

function saveEdit() {
    if (editingId.value === null) {
        return;
    }

    editForm
        .transform((d) => ({
            ...d,
            parent_id: d.parent_id === '' ? null : d.parent_id,
            color: d.color || null,
        }))
        .patch(GroupController.update.url(editingId.value), {
            preserveScroll: true,
            onSuccess: () => (editingId.value = null),
        });
}

function remove(g: GroupOption) {
    if (
        confirm(
            t(
                'Delete the group ":name"? Its monitors stay, but without a group; its sub-groups move up one level.',
                { name: g.name },
            ),
        )
    ) {
        router.delete(GroupController.destroy.url(g.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="t('Groups')" />

    <PageContainer>
        <PageHeader
            :title="t('Groups')"
            :description="
                t(
                    'Groups are folders for your sites and servers. Put a group inside another one to build a tree (for example Acme › Production).',
                )
            "
        />

        <form
            v-if="canManage"
            class="form-panel reveal"
            aria-labelledby="new-group-heading"
            @submit.prevent="create"
        >
            <h2
                id="new-group-heading"
                class="font-display text-lg font-semibold"
            >
                {{ t('Add a group') }}
            </h2>
            <div
                class="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto] md:items-end"
            >
                <div class="field-stack">
                    <Label for="new-name" class="field-label">{{
                        t('Group name')
                    }}</Label>
                    <Input id="new-name" v-model="createForm.name" required />
                    <InputError :message="createForm.errors.name" />
                </div>
                <div class="field-stack">
                    <Label for="new-parent" class="field-label">{{
                        t('Inside')
                    }}</Label>
                    <GroupSelect
                        id="new-parent"
                        v-model="createForm.parent_id"
                        :groups="groups"
                        :empty-label="t('Top level')"
                    />
                    <InputError :message="createForm.errors.parent_id" />
                </div>
                <div class="field-stack">
                    <Label for="new-color" class="field-label">{{
                        t('Colour')
                    }}</Label>
                    <input
                        id="new-color"
                        type="color"
                        class="h-10 w-14 cursor-pointer rounded-md border border-input bg-transparent p-1"
                        :value="createForm.color || DEFAULT_GROUP_COLOR"
                        @input="
                            createForm.color = (
                                $event.target as HTMLInputElement
                            ).value
                        "
                    />
                </div>
                <div class="field-stack">
                    <Label for="new-layout" class="field-label">{{
                        t('Display')
                    }}</Label>
                    <select
                        id="new-layout"
                        v-model="createForm.layout"
                        :class="select"
                    >
                        <option
                            v-for="l in layouts"
                            :key="l.value"
                            :value="l.value"
                        >
                            {{ l.label }}
                        </option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end">
                <Button type="submit" :disabled="createForm.processing">
                    <FolderPlus class="size-4" /> {{ t('Add a group') }}
                </Button>
            </div>
        </form>

        <EmptyState
            v-if="!groups.length"
            :icon="Folders"
            :title="t('No groups yet')"
            :description="t('No groups yet. Create your first one above.')"
        />

        <ul
            v-else
            class="reveal reveal-delay-1 divide-y overflow-hidden surface"
        >
            <li
                v-for="g in groups"
                :key="g.id"
                class="px-4 py-3 transition-colors hover:bg-muted/50"
            >
                <form
                    v-if="editingId === g.id"
                    class="grid gap-4 rounded-md border border-dashed bg-muted/30 p-4 md:grid-cols-[1fr_1fr_auto_auto] md:items-end"
                    :style="{ marginLeft: `${g.depth * 1.25}rem` }"
                    @submit.prevent="saveEdit"
                >
                    <div class="field-stack">
                        <Label :for="`edit-name-${g.id}`" class="field-label">
                            {{ t('Group name') }}
                        </Label>
                        <Input
                            :id="`edit-name-${g.id}`"
                            v-model="editForm.name"
                            required
                        />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div class="field-stack">
                        <Label :for="`edit-parent-${g.id}`" class="field-label">
                            {{ t('Inside') }}
                        </Label>
                        <GroupSelect
                            :id="`edit-parent-${g.id}`"
                            v-model="editForm.parent_id"
                            :groups="groups"
                            :exclude="editExcluded"
                            :empty-label="t('Top level')"
                        />
                        <InputError :message="editForm.errors.parent_id" />
                    </div>
                    <div class="field-stack">
                        <Label :for="`edit-color-${g.id}`" class="field-label">
                            {{ t('Colour') }}
                        </Label>
                        <div class="flex items-center gap-2">
                            <input
                                :id="`edit-color-${g.id}`"
                                type="color"
                                class="h-10 w-14 cursor-pointer rounded-md border border-input bg-transparent p-1"
                                :value="editForm.color || DEFAULT_GROUP_COLOR"
                                @input="
                                    editForm.color = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                            />
                            <Button
                                v-if="editForm.color"
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="editForm.color = ''"
                            >
                                {{ t('No colour') }}
                            </Button>
                        </div>
                    </div>
                    <div class="field-stack">
                        <Label :for="`edit-layout-${g.id}`" class="field-label">
                            {{ t('Display') }}
                        </Label>
                        <select
                            :id="`edit-layout-${g.id}`"
                            v-model="editForm.layout"
                            :class="select"
                        >
                            <option
                                v-for="l in layouts"
                                :key="l.value"
                                :value="l.value"
                            >
                                {{ l.label }}
                            </option>
                        </select>
                    </div>
                    <fieldset
                        v-if="notification_channels.length"
                        class="space-y-2 md:col-span-2"
                    >
                        <legend class="text-sm font-medium">
                            {{ t('Alert channels') }}
                        </legend>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t(
                                    'Leave empty to use all enabled channels. Pick channels to notify only this group’s team.',
                                )
                            }}
                        </p>
                        <ul class="flex flex-wrap gap-3">
                            <li
                                v-for="ch in notification_channels"
                                :key="ch.id"
                            >
                                <label
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            editForm.channel_ids.includes(ch.id)
                                        "
                                        @change="toggleChannel(ch.id)"
                                    />
                                    {{ ch.name }}
                                    <span
                                        v-if="!ch.enabled"
                                        class="text-xs text-muted-foreground"
                                        >({{ t('off') }})</span
                                    >
                                </label>
                            </li>
                        </ul>
                    </fieldset>
                    <label
                        class="flex min-h-10 items-center gap-2 text-sm md:col-span-2"
                    >
                        <input v-model="editForm.collapsed" type="checkbox" />
                        {{ t('Folded by default') }}
                    </label>
                    <div class="flex justify-end gap-2 md:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editingId = null"
                        >
                            {{ t('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{ t('Save') }}
                        </Button>
                    </div>
                </form>

                <div
                    v-else
                    class="flex flex-wrap items-center gap-3"
                    :style="{ marginLeft: `${g.depth * 1.25}rem` }"
                >
                    <span
                        class="size-3.5 shrink-0 rounded-full ring-4 ring-muted"
                        :style="{ backgroundColor: g.color ?? 'transparent' }"
                        :class="{
                            'border border-dashed border-muted-foreground/40':
                                !g.color,
                        }"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ g.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t(':count monitors', {
                                    count: g.checks_count ?? 0,
                                })
                            }}
                        </p>
                    </div>
                    <span class="chip">{{
                        layouts.find((l) => l.value === g.layout)?.label
                    }}</span>
                    <div v-if="canManage" class="flex">
                        <Button
                            size="icon"
                            variant="ghost"
                            :title="t('Edit')"
                            :aria-label="t('Edit')"
                            @click="startEdit(g)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            v-if="isAdmin"
                            size="icon"
                            variant="ghost"
                            :title="t('Delete')"
                            :aria-label="t('Delete')"
                            @click="remove(g)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </li>
        </ul>
    </PageContainer>
</template>
