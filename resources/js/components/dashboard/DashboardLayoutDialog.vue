<script setup lang="ts">
import { Settings2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    defaultDashboardLayout,
    type DashboardLayoutPrefs,
    type DashboardSection,
} from '@/lib/dashboardLayout';
import { t } from '@/lib/i18n';

const prefs = defineModel<DashboardLayoutPrefs>({ required: true });

const open = ref(false);
const draft = ref<DashboardLayoutPrefs>({ ...prefs.value });

watch(open, (isOpen) => {
    if (isOpen) {
        draft.value = { ...prefs.value };
    }
});

const sections: { key: DashboardSection; label: string; hint: string }[] = [
    {
        key: 'sites',
        label: 'Websites',
        hint: 'Website monitors and uptime strip',
    },
    {
        key: 'servers',
        label: 'Servers',
        hint: 'Hosts with agent metrics',
    },
    {
        key: 'heartbeats',
        label: 'Heartbeats',
        hint: 'Scheduled tasks and dead-man switches',
    },
    {
        key: 'renewals',
        label: 'Renewals',
        hint: 'Domains and certificates expiry',
    },
];

function apply() {
    prefs.value = { ...draft.value };
    open.value = false;
}

function reset() {
    draft.value = defaultDashboardLayout();
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                type="button"
                variant="outline"
                size="icon"
                class="size-8 shrink-0"
                :title="t('Customize dashboard')"
                :aria-label="t('Customize dashboard')"
            >
                <Settings2 class="size-4" aria-hidden="true" />
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('Customize dashboard') }}</DialogTitle>
                <DialogDescription class="text-left text-pretty">
                    {{
                        t(
                            'Choose which blocks appear on your dashboard. Saved in this browser.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <ul class="space-y-3" role="list">
                <li
                    v-for="section in sections"
                    :key="section.key"
                    class="flex gap-3 rounded-md border border-border/80 px-3 py-2.5"
                >
                    <input
                        :id="`dash-section-${section.key}`"
                        v-model="draft[section.key]"
                        type="checkbox"
                        class="mt-1 size-4 shrink-0 rounded border"
                    />
                    <Label
                        :for="`dash-section-${section.key}`"
                        class="min-w-0 cursor-pointer space-y-0.5 font-normal"
                    >
                        <span class="block text-sm font-medium">{{
                            t(section.label)
                        }}</span>
                        <span class="block text-xs text-muted-foreground">{{
                            t(section.hint)
                        }}</span>
                    </Label>
                </li>
            </ul>

            <DialogFooter class="gap-2 sm:justify-between">
                <Button type="button" variant="ghost" size="sm" @click="reset">
                    {{ t('Reset to default') }}
                </Button>
                <Button type="button" size="sm" @click="apply">
                    {{ t('Apply') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
