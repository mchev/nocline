<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { KeyRound, TriangleAlert } from '@lucide/vue';
import ApiTokenController from '@/actions/App/Modules/Api/Http/Controllers/ApiTokenController';
import CopyBlock from '@/components/CopyBlock.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import { t } from '@/lib/i18n';

type Token = {
    id: number;
    name: string;
    can_write: boolean;
    last_used_at: string | null;
    expires_at: string | null;
    expired: boolean;
    created_at: string | null;
};

defineProps<{
    tokens: Token[];
    new_secret: string | null;
}>();

const select =
    'h-10 w-full rounded-md border bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
const date = (iso: string) => new Date(iso).toLocaleDateString();

function revoke(token: Token) {
    if (
        confirm(
            t(
                'Revoke “:name”? Programs using it will stop working immediately.',
                { name: token.name },
            ),
        )
    ) {
        router.delete(ApiTokenController.destroy.url(token.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="$t('API tokens')" />

    <h1 class="sr-only">{{ $t('API tokens') }}</h1>

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            :title="$t('API tokens')"
            :description="
                $t(
                    'A token lets another program (a script, your website back-office…) use Nocline on your behalf, without your password.',
                )
            "
        />

        <div
            v-if="new_secret"
            class="grid gap-3 rounded-md border border-warning/40 bg-warning/15 p-5"
            role="alert"
        >
            <p class="flex items-center gap-2 font-medium">
                <TriangleAlert class="size-4 shrink-0" aria-hidden="true" />
                {{ $t('Copy your token now') }}
            </p>
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'For your safety, this is the only time it is shown. If you lose it, revoke it and create a new one. Treat it like a password: whoever has it can act as you.',
                    )
                }}
            </p>
            <CopyBlock :text="new_secret" :label="$t('API token')" />
        </div>

        <Form
            v-bind="ApiTokenController.store.form()"
            class="form-panel"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="field-stack">
                <Label for="token-name" class="field-label">{{
                    $t('What is this token for?')
                }}</Label>
                <Input
                    id="token-name"
                    name="name"
                    required
                    :placeholder="$t('Backup script, CRM…')"
                />
                <InputError :message="errors.name" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="field-label mb-1">
                    {{ $t('What may it do?') }}
                </legend>
                <label
                    class="flex items-start gap-3 rounded-md border p-3 text-sm transition has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:checked]:ring-2 has-[:checked]:ring-primary/30"
                >
                    <input
                        type="radio"
                        name="access"
                        value="read"
                        checked
                        class="mt-1 accent-primary"
                    />
                    <span>
                        <span class="font-medium">{{ $t('Look only') }}</span>
                        <span class="field-hint mt-0.5 block">{{
                            $t(
                                'It can read your monitors and their status, nothing else.',
                            )
                        }}</span>
                    </span>
                </label>
                <label
                    class="flex items-start gap-3 rounded-md border p-3 text-sm transition has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:checked]:ring-2 has-[:checked]:ring-primary/30"
                >
                    <input
                        type="radio"
                        name="access"
                        value="write"
                        class="mt-1 accent-primary"
                    />
                    <span>
                        <span class="font-medium">{{
                            $t('Look and change')
                        }}</span>
                        <span class="field-hint mt-0.5 block">{{
                            $t(
                                'It can also add, edit and delete what your own role allows.',
                            )
                        }}</span>
                    </span>
                </label>
                <InputError :message="errors.access" />
            </fieldset>

            <div class="field-stack">
                <Label for="token-expiry" class="field-label">{{
                    $t('Valid for')
                }}</Label>
                <select
                    id="token-expiry"
                    name="expires_in_days"
                    :class="select"
                >
                    <option value="">{{ $t('Until I revoke it') }}</option>
                    <option value="30">{{ $t('30 days') }}</option>
                    <option value="90">{{ $t('90 days') }}</option>
                    <option value="365">{{ $t('1 year') }}</option>
                </select>
                <InputError :message="errors.expires_in_days" />
            </div>

            <Button type="submit" class="w-fit" :disabled="processing">{{
                $t('Create token')
            }}</Button>
        </Form>

        <section class="space-y-3">
            <h2 class="font-display text-lg font-semibold">
                {{ $t('Your tokens') }}
            </h2>
            <EmptyState
                v-if="!tokens.length"
                :icon="KeyRound"
                :title="
                    $t(
                        'You have no token yet. Create one above when a program needs to talk to Nocline.',
                    )
                "
            />
            <ul v-else class="divide-y overflow-hidden surface">
                <li
                    v-for="token in tokens"
                    :key="token.id"
                    class="flex flex-wrap items-center justify-between gap-3 p-4"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ token.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            <span
                                class="chip"
                                :class="
                                    token.can_write
                                        ? 'chip-warning'
                                        : 'chip-info'
                                "
                            >
                                {{
                                    token.can_write
                                        ? $t('Look and change')
                                        : $t('Look only')
                                }}
                            </span>
                            ·
                            {{
                                token.last_used_at
                                    ? $t('Last used :date', {
                                          date: date(token.last_used_at),
                                      })
                                    : $t('Never used')
                            }}
                            <template v-if="token.expires_at">
                                ·
                                {{
                                    token.expired
                                        ? $t('Expired :date', {
                                              date: date(token.expires_at),
                                          })
                                        : $t('Expires :date', {
                                              date: date(token.expires_at),
                                          })
                                }}
                            </template>
                        </p>
                    </div>
                    <Button variant="outline" size="sm" @click="revoke(token)">
                        {{ $t('Revoke') }}
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
