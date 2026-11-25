<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
onBeforeUnmount(() => {
    clearInterval(timer);
    clearInterval(tick);
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

    return items;
});

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

            <header class="wb-header">
                <h1 class="wb-title">{{ name }}</h1>
                <p
                    class="wb-status"
                    :class="
                        data === null
                            ? 'wb-status--wait'
                            : allGood
                              ? 'wb-status--ok'
                              : 'wb-status--bad'
                    "
                    role="status"
                >
                    <span class="wb-status-dot" aria-hidden="true" />
                    {{
                        data === null
                            ? '…'
                            : allGood
                              ? $t('ALL GOOD')
                              : $t('ATTENTION')
                    }}
                </p>
            </header>

            <template v-if="data">
                <section
                    v-if="!allGood"
                    class="wb-alert"
                    role="alert"
                    aria-live="assertive"
                >
                    <p class="wb-alert-kicker">{{ $t('ATTENTION') }}</p>
                    <p class="wb-alert-list">
                        {{ problemLabels.join(' · ') }}
                    </p>
                </section>

                <div
                    class="wb-board"
                    :class="{
                        'wb-board--hosts': sortedHosts.length > 0,
                    }"
                >
                    <section
                        v-if="sortedSites.length"
                        class="wb-pane"
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
                        class="wb-pane wb-pane--hosts"
                        :aria-label="$t('Servers')"
                    >
                        <ul class="wb-grid wb-grid--hosts" role="list">
                            <WallboardHostTile
                                v-for="host in sortedHosts"
                                :key="host.id"
                                :host="host"
                            />
                        </ul>
                    </section>
                </div>
            </template>
        </div>
    </main>
</template>

<style scoped>
.wb-root {
    --wb-pad: clamp(0.35rem, 1.2vmin, 1rem);
    --wb-gap: clamp(0.2rem, 0.5vmin, 0.5rem);
    --wb-name: clamp(0.8rem, 2.35vmin, 1.45rem);
    --wb-title: clamp(1rem, 4vmin, 2.75rem);
    --wb-status: clamp(0.95rem, 3.2vmin, 2.5rem);
    --wb-alert: clamp(0.85rem, 2.4vmin, 1.75rem);
    --wb-site-min: clamp(6.25rem, 15vmin, 11rem);
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

.wb-header {
    display: flex;
    flex-shrink: 0;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    justify-content: space-between;
}

.wb-title {
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: var(--font-display, system-ui), sans-serif;
    font-size: var(--wb-title);
    font-weight: 600;
    line-height: 1.05;
    white-space: nowrap;
}

.wb-status {
    display: flex;
    gap: 0.4em;
    align-items: center;
    margin: 0;
    padding: 0.25em 0.65em;
    font-size: var(--wb-status);
    font-weight: 700;
    border-radius: 0.35rem;
}

.wb-status-dot {
    width: 0.55em;
    height: 0.55em;
    border-radius: 50%;
    background: currentcolor;
}

.wb-status--ok {
    color: #0a0a0a;
    background: var(--success);
}

.wb-status--bad {
    color: #fff;
    background: var(--danger);
    animation: wb-pulse 1.6s ease-in-out infinite;
}

.wb-status--wait {
    color: var(--muted-foreground);
    background: var(--muted);
}

.wb-alert {
    flex-shrink: 0;
    padding: 0.45em 0.65em;
    background: color-mix(in oklch, var(--danger) 88%, black);
    border: 2px solid var(--danger);
    border-radius: 0.35rem;
}

.wb-alert-kicker {
    margin: 0;
    font-size: calc(var(--wb-alert) * 0.85);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    opacity: 0.9;
}

.wb-alert-list {
    margin: 0.15em 0 0;
    font-size: var(--wb-alert);
    font-weight: 700;
    line-height: 1.2;
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

.wb-board--hosts .wb-pane:first-child {
    flex: 1;
    min-height: 0;
}

.wb-pane {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    background: color-mix(in oklch, var(--card) 85%, transparent);
    border: 1px solid var(--border);
    border-radius: 0.4rem;
}

.wb-pane--hosts {
    flex: 0 0 clamp(8rem, 34cqh, 22rem);
}

.wb-grid {
    display: grid;
    gap: clamp(2px, 0.35vmin, 5px);
    align-content: start;
    height: 100%;
    margin: 0;
    padding: clamp(3px, 0.4vmin, 6px);
    overflow: hidden auto;
    list-style: none;
}

.wb-grid--sites {
    grid-template-columns: repeat(
        auto-fill,
        minmax(min(var(--wb-site-min), 100%), 1fr)
    );
}

.wb-grid--hosts {
    grid-template-columns: repeat(
        auto-fill,
        minmax(min(var(--wb-host-min), 100%), 1fr)
    );
}

@container wallboard (min-width: 900px) {
    .wb-root {
        --wb-site-min: clamp(7rem, 11vmin, 12rem);
        --wb-name: clamp(0.9rem, 1.85vmin, 1.55rem);
    }
}

@container wallboard (min-height: 700px) {
    .wb-pane--hosts {
        flex-basis: clamp(9rem, 30cqh, 24rem);
    }
}

.wb-pane--hosts {
    --wb-host-name: clamp(0.85rem, 2vmin, 1.2rem);
}

:deep(.wb-tile) {
    display: flex;
    flex-direction: column;
    gap: 0.25em;
    align-items: center;
    justify-content: center;
    min-height: clamp(3.75rem, 10vmin, 6.5rem);
    padding: 0.35em 0.4em;
    text-align: center;
    border: 1px solid color-mix(in oklch, var(--border) 80%, transparent);
    border-radius: 0.3rem;
}

:deep(.wb-dot) {
    flex-shrink: 0;
    width: clamp(0.65rem, 1.8vmin, 1.1rem);
    height: clamp(0.65rem, 1.8vmin, 1.1rem);
    border-radius: 50%;
    background: var(--muted-foreground);
}

:deep(.wb-tile-name) {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-size: var(--wb-name);
    font-weight: 600;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

:deep(.wb-tile-meta) {
    font-size: calc(var(--wb-name) * 0.8);
    font-weight: 600;
    line-height: 1.1;
    color: var(--warning);
}

:deep(.wb-tile--up) {
    background: color-mix(in oklch, var(--card) 95%, var(--success) 5%);
}

:deep(.wb-tile--up .wb-dot) {
    background: var(--success);
}

:deep(.wb-tile--down) {
    background: color-mix(in oklch, var(--danger) 35%, var(--card));
    border-color: var(--danger);
    animation: wb-pulse 1.5s ease-in-out infinite;
}

:deep(.wb-tile--down .wb-dot) {
    background: var(--danger);
}

:deep(.wb-tile--unknown .wb-dot),
:deep(.wb-tile--maint .wb-dot) {
    background: var(--warning);
}

:deep(.wb-tile--warn) {
    border-color: var(--warning);
}

:deep(.wb-tile--warn .wb-dot) {
    background: var(--warning);
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