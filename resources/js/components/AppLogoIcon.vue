<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

const { className } = defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 120 120"
        fill="none"
        :class="cn('shrink-0', className)"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path
            d="M12 12H54L74 32H32V74L12 54ZM108 108H66L46 88H88V46L108 66Z"
            fill="currentColor"
        />
        <circle cx="60" cy="60" r="7" fill="currentColor" />
    </svg>
</template>
