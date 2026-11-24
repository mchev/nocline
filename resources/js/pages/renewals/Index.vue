<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarClock, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatTile from '@/components/ui-kit/StatTile.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { t } from '@/lib/i18n';
import { refresh } from '@/routes/renewals';

type Renewal = {
    id: number;
    kind: string;
    label: string;
    site: string | null;
    expires_on: string;
    days_remaining: number;
    group: string | null;
    source: string;
    last_checked_at: string | null;
};

const props = defineProps<{
    renewals: Renewal[];
    can_refresh: boolean;
}>();

const refreshing = ref(false);

const groups = computed(() => [
    {
        key: 'expired',
        title: t('Expired'),
        status: 'down' as const,
        chip: 'chip-danger',
        items: props.renewals.filter((r) => r.days_remaining < 0),
    },
    {
        key: 'soon',
        title: t('Within 30 days'),
        status: 'warning' as const,
        chip: 'chip-warning',
        items: props.renewals.filter(
            (r) => r.days_remaining >= 0 && r.days_remaining <= 30,
        ),
    },
    {
        key: 'later',
        title: t('Later'),
        status: 'up' as const,
        chip: 'chip',
        items: props.renewals.filter((r) => r.days_remaining > 30),
    },
]);

const urgentCount = computed(
    () => props.renewals.filter((r) => r.days_remaining <= 30).length,
);

const expiredCount = computed(
    () => props.renewals.filter((r) => r.days_remaining < 0).length,
);

const summary = computed(() => {
    if (urgentCount.value === 0) {
        return t('Nothing expires in the next 30 days.');
    }

    return urgentCount.value === 1
        ? t('1 thing expires in the next 30 days.')
        : t(':count things expire in the next 30 days.', {
              count: urgentCount.value,
          });
});

const itemName = (r: Renewal) =>
    r.kind === 'ssl'
        ? t('SSL certificate of :site', { site: r.site ?? r.label })
        : r.kind === 'domain'
          ? t('Domain :name', { name: r.label })
          : r.label;

const remaining = (d: number) =>
    d < 0
        ? t('expired :days days ago', { days: -d })
        : d === 0
          ? t('today')
          : t('in :days days', { days: d });

function refreshNow(): void {
    router.post(
        refresh().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (refreshing.value = true),
            onFinish: () => (refreshing.value = false),
        },
    );
}
</script>

<template>
    <Head :title="$t('Renewals')" />

    <PageContainer>
        <PageHeader :title="$t('Renewals')" :description="summary">
            <Button
                v-if="can_refresh"
                variant="outline"
                :disabled="refreshing"
                @click="refreshNow"
            >
                <RefreshCw
                    class="size-4"
                    :class="{ 'motion-safe:animate-spin': refreshing }"
                    aria-hidden="true"
                />
                {{ $t('Refresh now') }}
            </Button>
        </PageHeader>

        <div v-if="renewals.length" class="reveal grid gap-4 sm:grid-cols-2">
            <StatTile
                :label="$t('Due within 30 days')"
                :value="urgentCount"
                :tone="urgentCount > 0 ? 'warning' : 'success'"
                :hint="summary"
            />
            <StatTile
                :label="$t('Expired')"
                :value="expiredCount"
                :tone="expiredCount > 0 ? 'danger' : 'default'"
            />
        </div>

        <EmptyState
            v-if="!renewals.length"
            :icon="CalendarClock"
            :title="$t('Nothing to show yet.')"
            :description="
                $t(
                    'Add a website to monitor and its domain and SSL expiry appear here automatically.',
                )
            "
        />

        <template v-for="(g, gi) in groups" :key="g.key">
            <section
                v-if="g.items.length"
                class="reveal"
                :class="gi < 3 ? `reveal-delay-${gi + 1}` : ''"
                :aria-labelledby="`renewal-${g.key}`"
            >
                <h2
                    :id="`renewal-${g.key}`"
                    class="mb-3 text-sm font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ g.title }}
                    <span class="ml-1 numeric text-foreground">{{
                        g.items.length
                    }}</span>
                </h2>
                <ul class="divide-y overflow-hidden surface">
                    <li
                        v-for="r in g.items"
                        :key="r.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-muted/50"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <StatusDot
                                class="mt-1.5"
                                :status="
                                    r.days_remaining < 0
                                        ? 'down'
                                        : r.days_remaining <= 7
                                          ? 'warning'
                                          : 'up'
                                "
                                :label="remaining(r.days_remaining)"
                            />
                            <div class="min-w-0">
                                <p class="font-medium">{{ itemName(r) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="r.group">{{ r.group }} · </span>
                                    {{
                                        $t('Expires on :date', {
                                            date: r.expires_on,
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="chip"
                            :class="
                                r.days_remaining <= 7 ? 'chip-danger' : g.chip
                            "
                        >
                            {{ remaining(r.days_remaining) }}
                        </span>
                    </li>
                </ul>
            </section>
        </template>
    </PageContainer>
</template>
