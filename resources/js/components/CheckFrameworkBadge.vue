<script setup lang="ts">
import { computed } from 'vue';
import { frameworkIconUrl } from '@/lib/frameworkIcons';
import { t } from '@/lib/i18n';

const props = defineProps<{
    framework?: string | null;
    version?: string | null;
}>();

const iconUrl = computed(() => frameworkIconUrl(props.framework));

const title = computed(() => {
    switch (props.framework) {
        case 'laravel':
            return t('Laravel site');
        case 'wordpress':
            return props.version
                ? t('WordPress :version', { version: props.version })
                : t('WordPress site');
        case 'drupal':
            return t('Drupal site');
        default:
            return props.framework
                ? t(':stack site', { stack: props.framework })
                : '';
    }
});
</script>

<template>
    <img
        v-if="iconUrl"
        :src="iconUrl"
        :alt="title"
        :title="title"
        width="16"
        height="16"
        class="size-4 shrink-0 rounded-sm object-contain dark:brightness-110"
        loading="lazy"
        decoding="async"
    />
</template>
