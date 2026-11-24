import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch, type Ref } from 'vue';

type ListOptions = {
    itemHeight: number;
    gap?: number;
    max?: number;
    min?: number;
};

type GridOptions = {
    itemMinWidth: number;
    itemMinHeight: number;
    gap?: number;
    max?: number;
    min?: number;
};

function observe(
    el: Ref<HTMLElement | null>,
    measure: (width: number, height: number) => void,
): void {
    let frame = 0;
    let observer: ResizeObserver | undefined;

    const run = (width: number, height: number) => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => measure(width, height));
    };

    onMounted(() => {
        watch(
            el,
            (node, _, onCleanup) => {
                observer?.disconnect();
                observer = undefined;

                if (!node) {
                    return;
                }

                run(node.clientWidth, node.clientHeight);
                observer = new ResizeObserver((entries) => {
                    const entry = entries[0];
                    if (!entry) {
                        return;
                    }

                    const box = entry.contentBoxSize?.[0];
                    const width = box?.inlineSize ?? entry.contentRect.width;
                    const height = box?.blockSize ?? entry.contentRect.height;
                    run(width, height);
                });
                observer.observe(node);

                onCleanup(() => observer?.disconnect());
            },
            { immediate: true },
        );
    });

    onBeforeUnmount(() => {
        cancelAnimationFrame(frame);
        observer?.disconnect();
    });
}

/** How many list rows fit in the observed element. */
export function useListFit(options: ListOptions) {
    const el = ref<HTMLElement | null>(null);
    const count = shallowRef(options.min ?? 1);
    const gap = options.gap ?? 0;

    observe(el, (_width, height) => {
        if (height <= 0) {
            return;
        }

        const raw = Math.floor((height + gap) / (options.itemHeight + gap));
        count.value = Math.max(options.min ?? 1, Math.min(options.max ?? 50, raw || 1));
    });

    return { el, count };
}

/** How many grid cells fit (cols × rows) in the observed element. */
export function useGridFit(options: GridOptions) {
    const el = ref<HTMLElement | null>(null);
    const cols = shallowRef(1);
    const rows = shallowRef(1);
    const gap = options.gap ?? 12;

    observe(el, (width, height) => {
        if (width <= 0 || height <= 0) {
            return;
        }

        const nextCols = Math.max(1, Math.floor((width + gap) / (options.itemMinWidth + gap)));
        const nextRows = Math.max(1, Math.floor((height + gap) / (options.itemMinHeight + gap)));
        const capacity = Math.max(options.min ?? 1, Math.min(options.max ?? 50, nextCols * nextRows));

        cols.value = nextCols;
        rows.value = Math.max(1, Math.ceil(capacity / nextCols));
    });

    const capacity = computed(() => Math.max(options.min ?? 1, Math.min(options.max ?? 50, cols.value * rows.value)));

    return { el, cols, rows, capacity };
}
