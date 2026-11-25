<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import HostController from '@/actions/App/Modules/Hosts/Http/Controllers/HostController';
import { t } from '@/lib/i18n';

export type MetricAlertsConfig = {
    enabled: boolean;
    cpu_pct: number;
    mem_pct: number;
    disk_pct: number;
    load_1m: number | null;
    sustain_minutes: number;
};

const props = defineProps<{
    hostId: number;
    hostName: string;
    canManage: boolean;
    metricAlerts: MetricAlertsConfig;
    resourceAlert: { active: boolean; message: string | null };
}>();

const form = ref({ ...props.metricAlerts });
const saving = ref(false);

watch(
    () => props.metricAlerts,
    (value) => {
        form.value = { ...value };
    },
);

const loadEnabled = computed({
    get: () => form.value.load_1m !== null,
    set(enabled: boolean) {
        form.value.load_1m = enabled ? (props.metricAlerts.load_1m ?? 8) : null;
    },
});

function save() {
    if (saving.value) {
        return;
    }

    saving.value = true;

    router.patch(
        HostController.update.url(props.hostId),
        {
            name: props.hostName,
            metric_alerts: {
                ...form.value,
                load_1m: form.value.load_1m,
            },
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('Load alert thresholds saved.'));
            },
            onError: () => {
                toast.error(t('Could not save load alert thresholds.'));
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <section
        class="space-y-3 surface p-3"
        aria-labelledby="host-metric-alerts-heading"
    >
        <header class="space-y-1">
            <h2
                id="host-metric-alerts-heading"
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ t('Load alerts') }}
            </h2>
            <p class="text-xs text-pretty text-muted-foreground">
                {{
                    t(
                        'Notify when CPU, RAM or disk stay high for several minutes (Slack, email…).',
                    )
                }}
            </p>
        </header>

        <p
            v-if="resourceAlert.active && resourceAlert.message"
            class="rounded-md border border-warning/40 bg-warning/10 px-2.5 py-2 text-xs text-pretty text-warning"
            role="status"
        >
            {{ resourceAlert.message }}
        </p>

        <template v-if="canManage">
            <label
                class="flex cursor-pointer items-start gap-2 text-sm leading-snug"
            >
                <input
                    v-model="form.enabled"
                    type="checkbox"
                    class="mt-0.5 size-4 shrink-0 rounded border-input"
                />
                <span>{{ t('Enable load alerts on this server') }}</span>
            </label>

            <div
                class="grid gap-3 sm:grid-cols-2"
                :class="{ 'opacity-60': !form.enabled }"
            >
                <div class="field-stack">
                    <label class="text-xs font-medium" for="metric-cpu">{{
                        t('CPU (%)')
                    }}</label>
                    <input
                        id="metric-cpu"
                        v-model.number="form.cpu_pct"
                        type="number"
                        min="1"
                        max="100"
                        class="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                        :disabled="!form.enabled"
                    />
                </div>
                <div class="field-stack">
                    <label class="text-xs font-medium" for="metric-mem">{{
                        t('RAM (%)')
                    }}</label>
                    <input
                        id="metric-mem"
                        v-model.number="form.mem_pct"
                        type="number"
                        min="1"
                        max="100"
                        class="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                        :disabled="!form.enabled"
                    />
                </div>
                <div class="field-stack">
                    <label class="text-xs font-medium" for="metric-disk">{{
                        t('Disk (%)')
                    }}</label>
                    <input
                        id="metric-disk"
                        v-model.number="form.disk_pct"
                        type="number"
                        min="1"
                        max="100"
                        class="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                        :disabled="!form.enabled"
                    />
                </div>
                <div class="field-stack">
                    <label class="text-xs font-medium" for="metric-sustain">{{
                        t('Duration (minutes)')
                    }}</label>
                    <input
                        id="metric-sustain"
                        v-model.number="form.sustain_minutes"
                        type="number"
                        min="1"
                        max="60"
                        class="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                        :disabled="!form.enabled"
                    />
                </div>
                <div class="field-stack sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="loadEnabled"
                            type="checkbox"
                            class="size-4 rounded border-input"
                            :disabled="!form.enabled"
                        />
                        {{ t('Load average (1 min)') }}
                    </label>
                    <input
                        v-if="form.load_1m !== null"
                        v-model.number="form.load_1m"
                        type="number"
                        min="0.1"
                        step="0.1"
                        class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-2 text-sm sm:max-w-[8rem]"
                        :disabled="!form.enabled"
                    />
                </div>
            </div>

            <button
                type="button"
                class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground disabled:opacity-50"
                :disabled="saving"
                @click="save"
            >
                {{ saving ? t('Saving…') : t('Save thresholds') }}
            </button>
        </template>
    </section>
</template>
