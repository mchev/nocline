<script setup lang="ts">
import { computed } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';

const props = defineProps<{
    class?: string;
}>();

const className = computed(() => props.class);
</script>

<template>
    <SidebarInset :class="className">
        <slot />
    </SidebarInset>
</template>
