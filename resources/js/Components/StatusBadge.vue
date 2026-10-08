<script setup>
defineProps({
    status: { type: String, required: true },
});

const labels = {
    open: 'Open',
    under_review: 'Under review',
    planned: 'Planned',
    in_progress: 'In progress',
    resolved: 'Resolved',
    shipped: 'Shipped',
    closed: 'Closed',
};

const classes = {
    open: 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    under_review: 'bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-300',
    planned: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-300',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300',
    resolved: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300',
    shipped: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
    closed: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
};
</script>

<template>
    <span class="inline-flex rounded px-1.5 py-0.5 text-[0.6875rem] font-bold tracking-wide whitespace-nowrap uppercase" :class="classes[status]">
        {{ labels[status] ?? status }}
    </span>
</template>
