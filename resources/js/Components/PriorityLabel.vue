<script setup>
import PriorityIcon from '@/Components/PriorityIcon.vue';

defineProps({
    priority: { type: String, required: true },
});
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-sm font-medium whitespace-nowrap text-gray-700 capitalize dark:text-gray-300">
        <PriorityIcon :priority="priority" aria-hidden="true" />
        {{ priority }}
    </span>
</template>
