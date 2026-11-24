<script setup lang="ts">
import { computed } from 'vue';
import MonitorIdentityBadges from '@/components/MonitorIdentityBadges.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import UptimeHistoryDots from '@/components/ui-kit/UptimeHistoryDots.vue';
import { monitorUptimeHistoryProps } from '@/lib/monitorHistory';
import type { UptimeHistoryDot } from '@/types/checks';

export type WallboardSite = {
    id: number;
    name: string;
    status: string;
    latency_ms: number | null;
    in_maintenance: boolean;
    enabled: boolean;
    history?: UptimeHistoryDot[] | null;
    timeline?:
        | {
              s: UptimeHistoryDot;
              at: string;
              error?: string | null;
          }[]
        | null;
    uptime_24h_pct?: number | null;
    favicon_url?: string | null;
    ssl_status?: 'ok' | 'warning' | 'expired' | 'error' | 'unknown' | null;
    ssl_days_remaining?: number | null;
    site_framework?: string | null;
};

const props = defineProps<{
    site: WallboardSite;
}>();

const dotStatus = computed(() => {
    if (!props.site.enabled || props.site.in_maintenance) {
        return 'unknown' as const;
    }

    if (props.site.status === 'down') {
        return 'down' as const;
    }

    if (props.site.status === 'up') {
        return 'up' as const;
    }

    return 'unknown' as const;
});

const rowClass = computed(() => {
    if (!props.site.enabled || props.site.in_maintenance) {
        return 'opacity-60';
    }

    if (props.site.status === 'down') {
        return 'border-danger/50 bg-danger/10';
    }

    if (props.site.status === 'unknown') {
        return 'border-warning/40';
    }

    return '';
});

const latencyClass = computed(() => {
    const ms = props.site.latency_ms;
    if (ms === null) {
        return 'text-muted-foreground';
    }

    if (ms >= 2000) {
        return 'text-danger';
    }

    if (ms >= 800) {
        return 'text-warning';
    }

    return 'text-success';
});
</script>

<template>
    <li
        class="surface flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:grid sm:grid-cols-[auto_minmax(0,1fr)_minmax(0,11rem)_4.5rem] sm:items-center sm:gap-3 sm:px-5 sm:py-3.5"
        :class="rowClass"
    >
        <StatusDot
            class="shrink-0 scale-125"
            :status="dotStatus"
            :pulse="site.enabled && site.status === 'down'"
        />
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <MonitorIdentityBadges
                class="shrink-0 scale-110"
                :name="site.name"
                :favicon-url="site.favicon_url"
                :ssl-status="site.ssl_status"
                :ssl-days-remaining="site.ssl_days_remaining"
                :site-framework="site.site_framework"
            />
            <span
                class="min-w-0 truncate text-xl font-semibold lg:text-2xl"
                :title="site.name"
            >
                {{ site.name }}
            </span>
        </div>
        <UptimeHistoryDots
            v-bind="monitorUptimeHistoryProps(site, 'wallboard')"
            class="min-w-0 sm:max-w-none"
        />
        <p
            class="numeric w-full text-right text-lg font-semibold tabular-nums sm:w-auto sm:text-xl"
            :class="latencyClass"
        >
            <template v-if="site.latency_ms !== null">
                {{ site.latency_ms }}<span class="ml-0.5 text-base">ms</span>
            </template>
            <template v-else>—</template>
        </p>
    </li>
</template>