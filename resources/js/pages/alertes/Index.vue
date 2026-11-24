<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Bell, Pencil, Plus, Send, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ChannelController from '@/actions/App/Modules/Alertes/Http/Controllers/ChannelController';
import InputError from '@/components/InputError.vue';
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
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { t } from '@/lib/i18n';
import { index } from '@/routes/alertes/channels';

type Field = {
    name: string;
    label: string;
    type: string;
    required: boolean;
    secret: boolean;
    help: string | null;
    placeholder: string | null;
};
type Adapter = { key: string; label: string; fields: Field[] };
type Channel = {
    id: number;
    name: string;
    type: string;
    enabled: boolean;
    config: Record<string, string | null>;
};

const props = defineProps<{ channels: Channel[]; adapters: Adapter[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Alerts', href: index() }] },
});

const isAdmin = computed(() => usePage().props.auth.user.role === 'admin');
const select =
    'h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none';
const adapterLabel = (key: string) =>
    props.adapters.find((a) => a.key === key)?.label ?? key;

const open = ref(false);
const editing = ref<Channel | null>(null);
const form = useForm<Record<string, any>>({ name: '', type: '', enabled: true });
const adapter = computed(() =>
    props.adapters.find((a) => a.key === form.type),
);

function fill(type: string, values: Record<string, string | null> = {}) {
    const next: Record<string, any> = { ...form.data() };

    for (const key of Object.keys(next)) {
        if (!['name', 'type', 'enabled'].includes(key)) {
            delete next[key];
        }
    }

    next.type = type;
    props.adapters
        .find((a) => a.key === type)
        ?.fields.forEach((f) => (next[f.name] = values[f.name] ?? ''));
    form.defaults(next).reset();
}

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.defaults({ name: '', type: '', enabled: true }).reset();
    fill(
        props.adapters.find((a) => a.key === 'email')?.key ??
            props.adapters[0]?.key ??
            '',
    );
    open.value = true;
}

function openEdit(c: Channel) {
    editing.value = c;
    form.clearErrors();
    form.defaults({ name: c.name, enabled: c.enabled }).reset();
    form.name = c.name;
    fill(c.type, c.config);
    form.name = c.name;
    open.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        form.patch(ChannelController.update.url(editing.value.id), options);
    } else {
        form.post(ChannelController.store.url(), options);
    }
}

function sendTest(c: Channel) {
    router.post(ChannelController.test.url(c.id), {}, { preserveScroll: true });
}

function remove(c: Channel) {
    if (confirm(t('Delete the channel ":name"?', { name: c.name }))) {
        router.delete(ChannelController.destroy.url(c.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="t('Alerts')" />

    <PageContainer>
        <PageHeader
            :title="t('Notification channels')"
            :description="
                t(
                    'Every enabled channel receives incident alerts and renewal reminders.',
                )
            "
        >
            <Button v-if="isAdmin" @click="openCreate">
                <Plus class="size-4" /> {{ t('Add a channel') }}
            </Button>
        </PageHeader>

        <EmptyState
            v-if="!channels.length"
            :icon="Bell"
            :title="t('No channels yet')"
            :description="
                t(
                    'Add a channel (email, Slack, Brevo…) to be alerted when a check goes down.',
                )
            "
        >
            <Button v-if="isAdmin" @click="openCreate">
                {{ t('Add a channel') }}
            </Button>
        </EmptyState>

        <ul v-else class="surface divide-y overflow-hidden reveal">
            <li
                v-for="c in channels"
                :key="c.id"
                class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-muted/50"
                :class="{ 'opacity-60': !c.enabled }"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <StatusDot
                        :status="c.enabled ? 'up' : 'unknown'"
                        :label="c.enabled ? t('Enabled') : t('Disabled')"
                    />
                    <div class="min-w-0">
                        <p class="font-medium">{{ c.name }}</p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span class="chip chip-info">{{ adapterLabel(c.type) }}</span>
                            <span v-if="!c.enabled" class="chip">{{ t('Disabled') }}</span>
                        </div>
                    </div>
                </div>
                <div v-if="isAdmin" class="flex flex-wrap items-center gap-1">
                    <Button size="sm" variant="ghost" @click="sendTest(c)">
                        <Send class="size-4" /> {{ t('Send a test') }}
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        :title="t('Edit')"
                        :aria-label="t('Edit')"
                        @click="openEdit(c)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        :title="t('Delete')"
                        :aria-label="t('Delete')"
                        @click="remove(c)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? t('Edit channel') : t('Add a channel') }}
                    </DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="save">
                    <div class="field-stack">
                        <Label for="ch-type" class="field-label">{{ t('Type') }}</Label>
                        <select
                            id="ch-type"
                            :value="form.type"
                            :class="select"
                            :disabled="!!editing"
                            @change="
                                fill(($event.target as HTMLSelectElement).value)
                            "
                        >
                            <option
                                v-for="a in adapters"
                                :key="a.key"
                                :value="a.key"
                            >
                                {{ a.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div class="field-stack">
                        <Label for="ch-name" class="field-label">{{ t('Name') }}</Label>
                        <Input id="ch-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div
                        v-for="f in adapter?.fields"
                        :key="f.name"
                        class="field-stack"
                    >
                        <Label :for="`ch-${f.name}`" class="field-label">
                            {{ f.label }}
                        </Label>
                        <Input
                            :id="`ch-${f.name}`"
                            v-model="form[f.name]"
                            :type="f.type === 'textarea' ? 'text' : f.type"
                            :required="f.required && !(editing && f.secret)"
                            :placeholder="
                                editing && f.secret
                                    ? t('Leave blank to keep the current value')
                                    : (f.placeholder ?? '')
                            "
                            autocomplete="off"
                            :aria-describedby="f.help ? `ch-${f.name}-help` : undefined"
                        />
                        <p
                            v-if="f.help"
                            :id="`ch-${f.name}-help`"
                            class="field-hint"
                        >
                            {{ f.help }}
                        </p>
                        <InputError :message="form.errors[f.name]" />
                    </div>
                    <label class="flex min-h-10 items-center gap-2 text-sm">
                        <input v-model="form.enabled" type="checkbox" />
                        {{ t('Enabled') }}
                    </label>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="open = false">
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
