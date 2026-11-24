<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
};

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="rounded-sm font-medium text-primary underline decoration-primary/30 underline-offset-4 transition-colors hover:decoration-current focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
    >
        <slot />
    </Link>
</template>
