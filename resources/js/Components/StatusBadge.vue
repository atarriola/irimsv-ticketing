<script setup>
defineProps({
    status: { type: String, required: true },
});

const labels = {
    open: 'Open',
    in_progress: 'In progress',
    resolved: 'Resolved',
    closed: 'Closed',
};

const classes = {
    open: 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300',
    resolved: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300',
    closed: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
};
</script>

<template>
    <span class="inline-flex rounded px-1.5 py-0.5 text-[0.6875rem] font-bold tracking-wide whitespace-nowrap uppercase" :class="classes[status]">
        {{ labels[status] }}
    </span>
</template>
