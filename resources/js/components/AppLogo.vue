<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppWordmark from '@/components/AppWordmark.vue';
</script>

<template>
    <div
        class="grid size-8 shrink-0 place-items-center rounded-md bg-primary text-primary-foreground"
    >
        <AppLogoIcon class="size-[1.125rem]" />
    </div>
    <div
        class="ml-2 grid min-w-0 flex-1 text-left leading-none group-data-[collapsible=icon]:hidden"
    >
        <AppWordmark class="h-[1rem] max-w-[5.5rem]" />
    </div>
</template>