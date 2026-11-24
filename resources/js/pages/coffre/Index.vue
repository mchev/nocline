<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { KeyRound, Lock } from '@lucide/vue';
import { computed, ref } from 'vue';
import VaultController from '@/actions/App/Modules/Coffre/Http/Controllers/VaultController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';
import { audit } from '@/routes/coffre';
import type { GroupOption } from '@/types/groups';

type Entry = {
    id: number;
    label: string;
    url: string | null;
    group: string | null;
};

defineProps<{
    configured: boolean;
    entries: Entry[];
    groups: GroupOption[];
}>();

const isAdmin = computed(() => usePage().props.auth.user.role === 'admin');
const select =
    'h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none';

const CLEAR_AFTER_MS = 30_000;
const revealed = ref<{
    id: number;
    username: string | null;
    secret: string;
    notes: string | null;
} | null>(null);
const revealError = ref('');
const password = ref('');
let clearTimer: ReturnType<typeof setTimeout> | undefined;

function csrf(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function post(path: string, body: object) {
    return fetch(path, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(body),
    });
}

function hide() {
    revealed.value = null;
    password.value = '';
    clearTimeout(clearTimer);
}

async function reveal(id: number) {
    revealError.value = '';
    const res = await post(VaultController.reveal.url(id), {
        password: password.value,
    });

    if (!res.ok) {
        revealError.value = t('Incorrect password or access denied.');

        return;
    }

    revealed.value = { id, ...(await res.json()) };
    password.value = '';
    clearTimeout(clearTimer);
    clearTimer = setTimeout(hide, CLEAR_AFTER_MS);
}

async function copySecret() {
    if (!revealed.value) {
        return;
    }

    await navigator.clipboard.writeText(revealed.value.secret);
    void post(VaultController.copied.url(revealed.value.id), {});
    setTimeout(() => navigator.clipboard.writeText(''), CLEAR_AFTER_MS);
}
</script>

<template>
    <Head :title="$t('Vault')" />

    <PageContainer>
        <PageHeader
            :title="$t('Vault')"
            :description="
                $t(
                    'Encrypted secrets for your team. Reveals are logged and auto-hide after 30 seconds.',
                )
            "
        >
            <Button v-if="isAdmin" as-child variant="outline">
                <Link :href="audit()">{{ $t('Audit log') }}</Link>
            </Button>
        </PageHeader>

        <div
            v-if="!configured"
            class="reveal surface border-danger/40 bg-danger/10 p-5 text-sm"
            role="alert"
        >
            <p class="font-medium text-danger">
                {{
                    $t(
                        'The master key (VAULT_MASTER_KEY) is not configured: the vault is disabled.',
                    )
                }}
            </p>
        </div>

        <Form
            v-if="configured"
            v-bind="VaultController.store.form()"
            class="form-panel reveal"
            aria-labelledby="vault-add-heading"
            :reset-on-success="['secret', 'password', 'username', 'notes']"
            v-slot="{ errors, processing }"
        >
            <h2
                id="vault-add-heading"
                class="font-display text-lg font-semibold"
            >
                {{ $t('Add') }}
            </h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="field-stack">
                    <Label for="vault-label" class="field-label">{{
                        $t('Label')
                    }}</Label>
                    <Input id="vault-label" name="label" required />
                </div>
                <div class="field-stack">
                    <Label for="vault-url" class="field-label">{{
                        $t('URL (optional)')
                    }}</Label>
                    <Input id="vault-url" name="url" />
                </div>
                <div class="field-stack">
                    <Label for="vault-group" class="field-label">{{
                        $t('Group')
                    }}</Label>
                    <select id="vault-group" name="group_id" :class="select">
                        <option value="">{{ $t('Group…') }}</option>
                        <option v-for="g in groups" :key="g.id" :value="g.id">
                            {{ g.path }}
                        </option>
                    </select>
                </div>
                <div class="field-stack">
                    <Label for="vault-user" class="field-label">{{
                        $t('Username')
                    }}</Label>
                    <Input id="vault-user" name="username" autocomplete="off" />
                </div>
                <div class="field-stack">
                    <Label for="vault-secret" class="field-label">{{
                        $t('Secret')
                    }}</Label>
                    <Input
                        id="vault-secret"
                        name="secret"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                    <InputError :message="errors.secret" />
                </div>
                <div class="field-stack">
                    <Label for="vault-notes" class="field-label">
                        {{ $t('Notes (encrypted)') }}
                    </Label>
                    <Input id="vault-notes" name="notes" />
                </div>
                <div class="field-stack md:col-span-2">
                    <Label for="vault-password" class="field-label">
                        {{ $t('Your password (confirmation)') }}
                    </Label>
                    <Input
                        id="vault-password"
                        name="password"
                        type="password"
                        required
                    />
                    <InputError :message="errors.password" />
                </div>
                <div class="flex items-end">
                    <Button type="submit" class="w-full" :disabled="processing">
                        <Lock class="size-4" /> {{ $t('Add') }}
                    </Button>
                </div>
            </div>
        </Form>

        <div
            v-if="revealed"
            class="reveal surface border-warning/40 bg-warning/10 p-5 text-sm"
            role="status"
        >
            <p class="mb-3 font-medium">
                {{ $t('Secret shown for 30 s (this access is logged)') }}
            </p>
            <p v-if="revealed.username">
                {{ $t('Username') }}:
                <code class="select-all">{{ revealed.username }}</code>
            </p>
            <p>
                {{ $t('Secret') }}:
                <code class="select-all">{{ revealed.secret }}</code>
            </p>
            <p v-if="revealed.notes">{{ $t('Notes') }}: {{ revealed.notes }}</p>
            <div class="mt-3 flex gap-2">
                <Button size="sm" @click="copySecret">{{ $t('Copy') }}</Button>
                <Button size="sm" variant="ghost" @click="hide">{{
                    $t('Hide')
                }}</Button>
            </div>
        </div>

        <EmptyState
            v-if="configured && !entries.length"
            :icon="KeyRound"
            :title="$t('No entries.')"
            :description="
                $t(
                    'Add a secret above. You will need your password to reveal it later.',
                )
            "
        />

        <div
            v-else-if="entries.length"
            class="reveal reveal-delay-1 overflow-hidden surface"
            tabindex="0"
            role="region"
            :aria-label="$t('Vault')"
        >
            <table class="table-clean">
                <thead>
                    <tr>
                        <th scope="col">{{ $t('Label') }}</th>
                        <th scope="col">{{ $t('Group') }}</th>
                        <th scope="col" class="hidden md:table-cell">
                            {{ $t('URL (optional)') }}
                        </th>
                        <th scope="col" class="text-right">
                            {{ $t('Reveal') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in entries" :key="e.id">
                        <td class="font-medium">{{ e.label }}</td>
                        <td>
                            <span v-if="e.group" class="chip">{{
                                e.group
                            }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="hidden text-muted-foreground md:table-cell">
                            {{ e.url || '—' }}
                        </td>
                        <td>
                            <div
                                class="flex flex-wrap items-center justify-end gap-2"
                            >
                                <Input
                                    v-model="password"
                                    class="w-36"
                                    type="password"
                                    :placeholder="$t('Password')"
                                    autocomplete="current-password"
                                    :aria-label="$t('Password')"
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="reveal(e.id)"
                                >
                                    {{ $t('Reveal') }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <InputError :message="revealError" />
    </PageContainer>
</template>
