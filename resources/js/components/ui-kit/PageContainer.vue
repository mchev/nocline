<script setup lang="ts">
withDefaults(defineProps<{ size?: 'narrow' | 'default' | 'wide' | 'full' }>(), {
    size: 'default',
});
</script>

<template>
    <div
        class="mx-auto flex w-full flex-col gap-5 p-4 md:gap-6 md:px-6 md:py-5"
        :class="{
            'max-w-xl': size === 'narrow',
            'max-w-6xl': size === 'default',
            'max-w-[90rem]': size === 'wide',
        }"
    >
        <slot />
    </div>
</template>
