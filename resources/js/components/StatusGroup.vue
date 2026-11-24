<script setup lang="ts">
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { DEFAULT_GROUP_COLOR } from '@/lib/groups';
import { t } from '@/lib/i18n';
import type { StatusMonitor, StatusNode } from '@/types/status';

/** One public status section: its monitors, then its sub-groups (recursively). */
withDefaults(
    defineProps<{
        node?: StatusNode;
        monitors?: StatusMonitor[];
        uptimeDays: number;
    }>(),
    { node: undefined, monitors: undefined },
);

const statusText = (s: StatusMonitor['status']) =>
    ({
        operational: t('Operational'),
        down: t('Down'),
        maintenance: t('Maintenance'),
        unknown: t('Unknown'),
    })[s];

const statusTone = (s: StatusMonitor['status']) =>
    ({
        operational: 'up' as const,
        down: 'down' as const,
        maintenance: 'maintenance' as const,
        unknown: 'unknown' as const,
    })[s];

const chipTone = (s: StatusMonitor['status']) =>
    ({
        operational: 'chip-success',
        down: 'chip-danger',
        maintenance: 'chip-info',
        unknown: '',
    })[s];
</script>

<template>
    <section class="flex flex-col gap-3">
        <h2
            v-if="node"
            class="flex items-center gap-2 font-display text-lg font-semibold"
        >
            <span
                class="size-3 rounded-full"
                :style="{ backgroundColor: node.color ?? DEFAULT_GROUP_COLOR }"
                aria-hidden="true"
            />
            {{ node.name }}
        </h2>
        <ul
            v-if="(node?.monitors ?? monitors ?? []).length"
            class="divide-y overflow-hidden surface"
        >
            <li
                v-for="(m, i) in node?.monitors ?? monitors"
                :key="i"
                class="flex items-center justify-between gap-3 px-4 py-3.5"
            >
                <span class="flex min-w-0 items-center gap-2.5">
                    <StatusDot
                        :status="statusTone(m.status)"
                        :pulse="m.status === 'down'"
                        :label="statusText(m.status)"
                    />
                    <span class="truncate font-medium">{{ m.name }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-3 text-sm">
                    <span
                        v-if="m.uptime !== null"
                        class="numeric text-muted-foreground"
                    >
                        {{
                            t(':percent% over :days days', {
                                percent: m.uptime,
                                days: uptimeDays,
                            })
                        }}
                    </span>
                    <span class="chip" :class="chipTone(m.status)">
                        {{ statusText(m.status) }}
                    </span>
                </span>
            </li>
        </ul>
        <div
            v-if="node?.groups.length"
            class="ml-2 flex flex-col gap-4 border-l border-border/70 pl-4"
        >
            <StatusGroup
                v-for="(g, i) in node.groups"
                :key="i"
                :node="g"
                :uptime-days="uptimeDays"
            />
        </div>
    </section>
</template>
