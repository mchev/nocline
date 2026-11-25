<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowUpCircle } from '@lucide/vue';
import { computed } from 'vue';
import { t } from '@/lib/i18n';

type Update = { version: string; url: string };

const update = computed(() => usePage().props.update as Update | null);
const installed = computed(() => usePage().props.version as string);
</script>

<template>
    <aside
        v-if="update"
        role="status"
        class="mx-4 mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md border border-info/35 bg-info/10 px-3 py-2 text-sm md:mx-6"
    >
        <ArrowUpCircle class="size-5 shrink-0 text-info" aria-hidden="true" />
        <span class="font-medium">
            {{
                t('Nocline :version is available (you have :current).', {
                    version: update.version,
                    current: installed,
                })
            }}
        </span>
        <a
            :href="update.url"
            target="_blank"
            rel="noopener noreferrer"
            class="font-medium text-info underline underline-offset-2"
        >
            {{ t('See what is new') }}
        </a>
        <code class="chip font-mono text-[0.7rem] chip-info"
            >scripts/update.sh</code
        >
    </aside>
</template>
