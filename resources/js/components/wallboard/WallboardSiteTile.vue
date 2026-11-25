<script setup lang="ts">
import { computed } from 'vue';
import type { WallboardSite } from '@/types/wallboard';
import { t } from '@/lib/i18n';
import { wallboardSiteLabel } from '@/lib/wallboardLayout';

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

const title = computed(() => {
    if (props.site.in_maintenance) {
        return t('in maintenance');
    }
    if (props.site.status === 'down') {
        return t('Down');
    }

    return props.site.name;
});

const label = computed(() => wallboardSiteLabel(props.site));
</script>

<template>
    <li
        class="wb-tile wb-tile--site"
        :class="`wb-tile--${tone}`"
        :title="site.name !== label ? `${site.name} — ${site.target ?? ''}` : site.name"
    >
        <span class="wb-dot" aria-hidden="true" />
        <span class="wb-tile-name">{{ label }}</span>
        <span
            v-if="tone === 'down'"
            class="wb-tile-sub numeric"
        >
            {{
                site.latency_ms !== null
                    ? `${site.latency_ms} ms`
                    : $t('Down')
            }}
        </span>
        <span class="sr-only">{{ title }}</span>
    </li>
</template>