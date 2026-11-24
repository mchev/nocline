<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();
</script>

<template>
    <Head :title="$t('Security settings')" />

    <h1 class="sr-only">{{ $t('Security settings') }}</h1>

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            :title="$t('Update password')"
            :description="
                $t(
                    'Ensure your account is using a long, random password to stay secure',
                )
            "
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="form-panel"
            v-slot="{ errors, processing }"
        >
            <div class="field-stack">
                <Label for="current_password" class="field-label">{{
                    $t('Current password')
                }}</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                    :placeholder="$t('Current password')"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="field-stack">
                <Label for="password" class="field-label">{{
                    $t('New password')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    :placeholder="$t('New password')"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="field-stack">
                <Label for="password_confirmation" class="field-label">{{
                    $t('Confirm password')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :placeholder="$t('Confirm password')"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    {{ $t('Save') }}
                </Button>
            </div>
        </Form>

        <ManageTwoFactor
            :canManageTwoFactor="canManageTwoFactor"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />

        <ManagePasskeys
            :canManagePasskeys="canManagePasskeys"
            :passkeys="passkeys"
        />
    </div>
</template>
