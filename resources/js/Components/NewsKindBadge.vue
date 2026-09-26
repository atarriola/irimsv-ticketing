<script setup>
defineProps({
    kind: { type: String, required: true },
});

const labels = {
    event: 'Event',
    release: 'New feature',
    update: 'Update',
    maintenance: 'Maintenance',
    plan: 'Future plan',
    announcement: 'Announcement',
};

const classes = {
    event: 'bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-300',
    release: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300',
    update: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
    maintenance: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300',
    plan: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    announcement: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300',
};
</script>

<template>
    <span class="inline-flex rounded px-1.5 py-0.5 text-[0.6875rem] font-bold tracking-wide whitespace-nowrap uppercase" :class="classes[kind]">
        {{ labels[kind] }}
    </span>
</template>
