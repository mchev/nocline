<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';

const props = withDefaults(
    defineProps<{
        text: string;
        /** Names the copied content for screen readers (several bare "Copy" buttons are indistinguishable). */
        label: string;
        /** Stack copy control under the text (modals, long one-liners). */
        stacked?: boolean;
    }>(),
    { stacked: false },
);

const state = ref<'idle' | 'copied' | 'failed'>('idle');
const message = ref('');
let timer: ReturnType<typeof setTimeout> | undefined;

async function copy() {
    clearTimeout(timer);
    message.value = '';

    try {
        await navigator.clipboard.writeText(props.text);
        state.value = 'copied';
    } catch {
        state.value = 'failed';
    }

    requestAnimationFrame(() => {
        message.value =
            state.value === 'copied' ? t('Copied') : t('Copy failed');
    });
    timer = setTimeout(() => {
        state.value = 'idle';
        message.value = '';
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div
        class="surface-muted p-3"
        :class="stacked ? 'flex flex-col gap-3' : 'flex items-start gap-2'"
    >
        <pre
            class="min-w-0 text-sm select-all"
            :class="
                stacked
                    ? 'max-h-32 overflow-y-auto break-all whitespace-pre-wrap'
                    : 'flex-1 overflow-x-auto'
            "
            tabindex="0"
        ><code>{{ text }}</code></pre>
        <span
            class="relative flex shrink-0 items-center gap-2"
            :class="stacked ? 'justify-end' : ''"
        >
            <Button
                type="button"
                size="icon"
                variant="outline"
                class="size-10 rounded-md"
                :aria-label="t('Copy: :what', { what: label })"
                @click="copy"
            >
                <Check
                    v-if="state === 'copied'"
                    class="size-4"
                    aria-hidden="true"
                />
                <Copy v-else class="size-4" aria-hidden="true" />
            </Button>
            <span role="status" class="text-xs text-muted-foreground">{{
                message
            }}</span>
        </span>
    </div>
</template>
