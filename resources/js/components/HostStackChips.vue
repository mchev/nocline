<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';

const ORDER = [
    'php',
    'node',
    'mysql',
    'mariadb',
    'postgresql',
    'redis',
    'nginx',
    'docker',
] as const;

type StackKey = (typeof ORDER)[number];

const LABELS: Record<StackKey, string> = {
    php: 'PHP',
    node: 'Node',
    mysql: 'MySQL',
    mariadb: 'MariaDB',
    postgresql: 'PostgreSQL',
    redis: 'Redis',
    nginx: 'Nginx',
    docker: 'Docker',
};

const props = defineProps<{
    stack?: Record<string, string> | null;
}>();

const items = computed(() => {
    const stack = props.stack;

    if (!stack) {
        return [];
    }

    return ORDER.flatMap((key) => {
        const version = stack[key];

        if (!version) {
            return [];
        }

        return [
            {
                key,
                label: LABELS[key],
                version,
                title: `${LABELS[key]} ${version}`,
            },
        ];
    });
});
</script>

<template>
    <div
        v-if="items.length"
        class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
        :aria-label="t('Server software')"
    >
        <span
            v-for="item in items"
            :key="item.key"
            class="chip font-mono tabular-nums"
            :title="item.title"
        >
            {{ item.label }} {{ item.version }}
        </span>
    </div>
</template>