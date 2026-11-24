<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type Payload = {
    name: string;
    generated_at: string;
    summary: {
        up: number;
        down: number;
        unknown: number;
        maintenance: number;
        hosts_online: number;
        hosts_total: number;
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
    checks_failing: {
        id: number;
        name: string;
        since: string | null;
        latency_ms: number | null;
        error: string | null;
    }[];
};

const props = defineProps<{ token: string; name: string }>();

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
        data.value = await res.json();
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
        class="dark relative min-h-svh overflow-hidden bg-background p-6 text-foreground lg:p-10"
    >
        <div
            class="pointer-events-none absolute inset-0 aurora opacity-80"
            aria-hidden="true"
        />
        <div class="relative flex flex-col gap-8">
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

            <header class="flex flex-wrap items-center justify-between gap-4">
                <h1
                    class="font-display text-4xl font-semibold tracking-tight text-balance lg:text-5xl"
                >
                    {{ name }}
                </h1>
                <div
                    class="flex items-center gap-4 rounded-md px-8 py-3 text-3xl font-bold lg:text-4xl"
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
                <section
                    class="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6"
                    :aria-label="$t('Summary')"
                >
                    <div class="surface p-6 text-center lg:p-8">
                        <div
                            class="numeric text-7xl font-bold text-success lg:text-8xl"
                        >
                            {{ data.summary.up }}
                        </div>
                        <div class="mt-2 text-2xl text-muted-foreground">
                            {{ $t('checks OK') }}
                        </div>
                    </div>
                    <div
                        class="surface p-6 text-center lg:p-8"
                        :class="data.summary.down ? 'border-danger' : ''"
                    >
                        <div
                            class="numeric text-7xl font-bold lg:text-8xl"
                            :class="
                                data.summary.down
                                    ? 'text-danger'
                                    : 'text-muted-foreground/50'
                            "
                        >
                            {{ data.summary.down }}
                        </div>
                        <div class="mt-2 text-2xl text-muted-foreground">
                            {{ $t('down') }}
                        </div>
                    </div>
                    <div class="surface p-6 text-center lg:p-8">
                        <div
                            class="numeric text-7xl font-bold text-foreground/80 lg:text-8xl"
                        >
                            {{ data.summary.unknown }}
                        </div>
                        <div class="mt-2 text-2xl text-muted-foreground">
                            {{ $t('unknown') }}
                        </div>
                        <div
                            v-if="data.summary.maintenance"
                            class="mt-1 text-xl text-info"
                        >
                            {{ data.summary.maintenance }}
                            {{ $t('in maintenance') }}
                        </div>
                    </div>
                    <div class="surface p-6 text-center lg:p-8">
                        <div
                            class="numeric text-7xl font-bold text-highlight lg:text-8xl"
                        >
                            {{ data.summary.hosts_online }}/{{
                                data.summary.hosts_total
                            }}
                        </div>
                        <div class="mt-2 text-2xl text-muted-foreground">
                            {{ $t('hosts online') }}
                        </div>
                    </div>
                </section>

                <section
                    v-if="data.active_incidents.length"
                    class="rounded-md border border-danger bg-danger/15 p-6"
                >
                    <h2 class="mb-3 font-display text-3xl font-semibold">
                        {{ $t('Unresolved incidents') }}
                    </h2>
                    <ul class="space-y-2 text-2xl">
                        <li v-for="i in data.active_incidents" :key="i.id">
                            {{ i.title }} — {{ since(i.since) }}
                            <span
                                v-if="i.acknowledged"
                                class="text-muted-foreground"
                            >
                                ({{ $t('ack') }})
                            </span>
                        </li>
                    </ul>
                </section>

                <section v-if="data.checks_failing.length">
                    <h2 class="mb-3 font-display text-3xl font-semibold">
                        {{ $t('Ongoing failures') }}
                    </h2>
                    <ul class="space-y-2 text-2xl">
                        <li
                            v-for="c in data.checks_failing"
                            :key="c.id"
                            class="surface flex flex-wrap justify-between gap-2 px-6 py-3"
                        >
                            <span class="font-medium text-danger">{{
                                c.name
                            }}</span>
                            <span class="text-muted-foreground">
                                {{ c.error }} · {{ since(c.since) }}
                            </span>
                        </li>
                    </ul>
                </section>

                <section v-if="data.hosts.length">
                    <h2 class="mb-3 font-display text-3xl font-semibold">
                        {{ $t('Servers') }}
                    </h2>
                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            v-for="h in data.hosts"
                            :key="h.id"
                            class="surface p-5"
                            :class="
                                h.online ? '' : 'border-danger opacity-60'
                            "
                        >
                            <div class="truncate text-2xl font-semibold">
                                {{ h.name }}
                            </div>
                            <div
                                class="numeric mt-2 grid grid-cols-3 gap-2 text-xl font-semibold"
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
                            </div>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </main>
</template>
