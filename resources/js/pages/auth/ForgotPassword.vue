<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="$t('Forgot password')" />

    <p
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-success"
        role="status"
    >
        {{ status }}
    </p>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="field-stack">
                <Label for="email" class="field-label">{{
                    $t('Email address')
                }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    v-focus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="mt-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Email password reset link') }}
                </Button>
            </div>
        </Form>

        <p class="text-center text-sm text-muted-foreground">
            <span>{{ $t('Or, return to') }}</span>
            <TextLink :href="login()" class="ml-1">{{ $t('log in') }}</TextLink>
        </p>
    </div>
</template>
