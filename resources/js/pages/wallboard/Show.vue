<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { wallboardGridLayout } from '@/lib/wallboardLayout';
import type { WallboardSite } from '@/types/wallboard';
import type { HostMetrics } from '@/components/HostCard.vue';
import WallboardHostTile from '@/components/wallboard/WallboardHostTile.vue';
import WallboardSiteTile from '@/components/wallboard/WallboardSiteTile.vue';
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
        metrics: HostMetrics;
    }[];
    sites?: WallboardSite[];
};

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

const stale = computed(
    () =>
        lastSuccess.value === null ||
        now.value - lastSuccess.value > STALE_AFTER_MS,
);

const downSites = computed(() =>
    (data.value?.sites ?? []).filter(
        (s) => s.enabled && !s.in_maintenance && s.status === 'down',
    ),
);

const offlineHosts = computed(() =>
    (data.value?.hosts ?? []).filter((h) => !h.online),
);

const allGood = computed(
    () =>
        data.value !== null &&
        downSites.value.length === 0 &&
        offlineHosts.value.length === 0 &&
        data.value.active_incidents.length === 0,
);

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
    [...(data.value?.sites ?? [])].sort(
        (a, b) =>
            siteRank(a) - siteRank(b) ||
            a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }),
    ),
);

const sortedHosts = computed(() =>
    [...(data.value?.hosts ?? [])].sort(
        (a, b) =>
            Number(a.online) - Number(b.online) ||
            a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }),
    ),
);

const hostPaneRef = ref<HTMLElement | null>(null);
const hostGridCols = ref(1);
let hostGridObserver: ResizeObserver | undefined;

function syncHostGridColumns() {
    const pane = hostPaneRef.value;
    const count = sortedHosts.value.length;

    if (!pane || count === 0) {
        return;
    }

    const gap = 4;
    const { cols } = wallboardGridLayout(
        count,
        pane.clientWidth,
        pane.clientHeight,
        gap,
        { minCellW: 160, minCellH: 72 },
    );

    hostGridCols.value = Math.max(1, cols);
}

function bindHostGridObserver() {
    const pane = hostPaneRef.value;

    hostGridObserver?.disconnect();
    hostGridObserver = undefined;

    if (!pane || sortedHosts.value.length === 0) {
        return;
    }

    hostGridObserver = new ResizeObserver(() => syncHostGridColumns());
    hostGridObserver.observe(pane);
    syncHostGridColumns();
}

watch(
    sortedHosts,
    async () => {
        await nextTick();
        bindHostGridObserver();
    },
    { flush: 'post' },
);

const problemLabels = computed(() => {
    const items: string[] = [];
    for (const s of downSites.value) {
        items.push(s.name);
    }
    for (const h of offlineHosts.value) {
        items.push(`${h.name} (${t('Offline')})`);
    }
    for (const i of data.value?.active_incidents ?? []) {
        items.push(i.title);
    }

    return [...new Set(items)];
});

const problemCount = computed(() => problemLabels.value.length);

const clockIso = computed(() => new Date(now.value).toISOString());

const clockLabel = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(now.value)),
);

const since = (iso: string | null) => {
    if (!iso) {
        return '';
    }
    const m = Math.max(0, Math.floor((now.value - Date.parse(iso)) / 60000));
    return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h ${m % 60} min`;
};

onBeforeUnmount(() => {
    clearInterval(timer);
    clearInterval(tick);
    hostGridObserver?.disconnect();
});
</script>

<template>
    <Head :title="`${$t('Wallboard')} - ${name}`" />

    <main class="wb-root dark">
        <div class="wb-aurora" aria-hidden="true" />

        <div class="wb-shell">
            <div
                v-if="stale"
                class="wb-stale"
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
                class="wb-topbar"
                :class="{ 'wb-topbar--alert': data !== null && !allGood }"
                role="banner"
            >
                <h1 class="wb-title">{{ name }}</h1>
                <template v-if="data !== null && !allGood">
                    <div
                        class="wb-topbar-alerts"
                        role="alert"
                        aria-live="assertive"
                    >
                        <span class="wb-topbar-count" aria-hidden="true">{{
                            problemCount
                        }}</span>
                        <p class="wb-topbar-list">
                            {{ problemLabels.join(' · ') }}
                        </p>
                    </div>
                </template>
                <p
                    v-else-if="data !== null && allGood"
                    class="wb-topbar-ok"
                    role="status"
                >
                    {{ $t('ALL GOOD') }}
                </p>
                <p
                    v-else-if="data === null"
                    class="wb-topbar-wait"
                    role="status"
                >
                    …
                </p>
                <time
                    class="wb-clock numeric"
                    :datetime="clockIso"
                    :aria-label="$t('Local time')"
                >
                    {{ clockLabel }}
                </time>
            </header>

            <template v-if="data">
                <div
                    class="wb-board"
                    :class="{
                        'wb-board--hosts': sortedHosts.length > 0,
                    }"
                >
                    <section
                        v-if="sortedSites.length"
                        class="wb-pane wb-pane--sites"
                        :aria-label="$t('Websites')"
                    >
                        <ul class="wb-grid wb-grid--sites" role="list">
                            <WallboardSiteTile
                                v-for="site in sortedSites"
                                :key="site.id"
                                :site="site"
                            />
                        </ul>
                    </section>

                    <section
                        v-if="sortedHosts.length"
                        ref="hostPaneRef"
                        class="wb-pane wb-pane--hosts"
                        :aria-label="$t('Servers')"
                    >
                        <ul
                            class="wb-grid wb-grid--hosts"
                            role="list"
                            :style="{
                                gridTemplateColumns: `repeat(${hostGridCols}, minmax(0, 1fr))`,
                            }"
                        >
                            <WallboardHostTile
                                v-for="host in sortedHosts"
                                :key="host.id"
                                :host="host"
                            />
                        </ul>
                    </section>
                </div>

                <footer v-if="lastSuccess" class="wb-footer" aria-live="polite">
                    {{
                        $t('Last updated :date', {
                            date: since(new Date(lastSuccess).toISOString()),
                        })
                    }}
                </footer>
            </template>
        </div>
    </main>
</template>

<style scoped>
.wb-root {
    --wb-pad: clamp(0.35rem, 1.2vmin, 1rem);
    --wb-gap: clamp(0.2rem, 0.5vmin, 0.5rem);
    --wb-name: clamp(0.8rem, 2.35vmin, 1.45rem);
    --wb-title: clamp(0.95rem, 2.8vmin, 1.85rem);
    --wb-topbar: clamp(0.75rem, 1.85vmin, 1.15rem);
    --wb-alert: clamp(0.85rem, 2.4vmin, 1.75rem);
    --wb-host-min: clamp(11rem, 28vw, 16rem);
    --wb-host-name: clamp(0.85rem, 2vmin, 1.15rem);
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    padding: var(--wb-pad);
    color: var(--foreground);
    background: var(--background);
    container-type: size;
    container-name: wallboard;
}

.wb-aurora {
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0.65;
    background:
        radial-gradient(
            ellipse 70% 45% at 15% 0%,
            color-mix(in oklch, var(--primary) 25%, transparent),
            transparent 55%
        ),
        radial-gradient(
            ellipse 50% 35% at 90% 20%,
            color-mix(in oklch, var(--success) 15%, transparent),
            transparent 50%
        );
}

.wb-shell {
    position: relative;
    z-index: 1;
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--wb-gap);
    min-height: 0;
}

.wb-stale {
    flex-shrink: 0;
    padding: 0.35em 0.65em;
    font-size: var(--wb-alert);
    font-weight: 600;
    color: #111;
    background: var(--warning);
    border-radius: 0.35rem;
}

.wb-topbar {
    display: flex;
    flex-shrink: 0;
    gap: clamp(0.4rem, 1.2vmin, 0.85rem);
    align-items: center;
    min-height: 0;
    padding: 0.25em 0.45em;
    border: 1px solid color-mix(in oklch, var(--border) 80%, transparent);
    border-radius: 0.35rem;
    background: color-mix(in oklch, var(--card) 88%, transparent);
}

.wb-topbar--alert {
    border-color: var(--danger);
    background: color-mix(in oklch, var(--danger) 22%, var(--card));
}

.wb-title {
    flex-shrink: 0;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: var(--font-display, system-ui), sans-serif;
    font-size: var(--wb-title);
    font-weight: 700;
    line-height: 1.1;
    white-space: nowrap;
}

.wb-topbar-alerts {
    display: flex;
    flex: 1;
    gap: 0.45em;
    align-items: center;
    min-width: 0;
}

.wb-topbar-count {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    min-width: 1.65em;
    padding: 0.1em 0.35em;
    font-size: calc(var(--wb-topbar) * 1.05);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    color: var(--danger);
    background: #fff;
    border-radius: 0.25rem;
}

.wb-topbar-list {
    margin: 0;
    overflow: hidden;
    font-size: var(--wb-topbar);
    font-weight: 700;
    line-height: 1.15;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.wb-topbar-ok {
    flex: 1;
    margin: 0;
    font-size: var(--wb-topbar);
    font-weight: 700;
    color: color-mix(in oklch, var(--success) 85%, white);
    text-align: left;
}

.wb-topbar-wait {
    flex: 1;
    margin: 0;
    font-size: var(--wb-topbar);
    color: var(--muted-foreground);
}

.wb-clock {
    flex-shrink: 0;
    margin-left: auto;
    font-size: var(--wb-topbar);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
    color: var(--muted-foreground);
    white-space: nowrap;
}

.wb-footer {
    flex-shrink: 0;
    font-size: calc(var(--wb-alert) * 0.75);
    font-weight: 500;
    color: var(--muted-foreground);
    text-align: right;
}

.wb-incidents {
    flex-shrink: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: calc(var(--wb-alert) * 0.9);
    font-weight: 600;
    white-space: nowrap;
}

.wb-board {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--wb-gap);
    min-height: 0;
}

.wb-board--hosts .wb-pane--sites {
    flex: 1;
    min-height: 0;
}

.wb-pane--sites {
    flex: 1;
    min-height: 0;
}

.wb-pane {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}

.wb-pane--hosts {
    flex: 0 0 clamp(8rem, 34cqh, 22rem);
}

.wb-grid {
    display: grid;
    gap: clamp(3px, 0.45vmin, 6px);
    height: 100%;
    margin: 0;
    padding: 0;
    list-style: none;
}

.wb-grid--sites {
    flex: 1;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    align-content: start;
    grid-template-columns: repeat(auto-fill, minmax(min(20rem, 100%), 1fr));
    grid-auto-rows: auto;
}

.wb-grid--hosts {
    align-content: start;
}

@container wallboard (min-height: 700px) {
    .wb-pane--hosts {
        flex-basis: clamp(9rem, 30cqh, 24rem);
    }
}

.wb-pane--hosts {
    --wb-host-name: clamp(0.85rem, 2vmin, 1.2rem);
}

@keyframes wb-pulse {
    0%,
    100% {
        filter: brightness(1);
    }

    50% {
        filter: brightness(1.12);
    }
}
</style>
