<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { t } from '@/lib/i18n';

const props = withDefaults(
    defineProps<{
        text: string;
        /** Names the copied content for screen readers (several bare "Copy" buttons are indistinguishable). */
        label: string;
        /** @deprecated Layout is always GitHub-style (code + copy on one row). */
        stacked?: boolean;
    }>(),
    { stacked: false },
);

const state = ref<'idle' | 'copied' | 'failed'>('idle');
let timer: ReturnType<typeof setTimeout> | undefined;

async function copy() {
    clearTimeout(timer);

    try {
        await navigator.clipboard.writeText(props.text);
        state.value = 'copied';
    } catch {
        state.value = 'failed';
    }

    timer = setTimeout(() => {
        state.value = 'idle';
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="copy-block min-w-0">
        <pre class="copy-block__pre" tabindex="0"><code>{{ text }}</code></pre>
        <button
            type="button"
            class="copy-block__action"
            :aria-label="t('Copy: :what', { what: label })"
            @click="copy"
        >
            <Check
                v-if="state === 'copied'"
                class="size-4"
                aria-hidden="true"
            />
            <Copy v-else class="size-4" aria-hidden="true" />
            <span class="sr-only" role="status" aria-live="polite">
                {{
                    state === 'copied'
                        ? t('Copied')
                        : state === 'failed'
                          ? t('Copy failed')
                          : ''
                }}
            </span>
        </button>
    </div>
</template>

<style scoped>
.copy-block {
    display: flex;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid color-mix(in oklch, var(--border) 92%, transparent);
    border-radius: calc(var(--radius) + 2px);
    background: color-mix(in oklch, var(--muted) 55%, var(--card));
}

.copy-block__pre {
    min-width: 0;
    flex: 1;
    margin: 0;
    overflow-x: auto;
    padding: 0.55rem 0.75rem;
    font-family:
        ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas,
        'Liberation Mono', monospace;
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--foreground);
    white-space: pre;
    user-select: all;
}

.copy-block__pre code {
    font-family: inherit;
}

.copy-block__action {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    border: 0;
    border-left: 1px solid color-mix(in oklch, var(--border) 92%, transparent);
    background: color-mix(in oklch, var(--muted) 35%, transparent);
    color: var(--muted-foreground);
    cursor: pointer;
    transition:
        background-color 0.15s ease,
        color 0.15s ease;
}

.copy-block__action:hover {
    color: var(--foreground);
    background: color-mix(in oklch, var(--muted) 70%, var(--card));
}

.copy-block__action:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: -2px;
}
</style>
