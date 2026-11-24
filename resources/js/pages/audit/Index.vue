<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ScrollText } from '@lucide/vue';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { index } from '@/routes/audit';

const props = defineProps<{
    logs: {
        data: {
            id: number;
            sentence: string;
            at: string;
            ip: string | null;
            user_agent: string | null;
        }[];
        prev: string | null;
        next: string | null;
        total: number;
    };
    filters: {
        user_id: number | null;
        family: string | null;
        from: string | null;
        to: string | null;
    };
    users: { id: number; name: string }[];
    families: Record<string, string>;
    retention_days: number;
}>();

const form = reactive({
    user_id: props.filters.user_id ?? '',
    family: props.filters.family ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const select =
    'h-10 w-full rounded-md border bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function apply(): void {
    const query = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== ''),
    );

    router.get(index.url(), query, { preserveState: true });
}

function reset(): void {
    Object.assign(form, { user_id: '', family: '', from: '', to: '' });
    apply();
}
</script>

<template>
    <Head :title="$t('Activity log')" />

    <PageContainer size="wide">
        <PageHeader
            :title="$t('Activity log')"
            :description="
                $t('Who did what, and when. Entries are kept :days days.', {
                    days: retention_days,
                })
            "
        />

        <search class="form-panel" :aria-label="$t('Activity log')">
            <form
                class="grid items-end gap-4 md:grid-cols-5"
                @submit.prevent="apply"
            >
                <div class="field-stack">
                    <label for="audit-user" class="field-label">{{
                        $t('Who')
                    }}</label>
                    <select
                        id="audit-user"
                        v-model="form.user_id"
                        :class="select"
                    >
                        <option value="">{{ $t('Everyone') }}</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">
                            {{ u.name }}
                        </option>
                    </select>
                </div>
                <div class="field-stack">
                    <label for="audit-family" class="field-label">{{
                        $t('What')
                    }}</label>
                    <select
                        id="audit-family"
                        v-model="form.family"
                        :class="select"
                    >
                        <option value="">{{ $t('Everything') }}</option>
                        <option
                            v-for="(label, key) in families"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div class="field-stack">
                    <label for="audit-from" class="field-label">{{
                        $t('From')
                    }}</label>
                    <Input id="audit-from" v-model="form.from" type="date" />
                </div>
                <div class="field-stack">
                    <label for="audit-to" class="field-label">{{
                        $t('To')
                    }}</label>
                    <Input id="audit-to" v-model="form.to" type="date" />
                </div>
                <div class="flex gap-2">
                    <Button type="submit">{{ $t('Filter') }}</Button>
                    <Button type="button" variant="ghost" @click="reset">
                        {{ $t('Clear') }}
                    </Button>
                </div>
            </form>
        </search>

        <EmptyState
            v-if="logs.data.length === 0"
            :icon="ScrollText"
            :title="
                $t(
                    'Nothing recorded yet. Changes made by your team will show up here.',
                )
            "
        />

        <div v-else class="overflow-x-auto surface">
            <table class="table-clean">
                <caption class="sr-only">
                    {{
                        $t('Activity log')
                    }}
                </caption>
                <thead>
                    <tr>
                        <th scope="col">{{ $t('When') }}</th>
                        <th scope="col">{{ $t('What happened') }}</th>
                        <th scope="col">{{ $t('From') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in logs.data" :key="l.id">
                        <td
                            class="numeric whitespace-nowrap text-muted-foreground"
                        >
                            <time :datetime="l.at">{{
                                new Date(l.at).toLocaleString()
                            }}</time>
                        </td>
                        <td class="font-medium">{{ l.sentence }}</td>
                        <td
                            class="text-muted-foreground"
                            :title="l.user_agent ?? undefined"
                        >
                            {{ l.ip }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="logs.prev || logs.next"
            class="flex items-center justify-between text-sm"
            :aria-label="$t('Pages')"
        >
            <Link
                v-if="logs.prev"
                :href="logs.prev"
                preserve-scroll
                class="rounded-md px-3 py-2 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {{ $t('Newer') }}
            </Link>
            <span v-else />
            <Link
                v-if="logs.next"
                :href="logs.next"
                preserve-scroll
                class="rounded-md px-3 py-2 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {{ $t('Older') }}
            </Link>
        </nav>
    </PageContainer>
</template>
