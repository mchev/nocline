<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Loader2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SetupController from '@/actions/App/Modules/Setup/Http/Controllers/SetupController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';

const props = defineProps<{
    defaults: { url: string; timezone: string; sqlite_path: string };
    timezones: string[];
    locale: string;
}>();

defineOptions({
    layout: {
        title: 'Welcome to Phare',
        description: 'Set up your instance in a few steps',
    },
});

const page = usePage();
const locales = computed(
    () => page.props.locales as unknown as Record<string, string>,
);

const form = useForm({
    db: {
        driver: 'sqlite',
        database: props.defaults.sqlite_path,
        host: '127.0.0.1',
        port: '',
        username: '',
        password: '',
    },
    admin: { name: '', email: '', password: '', password_confirmation: '' },
    instance: {
        name: 'Phare',
        url: props.defaults.url,
        locale: props.locale,
        timezone: props.defaults.timezone,
    },
    mail: { host: '', port: '587', from: '', username: '', password: '' },
});

const steps = computed(() => [
    t('Language'),
    t('Database'),
    t('Administrator'),
    t('Instance'),
    t('Email'),
    t('Review'),
]);
const step = ref(0);
const stepError = ref<string | null>(null);
watch(form, () => (stepError.value = null), { deep: true });
const select =
    'h-10 w-full rounded-md border bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function chooseLocale(code: string) {
    form.instance.locale = code;
    router.post(
        SetupController.locale.url(),
        { locale: code },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['translations', 'locale', 'locales'],
        },
    );
}

function driverChanged() {
    if (form.db.driver === 'sqlite') {
        form.db.database = props.defaults.sqlite_path;
    } else if (form.db.database === props.defaults.sqlite_path) {
        form.db.database = '';
    }

    connection.value = null;
}

const connection = ref<{ ok: boolean; error?: string | null } | null>(null);
const testing = ref(false);

function xsrf(): string {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

async function testConnection(): Promise<boolean> {
    testing.value = true;
    connection.value = null;

    try {
        const response = await fetch(SetupController.testConnection.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrf(),
            },
            body: JSON.stringify(form.db),
        });
        connection.value = response.ok
            ? await response.json()
            : { ok: false, error: `HTTP ${response.status}` };
    } catch (e) {
        connection.value = { ok: false, error: String(e) };
    } finally {
        testing.value = false;
    }

    return connection.value?.ok === true;
}

function validateStep(index: number): string | null {
    const { db, admin, instance } = form;

    if (index === 1 && db.driver !== 'sqlite') {
        if (!db.host || !db.database || !db.username) {
            return t('Host, database name and username are required.');
        }
    }

    if (index === 2) {
        if (!admin.name || !admin.email) {
            return t('Name and email are required.');
        }

        if (admin.password.length < 12) {
            return t('The password must be at least 12 characters.');
        }

        if (admin.password !== admin.password_confirmation) {
            return t('The passwords do not match.');
        }
    }

    if (index === 3 && (!instance.name || !/^https?:\/\//.test(instance.url))) {
        return t('An instance name and a valid http(s) URL are required.');
    }

    return null;
}

async function next() {
    stepError.value = validateStep(step.value);

    if (stepError.value) {
        return;
    }

    if (step.value === 1 && !(await testConnection())) {
        stepError.value = t('Fix the database connection before continuing.');

        return;
    }

    step.value++;
}

function back() {
    stepError.value = null;
    step.value = Math.max(0, step.value - 1);
}

const errorSteps: Record<string, number> = {
    db: 1,
    admin: 2,
    instance: 3,
    mail: 4,
};

function install() {
    if (form.processing) {
        return;
    }

    form.post(SetupController.store.url(), {
        onError: (errors) => {
            const first = Object.keys(errors)[0]?.split('.')[0];
            step.value = errorSteps[first] ?? step.value;
        },
    });
}

function err(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}
</script>

<template>
    <Head :title="t('Setup')" />

    <div class="flex flex-col gap-6">
        <ol
            class="flex items-center justify-between gap-1"
            :aria-label="t('Setup')"
        >
            <li
                v-for="(label, i) in steps"
                :key="label"
                class="flex flex-col items-center gap-1.5"
                :aria-current="i === step ? 'step' : undefined"
                :class="
                    i === step
                        ? 'font-semibold text-foreground'
                        : 'text-muted-foreground'
                "
            >
                <span
                    class="flex size-8 items-center justify-center rounded-md border text-xs"
                    :class="
                        i < step
                            ? 'border-primary bg-primary text-primary-foreground'
                            : i === step
                              ? 'border-primary bg-primary/15 text-primary'
                              : 'border-border'
                    "
                >
                    <Check
                        v-if="i < step"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <template v-else>{{ i + 1 }}</template>
                </span>
                <span class="hidden text-[0.7rem] sm:block">{{ label }}</span>
            </li>
        </ol>

        <!-- 1. Language -->
        <section v-if="step === 0" class="form-panel gap-4">
            <h2 class="font-display text-lg font-semibold">
                {{ t('Choose your language') }}
            </h2>
            <div class="grid grid-cols-2 gap-3">
                <button
                    v-for="(name, code) in locales"
                    :key="code"
                    type="button"
                    class="rounded-md border p-4 text-left text-sm transition hover:border-primary/40 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        form.instance.locale === code
                            ? 'border-primary bg-primary/10 font-medium ring-2 ring-primary/30'
                            : ''
                    "
                    :aria-pressed="form.instance.locale === code"
                    @click="chooseLocale(String(code))"
                >
                    {{ name }}
                </button>
            </div>
            <p class="field-hint">
                {{
                    t(
                        'Want another language? Contributions are welcome: add a file in lang/.',
                    )
                }}
            </p>
        </section>

        <!-- 2. Database -->
        <section v-else-if="step === 1" class="form-panel gap-4">
            <h2 class="font-display text-lg font-semibold">
                {{ t('Database') }}
            </h2>
            <div class="field-stack">
                <Label for="db_driver" class="field-label">{{
                    t('Engine')
                }}</Label>
                <select
                    id="db_driver"
                    v-model="form.db.driver"
                    :class="select"
                    @change="driverChanged"
                >
                    <option value="sqlite">
                        {{ t('SQLite (trial / small instance)') }}
                    </option>
                    <option value="mysql">MySQL</option>
                    <option value="pgsql">
                        {{ t('PostgreSQL (recommended)') }}
                    </option>
                </select>
            </div>
            <template v-if="form.db.driver === 'sqlite'">
                <div class="field-stack">
                    <Label for="db_path" class="field-label">{{
                        t('Database file')
                    }}</Label>
                    <Input id="db_path" v-model="form.db.database" />
                </div>
            </template>
            <template v-else>
                <div class="grid grid-cols-3 gap-3">
                    <div class="field-stack col-span-2">
                        <Label for="db_host" class="field-label">{{
                            t('Host')
                        }}</Label>
                        <Input id="db_host" v-model="form.db.host" />
                    </div>
                    <div class="field-stack">
                        <Label for="db_port" class="field-label">{{
                            t('Port')
                        }}</Label>
                        <Input id="db_port" v-model="form.db.port" />
                    </div>
                </div>
                <div class="field-stack">
                    <Label for="db_name" class="field-label">{{
                        t('Database name')
                    }}</Label>
                    <Input id="db_name" v-model="form.db.database" />
                </div>
                <div class="field-stack">
                    <Label for="db_user" class="field-label">{{
                        t('Username')
                    }}</Label>
                    <Input id="db_user" v-model="form.db.username" />
                </div>
                <div class="field-stack">
                    <Label for="db_pass" class="field-label">{{
                        t('Password')
                    }}</Label>
                    <Input
                        id="db_pass"
                        v-model="form.db.password"
                        type="password"
                        autocomplete="new-password"
                    />
                </div>
            </template>
            <InputError :message="err('db.driver') ?? err('db.database')" />
            <div class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="testing"
                    @click="testConnection"
                >
                    <Loader2 v-if="testing" class="size-4 animate-spin" />
                    {{ t('Test connection') }}
                </Button>
                <span
                    v-if="connection?.ok"
                    class="chip chip-success"
                    role="status"
                >
                    {{ t('Connection successful.') }}
                </span>
                <span
                    v-else-if="connection"
                    class="chip chip-danger"
                    role="status"
                >
                    {{ connection.error }}
                </span>
            </div>
        </section>

        <!-- 3. Admin -->
        <section v-else-if="step === 2" class="form-panel gap-4">
            <h2 class="font-display text-lg font-semibold">
                {{ t('Administrator account') }}
            </h2>
            <div class="field-stack">
                <Label for="admin_name" class="field-label">{{
                    t('Name')
                }}</Label>
                <Input
                    id="admin_name"
                    v-model="form.admin.name"
                    autocomplete="name"
                />
                <InputError :message="err('admin.name')" />
            </div>
            <div class="field-stack">
                <Label for="admin_email" class="field-label">{{
                    t('Email')
                }}</Label>
                <Input
                    id="admin_email"
                    v-model="form.admin.email"
                    type="email"
                    autocomplete="email"
                />
                <InputError :message="err('admin.email')" />
            </div>
            <div class="field-stack">
                <Label for="admin_password" class="field-label">{{
                    t('Password')
                }}</Label>
                <Input
                    id="admin_password"
                    v-model="form.admin.password"
                    type="password"
                    autocomplete="new-password"
                />
                <p class="field-hint">
                    {{
                        t(
                            'Use upper and lower case letters, numbers and symbols.',
                        )
                    }}
                </p>
            </div>
            <div class="field-stack">
                <Label for="admin_password_confirmation" class="field-label">{{
                    t('Confirm password')
                }}</Label>
                <Input
                    id="admin_password_confirmation"
                    v-model="form.admin.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                />
                <InputError :message="err('admin.password')" />
            </div>
        </section>

        <!-- 4. Instance -->
        <section v-else-if="step === 3" class="form-panel gap-4">
            <h2 class="font-display text-lg font-semibold">
                {{ t('Instance') }}
            </h2>
            <div class="field-stack">
                <Label for="instance_name" class="field-label">{{
                    t('Instance name')
                }}</Label>
                <Input id="instance_name" v-model="form.instance.name" />
            </div>
            <div class="field-stack">
                <Label for="instance_url" class="field-label">{{
                    t('Public URL of the hub')
                }}</Label>
                <Input id="instance_url" v-model="form.instance.url" />
                <InputError :message="err('instance.url')" />
            </div>
            <div class="field-stack">
                <Label for="instance_tz" class="field-label">{{
                    t('Timezone')
                }}</Label>
                <select
                    id="instance_tz"
                    v-model="form.instance.timezone"
                    :class="select"
                >
                    <option v-for="tz in timezones" :key="tz" :value="tz">
                        {{ tz }}
                    </option>
                </select>
            </div>
        </section>

        <!-- 5. Mail -->
        <section v-else-if="step === 4" class="form-panel gap-4">
            <h2 class="font-display text-lg font-semibold">
                {{ t('Email (optional)') }}
            </h2>
            <p class="field-hint">
                {{
                    t(
                        'Needed to send alerts and reminders by email. You can skip this and configure it later in .env.',
                    )
                }}
            </p>
            <div class="field-stack">
                <Label for="mail_host" class="field-label">{{
                    t('SMTP server')
                }}</Label>
                <Input id="mail_host" v-model="form.mail.host" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="field-stack">
                    <Label for="mail_port" class="field-label">{{
                        t('Port')
                    }}</Label>
                    <Input id="mail_port" v-model="form.mail.port" />
                </div>
                <div class="field-stack">
                    <Label for="mail_from" class="field-label">{{
                        t('Sender address')
                    }}</Label>
                    <Input
                        id="mail_from"
                        v-model="form.mail.from"
                        type="email"
                    />
                </div>
            </div>
            <InputError :message="err('mail.from')" />
            <div class="field-stack">
                <Label for="mail_user" class="field-label">{{
                    t('SMTP username')
                }}</Label>
                <Input id="mail_user" v-model="form.mail.username" />
            </div>
            <div class="field-stack">
                <Label for="mail_pass" class="field-label">{{
                    t('SMTP password')
                }}</Label>
                <Input
                    id="mail_pass"
                    v-model="form.mail.password"
                    type="password"
                    autocomplete="new-password"
                />
            </div>
        </section>

        <!-- 6. Review -->
        <section v-else class="form-panel gap-4">
            <h2 class="font-display text-lg font-semibold">
                {{ t('Review and install') }}
            </h2>
            <dl
                class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 surface-muted p-4 text-sm"
            >
                <dt class="text-muted-foreground">{{ t('Language') }}</dt>
                <dd>{{ locales[form.instance.locale] }}</dd>
                <dt class="text-muted-foreground">{{ t('Database') }}</dt>
                <dd>{{ form.db.driver }}</dd>
                <dt class="text-muted-foreground">{{ t('Administrator') }}</dt>
                <dd>{{ form.admin.email }}</dd>
                <dt class="text-muted-foreground">{{ t('Instance') }}</dt>
                <dd>{{ form.instance.name }} — {{ form.instance.url }}</dd>
                <dt class="text-muted-foreground">{{ t('Email') }}</dt>
                <dd>{{ form.mail.host || t('Not configured') }}</dd>
            </dl>
            <InputError
                v-for="(message, key) in form.errors"
                :key="key"
                :message="String(message)"
            />
        </section>

        <p v-if="stepError" class="text-sm text-danger" role="alert">
            {{ stepError }}
        </p>

        <div class="flex justify-between gap-3">
            <Button
                type="button"
                variant="ghost"
                :disabled="step === 0"
                @click="back"
            >
                {{ t('Back') }}
            </Button>
            <Button
                v-if="step < steps.length - 1"
                key="continue"
                type="button"
                :disabled="testing"
                @click="next"
            >
                {{ step === 4 && !form.mail.host ? t('Skip') : t('Continue') }}
            </Button>
            <Button
                v-else
                key="install"
                type="button"
                :disabled="form.processing"
                @click="install"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ t('Install Phare') }}
            </Button>
        </div>
    </div>
</template>
