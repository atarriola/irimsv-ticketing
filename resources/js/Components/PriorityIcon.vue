<script setup>
defineProps({
    priority: { type: String, required: true },
});

// Jira's convention: arrows point up for urgent work and down for work that can wait, so the shape carries the meaning as well as the colour.
const priorities = {
    critical: { name: 'Critical', classes: 'text-red-600 dark:text-red-400', paths: ['m5.25 11.25 6.75-6.75 6.75 6.75', 'm5.25 18 6.75-6.75L18.75 18'] },
    high: { name: 'High', classes: 'text-orange-500 dark:text-orange-400', paths: ['m5.25 15 6.75-6.75L18.75 15'] },
    medium: { name: 'Medium', classes: 'text-amber-500 dark:text-amber-400', paths: ['M5.25 9.5h13.5', 'M5.25 14.5h13.5'] },
    low: { name: 'Low', classes: 'text-blue-500 dark:text-blue-400', paths: ['m5.25 9 6.75 6.75L18.75 9'] },
};
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="size-4 shrink-0"
        :class="priorities[priority].classes"
        role="img"
        :aria-label="`${priorities[priority].name} priority`"
    >
        <title>{{ priorities[priority].name }} priority</title>
        <path v-for="path in priorities[priority].paths" :key="path" :d="path" />
    </svg>
</template>
