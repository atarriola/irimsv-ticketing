<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    // Whose turn it is: "support" or "requester"; nothing is shown when the ticket waits on nobody.
    waitingOn: { type: String, default: null },
    label: { type: String, default: null },
});

const classes = {
    support: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    requester: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
};
</script>

<template>
    <span v-if="waitingOn" class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[0.6875rem] font-semibold whitespace-nowrap" :class="classes[waitingOn]">
        <AppIcon name="clock" class="size-3" />
        {{ label ?? (waitingOn === 'support' ? 'Waiting on support' : 'Waiting on requester') }}
    </span>
</template>
