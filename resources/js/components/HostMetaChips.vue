<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
    publicIpv4?: string | null;
    hostname?: string | null;
    distro?: string | null;
    providerLabel?: string | null;
    providerRegion?: string | null;
    compact?: boolean;
}>();

const providerTitle = computed(() => {
    if (!props.providerLabel) {
        return '';
    }

    return props.providerRegion
        ? `${props.providerLabel} (${props.providerRegion})`
        : props.providerLabel;
});
</script>

<template>
    <div
        v-if="publicIpv4 || providerLabel || (!compact && (hostname || distro))"
        class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
    >
        <span v-if="publicIpv4" class="chip font-mono" :title="t('Public IP')">
            {{ publicIpv4 }}
        </span>
        <span
            v-if="providerLabel"
            class="chip chip-info"
            :title="providerTitle"
        >
            {{ providerLabel }}
        </span>
        <template v-if="!compact">
            <span v-if="hostname" class="truncate" :title="t('Hostname')">
                {{ hostname }}
            </span>
            <span v-if="distro" class="truncate" :title="t('Operating system')">
                {{ distro }}
            </span>
        </template>
    </div>
</template>
