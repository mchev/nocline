<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Globe, Server, Wrench } from '@lucide/vue';
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

type ObjectStorageProviderOption = {
    value: string;
    label: string;
    regions: { value: string; label: string }[];
    needs_endpoint: boolean;
    show_region_with_endpoint?: boolean;
};

const props = defineProps<{
    groups: GroupOption[];
    has_probe: boolean;
    object_storage_providers: ObjectStorageProviderOption[];
}>();

type Kind = 'website' | 'server' | 'service';
const initial = new URLSearchParams(
    typeof window === 'undefined' ? '' : window.location.search,
).get('kind') as Kind | null;
const kind = ref<Kind | null>(
    initial && ['website', 'server', 'service'].includes(initial)
        ? initial
        : null,
);

const website = useForm({
    url: '',
    name: '',
    interval_sec: 60,
    watch_health: false,
    framework: '',
    group_id: '' as string | number,
    group_name: '',
});

const websiteIntervalOptions = computed(() => [
    { value: 60, label: t('Every minute') },
    { value: 300, label: t('Every 5 minutes') },
    { value: 600, label: t('Every 10 minutes') },
    { value: 900, label: t('Every 15 minutes') },
    { value: 3600, label: t('Every hour') },
]);
const server = useForm({
    name: '',
    group_id: '' as string | number,
    group_name: '',
});
const heartbeat = useForm({
    name: '',
    frequency: 'day',
    custom_value: 15 as string | number,
    custom_unit: 'minutes',
    group_id: '' as string | number,
    group_name: '',
});
const FREQUENCIES: Record<string, number> = {
    minute: 60,
    hour: 3600,
    day: 86400,
    week: 604800,
};
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
    kind: 'object_storage',
    host: '',
    port: '' as string | number,
    name: '',
    group_id: '' as string | number,
    group_name: '',
});

const objectStorage = useForm({
    provider: 'ovh',
    region: 'gra',
    level: 'platform',
    bucket: '',
    health_path: 'health.txt',
    endpoint_host: '',
    name: '',
    group_id: '' as string | number,
    group_name: '',
});

const serviceKinds = computed(() => [
    {
        value: 'object_storage',
        label: t('Object storage (S3)'),
        hint: t(
            'OVH, AWS, Scaleway… Check that the cloud storage endpoint or your bucket answers.',
        ),
        port: '',
    },
    {
        value: 'mail',
        label: t('A mail server'),
        hint: t('Is my mail server reachable?'),
        port: 587,
    },
    {
        value: 'port',
        label: t('A port / an application'),
        hint: t('Is a service answering on a given port?'),
        port: 443,
    },
    {
        value: 'ping',
        label: t('A machine or device (ping)'),
        hint: t('Does it answer to a ping? (needs a probe)'),
        port: '',
    },
    {
        value: 'dns',
        label: t('A domain name (DNS)'),
        hint: t('Does the domain still resolve? (needs a probe)'),
        port: '',
    },
    {
        value: 'heartbeat',
        label: t('A scheduled task (heartbeat)'),
        hint: t('A backup, a cron job… We warn you if it stops reporting.'),
        port: '',
    },
]);
const current = computed(() =>
    serviceKinds.value.find((k) => k.value === service.kind),
);
const needsPort = computed(() => ['mail', 'port'].includes(service.kind));
const isHeartbeat = computed(() => service.kind === 'heartbeat');
const isObjectStorage = computed(() => service.kind === 'object_storage');
const needsProbe = computed(() => ['ping', 'dns'].includes(service.kind));
const storageProvider = computed(() =>
    props.object_storage_providers.find((p) => p.value === objectStorage.provider),
);
const storageRegions = computed(() => storageProvider.value?.regions ?? []);
const storageNeedsEndpoint = computed(
    () => storageProvider.value?.needs_endpoint ?? false,
);
const storageShowsRegion = computed(
    () =>
        !storageNeedsEndpoint.value ||
        (storageProvider.value?.show_region_with_endpoint ?? false),
);
const isBucketLevel = computed(() => objectStorage.level === 'bucket');
const isBackupLevel = computed(() => objectStorage.level === 'backup');
const select = 'h-10 rounded-md border bg-transparent px-3 text-sm';

function pickServiceKind(value: string) {
    service.kind = value;
    service.port =
        serviceKinds.value.find((k) => k.value === value)?.port ?? '';
    if (value === 'object_storage') {
        const p = props.object_storage_providers.find(
            (pr) => pr.value === objectStorage.provider,
        );
        if (p?.regions[0]) {
            objectStorage.region = p.regions[0].value;
        }
    }
}

function submitObjectStorage() {
    const payload = {
        provider: objectStorage.provider,
        region:
            storageNeedsEndpoint.value && !storageProvider.value?.show_region_with_endpoint
                ? null
                : objectStorage.region,
        level: objectStorage.level,
        bucket: isBucketLevel.value ? objectStorage.bucket : null,
        health_path: isBucketLevel.value ? objectStorage.health_path : null,
        endpoint_host: storageNeedsEndpoint.value
            ? objectStorage.endpoint_host
            : null,
        name: objectStorage.name || null,
        group_id: objectStorage.group_id,
        group_name: objectStorage.group_name,
    };
    objectStorage
        .transform(() => payload)
        .post(MonitorController.objectStorage.url());
}

function cards() {
    return [
        {
            key: 'website' as Kind,
            icon: Globe,
            title: t('A website'),
            text: t(
                'We warn you if it goes down, and before its domain name or SSL certificate expires.',
            ),
        },
        {
            key: 'server' as Kind,
            icon: Server,
            title: t('A server'),
            text: t(
                'We follow its health (processor, memory, disk) and warn you if it stops responding.',
            ),
        },
        {
            key: 'service' as Kind,
            icon: Wrench,
            title: t('Something else'),
            text: t(
                'Object storage, mail server, application, ping, DNS, scheduled task…',
            ),
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

const containerSize = computed(() => (kind.value ? 'narrow' : 'default'));
</script>

<template>
    <Head :title="t('Add a monitor')" />

    <PageContainer :size="containerSize">
        <PageHeader
            :eyebrow="t('Add a monitor')"
            :title="pageTitle"
            :description="
                kind
                    ? undefined
                    : t('Pick one. We will ask only what is needed.')
            "
        />

        <!-- Step 1: what? -->
        <ul
            v-if="!kind"
            class="mx-auto flex w-full max-w-2xl flex-col gap-3"
            role="list"
        >
            <li v-for="(c, i) in cards()" :key="c.key">
                <button
                    type="button"
                    class="group monitor-kind-card reveal flex w-full items-start gap-4 text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :class="`reveal-delay-${i + 1}`"
                    @click="kind = c.key"
                >
                    <span
                        class="grid size-11 shrink-0 place-items-center rounded-lg bg-primary/12 text-primary transition motion-safe:group-hover:scale-[1.03]"
                    >
                        <component
                            :is="c.icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="min-w-0 flex-1 pt-0.5">
                        <span
                            class="block font-display text-base font-semibold text-foreground"
                            >{{ c.title }}</span
                        >
                        <span
                            class="mt-1 block text-sm leading-relaxed text-pretty text-muted-foreground"
                            >{{ c.text }}</span
                        >
                    </span>
                    <ArrowRight
                        class="mt-2 size-4 shrink-0 text-muted-foreground transition group-hover:text-primary motion-safe:group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </button>
            </li>
        </ul>

        <div v-else class="mx-auto w-full max-w-xl space-y-4">
            <Button variant="ghost" class="w-fit" @click="kind = null">
                <ArrowLeft class="size-4" /> {{ t('Change') }}
            </Button>

            <!-- Website -->
            <form
                v-if="kind === 'website'"
                class="form-panel"
                aria-labelledby="website-heading"
                @submit.prevent="website.post(MonitorController.website.url())"
            >
                <h2 id="website-heading" class="sr-only">
                    {{ t('Monitor a website') }}
                </h2>
                <div class="field-stack">
                    <Label for="website-name"
                        >{{ t('Name') }}
                        <span class="font-normal text-muted-foreground"
                            >({{ t('optional') }})</span
                        ></Label
                    >
                    <Input
                        id="website-name"
                        v-model="website.name"
                        :placeholder="t('Defaults to the address')"
                        autocomplete="off"
                    />
                    <InputError :message="website.errors.name" />
                </div>
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
                <SiteDetection
                    v-model:watch-health="website.watch_health"
                    v-model:framework="website.framework"
                    :url="website.url"
                />
                <div class="field-stack">
                    <Label for="website-interval">{{
                        t('How often should it run?')
                    }}</Label>
                    <select
                        id="website-interval"
                        v-model.number="website.interval_sec"
                        :class="select"
                    >
                        <option
                            v-for="opt in websiteIntervalOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError :message="website.errors.interval_sec" />
                </div>
                <GroupPicker
                    v-model:group-id="website.group_id"
                    v-model:group-name="website.group_name"
                    :groups="groups"
                    :error="website.errors.group_id"
                    id="website-group"
                />
                <Button
                    type="submit"
                    size="lg"
                    :disabled="website.processing || !website.url"
                    >{{ t('Start monitoring') }}</Button
                >
            </form>

            <!-- Server -->
            <form
                v-if="kind === 'server'"
                class="form-panel"
                aria-labelledby="server-heading"
                @submit.prevent="server.post(MonitorController.server.url())"
            >
                <h2 id="server-heading" class="sr-only">
                    {{ t('Monitor a server') }}
                </h2>
                <div class="field-stack">
                    <Label for="server-name">{{
                        t('Give your server a name')
                    }}</Label>
                    <Input
                        id="server-name"
                        v-model="server.name"
                        autofocus
                        :placeholder="t('Web server, Paris')"
                        autocomplete="off"
                    />
                    <InputError :message="server.errors.name" />
                </div>
                <GroupPicker
                    v-model:group-id="server.group_id"
                    v-model:group-name="server.group_name"
                    :groups="groups"
                    :error="server.errors.group_id"
                    id="server-group"
                />
                <p class="surface-muted p-4 text-sm text-muted-foreground">
                    {{
                        t(
                            'Next, we give you one line to paste on the server. That is all.',
                        )
                    }}
                </p>
                <Button
                    type="submit"
                    size="lg"
                    :disabled="server.processing || !server.name"
                    >{{ t('Continue') }}</Button
                >
            </form>

            <!-- Something else -->
            <div v-if="kind === 'service'" class="flex flex-col gap-5">
                <fieldset class="flex flex-col gap-2">
                    <legend class="sr-only">
                        {{ t('What do you want to monitor?') }}
                    </legend>
                    <button
                        v-for="k in serviceKinds"
                        :key="k.value"
                        type="button"
                        class="monitor-kind-card flex w-full flex-col gap-0.5 px-4 py-3 text-left text-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        :class="
                            service.kind === k.value
                                ? 'border-primary ring-2 ring-primary/35'
                                : ''
                        "
                        :aria-pressed="service.kind === k.value"
                        @click="pickServiceKind(k.value)"
                    >
                        <span class="font-display font-semibold">{{
                            k.label
                        }}</span>
                        <span
                            class="mt-1 block text-xs text-pretty text-muted-foreground"
                            >{{ k.hint }}</span
                        >
                    </button>
                </fieldset>

                <form
                    v-if="isObjectStorage"
                    class="form-panel"
                    @submit.prevent="submitObjectStorage"
                >
                    <div class="field-stack">
                        <Label for="os-provider">{{ t('Cloud provider') }}</Label>
                        <select
                            id="os-provider"
                            v-model="objectStorage.provider"
                            :class="select"
                        >
                            <option
                                v-for="p in object_storage_providers"
                                :key="p.value"
                                :value="p.value"
                            >
                                {{ p.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="storageNeedsEndpoint" class="field-stack">
                        <Label for="os-endpoint">{{ t('S3 endpoint (host only)') }}</Label>
                        <Input
                            id="os-endpoint"
                            v-model="objectStorage.endpoint_host"
                            :placeholder="
                                objectStorage.provider === 'laravel_cloud'
                                    ? 'xxxxxxxx.r2.cloudflarestorage.com'
                                    : 's3.example.com'
                            "
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="none"
                        />
                        <InputError :message="objectStorage.errors.endpoint_host" />
                        <p
                            v-if="objectStorage.provider === 'laravel_cloud'"
                            class="field-hint"
                        >
                            {{
                                t(
                                    'Copy the endpoint from your Laravel Cloud bucket credentials (AWS_ENDPOINT_URL, host only).',
                                )
                            }}
                        </p>
                    </div>
                    <div v-if="storageShowsRegion" class="field-stack">
                        <Label for="os-region">{{
                            objectStorage.provider === 'laravel_cloud'
                                ? t('App region (label only)')
                                : t('Region')
                        }}</Label>
                        <select
                            id="os-region"
                            v-model="objectStorage.region"
                            :class="select"
                        >
                            <option
                                v-for="r in storageRegions"
                                :key="r.value"
                                :value="r.value"
                            >
                                {{ r.label }}
                            </option>
                        </select>
                        <InputError :message="objectStorage.errors.region" />
                    </div>
                    <fieldset class="field-stack">
                        <legend class="text-sm font-medium">
                            {{ t('What should we check?') }}
                        </legend>
                        <label class="flex cursor-pointer gap-2 text-sm">
                            <input
                                v-model="objectStorage.level"
                                type="radio"
                                name="os-level"
                                value="platform"
                            />
                            <span>{{
                                t('The storage service answers (recommended first step)')
                            }}</span>
                        </label>
                        <label class="flex cursor-pointer gap-2 text-sm">
                            <input
                                v-model="objectStorage.level"
                                type="radio"
                                name="os-level"
                                value="bucket"
                            />
                            <span>{{
                                t('A public file in my bucket is reachable')
                            }}</span>
                        </label>
                        <label class="flex cursor-pointer gap-2 text-sm">
                            <input
                                v-model="objectStorage.level"
                                type="radio"
                                name="os-level"
                                value="backup"
                            />
                            <span>{{
                                t('My backup job still runs (heartbeat)')
                            }}</span>
                        </label>
                    </fieldset>
                    <div v-if="isBucketLevel" class="field-stack">
                        <Label for="os-bucket">{{ t('Bucket name') }}</Label>
                        <Input
                            id="os-bucket"
                            v-model="objectStorage.bucket"
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="none"
                        />
                        <InputError :message="objectStorage.errors.bucket" />
                        <Label for="os-health">{{ t('File to fetch') }}</Label>
                        <Input
                            id="os-health"
                            v-model="objectStorage.health_path"
                            placeholder="health.txt"
                            autocomplete="off"
                        />
                        <p class="field-hint">
                            {{
                                t(
                                    'Create this file in the bucket and allow public read (or use a stable public URL). Phare checks it returns HTTP success.',
                                )
                            }}
                        </p>
                    </div>
                    <p
                        v-if="isBackupLevel"
                        class="surface-muted p-4 text-sm text-muted-foreground"
                    >
                        {{
                            t(
                                'Next, we give you a secret URL. Call it from your backup script after a successful upload to S3.',
                            )
                        }}
                    </p>
                    <GroupPicker
                        v-model:group-id="objectStorage.group_id"
                        v-model:group-name="objectStorage.group_name"
                        :groups="groups"
                        :error="objectStorage.errors.group_id"
                        id="object-storage-group"
                    />
                    <details class="text-sm">
                        <summary class="cursor-pointer text-muted-foreground">
                            {{ t('Name it yourself (optional)') }}
                        </summary>
                        <Input
                            v-model="objectStorage.name"
                            class="mt-2"
                            :placeholder="t('Object storage (S3)')"
                        />
                    </details>
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="
                            objectStorage.processing ||
                            (storageNeedsEndpoint && !objectStorage.endpoint_host) ||
                            (isBucketLevel && !objectStorage.bucket)
                        "
                        >{{ isBackupLevel ? t('Continue') : t('Start monitoring') }}</Button
                    >
                </form>

                <form
                    v-else-if="!isHeartbeat"
                    class="form-panel"
                    @submit.prevent="
                        service.post(MonitorController.service.url())
                    "
                >
                    <div class="field-stack">
                        <Label for="svc-host">{{
                            service.kind === 'dns'
                                ? t('Domain name')
                                : t('Address (name or IP)')
                        }}</Label>
                        <Input
                            id="svc-host"
                            v-model="service.host"
                            :placeholder="
                                service.kind === 'dns'
                                    ? 'example.com'
                                    : 'mail.example.com'
                            "
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="none"
                            spellcheck="false"
                        />
                        <InputError :message="service.errors.host" />
                    </div>
                    <div v-if="needsPort" class="field-stack">
                        <Label for="svc-port">{{ t('Port') }}</Label>
                        <Input
                            id="svc-port"
                            v-model="service.port"
                            type="text"
                            inputmode="numeric"
                        />
                        <InputError :message="service.errors.port" />
                        <p v-if="service.kind === 'mail'" class="field-hint">
                            {{
                                t(
                                    'Common mail ports: 25, 465 or 587 (sending), 993 (reading).',
                                )
                            }}
                        </p>
                    </div>
                    <p
                        v-if="needsProbe && !has_probe"
                        class="surface border-warning/40 bg-warning/10 p-4 text-sm"
                        role="status"
                    >
                        {{
                            t(
                                'This kind of check runs from a probe, and none is connected yet. You can add it now and connect a probe later.',
                            )
                        }}
                    </p>
                    <GroupPicker
                        v-model:group-id="service.group_id"
                        v-model:group-name="service.group_name"
                        :groups="groups"
                        :error="service.errors.group_id"
                        id="service-group"
                    />
                    <details class="text-sm">
                        <summary class="cursor-pointer text-muted-foreground">
                            {{ t('Name it yourself (optional)') }}
                        </summary>
                        <Input
                            v-model="service.name"
                            class="mt-2"
                            :placeholder="current?.label"
                        />
                    </details>
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="service.processing || !service.host"
                        >{{ t('Start monitoring') }}</Button
                    >
                </form>

                <!-- Scheduled task (heartbeat) -->
                <form
                    v-else
                    class="form-panel"
                    @submit.prevent="submitHeartbeat"
                >
                    <div class="field-stack">
                        <Label for="hb-name">{{ t('Name of the task') }}</Label>
                        <Input
                            id="hb-name"
                            v-model="heartbeat.name"
                            :placeholder="t('Nightly backup')"
                            autocomplete="off"
                        />
                        <InputError :message="heartbeat.errors.name" />
                    </div>
                    <div class="field-stack">
                        <Label for="hb-frequency">{{
                            t('How often should it run?')
                        }}</Label>
                        <select
                            id="hb-frequency"
                            v-model="heartbeat.frequency"
                            :class="select"
                        >
                            <option value="minute">
                                {{ t('Every minute') }}
                            </option>
                            <option value="hour">{{ t('Every hour') }}</option>
                            <option value="day">{{ t('Every day') }}</option>
                            <option value="week">{{ t('Every week') }}</option>
                            <option value="custom">
                                {{ t('Another frequency…') }}
                            </option>
                        </select>
                        <div
                            v-if="heartbeat.frequency === 'custom'"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <span class="text-sm">{{ t('Every') }}</span>
                            <Input
                                v-model="heartbeat.custom_value"
                                type="text"
                                inputmode="numeric"
                                min="1"
                                class="w-24"
                                :aria-label="t('Number')"
                            />
                            <select
                                v-model="heartbeat.custom_unit"
                                :class="select"
                                :aria-label="t('Unit')"
                            >
                                <option value="minutes">
                                    {{ t('minutes') }}
                                </option>
                                <option value="hours">{{ t('hours') }}</option>
                                <option value="days">{{ t('days') }}</option>
                            </select>
                        </div>
                        <InputError
                            :message="
                                (heartbeat.errors as Record<string, string>)
                                    .interval_sec
                            "
                        />
                        <p class="field-hint">
                            {{
                                t(
                                    'Your task will tell Phare each time it runs. If Phare hears nothing for a little longer than this, we warn you.',
                                )
                            }}
                        </p>
                    </div>
                    <GroupPicker
                        v-model:group-id="heartbeat.group_id"
                        v-model:group-name="heartbeat.group_name"
                        :groups="groups"
                        :error="heartbeat.errors.group_id"
                        id="heartbeat-group"
                    />
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="
                            heartbeat.processing ||
                            !heartbeat.name ||
                            heartbeatInterval < 60
                        "
                        >{{ t('Continue') }}</Button
                    >
                </form>
            </div>
        </div>
    </PageContainer>
</template>
