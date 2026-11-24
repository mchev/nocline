<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import IncidentController from '@/actions/App/Modules/Incidents/Http/Controllers/IncidentController';
import { Button } from '@/components/ui/button';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';

type Incident = {
    id: number;
    title: string;
    check: string;
    started_at: string;
    resolved_at: string | null;
    acknowledged_at: string | null;
    acknowledged_by: string | null;
    last_error: string | null;
};

defineProps<{ incidents: { data: Incident[] } }>();

const canManage = computed(() => usePage().props.auth.user.role !== 'viewer');
const fmt = (d: string) => new Date(d).toLocaleString();
</script>

<template>
    <Head :title="$t('Incidents')" />

    <PageContainer>
        <PageHeader
            :title="$t('Incidents')"
            :description="
                $t(
                    'Things that went wrong — acknowledge them so the team knows someone is on it.',
                )
            "
        />

        <EmptyState
            v-if="!incidents.data.length"
            :icon="ShieldCheck"
            :title="$t('No incidents.')"
            :description="$t('When a monitor goes down, it shows up here.')"
        />

        <ol v-else class="flex flex-col gap-3">
            <li
                v-for="(i, idx) in incidents.data"
                :key="i.id"
                class="reveal flex flex-wrap items-start justify-between gap-4 surface p-5"
                :class="idx < 3 ? `reveal-delay-${idx + 1}` : ''"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <StatusDot
                        class="mt-2"
                        :status="
                            i.resolved_at
                                ? 'up'
                                : i.acknowledged_at
                                  ? 'warning'
                                  : 'down'
                        "
                        :pulse="!i.resolved_at && !i.acknowledged_at"
                        :label="
                            i.resolved_at
                                ? $t('resolved')
                                : i.acknowledged_at
                                  ? $t('acknowledged')
                                  : $t('open')
                        "
                    />
                    <div class="min-w-0">
                        <h2 class="font-display text-base font-semibold">
                            {{ i.title }}
                        </h2>
                        <p
                            v-if="i.last_error"
                            class="text-sm break-words text-muted-foreground"
                        >
                            {{ i.last_error }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ $t('Started') }}
                            <time :datetime="i.started_at">{{
                                fmt(i.started_at)
                            }}</time>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="i.resolved_at" class="chip chip-success">
                        {{ $t('resolved :date', { date: fmt(i.resolved_at) }) }}
                    </span>
                    <span
                        v-else-if="i.acknowledged_at"
                        class="chip chip-warning"
                    >
                        {{ $t('ack :user', { user: i.acknowledged_by ?? '' }) }}
                    </span>
                    <span v-else class="chip chip-danger">{{
                        $t('open')
                    }}</span>
                    <Form
                        v-if="canManage && !i.resolved_at && !i.acknowledged_at"
                        v-bind="IncidentController.acknowledge.form(i.id)"
                    >
                        <Button type="submit" variant="outline" size="sm">
                            {{ $t('Acknowledge') }}
                        </Button>
                    </Form>
                </div>
            </li>
        </ol>
    </PageContainer>
</template>
