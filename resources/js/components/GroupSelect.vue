<script setup lang="ts">
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

withDefaults(
    defineProps<{
        groups: GroupOption[];
        emptyLabel?: string;
        name?: string;
        id?: string;
        /** Group ids that must not be selectable (e.g. the group being edited). */
        exclude?: number[];
    }>(),
    { emptyLabel: undefined, name: undefined, id: undefined, exclude: () => [] },
);

const model = defineModel<string | number>({ default: '' });
</script>

<template>
    <select
        :id="id"
        v-model="model"
        :name="name"
        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none dark:bg-transparent"
    >
        <option value="">{{ emptyLabel ?? t('No group') }}</option>
        <option
            v-for="g in groups.filter((x) => !exclude.includes(x.id))"
            :key="g.id"
            :value="g.id"
        >
            {{ g.path }}
        </option>
    </select>
</template>
