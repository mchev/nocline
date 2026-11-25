<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import { computed } from 'vue';
import UserController from '@/actions/App/Modules/Users/Http/Controllers/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';

defineProps<{
    users: { id: number; name: string; email: string; role: string }[];
    roles: string[];
}>();

const meId = computed(() => usePage().props.auth.user.id);
const select =
    'h-10 rounded-md border bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head :title="$t('Users')" />

    <PageContainer>
        <PageHeader
            :title="$t('Users')"
            :description="$t('People who can sign in to this Nocline instance.')"
        />

        <Form
            v-bind="UserController.store.form()"
            class="form-panel"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <h2 class="font-display text-lg font-semibold">
                {{ $t('Invite someone') }}
            </h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="field-stack">
                    <Label for="user-name" class="field-label">{{
                        $t('Name')
                    }}</Label>
                    <Input
                        id="user-name"
                        name="name"
                        :placeholder="$t('Name')"
                        required
                        autocomplete="name"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="field-stack">
                    <Label for="user-email" class="field-label">{{
                        $t('Email')
                    }}</Label>
                    <Input
                        id="user-email"
                        name="email"
                        type="email"
                        :placeholder="$t('Email')"
                        required
                        autocomplete="email"
                    />
                    <InputError :message="errors.email" />
                </div>
                <div class="field-stack">
                    <Label for="user-password" class="field-label">{{
                        $t('Password')
                    }}</Label>
                    <Input
                        id="user-password"
                        name="password"
                        type="password"
                        :placeholder="$t('Password (12+)')"
                        required
                        autocomplete="new-password"
                    />
                    <p class="field-hint">
                        {{ $t('At least 12 characters.') }}
                    </p>
                    <InputError :message="errors.password" />
                </div>
                <div class="field-stack">
                    <Label for="user-role" class="field-label">{{
                        $t('Role')
                    }}</Label>
                    <select id="user-role" name="role" :class="select">
                        <option v-for="r in roles" :key="r" :value="r">
                            {{ r }}
                        </option>
                    </select>
                </div>
            </div>
            <Button type="submit" class="w-fit" :disabled="processing">
                {{ $t('Create') }}
            </Button>
        </Form>

        <EmptyState
            v-if="!users.length"
            :icon="Users"
            :title="$t('No users yet.')"
        />

        <div v-else class="overflow-x-auto surface">
            <table class="table-clean">
                <caption class="sr-only">
                    {{
                        $t('Users')
                    }}
                </caption>
                <thead>
                    <tr>
                        <th scope="col">{{ $t('Name') }}</th>
                        <th scope="col">{{ $t('Email') }}</th>
                        <th scope="col">{{ $t('Role') }}</th>
                        <th scope="col" class="text-right">
                            <span class="sr-only">{{ $t('Actions') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id">
                        <td class="font-medium">{{ u.name }}</td>
                        <td class="text-muted-foreground">{{ u.email }}</td>
                        <td>
                            <Form
                                v-if="u.id !== meId"
                                v-bind="UserController.update.form(u.id)"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <select
                                    name="role"
                                    :class="select"
                                    :value="u.role"
                                    :aria-label="$t('Role')"
                                >
                                    <option
                                        v-for="r in roles"
                                        :key="r"
                                        :value="r"
                                    >
                                        {{ r }}
                                    </option>
                                </select>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                >
                                    {{ $t('Save') }}
                                </Button>
                            </Form>
                            <span v-else class="chip chip-primary">
                                {{ u.role }} · {{ $t('you') }}
                            </span>
                        </td>
                        <td class="text-right">
                            <Form
                                v-if="u.id !== meId"
                                v-bind="UserController.destroy.form(u.id)"
                            >
                                <Button type="submit" variant="ghost" size="sm">
                                    {{ $t('Delete') }}
                                </Button>
                            </Form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PageContainer>
</template>
