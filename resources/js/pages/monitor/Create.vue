<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Globe, Server, Wrench } from '@lucide/vue';
import { computed, ref } from 'vue';
import MonitorController from '@/actions/App/Modules/Checks/Http/Controllers/MonitorController';
import GroupPicker from '@/components/GroupPicker.vue';
import SiteDetection from '@/components/SiteDetection.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

defineProps<{
    groups: GroupOption[];
    has_probe: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Add a monitor', href: '/monitor/new' }] },
});

type Kind = 'website' | 'server' | 'service';
const initial = new URLSearchParams(
    typeof window === 'undefined' ? '' : window.location.search,
).get('kind') as Kind | null;
const kind = ref<Kind | null>(
    initial && ['website', 'server', 'service'].includes(initial)
        ? initial
        : null,
);

const website = useForm({ url: '', name: '', watch_health: false, framework: '', group_id: '' as string | number, group_name: '' });
const server = useForm({ name: '', group_id: '' as string | number, group_name: '' });
const heartbeat = useForm({
    name: '',
    frequency: 'day',
    custom_value: 15 as string | number,
    custom_unit: 'minutes',
    group_id: '' as string | number,
    group_name: '',
});
const FREQUENCIES: Record<string, number> = { minute: 60, hour: 3600, day: 86400, week: 604800 };
const UNITS: Record<string, number> = { minutes: 60, hours: 3600, days: 86400 };
const heartbeatInterval = computed(() =>
    heartbeat.frequency === 'custom'
        ? Number(heartbeat.custom_value || 0) * UNITS[heartbeat.custom_unit]
        : FREQUENCIES[heartbeat.frequency],
);

function submitHeartbeat() {
    heartbeat
        .transform((d) => ({
            name: d.name,
            interval_sec: heartbeatInterval.value,
            group_id: d.group_id,
            group_name: d.group_name,
        }))
        .post(MonitorController.heartbeat.url());
}

const service = useForm({
    kind: 'mail',
    host: '',
    port: '' as string | number,
    name: '',
    group_id: '' as string | number,
    group_name: '',
});

const serviceKinds = computed(() => [
    { value: 'mail', label: t('A mail server'), hint: t('Is my mail server reachable?'), port: 587 },
    { value: 'port', label: t('A port / an application'), hint: t('Is a service answering on a given port?'), port: 443 },
    { value: 'ping', label: t('A machine or device (ping)'), hint: t('Does it answer to a ping? (needs a probe)'), port: '' },
    { value: 'dns', label: t('A domain name (DNS)'), hint: t('Does the domain still resolve? (needs a probe)'), port: '' },
    { value: 'heartbeat', label: t('A scheduled task (heartbeat)'), hint: t('A backup, a cron job… We warn you if it stops reporting.'), port: '' },
]);
const current = computed(() => serviceKinds.value.find((k) => k.value === service.kind));
const needsPort = computed(() => ['mail', 'port'].includes(service.kind));
const isHeartbeat = computed(() => service.kind === 'heartbeat');
const needsProbe = computed(() => ['ping', 'dns'].includes(service.kind));
const select = 'h-10 rounded-md border bg-transparent px-3 text-sm';

function pickServiceKind(value: string) {
    service.kind = value;
    service.port = serviceKinds.value.find((k) => k.value === value)?.port ?? '';
}

function cards() {
    return [
        {
            key: 'website' as Kind,
            icon: Globe,
            title: t('A website'),
            text: t('We warn you if it goes down, and before its domain name or SSL certificate expires.'),
        },
        {
            key: 'server' as Kind,
            icon: Server,
            title: t('A server'),
            text: t('We follow its health (processor, memory, disk) and warn you if it stops responding.'),
        },
        {
            key: 'service' as Kind,
            icon: Wrench,
            title: t('Something else'),
            text: t('A mail server, an application, a machine on your network, a domain name, a scheduled task…'),
        },
    ];
}

const pageTitle = computed(() => {
    if (kind.value === 'website') {
        return t('Monitor a website');
    }

    if (kind.value === 'server') {
        return t('Monitor a server');
    }

    if (kind.value === 'service') {
        return t('Monitor something else');
    }

    return t('What do you want to monitor?');
});
</script>

<template>
    <Head :title="t('Add a monitor')" />

    <PageContainer size="narrow">
        <PageHeader
            :eyebrow="t('Add a monitor')"
            :title="pageTitle"
            :description="kind ? undefined : t('Pick one. We will ask only what is needed.')"
        />

        <!-- Step 1: what? -->
        <div v-if="!kind" class="grid gap-4 md:grid-cols-3">
            <button
                v-for="(c, i) in cards()"
                :key="c.key"
                type="button"
                class="surface group flex flex-col gap-3 p-6 text-left transition hover:border-primary/50  focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none  reveal"
                :class="`reveal-delay-${i + 1}`"
                @click="kind = c.key"
            >
                <span class="grid size-12 place-items-center rounded-md bg-primary/10 text-primary transition motion-safe:group-hover:scale-105">
                    <component :is="c.icon" class="size-6" aria-hidden="true" />
                </span>
                <span class="font-display text-xl font-semibold">{{ c.title }}</span>
                <span class="text-sm text-pretty text-muted-foreground">{{ c.text }}</span>
            </button>
        </div>

        <Button v-else variant="ghost" class="w-fit" @click="kind = null">
            <ArrowLeft class="size-4" /> {{ t('Change') }}
        </Button>

        <!-- Website -->
        <form
            v-if="kind === 'website'"
            class="form-panel"
            aria-labelledby="website-heading"
            @submit.prevent="website.post(MonitorController.website.url())"
        >
            <h2 id="website-heading" class="sr-only">{{ t('Monitor a website') }}</h2>
            <div class="field-stack">
                <Label for="url">{{ t('Website address') }}</Label>
                <Input
                    id="url"
                    v-model="website.url"
                    autofocus
                    placeholder="example.com"
                    autocomplete="url"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                    enterkeyhint="next"
                />
                <InputError :message="website.errors.url" />
            </div>
            <SiteDetection v-model:watch-health="website.watch_health" v-model:framework="website.framework" :url="website.url" />
            <GroupPicker v-model:group-id="website.group_id" v-model:group-name="website.group_name" :groups="groups" :error="website.errors.group_id" id="website-group" />
            <details class="text-sm">
                <summary class="cursor-pointer text-muted-foreground">{{ t('Name it yourself (optional)') }}</summary>
                <Input v-model="website.name" class="mt-2" :placeholder="t('Defaults to the address')" />
            </details>
            <Button type="submit" size="lg" :disabled="website.processing || !website.url">{{ t('Start monitoring') }}</Button>
        </form>

        <!-- Server -->
        <form
            v-if="kind === 'server'"
            class="form-panel"
            aria-labelledby="server-heading"
            @submit.prevent="server.post(MonitorController.server.url())"
        >
            <h2 id="server-heading" class="sr-only">{{ t('Monitor a server') }}</h2>
            <div class="field-stack">
                <Label for="server-name">{{ t('Give your server a name') }}</Label>
                <Input id="server-name" v-model="server.name" autofocus :placeholder="t('Web server, Paris')" autocomplete="off" />
                <InputError :message="server.errors.name" />
            </div>
            <GroupPicker v-model:group-id="server.group_id" v-model:group-name="server.group_name" :groups="groups" :error="server.errors.group_id" id="server-group" />
            <p class="surface-muted p-4 text-sm text-muted-foreground">
                {{ t('Next, we give you one line to paste on the server. That is all.') }}
            </p>
            <Button type="submit" size="lg" :disabled="server.processing || !server.name">{{ t('Continue') }}</Button>
        </form>

        <!-- Something else -->
        <div v-if="kind === 'service'" class="grid gap-5">
            <fieldset class="grid gap-3 sm:grid-cols-2">
                <legend class="sr-only">{{ t('What do you want to monitor?') }}</legend>
                <button
                    v-for="k in serviceKinds"
                    :key="k.value"
                    type="button"
                    class="surface p-4 text-left text-sm transition hover:border-primary/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :class="service.kind === k.value ? 'border-primary ring-2 ring-primary/40' : ''"
                    :aria-pressed="service.kind === k.value"
                    @click="pickServiceKind(k.value)"
                >
                    <span class="font-display font-semibold">{{ k.label }}</span>
                    <span class="mt-1 block text-xs text-pretty text-muted-foreground">{{ k.hint }}</span>
                </button>
            </fieldset>

            <form
                v-if="!isHeartbeat"
                class="form-panel"
                @submit.prevent="service.post(MonitorController.service.url())"
            >
                <div class="field-stack">
                    <Label for="svc-host">{{ service.kind === 'dns' ? t('Domain name') : t('Address (name or IP)') }}</Label>
                    <Input
                        id="svc-host"
                        v-model="service.host"
                        :placeholder="service.kind === 'dns' ? 'example.com' : 'mail.example.com'"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="none"
                        spellcheck="false"
                    />
                    <InputError :message="service.errors.host" />
                </div>
                <div v-if="needsPort" class="field-stack">
                    <Label for="svc-port">{{ t('Port') }}</Label>
                    <Input id="svc-port" v-model="service.port" type="text" inputmode="numeric" />
                    <InputError :message="service.errors.port" />
                    <p v-if="service.kind === 'mail'" class="field-hint">
                        {{ t('Common mail ports: 25, 465 or 587 (sending), 993 (reading).') }}
                    </p>
                </div>
                <p v-if="needsProbe && !has_probe" class="surface border-warning/40 bg-warning/10 p-4 text-sm" role="status">
                    {{ t('This kind of check runs from a probe, and none is connected yet. You can add it now and connect a probe later.') }}
                </p>
                <GroupPicker v-model:group-id="service.group_id" v-model:group-name="service.group_name" :groups="groups" :error="service.errors.group_id" id="service-group" />
                <details class="text-sm">
                    <summary class="cursor-pointer text-muted-foreground">{{ t('Name it yourself (optional)') }}</summary>
                    <Input v-model="service.name" class="mt-2" :placeholder="current?.label" />
                </details>
                <Button type="submit" size="lg" :disabled="service.processing || !service.host">{{ t('Start monitoring') }}</Button>
            </form>

            <!-- Scheduled task (heartbeat) -->
            <form v-else class="form-panel" @submit.prevent="submitHeartbeat">
                <div class="field-stack">
                    <Label for="hb-name">{{ t('Name of the task') }}</Label>
                    <Input id="hb-name" v-model="heartbeat.name" :placeholder="t('Nightly backup')" autocomplete="off" />
                    <InputError :message="heartbeat.errors.name" />
                </div>
                <div class="field-stack">
                    <Label for="hb-frequency">{{ t('How often should it run?') }}</Label>
                    <select id="hb-frequency" v-model="heartbeat.frequency" :class="select">
                        <option value="minute">{{ t('Every minute') }}</option>
                        <option value="hour">{{ t('Every hour') }}</option>
                        <option value="day">{{ t('Every day') }}</option>
                        <option value="week">{{ t('Every week') }}</option>
                        <option value="custom">{{ t('Another frequency…') }}</option>
                    </select>
                    <div v-if="heartbeat.frequency === 'custom'" class="flex flex-wrap items-center gap-2">
                        <span class="text-sm">{{ t('Every') }}</span>
                        <Input v-model="heartbeat.custom_value" type="text" inputmode="numeric" min="1" class="w-24" :aria-label="t('Number')" />
                        <select v-model="heartbeat.custom_unit" :class="select" :aria-label="t('Unit')">
                            <option value="minutes">{{ t('minutes') }}</option>
                            <option value="hours">{{ t('hours') }}</option>
                            <option value="days">{{ t('days') }}</option>
                        </select>
                    </div>
                    <InputError :message="(heartbeat.errors as Record<string, string>).interval_sec" />
                    <p class="field-hint">
                        {{ t('Your task will tell Phare each time it runs. If Phare hears nothing for a little longer than this, we warn you.') }}
                    </p>
                </div>
                <GroupPicker v-model:group-id="heartbeat.group_id" v-model:group-name="heartbeat.group_name" :groups="groups" :error="heartbeat.errors.group_id" id="heartbeat-group" />
                <Button type="submit" size="lg" :disabled="heartbeat.processing || !heartbeat.name || heartbeatInterval < 60">{{ t('Continue') }}</Button>
            </form>
        </div>
    </PageContainer>
</template>
