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
        viewBox="0 0 150 150"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <image
            href="/brand/phare-symbole-brume.png"
            width="150"
            height="150"
            preserveAspectRatio="xMidYMid meet"
        />
    </svg>
</template>
