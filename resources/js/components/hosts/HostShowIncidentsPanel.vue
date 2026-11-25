<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Siren } from '@lucide/vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import incidentsRoutes from '@/routes/incidents';
import { t } from '@/lib/i18n';

export type HostIncidentRow = {
    id: number;
    check_id: number;
    title: string;
    check: string | null;
    started_at: string;
    resolved_at: string | null;
    acknowledged_at: string | null;
    last_error: string | null;
};

defineProps<{
    incidents: HostIncidentRow[];
}>();

const fmt = (d: string) => new Date(d).toLocaleString();
</script>

<template>
    <section class="surface p-5" aria-labelledby="host-incidents-heading">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2
                id="host-incidents-heading"
                class="flex items-center gap-2 text-sm font-semibold"
            >
                <Siren class="size-4 text-primary" aria-hidden="true" />
                {{ t('Recent incidents on this server') }}
                <span class="chip">{{ incidents.length }}</span>
            </h2>
            <Link
                :href="incidentsRoutes.index.url()"
                class="text-xs font-medium text-primary hover:underline"
            >
                {{ t('All incidents') }}
            </Link>
        </header>

        <ul v-if="incidents.length" class="divide-y divide-border" role="list">
            <li
                v-for="incident in incidents"
                :key="incident.id"
                class="flex gap-3 py-3 first:pt-0 last:pb-0"
            >
                <StatusDot
                    class="mt-0.5 shrink-0"
                    :status="
                        incident.resolved_at
                            ? 'up'
                            : incident.acknowledged_at
                              ? 'warning'
                              : 'down'
                    "
                    :pulse="!incident.resolved_at && !incident.acknowledged_at"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ incident.title }}
                    </p>
                    <p
                        v-if="incident.check"
                        class="text-xs text-muted-foreground"
                    >
                        {{ incident.check }}
                    </p>
                    <p
                        v-if="incident.last_error"
                        class="mt-1 text-xs break-words text-muted-foreground"
                    >
                        {{ incident.last_error }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <time :datetime="incident.started_at">{{
                            fmt(incident.started_at)
                        }}</time>
                        <template v-if="incident.resolved_at">
                            →
                            <time :datetime="incident.resolved_at">{{
                                fmt(incident.resolved_at)
                            }}</time>
                        </template>
                    </p>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            {{ t('No incidents for this server yet.') }}
        </p>
    </section>
</template>
