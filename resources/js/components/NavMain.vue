<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

export type NavSection = {
    title?: string;
    items: NavItem[];
};

defineProps<{
    sections: NavSection[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup
        v-for="(section, index) in sections"
        :key="section.title ?? index"
        class="px-2"
        :class="index === 0 ? 'py-0' : 'pt-1'"
    >
        <SidebarGroupLabel v-if="section.title">
            {{ section.title }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem
                v-for="item in section.items"
                :key="item.title"
            >
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
