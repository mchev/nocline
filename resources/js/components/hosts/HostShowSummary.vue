<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound, Trash2, Unplug } from '@lucide/vue';
import { computed } from 'vue';
import type { HostMetrics } from '@/components/HostCard.vue';
import { Button } from '@/components/ui/button';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import {
    hostMetricTone,
    metricThresholdsFromAlert,
} from '@/lib/hostMetricTone';
import HostStackChips from '@/components/HostStackChips.vue';
import { t } from '@/lib/i18n';
import { index as hostsIndex } from '@/routes/hosts';

const props = defineProps<{
    name: string;
    enrolled: boolean;
    online: boolean;
    lastSeenAt: string | null;
    metrics: HostMetrics;
    metricAlerts: { cpu_pct: number; mem_pct: number; disk_pct: number };
    agentVersion: string | null;
    agentOutdated: boolean;
    accentColor: string | null;
    hostname: string | null;
    distro: string | null;
    kernel: string | null;
    publicIpv4: string | null;
    providerLabel: string | null;
    providerRegion: string | null;
    stack?: Record<string, string> | null;
    stackWarnings?: Record<string, string> | null;
    resourceAlertActive: boolean;
    resourceAlertMessage: string | null;
    rebootRequired: boolean;
    rebootRequiredMessage: string | null;
    canManage: boolean;
    isAdmin: boolean;
    installHref: string;
}>();

const emit = defineEmits<{
    newToken: [];
    revoke: [];
    delete: [];
}>();

const statusDot = computed(() => {
    if (!props.enrolled) {
        return 'unknown' as const;
    }

    return props.online ? ('up' as const) : ('down' as const);
});

const statusLabel = computed(() => {
    if (!props.enrolled) {
        return t('Not enrolled');
    }

    if (props.resourceAlertActive && props.online) {
        return t('High load');
    }

    if (props.rebootRequired && props.online) {
        return t('Reboot pending');
    }

    return props.online ? t('Online') : t('Offline');
});

const lastSeenLabel = computed(() => {
    if (!props.lastSeenAt) {
        return t('No signal from the agent yet');
    }

    const sec = Math.max(
        0,
        Math.round((Date.now() - new Date(props.lastSeenAt).getTime()) / 1000),
    );

    if (sec < 60) {
        return t(':n s ago', { n: sec });
    }

    if (sec < 3600) {
        return t(':n min ago', { n: Math.floor(sec / 60) });
    }

    return t(':n h ago', { n: Math.floor(sec / 3600) });
});

const pct = (v: number | null) => (v === null ? '—' : `${Math.round(v)}%`);

const providerTitle = computed(() => {
    if (!props.providerLabel) {
        return '';
    }

    return props.providerRegion
        ? `${props.providerLabel} (${props.providerRegion})`
        : props.providerLabel;
});

const metaChips = computed(() => {
    const chips: string[] = [];

    if (props.distro) {
        chips.push(props.distro);
    }

    if (props.kernel) {
        chips.push(props.kernel);
    }

    if (props.hostname) {
        chips.push(props.hostname);
    }

    if (props.publicIpv4) {
        chips.push(props.publicIpv4);
    }

    if (providerTitle.value) {
        chips.push(providerTitle.value);
    }

    return chips;
});

function valueClass(key: 'cpu' | 'mem' | 'disk', v: number | null): string {
    if (v === null) {
        return '';
    }

    const custom =
        key === 'cpu'
            ? metricThresholdsFromAlert(props.metricAlerts.cpu_pct)
            : key === 'mem'
              ? metricThresholdsFromAlert(props.metricAlerts.mem_pct)
              : metricThresholdsFromAlert(props.metricAlerts.disk_pct);
    const tone = hostMetricTone(v, false, custom);

    if (tone === 'danger') {
        return 'text-danger';
    }

    if (tone === 'warning') {
        return 'text-warning';
    }

    return '';
}

const loadStats = computed(() => [
    { key: 'cpu' as const, label: t('CPU'), value: props.metrics.cpu_now },
    {
        key: 'mem' as const,
        label: t('Memory'),
        value: props.metrics.mem_now,
    },
    {
        key: 'disk' as const,
        label: t('Disk'),
        value: props.metrics.disk_now,
    },
]);
</script>

<template>
    <header class="overflow-hidden surface">
        <div
            class="h-0.5 w-full"
            :style="{ backgroundColor: accentColor ?? 'var(--primary)' }"
            aria-hidden="true"
        />
        <div class="space-y-2 px-4 py-3">
            <!-- Ligne 1 : identité + actions -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                <Link
                    :href="hostsIndex()"
                    class="inline-flex shrink-0 items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span class="sr-only sm:not-sr-only">{{
                        t('Back to servers')
                    }}</span>
                </Link>
                <span
                    class="hidden h-4 w-px bg-border sm:block"
                    aria-hidden="true"
                />
                <StatusDot
                    :status="statusDot"
                    :pulse="enrolled && online"
                    :label="statusLabel"
                />
                <h1
                    class="min-w-0 truncate font-display text-lg font-semibold tracking-tight"
                >
                    {{ name }}
                </h1>
                <span
                    class="chip shrink-0"
                    :class="{
                        'chip-success':
                            enrolled && online && !resourceAlertActive,
                        'chip-danger': enrolled && !online,
                        'chip-warning':
                            online && (resourceAlertActive || rebootRequired),
                    }"
                >
                    {{ statusLabel }}
                </span>
                <span class="text-muted-foreground">·</span>
                <span class="truncate text-muted-foreground">{{
                    lastSeenLabel
                }}</span>
                <template v-if="enrolled && agentVersion">
                    <span class="text-muted-foreground">·</span>
                    <span class="text-muted-foreground">{{ t('Agent') }}</span>
                    <span
                        class="font-mono tabular-nums"
                        :class="agentOutdated ? 'text-warning' : ''"
                    >
                        v{{ agentVersion }}
                    </span>
                </template>
                <div
                    v-if="canManage"
                    class="ml-auto flex shrink-0 items-center gap-1"
                >
                    <Link v-if="!enrolled" :href="installHref">
                        <Button variant="default" size="sm" type="button">
                            {{ t('Install agent') }}
                        </Button>
                    </Link>
                    <template v-else>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-8"
                            :title="t('New enrollment token')"
                            :aria-label="t('New enrollment token')"
                            @click="emit('newToken')"
                        >
                            <KeyRound class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :title="t('Revoke agent')"
                            :aria-label="t('Revoke agent')"
                            @click="emit('revoke')"
                        >
                            <Unplug class="size-4" />
                        </Button>
                    </template>
                    <Button
                        v-if="isAdmin"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 text-danger hover:text-danger"
                        :title="t('Delete server')"
                        :aria-label="t('Delete server')"
                        @click="emit('delete')"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Ligne 2 : contexte machine + charge -->
            <div
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
            >
                <template v-if="metaChips.length">
                    <span
                        v-for="(chip, i) in metaChips"
                        :key="`${chip}-${i}`"
                        class="chip max-w-[12rem] truncate font-normal"
                        :title="chip"
                    >
                        {{ chip }}
                    </span>
                </template>
                <template v-if="enrolled">
                    <span
                        v-if="metaChips.length"
                        class="text-border"
                        aria-hidden="true"
                        >|</span
                    >
                    <span
                        v-for="(stat, i) in loadStats"
                        :key="stat.key"
                        class="inline-flex items-center gap-1 tabular-nums"
                    >
                        <span v-if="i > 0" aria-hidden="true">·</span>
                        <span>{{ stat.label }}</span>
                        <strong
                            class="font-semibold text-foreground"
                            :class="valueClass(stat.key, stat.value)"
                        >
                            {{ pct(stat.value) }}
                        </strong>
                    </span>
                </template>
            </div>

            <HostStackChips
                v-if="enrolled && stack"
                class="pt-0.5"
                :stack="stack"
                :stack-warnings="stackWarnings"
            />

            <p
                v-if="resourceAlertActive && resourceAlertMessage"
                class="truncate text-xs text-warning"
                role="status"
                :title="resourceAlertMessage"
            >
                {{ resourceAlertMessage }}
            </p>
            <p
                v-if="rebootRequired && rebootRequiredMessage"
                class="text-xs text-pretty text-warning"
                role="status"
            >
                {{ rebootRequiredMessage }}
            </p>
        </div>
    </header>
</template>
