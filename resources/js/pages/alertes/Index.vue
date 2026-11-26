<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Bell, Pencil, Plus, Send, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ChannelController from '@/actions/App/Modules/Alertes/Http/Controllers/ChannelController';
import NotificationPreferencesController from '@/actions/App/Modules/Alertes/Http/Controllers/NotificationPreferencesController';
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

type RenewalNotifyMode = 'off' | 'expired_only' | 'thresholds';

type NotificationPreferencesPayload = {
    incidents: { opened: boolean; resolved: boolean };
    renewals: Record<
        'ssl' | 'domain' | 'contract' | 'other',
        RenewalNotifyMode
    >;
};

const props = defineProps<{
    channels: Channel[];
    adapters: Adapter[];
    notification_preferences: NotificationPreferencesPayload;
}>();

const renewalKinds: {
    key: keyof NotificationPreferencesPayload['renewals'];
    label: string;
}[] = [
    { key: 'ssl', label: t('SSL certificates') },
    { key: 'domain', label: t('Domain names') },
    { key: 'contract', label: t('Contracts') },
    { key: 'other', label: t('Other renewals') },
];

const renewalModeLabels: Record<RenewalNotifyMode, string> = {
    off: t('Disabled'),
    expired_only: t('Only when expired'),
    thresholds: t('Before expiry (30, 14, 7, 1 days)'),
};

const prefsForm = useForm<NotificationPreferencesPayload>({
    ...props.notification_preferences,
});

watch(
    () => props.notification_preferences,
    (value) => {
        prefsForm.defaults({ ...value }).reset();
    },
    { deep: true },
);

function savePreferences() {
    prefsForm.patch(NotificationPreferencesController.update.url(), {
        preserveScroll: true,
    });
}

const isAdmin = computed(() => usePage().props.auth.user.role === 'admin');
const select =
    'h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none';
const adapterLabel = (key: string) =>
    props.adapters.find((a) => a.key === key)?.label ?? key;

const open = ref(false);
const editing = ref<Channel | null>(null);
const form = useForm<Record<string, any>>({
    name: '',
    type: '',
    enabled: true,
});
const adapter = computed(() => props.adapters.find((a) => a.key === form.type));

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
                    'Configure where alerts go and which events are sent to your channels.',
                )
            "
        >
            <Button v-if="isAdmin" @click="openCreate">
                <Plus class="size-4" /> {{ t('Add a channel') }}
            </Button>
        </PageHeader>

        <section
            v-if="isAdmin"
            class="reveal mb-6 space-y-4 overflow-hidden surface p-5"
            aria-labelledby="alert-prefs-heading"
        >
            <header class="space-y-1">
                <h2
                    id="alert-prefs-heading"
                    class="font-display text-sm font-semibold"
                >
                    {{ t('What we notify') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Applies to all enabled channels. Dashboard and status pages are not affected.',
                        )
                    }}
                </p>
            </header>

            <form class="grid gap-6" @submit.prevent="savePreferences">
                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium">
                        {{ t('Incidents') }}
                    </legend>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="prefsForm.incidents.opened"
                            type="checkbox"
                            class="size-4 rounded border-input"
                        />
                        {{ t('Monitor goes down or has a problem') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="prefsForm.incidents.resolved"
                            type="checkbox"
                            class="size-4 rounded border-input"
                        />
                        {{ t('Monitor is back up') }}
                    </label>
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium">
                        {{ t('Renewals') }}
                    </legend>
                    <div
                        v-for="kind in renewalKinds"
                        :key="kind.key"
                        class="field-stack max-w-md"
                    >
                        <Label
                            :for="`renewal-${kind.key}`"
                            class="field-label"
                            >{{ kind.label }}</Label
                        >
                        <select
                            :id="`renewal-${kind.key}`"
                            v-model="prefsForm.renewals[kind.key]"
                            :class="select"
                        >
                            <option
                                v-for="(label, mode) in renewalModeLabels"
                                :key="mode"
                                :value="mode"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                </fieldset>

                <div>
                    <Button
                        type="submit"
                        :disabled="prefsForm.processing"
                        size="sm"
                    >
                        {{ t('Save preferences') }}
                    </Button>
                </div>
            </form>
        </section>

        <h2 class="mb-3 font-display text-sm font-semibold">
            {{ t('Notification channels') }}
        </h2>

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

        <ul v-else class="reveal divide-y overflow-hidden surface">
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
                            <span class="chip chip-info">{{
                                adapterLabel(c.type)
                            }}</span>
                            <span v-if="!c.enabled" class="chip">{{
                                t('Disabled')
                            }}</span>
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
                        <Label for="ch-type" class="field-label">{{
                            t('Type')
                        }}</Label>
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
                        <Label for="ch-name" class="field-label">{{
                            t('Name')
                        }}</Label>
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
                            :aria-describedby="
                                f.help ? `ch-${f.name}-help` : undefined
                            "
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
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
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
