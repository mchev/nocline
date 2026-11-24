<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path class="fill-primary-foreground" d="M6 20h12l-1.5-3H7.5L6 20Z" />
        <path class="fill-primary-foreground" d="M9.5 8.5h5v10h-5V8.5Z" />
        <path class="fill-primary-foreground" d="M8.5 8.5h7l-2.5-3.5h-2l-2.5 3.5Z" />
        <path class="fill-primary-foreground" d="M2.5 10.5h4v1.6h-4V10.5ZM17.5 10.5h4v1.6h-4V10.5Z" />
        <path class="fill-primary" d="M11.35 12.2h1.3v4.2h-1.3v-4.2Z" />
    </svg>
</template>