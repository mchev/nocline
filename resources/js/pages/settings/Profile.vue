<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="$t('Profile settings')" />

    <h1 class="sr-only">{{ $t('Profile settings') }}</h1>

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            :title="$t('Profile')"
            :description="$t('Update your name and email address')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="form-panel"
            v-slot="{ errors, processing }"
        >
            <div class="field-stack">
                <Label for="name" class="field-label">{{ $t('Name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="$t('Full name')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="field-stack">
                <Label for="email" class="field-label">{{
                    $t('Email address')
                }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :placeholder="$t('Email address')"
                />
                <InputError :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="field-hint">
                    {{ $t('Your email address is unverified.') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline underline-offset-4"
                    >
                        {{
                            $t('Click here to re-send the verification email.')
                        }}
                    </Link>
                </p>

                <p
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-success"
                    role="status"
                >
                    {{
                        $t(
                            'A new verification link has been sent to your email address.',
                        )
                    }}
                </p>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{ $t('Save') }}
                </Button>
            </div>
        </Form>

        <DeleteUser />
    </div>
</template>
