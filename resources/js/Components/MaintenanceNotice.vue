<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    // The latest maintenance post, or null when none went out recently: { id, title, excerpt, url }.
    notice: { type: Object, default: null },
});
</script>

<template>
    <div
        v-if="notice"
        role="status"
        class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-100"
    >
        <AppIcon name="wrench" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
        <span class="flex min-w-0 flex-col gap-0.5">
            <span class="font-semibold">Maintenance notice: {{ notice.title }}</span>
            <span class="line-clamp-2 text-amber-800 dark:text-amber-200/80">{{ notice.excerpt }}</span>
            <Link :href="notice.url" class="w-fit font-medium underline decoration-amber-400 underline-offset-4 hover:decoration-amber-800">Read the notice</Link>
        </span>
    </div>
</template>
