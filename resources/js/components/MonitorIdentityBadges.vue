<script setup lang="ts">
import CheckFavicon from '@/components/CheckFavicon.vue';
import CheckFrameworkBadge from '@/components/CheckFrameworkBadge.vue';
import CheckSslBadge from '@/components/CheckSslBadge.vue';

withDefaults(
    defineProps<{
        name: string;
        faviconUrl?: string | null;
        sslStatus?: 'ok' | 'warning' | 'expired' | 'error' | 'unknown' | null;
        sslDaysRemaining?: number | null;
        siteFramework?: string | null;
        wordpressVersion?: string | null;
        faviconSizeClass?: string;
        showFavicon?: boolean;
        showSsl?: boolean;
        showFramework?: boolean;
    }>(),
    {
        faviconUrl: null,
        sslStatus: null,
        sslDaysRemaining: null,
        siteFramework: null,
        wordpressVersion: null,
        faviconSizeClass: 'size-4',
        showFavicon: true,
        showSsl: true,
        showFramework: true,
    },
);
</script>

<template>
    <span class="flex shrink-0 items-center gap-1" :aria-hidden="true">
        <CheckFavicon
            v-if="showFavicon"
            :url="faviconUrl"
            :label="name"
            :size-class="faviconSizeClass"
        />
        <CheckSslBadge
            v-if="showSsl"
            :status="sslStatus"
            :days-remaining="sslDaysRemaining"
        />
        <CheckFrameworkBadge
            v-if="showFramework"
            :framework="siteFramework"
            :version="
                siteFramework === 'wordpress' ? wordpressVersion : undefined
            "
        />
    </span>
</template>
