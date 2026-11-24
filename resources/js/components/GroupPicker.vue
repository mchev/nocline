<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

const NEW = '__new__';

const props = withDefaults(
    defineProps<{
        groups: GroupOption[];
        error?: string;
        id?: string;
    }>(),
    { error: undefined, id: 'group-picker' },
);

const groupId = defineModel<string | number>('groupId', { default: '' });
const groupName = defineModel<string>('groupName', { default: '' });

const creating = ref(false);

const selectValue = computed(() =>
    creating.value ? NEW : String(groupId.value ?? ''),
);

async function onChange(event: Event) {
    const value = (event.target as HTMLSelectElement).value;

    if (value === NEW) {
        creating.value = true;
        groupId.value = '';
        await nextTick();
        (
            document.getElementById(`${props.id}-name`) as HTMLInputElement | null
        )?.focus();

        return;
    }

    creating.value = false;
    groupName.value = '';
    groupId.value = value === '' ? '' : Number(value);
}
</script>

<template>
    <div class="field-stack">
        <label :for="id" class="field-label">{{ t('Group (optional)') }}</label>
        <select
            :id="id"
            :value="selectValue"
            class="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
            :aria-describedby="`${id}-hint`"
            @change="onChange"
        >
            <option value="">{{ t('No group') }}</option>
            <option v-for="g in groups" :key="g.id" :value="String(g.id)">
                {{ g.path }}
            </option>
            <option :value="NEW">{{ t('+ New group…') }}</option>
        </select>
        <Input
            v-if="creating"
            :id="`${id}-name`"
            v-model="groupName"
            :placeholder="t('Group name')"
            :aria-label="t('Group name')"
        />
        <InputError :message="error" />
        <p :id="`${id}-hint`" class="field-hint">
            {{
                t(
                    'Groups are folders: use them to sort things by customer, project or place. You can change it later.',
                )
            }}
        </p>
    </div>
</template>
