<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink, Globe, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import StatusPageController from '@/actions/App/Modules/StatusPages/Http/Controllers/StatusPageController';
import GroupSelect from '@/components/GroupSelect.vue';
import InputError from '@/components/InputError.vue';
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
import { index } from '@/routes/status-pages';
import type { GroupOption } from '@/types/groups';

type Page = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    group_id: number | null;
    group: string | null;
    accent_color: string | null;
    logo_url: string | null;
    show_incidents: boolean;
    enabled: boolean;
    url: string;
};

defineProps<{
    pages: Page[];
    groups: GroupOption[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Status pages', href: index() }] },
});

const canManage = computed(() => usePage().props.auth.user.role !== 'viewer');
const DEFAULT_ACCENT = '#2563eb';

type Fields = {
    title: string;
    slug: string;
    description: string;
    group_id: string | number;
    accent_color: string;
    logo: File | null;
    remove_logo: boolean;
    show_incidents: boolean;
    enabled: boolean;
};

const form = useForm<Fields>({
    title: '',
    slug: '',
    description: '',
    group_id: '',
    accent_color: '',
    logo: null,
    remove_logo: false,
    show_incidents: true,
    enabled: true,
});
const open = ref(false);
const editing = ref<Page | null>(null);

function startCreate() {
    editing.value = null;
    form.clearErrors();
    form.defaults({
        title: '',
        slug: '',
        description: '',
        group_id: '',
        accent_color: '',
        logo: null,
        remove_logo: false,
        show_incidents: true,
        enabled: true,
    }).reset();
    open.value = true;
}

function startEdit(p: Page) {
    editing.value = p;
    form.clearErrors();
    form.defaults({
        title: p.title,
        slug: p.slug,
        description: p.description ?? '',
        group_id: p.group_id ?? '',
        accent_color: p.accent_color ?? '',
        logo: null,
        remove_logo: false,
        show_incidents: p.show_incidents,
        enabled: p.enabled,
    }).reset();
    open.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => (open.value = false),
    };

    form.transform((d) => ({
        ...d,
        group_id: d.group_id === '' ? null : d.group_id,
        accent_color: d.accent_color || null,
        ...(editing.value ? { _method: 'patch' } : {}),
    }));

    // Files need a multipart POST: updates are spoofed as PATCH.
    form.post(
        editing.value
            ? StatusPageController.update.url(editing.value.id)
            : StatusPageController.store.url(),
        options,
    );
}

function remove(p: Page) {
    if (confirm(t('Delete the status page ":name"? Its public address will stop working.', { name: p.title }))) {
        router.delete(StatusPageController.destroy.url(p.id), { preserveScroll: true });
    }
}

const copied = ref<number | null>(null);

async function copy(p: Page) {
    await navigator.clipboard.writeText(p.url);
    copied.value = p.id;
    setTimeout(() => (copied.value = null), 2000);
}

const previewUrl = computed(() => (editing.value ? editing.value.url : null));
</script>

<template>
    <Head :title="t('Status pages')" />

    <PageContainer>
        <PageHeader
            :title="t('Status pages')"
            :description="t('Give your client one public address showing whether their sites and servers are working. No login needed.')"
        >
            <Button v-if="canManage" @click="startCreate"><Plus class="size-4" /> {{ t('New status page') }}</Button>
        </PageHeader>

        <EmptyState
            v-if="pages.length === 0"
            :icon="Globe"
            :title="t('No status page yet')"
            :description="t('Create one for a client: pick their group and share the address with them.')"
        >
            <Button v-if="canManage" @click="startCreate">{{ t('New status page') }}</Button>
        </EmptyState>

        <ul v-else class="surface divide-y overflow-hidden">
            <li v-for="p in pages" :key="p.id" class="flex flex-wrap items-center gap-3 p-4 transition-colors hover:bg-muted/50">
                <span class="size-3.5 shrink-0 rounded-full ring-4 ring-muted" :style="{ backgroundColor: p.accent_color ?? DEFAULT_ACCENT }" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <p class="font-medium">
                        {{ p.title }}
                        <span v-if="!p.enabled" class="chip ml-1">{{ t('Disabled') }}</span>
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        <code>{{ p.url }}</code> · {{ p.group ?? t('Everything') }}
                    </p>
                </div>
                <div class="flex">
                    <Button size="icon" variant="ghost" :title="t('Copy the address')" :aria-label="t('Copy the address')" @click="copy(p)">
                        <Check v-if="copied === p.id" class="size-4" />
                        <Copy v-else class="size-4" />
                    </Button>
                    <Button as-child size="icon" variant="ghost" :title="t('Open')" :aria-label="t('Open')">
                        <a :href="p.url" target="_blank" rel="noopener"><ExternalLink class="size-4" /></a>
                    </Button>
                    <template v-if="canManage">
                        <Button size="icon" variant="ghost" :title="t('Edit')" :aria-label="t('Edit')" @click="startEdit(p)">
                            <Pencil class="size-4" />
                        </Button>
                        <Button size="icon" variant="ghost" :title="t('Delete')" :aria-label="t('Delete')" @click="remove(p)">
                            <Trash2 class="size-4" />
                        </Button>
                    </template>
                </div>
            </li>
        </ul>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ editing ? t('Edit status page') : t('New status page') }}</DialogTitle>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="save">
                    <div class="field-stack">
                        <Label for="sp-title" class="field-label">{{ t('Title') }}</Label>
                        <Input id="sp-title" v-model="form.title" :placeholder="t('Acme — service status')" required />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="field-stack">
                        <Label for="sp-slug" class="field-label">{{ t('Address') }}</Label>
                        <Input id="sp-slug" v-model="form.slug" :placeholder="t('Filled in from the title')" aria-describedby="sp-slug-help" />
                        <p id="sp-slug-help" class="field-hint">{{ t('Lowercase letters, numbers and dashes only.') }}</p>
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div class="field-stack">
                        <Label for="sp-group" class="field-label">{{ t('What to show') }}</Label>
                        <GroupSelect id="sp-group" v-model="form.group_id" :groups="groups" :empty-label="t('Everything')" />
                        <InputError :message="form.errors.group_id" />
                    </div>
                    <div class="field-stack">
                        <Label for="sp-desc" class="field-label">{{ t('Message for visitors (optional)') }}</Label>
                        <textarea id="sp-desc" v-model="form.description" maxlength="500" rows="3" class="rounded-md border bg-transparent px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="field-stack">
                            <Label for="sp-color" class="field-label">{{ t('Accent colour') }}</Label>
                            <input id="sp-color" v-model="form.accent_color" type="color" class="h-10 w-full cursor-pointer rounded-md border bg-transparent p-1 accent-primary" />
                            <InputError :message="form.errors.accent_color" />
                        </div>
                        <div class="field-stack">
                            <Label for="sp-logo" class="field-label">{{ t('Logo (PNG, JPG or WebP, 1 MB max)') }}</Label>
                            <input
                                id="sp-logo"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                class="text-sm file:mr-3 file:h-10 file:rounded-md file:border file:bg-muted file:px-3 file:text-sm"
                                @change="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <InputError :message="form.errors.logo" />
                            <label v-if="editing?.logo_url" class="flex items-center gap-2 text-xs">
                                <input v-model="form.remove_logo" type="checkbox" class="accent-primary" /> {{ t('Remove the current logo') }}
                            </label>
                        </div>
                    </div>
                    <label class="flex min-h-10 items-center gap-2 text-sm">
                        <input v-model="form.show_incidents" type="checkbox" class="accent-primary" /> {{ t('Show recent incidents') }}
                    </label>
                    <label class="flex min-h-10 items-center gap-2 text-sm">
                        <input v-model="form.enabled" type="checkbox" class="accent-primary" /> {{ t('Page is public') }}
                    </label>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button v-if="previewUrl" as-child variant="link" type="button">
                            <a :href="previewUrl" target="_blank" rel="noopener">{{ t('See the page') }}</a>
                        </Button>
                        <Button type="button" variant="outline" @click="open = false">{{ t('Cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ t('Save') }}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </PageContainer>
</template>
