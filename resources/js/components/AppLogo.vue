<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="grid size-8 shrink-0 place-items-center rounded-md bg-primary text-primary-foreground"
    >
        <AppLogoIcon class="size-[1.125rem]" />
    </div>
    <div class="ml-2 grid min-w-0 flex-1 text-left leading-none group-data-[collapsible=icon]:hidden">
        <span class="font-display text-[1.05rem] font-semibold tracking-tight lowercase text-foreground">
            phare<span class="text-primary">.</span>
        </span>
    </div>
</template>