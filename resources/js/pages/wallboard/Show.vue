<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Globe } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import WallboardSiteRow, {
    type WallboardSite,
} from '@/components/wallboard/WallboardSiteRow.vue';
import { MONITOR_HISTORY_MINUTES } from '@/lib/monitorHistory';
import { t } from '@/lib/i18n';

type Payload = {
    name: string;
    summary: {
        down: number;
        maintenance: number;
    };
    active_incidents: {
        id: number;
        title: string;
        since: string;
        acknowledged: boolean;
    }[];
    hosts: {
        id: number;
        name: string;
        online: boolean;
        cpu_avg_5m: number | null;
        mem_pct: number | null;
        disk_pct: number | null;
    }[];
    sites?: WallboardSite[];
};

const WALLBOARD_SITE_CAP = 24;

const props = defineProps<{
    token: string;
    name: string;
    hubRevision: string;
}>();

const STALE_AFTER_MS = 90_000;
const REFRESH_MS = 15_000;

const data = ref<Payload | null>(null);
const lastSuccess = ref<number | null>(null);
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;
let tick: ReturnType<typeof setInterval> | undefined;

async function refresh() {
    try {
        const res = await fetch(`/api/wallboard/${props.token}`, {
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) {
            throw new Error(String(res.status));
        }
        const json = (await res.json()) as Payload & { hub_revision?: string };
        if (json.hub_revision && json.hub_revision !== props.hubRevision) {
            window.location.reload();

            return;
        }
        data.value = json;
        lastSuccess.value = Date.now();
    } catch {
        // keep the last data; the stale banner takes over
    }
}

onMounted(() => {
    void refresh();
    timer = setInterval(refresh, REFRESH_MS);
    tick = setInterval(() => (now.value = Date.now()), 1000);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    clearInterval(tick);
});

const stale = computed(
    () =>
        lastSuccess.value === null ||
        now.value - lastSuccess.value > STALE_AFTER_MS,
);
const allGood = computed(
    () =>
        data.value !== null &&
        data.value.summary.down === 0 &&
        data.value.active_incidents.length === 0,
);
const level = (v: number | null, warn: number, crit: number) =>
    v === null
        ? 'text-muted-foreground'
        : v >= crit
          ? 'text-danger'
          : v >= warn
            ? 'text-warning'
            : 'text-success';
const siteRank = (s: WallboardSite) => {
    if (s.enabled && !s.in_maintenance && s.status === 'down') {
        return 0;
    }
    if (s.in_maintenance) {
        return 2;
    }
    if (s.status === 'unknown') {
        return 1;
    }

    return 3;
};

const sortedSites = computed(() =>
    [...(data.value?.sites ?? [])]
        .sort(
            (a, b) =>
                siteRank(a) - siteRank(b) ||
                a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }),
        )
        .slice(0, WALLBOARD_SITE_CAP),
);

const hiddenSiteCount = computed(() =>
    Math.max(0, (data.value?.sites?.length ?? 0) - sortedSites.value.length),
);

const sortedHosts = computed(() =>
    [...(data.value?.hosts ?? [])].sort(
        (a, b) =>
            Number(a.online) - Number(b.online) ||
            a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }),
    ),
);

const since = (iso: string | null) => {
    if (!iso) {
        return '';
    }
    const m = Math.max(0, Math.floor((now.value - Date.parse(iso)) / 60000));
    return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h ${m % 60} min`;
};
</script>

<template>
    <Head :title="`${$t('Wallboard')} - ${name}`" />

    <main
        class="dark relative flex h-dvh max-h-dvh w-full flex-col overflow-hidden bg-background p-4 text-foreground lg:p-6"
    >
        <div
            class="pointer-events-none absolute inset-0 opacity-80 aurora"
            aria-hidden="true"
        />
        <div class="relative flex min-h-0 flex-1 flex-col gap-3">
            <div
                v-if="stale"
                class="rounded-md bg-warning px-6 py-3 text-2xl font-semibold text-black"
                role="alert"
                data-test="stale-banner"
            >
                {{
                    $t('Stale data — last update :ago ago', {
                        ago: lastSuccess
                            ? since(new Date(lastSuccess).toISOString())
                            : '—',
                    })
                }}
            </div>

            <header
                class="flex shrink-0 flex-wrap items-center justify-between gap-3"
            >
                <h1
                    class="truncate font-display text-3xl font-semibold tracking-tight lg:text-4xl"
                >
                    {{ name }}
                </h1>
                <div
                    class="flex shrink-0 items-center gap-3 rounded-md px-5 py-2 text-2xl font-bold lg:text-3xl"
                    :class="
                        data === null
                            ? 'bg-muted text-muted-foreground'
                            : allGood
                              ? 'bg-success text-black'
                              : 'bg-danger text-white'
                    "
                    role="status"
                >
                    <span
                        class="size-4 rounded-full bg-current motion-safe:animate-pulse"
                        aria-hidden="true"
                    />
                    {{
                        data === null
                            ? '…'
                            : allGood
                              ? $t('ALL GOOD')
                              : $t('ATTENTION')
                    }}
                </div>
            </header>

            <template v-if="data">
                <div
                    v-if="data.active_incidents.length"
                    class="shrink-0 truncate rounded-md border border-danger bg-danger/15 px-4 py-2 text-lg font-semibold lg:text-xl"
                    role="alert"
                >
                    <span
                        v-for="(i, idx) in data.active_incidents"
                        :key="i.id"
                    >
                        <template v-if="idx"> · </template>
                        {{ i.title }} — {{ since(i.since) }}
                        <span v-if="i.acknowledged" class="text-muted-foreground">
                            ({{ $t('ack') }})
                        </span>
                    </span>
                </div>

                <div
                    class="grid min-h-0 flex-1 gap-3"
                    :class="
                        sortedSites.length && sortedHosts.length
                            ? 'lg:grid-cols-2'
                            : 'grid-cols-1'
                    "
                >
                    <section
                        v-if="sortedSites.length"
                        class="flex min-h-0 min-w-0 flex-col overflow-hidden surface"
                    >
                        <div
                            class="flex shrink-0 items-center justify-between gap-2 border-b border-border px-3 py-2"
                        >
                            <h2
                                class="flex items-center gap-2 font-display text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                <Globe
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                {{ $t('Websites') }}
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t('Last :minutes minutes', {
                                        minutes: MONITOR_HISTORY_MINUTES,
                                    })
                                }}
                                <span v-if="hiddenSiteCount" class="ml-1"
                                    >+{{ hiddenSiteCount }}</span
                                >
                            </p>
                        </div>
                        <ul
                            class="flex min-h-0 flex-1 flex-col gap-1 overflow-hidden p-1"
                            role="list"
                        >
                            <WallboardSiteRow
                                v-for="site in sortedSites"
                                :key="site.id"
                                :site="site"
                            />
                        </ul>
                    </section>

                    <section
                        v-if="sortedHosts.length"
                        class="flex min-h-0 min-w-0 flex-col overflow-hidden surface"
                    >
                        <h2
                            class="shrink-0 border-b border-border px-3 py-2 font-display text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ $t('Servers') }}
                        </h2>
                        <ul
                            class="flex min-h-0 flex-1 flex-col gap-1 overflow-hidden p-1"
                            role="list"
                        >
                            <li
                                v-for="h in sortedHosts"
                                :key="h.id"
                                class="flex items-center gap-2 rounded-md border border-border px-3 py-2"
                                :class="
                                    h.online
                                        ? 'bg-card'
                                        : 'border-danger bg-danger/10'
                                "
                            >
                                <span
                                    class="size-2.5 shrink-0 rounded-full"
                                    :class="
                                        h.online ? 'bg-success' : 'bg-danger'
                                    "
                                    aria-hidden="true"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-lg font-semibold"
                                >
                                    {{ h.name }}
                                </span>
                                <span
                                    class="numeric flex shrink-0 gap-2 text-sm font-semibold"
                                >
                                    <span :class="level(h.cpu_avg_5m, 70, 90)">
                                        CPU {{ h.cpu_avg_5m ?? '–' }}%
                                    </span>
                                    <span :class="level(h.mem_pct, 80, 92)">
                                        RAM {{ h.mem_pct ?? '–' }}%
                                    </span>
                                    <span :class="level(h.disk_pct, 80, 92)">
                                        DSK {{ h.disk_pct ?? '–' }}%
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </section>
                </div>
            </template>
        </div>
    </main>
</template>
