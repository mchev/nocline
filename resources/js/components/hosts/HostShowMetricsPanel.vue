<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import HostMetricsChart, {
    type HostMetricPoint,
} from '@/components/HostMetricsChart.vue';
import { t } from '@/lib/i18n';

defineProps<{
    enrolled: boolean;
    chartHours: number;
    chartLoading: boolean;
    chartPoints: HostMetricPoint[];
    chartEndAt: string;
}>();

const emit = defineEmits<{
    hoursChange: [hours: number];
}>();
</script>

<template>
    <section
        v-if="enrolled"
        class="surface p-5"
        :aria-label="t('Server load (CPU, RAM, disk)')"
    >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">
                {{ t('Server load (CPU, RAM, disk)') }}
            </h2>
            <label class="flex items-center gap-2 text-sm">
                <span class="text-muted-foreground">{{ t('Range') }}</span>
                <select
                    class="h-9 rounded-md border bg-transparent px-2 text-sm"
                    :value="chartHours"
                    :disabled="chartLoading"
                    @change="
                        emit(
                            'hoursChange',
                            Number(($event.target as HTMLSelectElement).value),
                        )
                    "
                >
                    <option :value="24">{{ t('24 hours') }}</option>
                    <option :value="168">{{ t('7 days') }}</option>
                    <option :value="720">{{ t('30 days') }}</option>
                </select>
            </label>
        </div>
        <div v-if="chartLoading" class="flex justify-center py-8">
            <LoaderCircle
                class="size-8 animate-spin text-muted-foreground"
                aria-hidden="true"
            />
        </div>
        <HostMetricsChart
            v-else
            :points="chartPoints"
            :hours="chartHours"
            :end-at="chartEndAt"
        />
        <p
            v-if="!chartLoading && chartPoints.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
        >
            {{
                t(
                    'No metrics yet — data appears a few minutes after the agent connects.',
                )
            }}
        </p>
    </section>
</template>
