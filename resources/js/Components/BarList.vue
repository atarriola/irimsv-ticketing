<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: String,
    items: { type: Array, required: true },
    unit: { type: String, default: 'tickets' },
});

const total = computed(() => props.items.reduce((sum, item) => sum + item.count, 0));
const largest = computed(() => Math.max(...props.items.map((item) => item.count), 1));

function share(item) {
    return total.value === 0 ? 0 : Math.round((item.count / total.value) * 100);
}
</script>

<template>
    <section class="hud-corners flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <header class="flex flex-col gap-0.5">
            <h2 class="text-sm font-semibold">{{ title }}</h2>
            <p v-if="subtitle" class="text-xs text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
        </header>

        <p v-if="total === 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Nothing to show yet.</p>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="item in items"
                :key="item.key"
                class="grid grid-cols-[8rem_minmax(0,1fr)] items-center gap-3 rounded-md text-sm"
                :title="`${item.label}: ${item.count} of ${total} ${unit} (${share(item)}%)`"
            >
                <span class="truncate text-gray-600 dark:text-gray-400">{{ item.label }}</span>
                <span class="flex items-center gap-2">
                    <span
                        class="h-1.5 min-w-0.5 origin-left animate-hud-grow rounded-full bg-hud"
                        :style="{ width: `calc((100% - 2.5rem) * ${item.count / largest})` }"
                        aria-hidden="true"
                    ></span>
                    <span class="font-medium tabular-nums">{{ item.count }}</span>
                </span>
            </li>
        </ul>
    </section>
</template>
