<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import UpdateBanner from '@/components/UpdateBanner.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <AppShell>
        <AppSidebar />
        <AppContent
            class="relative h-dvh min-h-0 min-w-0 overflow-hidden aurora"
        >
            <SidebarTrigger
                class="absolute top-3 left-3 z-30 size-9 bg-background/80 shadow-sm backdrop-blur-md md:hidden"
            />
            <UpdateBanner />
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
