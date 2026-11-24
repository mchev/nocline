<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ScrollText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { audit, index } from '@/routes/coffre';

defineProps<{
    logs: {
        data: {
            id: number;
            entry: string;
            user: string | null;
            action: string;
            at: string;
            ip: string | null;
        }[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Vault', href: index() },
            { title: 'Vault audit', href: audit() },
        ],
    },
});

const actionChip = (action: string) => {
    if (action.includes('reveal') || action.includes('copy')) {
        return 'chip-warning';
    }

    if (action.includes('create') || action.includes('store')) {
        return 'chip-success';
    }

    if (action.includes('delete') || action.includes('destroy')) {
        return 'chip-danger';
    }

    return 'chip-info';
};
</script>

<template>
    <Head :title="$t('Vault audit')" />

    <PageContainer>
        <PageHeader
            :title="$t('Vault audit')"
            :description="$t('Who revealed or changed vault secrets, and when.')"
        >
            <Button as-child variant="outline">
                <Link :href="index()">{{ $t('Vault') }}</Link>
            </Button>
        </PageHeader>

        <EmptyState
            v-if="!logs.data.length"
            :icon="ScrollText"
            :title="$t('Nothing recorded yet')"
            :description="$t('Reveal and edit actions will appear here.')"
        />

        <div
            v-else
            class="surface overflow-hidden reveal"
            tabindex="0"
            role="region"
            :aria-label="$t('Vault audit')"
        >
            <table class="table-clean">
                <thead>
                    <tr>
                        <th scope="col">{{ $t('When') }}</th>
                        <th scope="col">{{ $t('Who') }}</th>
                        <th scope="col">{{ $t('What') }}</th>
                        <th scope="col">{{ $t('Entry') }}</th>
                        <th scope="col" class="hidden sm:table-cell">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in logs.data" :key="l.id">
                        <td>
                            <time class="text-muted-foreground" :datetime="l.at">
                                {{ new Date(l.at).toLocaleString() }}
                            </time>
                        </td>
                        <td class="font-medium">{{ l.user ?? '—' }}</td>
                        <td>
                            <span class="chip" :class="actionChip(l.action)">
                                {{ l.action }}
                            </span>
                        </td>
                        <td>{{ l.entry }}</td>
                        <td class="hidden text-muted-foreground sm:table-cell">
                            {{ l.ip ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PageContainer>
</template>
