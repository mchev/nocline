<script setup lang="ts">
import { Lock, LockOpen, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';
import { t } from '@/lib/i18n';

export type SslStatus = 'ok' | 'warning' | 'expired' | 'error' | 'unknown';

const props = defineProps<{
    status?: SslStatus | null;
    daysRemaining?: number | null;
}>();

const title = computed(() => {
    switch (props.status) {
        case 'ok':
            return props.daysRemaining != null
                ? t('SSL valid — :days days left', {
                      days: props.daysRemaining,
                  })
                : t('SSL valid');
        case 'warning':
            return props.daysRemaining != null
                ? t('SSL expires soon — :days days left', {
                      days: props.daysRemaining,
                  })
                : t('SSL expires soon');
        case 'expired':
            return t('SSL certificate expired');
        case 'error':
            return t('Could not read SSL certificate');
        case 'unknown':
            return t('SSL not checked yet');
        default:
            return '';
    }
});

const tone = computed(() => {
    switch (props.status) {
        case 'ok':
            return 'text-success';
        case 'warning':
            return 'text-warning';
        case 'expired':
        case 'error':
            return 'text-danger';
        default:
            return 'text-muted-foreground';
    }
});
</script>

<template>
    <span
        v-if="status"
        class="inline-flex shrink-0"
        :title="title"
        :aria-label="title"
    >
        <Lock
            v-if="status === 'ok'"
            class="size-4"
            :class="tone"
            aria-hidden="true"
        />
        <ShieldAlert
            v-else-if="status === 'warning'"
            class="size-4"
            :class="tone"
            aria-hidden="true"
        />
        <LockOpen v-else class="size-4" :class="tone" aria-hidden="true" />
    </span>
</template>
