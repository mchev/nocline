<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Copy, Tv } from '@lucide/vue';
import { computed } from 'vue';
import WallboardController from '@/actions/App/Modules/Wallboard/Http/Controllers/WallboardController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

defineProps<{
    wallboards: { id: number; name: string; group: string | null }[];
    groups: GroupOption[];
}>();

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const url = computed(() => page.props.wallboardUrl as string | null);
const select =
    'h-10 rounded-md border bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function copyUrl() {
    if (url.value) {
        void navigator.clipboard?.writeText(url.value);
    }
}

function regenerate(w: { id: number; name: string }) {
    if (
        !confirm(
            t(
                'Generate a new URL for ":name"? The old link stops working immediately.',
                {
                    name: w.name,
                },
            ),
        )
    ) {
        return;
    }

    router.post(
        WallboardController.regenerate.url(w.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="$t('Wallboards')" />

    <PageContainer size="narrow">
        <PageHeader
            :title="$t('Wallboards')"
            :description="
                $t(
                    'Wallboard: a full-screen display for a TV, showing what needs attention.',
                )
            "
        />

        <div
            v-if="url"
            class="rounded-md border border-warning/40 bg-warning/15 p-5 text-sm"
            role="status"
        >
            <p class="font-medium">
                {{ $t('Screen URL (shown once, keep it safe)') }}
            </p>
            <div class="mt-2 flex items-start gap-2">
                <code
                    class="block min-w-0 flex-1 rounded-md bg-background/60 p-3 break-all select-all"
                    >{{ url }}</code
                >
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    :title="$t('Copy')"
                    :aria-label="$t('Copy')"
                    @click="copyUrl"
                >
                    <Copy class="size-4" />
                </Button>
            </div>
        </div>

        <Form
            v-if="isAdmin"
            v-bind="WallboardController.store.form()"
            class="form-panel sm:grid sm:grid-cols-[1fr_auto_auto] sm:items-end sm:gap-4"
            reset-on-success
            v-slot="{ processing }"
        >
            <div class="field-stack">
                <Label for="wb-name" class="field-label">{{
                    $t('Name')
                }}</Label>
                <Input
                    id="wb-name"
                    name="name"
                    :placeholder="$t('Name (e.g. Office NOC)')"
                    required
                />
            </div>
            <div class="field-stack">
                <Label for="wb-group" class="field-label">{{
                    $t('Group')
                }}</Label>
                <select id="wb-group" name="group_id" :class="select">
                    <option value="">{{ $t('All groups') }}</option>
                    <option v-for="g in groups" :key="g.id" :value="g.id">
                        {{ g.path }}
                    </option>
                </select>
            </div>
            <Button type="submit" :disabled="processing">{{
                $t('Create')
            }}</Button>
        </Form>

        <EmptyState
            v-if="!wallboards.length"
            :icon="Tv"
            :title="$t('No wallboards.')"
        />
        <ul v-else class="divide-y overflow-hidden surface">
            <li
                v-for="w in wallboards"
                :key="w.id"
                class="flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <span class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-md bg-primary/10 text-primary"
                    >
                        <Tv class="size-5" aria-hidden="true" />
                    </span>
                    <span>
                        <span class="block font-medium">{{ w.name }}</span>
                        <span class="text-sm text-muted-foreground">
                            {{ w.group ?? $t('all groups') }}
                        </span>
                    </span>
                </span>
                <div v-if="isAdmin" class="flex flex-wrap items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="regenerate(w)"
                    >
                        {{ $t('New URL') }}
                    </Button>
                    <Form v-bind="WallboardController.destroy.form(w.id)">
                        <Button type="submit" variant="ghost" size="sm">
                            {{ $t('Revoke') }}
                        </Button>
                    </Form>
                </div>
            </li>
        </ul>
    </PageContainer>
</template>
