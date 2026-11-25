<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pause, Pencil, Play, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import CheckController from '@/actions/App/Modules/Checks/Http/Controllers/CheckController';
import HostController from '@/actions/App/Modules/Hosts/Http/Controllers/HostController';
import Sparkline from '@/components/ui-kit/Sparkline.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import MonitorIdentityBadges from '@/components/MonitorIdentityBadges.vue';
import UptimeHistoryDots from '@/components/ui-kit/UptimeHistoryDots.vue';
import { monitorUptimeHistoryProps } from '@/lib/monitorHistory';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { checkTypeLabel } from '@/lib/checks';
import { hostMetricTone } from '@/lib/hostMetricTone';
import { t } from '@/lib/i18n';
import { siteHealthCompanionLabel } from '@/lib/siteHealthCompanion';
import type { Check } from '@/types/checks';
import type { GroupLayout } from '@/types/groups';

const props = defineProps<{
    check: Check;
    layout: GroupLayout;
    selected: boolean;
    canManage: boolean;
    isAdmin: boolean;
    statusText: string;
    categoryText: string | null;
    /** Nested Laravel /up row under the main site monitor. */
    companionRow?: boolean;
}>();

defineEmits<{
    select: [on: boolean];
    pause: [];
    edit: [];
    remove: [];
    tag: [tag: string];
}>();

const variant = (c: Check) =>
    !c.enabled
        ? 'muted'
        : c.in_maintenance
          ? 'info'
          : c.status === 'up'
            ? 'success'
            : c.status === 'down'
              ? 'danger'
              : 'muted';

const label = (c: Check, statusText: string) =>
    !c.enabled ? t('Paused') : c.in_maintenance ? t('Maintenance') : statusText;

const dotStatus = (c: Check) =>
    !c.enabled ? 'unknown' : c.in_maintenance ? 'maintenance' : c.status;

const latencyTone = (ms: number | null) =>
    ms === null ? '' : ms > 1500 ? 'text-warning' : 'text-foreground';

const isServer = computed(
    () => props.check.category === 'server' || props.check.type === 'agent',
);
const isWebsite = computed(
    () => props.check.category === 'website' || props.check.type === 'http',
);
const isHeartbeat = computed(() => props.check.type === 'heartbeat');

const relativeAgo = (iso: string | null) => {
    if (!iso) {
        return t('never');
    }

    const sec = Math.max(
        0,
        Math.round((Date.now() - new Date(iso).getTime()) / 1000),
    );

    if (sec < 60) {
        return t(':n s ago', { n: sec });
    }

    if (sec < 3600) {
        return t(':n min ago', { n: Math.floor(sec / 60) });
    }

    return t(':n h ago', { n: Math.floor(sec / 3600) });
};

const pct = (v: number | null) => (v === null ? '—' : `${Math.round(v)}%`);

const freeDisk = computed(() => {
    const used = props.check.metrics?.disk_now ?? null;

    return used === null ? null : Math.max(0, 100 - used);
});

const supportsHistory = computed(
    () =>
        props.check.category === 'website' ||
        props.check.type === 'heartbeat' ||
        props.check.type === 'http' ||
        props.check.type === 'tcp' ||
        props.check.type === 'icmp',
);

const uptimeHistory = computed(() => monitorUptimeHistoryProps(props.check));
</script>

<template>
    <!-- list: table row -->
    <tr
        v-if="layout === 'list'"
        class="group transition-colors hover:bg-muted/40"
        :class="{
            'opacity-60': !check.enabled,
            'bg-primary/5': selected,
            'border-l-2 border-primary/25 bg-muted/15': companionRow,
        }"
    >
        <td v-if="canManage" class="w-10 pl-4">
            <label class="target">
                <input
                    type="checkbox"
                    class="size-4 accent-primary"
                    :checked="selected"
                    :aria-label="t('Select')"
                    @change="
                        $emit(
                            'select',
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
            </label>
        </td>
        <td
            class="w-36 whitespace-nowrap"
            :class="companionRow ? 'px-3 py-2' : 'px-3 py-3'"
        >
            <span class="inline-flex items-center gap-3 p-1">
                <StatusDot
                    :class="{ 'scale-90': companionRow }"
                    :status="dotStatus(check)"
                    :pulse="check.enabled"
                />
                <span
                    class="chip"
                    :class="{
                        'chip-success': variant(check) === 'success',
                        'chip-danger': variant(check) === 'danger',
                        'chip-info': variant(check) === 'info',
                    }"
                >
                    {{ label(check, statusText) }}
                </span>
            </span>
        </td>
        <td class="max-w-md px-2" :class="companionRow ? 'py-2 pl-5' : 'py-4'">
            <div class="flex min-w-0 items-center gap-2.5">
                <MonitorIdentityBadges
                    v-if="isWebsite && !companionRow"
                    :name="check.name"
                    :favicon-url="check.favicon_url"
                    :ssl-status="check.ssl_status"
                    :ssl-days-remaining="check.ssl_days_remaining"
                    :site-framework="check.site_framework"
                    :wordpress-version="check.wordpress_version"
                />
                <Link
                    :href="CheckController.show.url(check.id)"
                    class="truncate hover:text-primary hover:underline focus-visible:underline"
                    :class="
                        companionRow
                            ? 'text-sm font-medium text-muted-foreground'
                            : 'font-semibold text-foreground'
                    "
                    @click.stop
                >
                    {{ companionRow ? siteHealthCompanionLabel() : check.name }}
                </Link>
            </div>
            <div
                v-if="!companionRow"
                class="max-w-80 truncate text-xs text-muted-foreground"
            >
                {{ checkTypeLabel(check.type)
                }}<template v-if="check.target"> · {{ check.target }}</template>
                <template
                    v-if="
                        check.host_name &&
                        check.host_id &&
                        check.type !== 'agent'
                    "
                >
                    ·
                    <Link
                        :href="HostController.show.url(check.host_id)"
                        class="text-primary hover:underline"
                        >{{ check.host_name }}</Link
                    >
                </template>
                <span v-if="check.last_error" class="text-danger">
                    — {{ check.last_error }}</span
                >
            </div>
        </td>
        <td
            class="hidden w-48 min-w-[11rem] md:table-cell"
            :class="companionRow ? 'py-2' : 'py-3'"
        >
            <UptimeHistoryDots
                v-if="supportsHistory"
                v-bind="uptimeHistory"
                :class="{ 'origin-left scale-[0.92]': companionRow }"
            />
            <span v-else class="text-xs text-muted-foreground">—</span>
        </td>
        <td class="hidden md:table-cell">
            <div class="flex flex-wrap gap-1">
                <Badge v-if="categoryText" variant="secondary">{{
                    categoryText
                }}</Badge>
                <Badge
                    v-for="tag in check.tags"
                    :key="tag"
                    variant="outline"
                    class="cursor-pointer hover:bg-muted"
                    @click="$emit('tag', tag)"
                    >{{ tag }}</Badge
                >
            </div>
        </td>
        <td class="w-24 text-right whitespace-nowrap">
            <template v-if="isHeartbeat">
                <span class="text-xs text-muted-foreground">{{
                    relativeAgo(check.since_at)
                }}</span>
            </template>
            <template v-else-if="isServer && check.metrics">
                <span class="numeric text-xs text-muted-foreground">
                    CPU {{ pct(check.metrics.cpu_now) }}
                </span>
            </template>
            <span
                v-else-if="check.latency_ms !== null"
                class="numeric text-sm font-medium"
                :class="latencyTone(check.latency_ms)"
            >
                {{ check.latency_ms
                }}<span class="ml-0.5 text-xs font-normal text-muted-foreground"
                    >ms</span
                >
            </span>
        </td>
        <td v-if="canManage" class="w-36 pr-3 text-right whitespace-nowrap">
            <Button
                size="icon"
                variant="ghost"
                :title="check.enabled ? t('Pause') : t('Resume')"
                :aria-label="check.enabled ? t('Pause') : t('Resume')"
                @click="$emit('pause')"
            >
                <Pause v-if="check.enabled" class="size-4" />
                <Play v-else class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                :title="t('Edit')"
                :aria-label="t('Edit')"
                @click="$emit('edit')"
            >
                <Pencil class="size-4" />
            </Button>
            <Button
                v-if="isAdmin"
                size="icon"
                variant="ghost"
                :title="t('Delete')"
                :aria-label="t('Delete')"
                @click="$emit('remove')"
            >
                <Trash2 class="size-4" />
            </Button>
        </td>
    </tr>

    <!-- grid: differentiated cards -->
    <article
        v-else-if="layout === 'grid'"
        class="flex flex-col gap-3 surface p-5 transition"
        :class="{
            'opacity-60': !check.enabled,
            'ring-2 ring-primary/40': selected,
            'border-danger/40':
                check.enabled &&
                !check.in_maintenance &&
                check.status === 'down',
        }"
    >
        <div class="flex items-start gap-2">
            <label v-if="canManage" class="target -ml-1">
                <input
                    type="checkbox"
                    class="size-4 accent-primary"
                    :checked="selected"
                    :aria-label="t('Select')"
                    @change="
                        $emit(
                            'select',
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
            </label>
            <div class="min-w-0 flex-1">
                <h3
                    class="flex items-center gap-2 truncate font-display text-base font-semibold"
                >
                    <StatusDot
                        :status="dotStatus(check)"
                        :pulse="check.enabled"
                    />
                    <MonitorIdentityBadges
                        v-if="isWebsite"
                        :name="check.name"
                        :favicon-url="check.favicon_url"
                        :ssl-status="check.ssl_status"
                        :ssl-days-remaining="check.ssl_days_remaining"
                        :site-framework="check.site_framework"
                        :wordpress-version="check.wordpress_version"
                        favicon-size-class="size-5"
                    />
                    <Link
                        :href="CheckController.show.url(check.id)"
                        class="truncate hover:text-primary hover:underline"
                        @click.stop
                    >
                        {{ check.name }}
                    </Link>
                </h3>
                <p class="mt-0.5 truncate text-xs text-muted-foreground">
                    <template v-if="isWebsite">{{
                        check.target || checkTypeLabel(check.type)
                    }}</template>
                    <template v-else-if="isServer">
                        {{
                            check.host_online === true
                                ? t('Agent online')
                                : check.host_online === false
                                  ? t('Agent offline')
                                  : checkTypeLabel(check.type)
                        }}
                    </template>
                    <template v-else-if="isHeartbeat">{{
                        t('Heartbeat')
                    }}</template>
                    <template v-else>
                        {{ checkTypeLabel(check.type)
                        }}<template v-if="check.target">
                            · {{ check.target }}</template
                        >
                    </template>
                </p>
            </div>
            <span
                class="chip shrink-0"
                :class="{
                    'chip-success': variant(check) === 'success',
                    'chip-danger': variant(check) === 'danger',
                    'chip-info': variant(check) === 'info',
                }"
            >
                {{ label(check, statusText) }}
            </span>
        </div>

        <p v-if="check.last_error" class="line-clamp-2 text-xs text-danger">
            {{ check.last_error }}
        </p>

        <UptimeHistoryDots
            v-if="supportsHistory"
            v-bind="uptimeHistory"
            class="w-full"
        />

        <!-- Server: CPU / RAM / disk sparklines -->
        <div v-if="isServer && check.metrics" class="grid gap-1.5">
            <div class="grid grid-cols-[3rem_1fr_2.25rem] items-center gap-2">
                <span
                    class="text-[0.65rem] font-medium tracking-wide text-muted-foreground uppercase"
                    >CPU</span
                >
                <Sparkline
                    :values="check.metrics.cpu"
                    :tone="hostMetricTone(check.metrics.cpu_now)"
                    :label="`CPU ${pct(check.metrics.cpu_now)}`"
                />
                <span class="text-right numeric text-xs font-semibold">{{
                    pct(check.metrics.cpu_now)
                }}</span>
            </div>
            <div class="grid grid-cols-[3rem_1fr_2.25rem] items-center gap-2">
                <span
                    class="text-[0.65rem] font-medium tracking-wide text-muted-foreground uppercase"
                    >RAM</span
                >
                <Sparkline
                    :values="check.metrics.mem"
                    :tone="hostMetricTone(check.metrics.mem_now)"
                    :label="`RAM ${pct(check.metrics.mem_now)}`"
                />
                <span class="text-right numeric text-xs font-semibold">{{
                    pct(check.metrics.mem_now)
                }}</span>
            </div>
            <div class="grid grid-cols-[3rem_1fr_2.25rem] items-center gap-2">
                <span
                    class="text-[0.65rem] font-medium tracking-wide text-muted-foreground uppercase"
                    >{{ t('Disk') }}</span
                >
                <Sparkline
                    :values="
                        (check.metrics.disk ?? []).map((v) =>
                            v === null ? null : Math.max(0, 100 - v),
                        )
                    "
                    :tone="hostMetricTone(freeDisk, true)"
                    :label="`${t('Disk free')} ${pct(freeDisk)}`"
                />
                <span class="text-right numeric text-xs font-semibold">{{
                    pct(freeDisk)
                }}</span>
            </div>
        </div>

        <!-- Website: latency hero -->
        <p
            v-else-if="isWebsite && check.latency_ms !== null"
            class="numeric text-2xl font-semibold"
            :class="latencyTone(check.latency_ms)"
        >
            {{ check.latency_ms
            }}<span class="ml-1 text-xs font-normal text-muted-foreground"
                >ms</span
            >
        </p>

        <!-- Heartbeat: last ping -->
        <p v-else-if="isHeartbeat" class="text-sm text-muted-foreground">
            {{ t('Last ping') }}:
            <span class="font-medium text-foreground">{{
                relativeAgo(check.since_at)
            }}</span>
        </p>

        <!-- Other: optional latency -->
        <p
            v-else-if="check.latency_ms !== null"
            class="numeric text-2xl font-semibold"
            :class="latencyTone(check.latency_ms)"
        >
            {{ check.latency_ms
            }}<span class="ml-1 text-xs font-normal text-muted-foreground"
                >ms</span
            >
        </p>

        <div v-if="check.tags.length" class="flex flex-wrap gap-1">
            <Badge
                v-for="tag in check.tags"
                :key="tag"
                variant="outline"
                class="cursor-pointer hover:bg-muted"
                @click="$emit('tag', tag)"
                >{{ tag }}</Badge
            >
        </div>

        <footer v-if="canManage" class="mt-auto -mr-2 -mb-2 flex justify-end">
            <Button
                size="icon"
                variant="ghost"
                :title="check.enabled ? t('Pause') : t('Resume')"
                :aria-label="check.enabled ? t('Pause') : t('Resume')"
                @click="$emit('pause')"
            >
                <Pause v-if="check.enabled" class="size-4" />
                <Play v-else class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                :title="t('Edit')"
                :aria-label="t('Edit')"
                @click="$emit('edit')"
            >
                <Pencil class="size-4" />
            </Button>
            <Button
                v-if="isAdmin"
                size="icon"
                variant="ghost"
                :title="t('Delete')"
                :aria-label="t('Delete')"
                @click="$emit('remove')"
            >
                <Trash2 class="size-4" />
            </Button>
        </footer>
    </article>

    <!-- compact: dense chip -->
    <span
        v-else
        class="chip inline-flex max-w-full items-center gap-2 border border-border/60 py-1.5 pr-3 pl-3 transition-colors hover:bg-muted/50"
        :class="{
            'opacity-60': !check.enabled,
            'ring-2 ring-primary/40': selected,
            'chip-danger':
                check.enabled &&
                !check.in_maintenance &&
                check.status === 'down',
            'chip-success':
                check.enabled && !check.in_maintenance && check.status === 'up',
            'chip-info': check.in_maintenance,
        }"
        :title="`${statusText} · ${check.target}${check.last_error ? ' — ' + check.last_error : ''}`"
    >
        <label v-if="canManage" class="target -ml-1">
            <input
                type="checkbox"
                class="size-3.5 accent-primary"
                :checked="selected"
                :aria-label="t('Select')"
                @change="
                    $emit('select', ($event.target as HTMLInputElement).checked)
                "
            />
        </label>
        <StatusDot
            :status="dotStatus(check)"
            :pulse="check.enabled"
            :label="label(check, statusText)"
        />
        <MonitorIdentityBadges
            v-if="isWebsite"
            :name="check.name"
            :favicon-url="check.favicon_url"
            :ssl-status="check.ssl_status"
            :ssl-days-remaining="check.ssl_days_remaining"
            :site-framework="check.site_framework"
            :wordpress-version="check.wordpress_version"
            favicon-size-class="size-3.5"
        />
        <Link
            :href="CheckController.show.url(check.id)"
            class="truncate font-medium text-foreground hover:text-primary hover:underline"
            @click.stop
        >
            {{ check.name }}
        </Link>
        <UptimeHistoryDots
            v-if="supportsHistory"
            v-bind="uptimeHistory"
            class="hidden min-w-[9rem] sm:flex"
        />
        <span v-if="isHeartbeat" class="text-muted-foreground">{{
            relativeAgo(check.since_at)
        }}</span>
        <span
            v-else-if="isServer && check.metrics?.cpu_now != null"
            class="numeric text-muted-foreground"
        >
            {{ pct(check.metrics.cpu_now) }}
        </span>
        <span
            v-else-if="check.latency_ms !== null"
            class="numeric text-muted-foreground"
            >{{ check.latency_ms }} ms</span
        >
        <button
            v-if="canManage"
            type="button"
            class="target -mr-1 rounded-md text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :title="t('Edit')"
            :aria-label="t('Edit')"
            @click="$emit('edit')"
        >
            <Pencil class="size-3" />
        </button>
    </span>
</template>

<style scoped>
.target {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-inline-size: 1.5rem;
    min-block-size: 1.5rem;
}

@media (pointer: coarse) {
    .target {
        min-inline-size: 2.75rem;
        min-block-size: 2.75rem;
    }
}
</style>
