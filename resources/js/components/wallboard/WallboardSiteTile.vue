<script setup lang="ts">
import { computed } from 'vue';
import CheckFavicon from '@/components/CheckFavicon.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import UptimeHistoryDots from '@/components/ui-kit/UptimeHistoryDots.vue';
import { monitorUptimeHistoryProps } from '@/lib/monitorHistory';
import type { WallboardSite } from '@/types/wallboard';
import { t } from '@/lib/i18n';
import { wallboardSiteCaption } from '@/lib/wallboardLayout';

const props = defineProps<{
    site: WallboardSite;
}>();

const tone = computed(() => {
    if (!props.site.enabled || props.site.in_maintenance) {
        return 'maint' as const;
    }
    if (props.site.status === 'down') {
        return 'down' as const;
    }
    if (props.site.status === 'unknown') {
        return 'unknown' as const;
    }

    return 'up' as const;
});

const dotStatus = computed(() => {
    if (tone.value === 'maint') {
        return 'maintenance' as const;
    }
    if (tone.value === 'unknown') {
        return 'unknown' as const;
    }

    return tone.value;
});

const caption = computed(() => wallboardSiteCaption(props.site));

const historyProps = computed(() =>
    monitorUptimeHistoryProps(props.site, 'wallboard'),
);

const latencyLabel = computed(() => {
    if (props.site.latency_ms === null) {
        return tone.value === 'down' ? t('Down') : '—';
    }

    return `${props.site.latency_ms} ms`;
});
</script>

<template>
    <li
        class="wb-site-card"
        :class="`wb-site-card--${tone}`"
        :title="caption"
    >
        <div class="wb-site-main">
            <StatusDot
                :status="dotStatus"
                :pulse="tone === 'down'"
                class="wb-site-dot"
            />
            <UptimeHistoryDots
                v-bind="historyProps"
                class="wb-site-history"
            />
            <p
                class="wb-site-lat numeric"
                :class="{ 'wb-site-lat--bad': tone === 'down' }"
            >
                {{ latencyLabel }}
            </p>
        </div>
        <div class="wb-site-caption">
            <CheckFavicon
                :url="site.favicon_url"
                :label="caption"
                size-class="wb-site-favicon"
            />
            <p class="wb-site-caption-text">{{ caption }}</p>
        </div>
    </li>
</template>

<style scoped>
.wb-site-card {
    display: flex;
    flex-direction: column;
    gap: 0.2em;
    min-width: 0;
    min-height: clamp(2.85rem, 5.5cqh, 4rem);
    padding: 0.3em 0.5em;
    border: 1px solid color-mix(in oklch, var(--border) 85%, transparent);
    border-radius: 0.35rem;
    background: color-mix(in oklch, var(--card) 92%, transparent);
}

.wb-site-main {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: clamp(0.35rem, 1vmin, 0.65rem);
    align-items: center;
    min-width: 0;
}

.wb-site-card--up {
    border-color: color-mix(in oklch, var(--success) 35%, var(--border));
}

.wb-site-card--down {
    border-color: var(--danger);
    background: color-mix(in oklch, var(--danger) 22%, var(--card));
    animation: wb-site-pulse 1.5s ease-in-out infinite;
}

.wb-site-card--unknown,
.wb-site-card--maint {
    border-color: color-mix(in oklch, var(--warning) 45%, var(--border));
}

.wb-site-dot {
    justify-self: start;
    transform: scale(1.35);
}

.wb-site-favicon {
    width: clamp(1.1rem, 2.4vmin, 1.65rem);
    height: clamp(1.1rem, 2.4vmin, 1.65rem);
}

.wb-site-history {
    min-width: 0;
}

.wb-site-history :deep(.uptime-dots-track) {
    max-width: none;
}

.wb-site-lat {
    margin: 0;
    font-size: clamp(0.75rem, 1.8vmin, 1rem);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    text-align: right;
    white-space: nowrap;
    color: var(--muted-foreground);
}

.wb-site-lat--bad {
    color: var(--danger);
}

.wb-site-caption {
    display: flex;
    gap: 0.35em;
    align-items: center;
    min-width: 0;
    padding-top: 0.05em;
    border-top: 1px solid color-mix(in oklch, var(--border) 55%, transparent);
}

.wb-site-caption-text {
    margin: 0;
    overflow: hidden;
    font-size: clamp(0.72rem, 1.75vmin, 1.05rem);
    font-weight: 600;
    line-height: 1.1;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@keyframes wb-site-pulse {
    0%,
    100% {
        filter: brightness(1);
    }

    50% {
        filter: brightness(1.08);
    }
}
</style>