<script setup lang="ts">
import { Link, useHttp } from '@inertiajs/vue3';
import { CircleCheck, CircleHelp } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import SiteDetectionController from '@/actions/App/Modules/Checks/Http/Controllers/SiteDetectionController';
import { Skeleton } from '@/components/ui/skeleton';
import { t } from '@/lib/i18n';

type Suggestion = {
    key: string;
    label: string;
    description: string;
    default: boolean;
};
type Detection = {
    reachable: boolean;
    framework: 'laravel' | 'wordpress' | 'unknown';
    reasons: string[];
    summary: string;
    suggestions: Suggestion[];
};

const props = defineProps<{ url: string }>();
const watchHealth = defineModel<boolean>('watchHealth', { default: false });
const framework = defineModel<string>('framework', { default: '' });

const http = useHttp({ url: '' });
const loading = ref(false);
const result = ref<Detection | null>(null);
const fetchFailed = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let lastInspected = '';

function looksLikeAddress(value: string): boolean {
    return /^(https?:\/\/)?[^\s/]+\.[^\s/.]{2,}/i.test(value.trim());
}

function reset(): void {
    result.value = null;
    fetchFailed.value = false;
    watchHealth.value = false;
    framework.value = '';
}

async function inspect(): Promise<void> {
    const address = props.url.trim();
    clearTimeout(timer);

    if (!looksLikeAddress(address)) {
        http.cancel();
        loading.value = false;
        lastInspected = '';
        reset();

        return;
    }

    if (
        address === lastInspected &&
        (result.value !== null || fetchFailed.value)
    ) {
        return;
    }

    http.cancel();
    lastInspected = address;
    loading.value = true;
    fetchFailed.value = false;
    result.value = null;
    http.url = address;

    try {
        const response = (await http.submit(
            SiteDetectionController.post(),
        )) as Detection;

        if (address !== props.url.trim()) {
            return;
        }

        result.value = response;
        framework.value =
            response.framework === 'unknown' ? '' : response.framework;
        watchHealth.value = response.suggestions.some(
            (s) => s.key === 'watch_health' && s.default,
        );
    } catch {
        if (address === props.url.trim()) {
            result.value = null;
            fetchFailed.value = true;
            watchHealth.value = false;
            framework.value = '';
        }
    } finally {
        if (address === lastInspected) {
            loading.value = false;
        }
    }
}

watch(
    () => props.url,
    () => {
        clearTimeout(timer);
        lastInspected = '';
        timer = setTimeout(inspect, 600);
    },
);

onBeforeUnmount(() => {
    clearTimeout(timer);
    http.cancel();
});

defineExpose({ inspect });
</script>

<template>
    <div
        v-if="loading || result || fetchFailed"
        class="site-detection"
        aria-live="polite"
    >
        <div
            v-if="loading"
            aria-hidden="true"
            class="grid gap-2 surface-muted rounded-md p-4"
        >
            <Skeleton class="h-4 w-2/3" />
            <Skeleton class="h-4 w-1/2" />
            <Skeleton class="h-4 w-3/5" />
        </div>

        <div
            v-else-if="fetchFailed"
            class="rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            {{
                t(
                    'We could not check this address from here. You can still add it: we will keep trying.',
                )
            }}
        </div>

        <div
            v-else-if="result"
            class="grid gap-3 surface-muted rounded-md p-4 text-sm"
        >
            <p class="flex items-start gap-2 font-display font-semibold">
                <span
                    class="grid size-8 shrink-0 place-items-center rounded-md"
                    :class="
                        result.reachable
                            ? 'bg-success/15 text-success'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    <CircleCheck
                        v-if="result.reachable"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <CircleHelp v-else class="size-4" aria-hidden="true" />
                </span>
                <span class="min-w-0 pt-1">{{ result.summary }}</span>
            </p>

            <ul
                v-if="result.reachable && result.reasons.length"
                class="list-inside list-disc space-y-0.5 ps-10 text-xs text-muted-foreground"
            >
                <li v-for="(reason, i) in result.reasons" :key="i">
                    {{ reason }}
                </li>
            </ul>

            <ul
                v-if="result.suggestions.length"
                class="grid gap-3 border-t pt-3"
            >
                <li v-for="s in result.suggestions" :key="s.key">
                    <label
                        v-if="s.key === 'watch_health'"
                        class="flex min-h-11 cursor-pointer items-start gap-3"
                    >
                        <input
                            v-model="watchHealth"
                            type="checkbox"
                            class="mt-1 size-4 accent-primary"
                        />
                        <span>
                            <span class="block font-medium">{{ s.label }}</span>
                            <span class="field-hint mt-0.5 block">{{
                                s.description
                            }}</span>
                        </span>
                    </label>
                    <div v-else class="flex items-start gap-3">
                        <span
                            class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground"
                        >
                            <CircleCheck class="size-3.5" aria-hidden="true" />
                        </span>
                        <span>
                            <span class="block font-medium">{{ s.label }}</span>
                            <span class="field-hint mt-0.5 block">{{
                                s.description
                            }}</span>
                        </span>
                    </div>
                </li>
            </ul>

            <p
                v-if="result.framework === 'laravel'"
                class="border-t pt-3 text-muted-foreground"
            >
                {{ t('Using the Laravel scheduler?') }}
                <Link
                    href="/monitor/new?kind=heartbeat"
                    class="font-medium text-primary underline underline-offset-4"
                >
                    {{ t('Monitor your scheduled tasks too') }}
                </Link>
            </p>
        </div>
    </div>
</template>
