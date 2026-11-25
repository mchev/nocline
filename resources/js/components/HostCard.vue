<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AgentUpdateDialog from '@/components/AgentUpdateDialog.vue';
import HostMetaChips from '@/components/HostMetaChips.vue';
import Sparkline from '@/components/ui-kit/Sparkline.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { hostDiskCaption } from '@/lib/hostDiskCaption';
import { hostMetricTone } from '@/lib/hostMetricTone';
import { t } from '@/lib/i18n';

export type HostMetrics = {
    cpu: (number | null)[];
    mem: (number | null)[];
    disk: (number | null)[];
    cpu_now: number | null;
    mem_now: number | null;
    disk_now: number | null;
    disk_used_bytes?: number | null;
    disk_total_bytes?: number | null;
};

const props = withDefaults(
    defineProps<{
        name: string;
        online: boolean;
        enrolled?: boolean;
        metrics: HostMetrics;
        agentVersion?: string | null;
        agentOutdated?: boolean;
        lastSeenAt?: string | null;
        compact?: boolean;
        detailHref?: string | null;
        accentColor?: string | null;
        publicIpv4?: string | null;
        providerLabel?: string | null;
        hostId?: number | null;
        /** When true, the “update available” badge opens a modal with the curl command. */
        updateCommandEnabled?: boolean;
    }>(),
    {
        compact: false,
        detailHref: null,
        accentColor: null,
        publicIpv4: null,
        providerLabel: null,
        hostId: null,
        updateCommandEnabled: false,
    },
);

const updateDialogOpen = ref(false);

function onUpdateBadgeClick() {
    if (props.updateCommandEnabled && props.hostId !== null) {
        updateDialogOpen.value = true;
    }
}

const status = computed(() => {
    if (props.enrolled === false) {
        return 'unknown' as const;
    }

    return props.online ? ('up' as const) : ('down' as const);
});

const statusLabel = computed(() => {
    if (props.enrolled === false) {
        return t('Not enrolled');
    }

    return props.online ? t('Online') : t('Offline');
});

const pct = (v: number | null) => (v === null ? '—' : `${Math.round(v)}%`);

const showMetrics = computed(
    () => props.enrolled !== false && (props.online || !props.compact),
);

const lastSeenLabel = computed(() => {
    if (!props.lastSeenAt) {
        return t('No signal from the agent yet');
    }

    const sec = Math.max(
        0,
        Math.round((Date.now() - new Date(props.lastSeenAt).getTime()) / 1000),
    );

    if (sec < 60) {
        return t(':n s ago', { n: sec });
    }

    if (sec < 3600) {
        return t(':n min ago', { n: Math.floor(sec / 60) });
    }

    return t(':n h ago', { n: Math.floor(sec / 3600) });
});

const sparkRows = computed(() => [
    {
        key: 'cpu',
        label: t('CPU'),
        now: props.metrics.cpu_now,
        series: props.metrics.cpu,
        display: pct(props.metrics.cpu_now),
    },
    {
        key: 'mem',
        label: t('RAM'),
        now: props.metrics.mem_now,
        series: props.metrics.mem,
        display: pct(props.metrics.mem_now),
    },
]);

const diskCaption = computed(() => hostDiskCaption(props.metrics));

const diskTone = computed(() =>
    hostMetricTone(props.metrics.disk_now, false),
);
</script>

<template>
    <div class="contents">
        <article
            class="host-card flex flex-col surface"
            :class="{
                'gap-3 p-4': !compact,
                'gap-2 p-3': compact,
                'border-danger/40': enrolled !== false && !online,
                'opacity-70': enrolled === false,
            }"
            :style="
                accentColor
                    ? { borderLeftWidth: '4px', borderLeftColor: accentColor }
                    : undefined
            "
        >
            <header class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3
                        class="flex items-center gap-2 truncate font-display font-semibold"
                        :class="compact ? 'text-sm' : 'text-base'"
                    >
                        <StatusDot
                            :status="status"
                            :pulse="enrolled !== false && !online"
                        />
                        <Link
                            v-if="detailHref"
                            :href="detailHref"
                            class="truncate hover:text-primary hover:underline"
                        >
                            {{ name }}
                        </Link>
                        <span v-else class="truncate">{{ name }}</span>
                    </h3>
                    <HostMetaChips
                        v-if="!compact && (publicIpv4 || providerLabel)"
                        class="mt-0.5"
                        compact
                        :public-ipv4="publicIpv4"
                        :provider-label="providerLabel"
                    />
                    <p
                        v-if="agentVersion && !compact"
                        class="mt-0.5 text-xs text-muted-foreground"
                    >
                        agent {{ agentVersion }}
                        <button
                            v-if="
                                agentOutdated && updateCommandEnabled && hostId
                            "
                            type="button"
                            class="ml-1 chip cursor-pointer chip-warning underline-offset-2 hover:underline"
                            :aria-label="
                                t('Get command to update the agent on :name', {
                                    name,
                                })
                            "
                            @click.stop="onUpdateBadgeClick"
                        >
                            {{ t('update available') }}
                        </button>
                        <span
                            v-else-if="agentOutdated"
                            class="ml-1 chip chip-warning"
                            >{{ t('update available') }}</span
                        >
                    </p>
                </div>
                <span
                    class="chip shrink-0"
                    :class="{
                        'chip-success': enrolled !== false && online,
                        'chip-danger': enrolled !== false && !online,
                    }"
                >
                    {{ statusLabel }}
                </span>
            </header>

            <HostMetaChips
                v-if="compact && (publicIpv4 || providerLabel)"
                compact
                class="px-0.5"
                :public-ipv4="publicIpv4"
                :provider-label="providerLabel"
            />

            <div
                v-if="!showMetrics"
                class="flex flex-col gap-2 rounded-md border border-dashed border-border/80 bg-muted/20 px-3 py-2.5 text-sm"
            >
                <p class="text-pretty text-muted-foreground">
                    {{ lastSeenLabel }}
                </p>
                <Link
                    v-if="detailHref"
                    :href="detailHref"
                    class="w-fit text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    {{ t('Open server') }}
                </Link>
            </div>

            <ul v-else class="grid gap-2" role="list">
                <li
                    v-for="row in sparkRows"
                    :key="row.key"
                    class="grid grid-cols-[3.5rem_1fr_2.5rem] items-center gap-2"
                >
                    <span class="text-xs font-medium text-muted-foreground">{{
                        row.label
                    }}</span>
                    <Sparkline
                        subtle
                        :values="row.series"
                        :tone="hostMetricTone(row.now, false)"
                        :label="`${row.label} ${row.display}`"
                    />
                    <span
                        class="text-right numeric text-sm font-semibold tabular-nums"
                        :class="{
                            'text-danger': (row.now ?? 0) >= 90,
                            'text-warning':
                                (row.now ?? 0) >= 75 && (row.now ?? 0) < 90,
                        }"
                    >
                        {{ row.display }}
                    </span>
                </li>
                <li
                    class="grid grid-cols-[3.5rem_1fr] items-center gap-2 border-t border-border/70 pt-2"
                >
                    <span class="text-xs font-medium text-muted-foreground">{{
                        t('Disk')
                    }}</span>
                    <span
                        class="truncate text-right numeric text-sm font-semibold tabular-nums"
                        :class="{
                            'text-danger': diskTone === 'danger',
                            'text-warning': diskTone === 'warning',
                        }"
                    >
                        {{ diskCaption }}
                    </span>
                </li>
            </ul>

            <footer
                v-if="$slots.actions"
                class="mt-auto -mb-1 flex flex-wrap items-center gap-1 border-t pt-2"
            >
                <slot name="actions" />
            </footer>
        </article>

        <AgentUpdateDialog
            v-if="hostId && updateCommandEnabled"
            :open="updateDialogOpen"
            :host-id="hostId"
            :host-name="name"
            :agent-version="agentVersion ?? null"
            @update:open="updateDialogOpen = $event"
        />
    </div>
</template>

<style scoped>
.host-card {
    container: host-card / inline-size;
}
</style>
