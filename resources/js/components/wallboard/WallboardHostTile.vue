<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
    host: {
        id: number;
        name: string;
        online: boolean;
        cpu_avg_5m: number | null;
        mem_pct: number | null;
        disk_pct: number | null;
    };
}>();

const stressed = computed(
    () =>
        props.host.online &&
        ((props.host.cpu_avg_5m ?? 0) >= 90 ||
            (props.host.mem_pct ?? 0) >= 92 ||
            (props.host.disk_pct ?? 0) >= 92),
);

const tone = computed(() => {
    if (!props.host.online) {
        return 'down' as const;
    }
    if (stressed.value) {
        return 'warn' as const;
    }

    return 'up' as const;
});

const hint = computed(() => {
    if (!props.host.online) {
        return t('Offline');
    }
    if (!stressed.value) {
        return props.host.name;
    }
    const parts: string[] = [];
    if ((props.host.cpu_avg_5m ?? 0) >= 90) {
        parts.push(`CPU ${props.host.cpu_avg_5m}%`);
    }
    if ((props.host.mem_pct ?? 0) >= 92) {
        parts.push(`RAM ${props.host.mem_pct}%`);
    }
    if ((props.host.disk_pct ?? 0) >= 92) {
        parts.push(`DSK ${props.host.disk_pct}%`);
    }

    return parts.join(' · ');
});
</script>

<template>
    <li
        class="wb-tile wb-tile--host"
        :class="`wb-tile--${tone}`"
        :title="hint"
    >
        <span class="wb-dot" aria-hidden="true" />
        <span class="wb-tile-name">{{ host.name }}</span>
        <span v-if="stressed" class="wb-tile-meta numeric">{{ hint }}</span>
    </li>
</template>