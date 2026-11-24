<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import UpdateBanner from '@/components/UpdateBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="aurora h-dvh min-h-0 min-w-0 overflow-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <UpdateBanner />
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
