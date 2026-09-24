<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    paginator: { type: Object, required: true },
});

const buttonClasses =
    'rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-medium hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800';
</script>

<template>
    <nav v-if="paginator.last_page > 1" class="flex items-center justify-between gap-4 text-sm" aria-label="Pagination">
        <span class="text-gray-600 dark:text-gray-400">Page {{ paginator.current_page }} of {{ paginator.last_page }}</span>
        <span class="flex gap-2">
            <Link v-if="paginator.prev_page_url" :href="paginator.prev_page_url" :class="buttonClasses">Previous</Link>
            <Link v-if="paginator.next_page_url" :href="paginator.next_page_url" :class="buttonClasses">Next</Link>
        </span>
    </nav>
</template>
