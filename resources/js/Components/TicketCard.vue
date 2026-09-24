<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import PriorityIcon from '@/Components/PriorityIcon.vue';
import TicketTypeIcon from '@/Components/TicketTypeIcon.vue';

defineProps({
    ticket: { type: Object, required: true },
    showRequester: Boolean,
});
</script>

<template>
    <Link
        :href="`/tickets/${ticket.id}`"
        prefetch
        class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-gray-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-500 dark:focus-visible:outline-gray-100"
    >
        <span class="line-clamp-3 text-sm break-words text-gray-900 dark:text-gray-100">{{ ticket.subject }}</span>

        <span v-if="ticket.category || showRequester" class="flex flex-wrap gap-1.5">
            <span v-if="ticket.category" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ ticket.category }}</span>
            <span v-if="showRequester" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ ticket.requester }}</span>
        </span>

        <span class="flex items-center justify-between gap-2">
            <span class="flex min-w-0 items-center gap-1.5">
                <TicketTypeIcon :type="ticket.type_key" :label="ticket.type" />
                <span class="truncate text-xs font-semibold text-gray-600 dark:text-gray-400" :class="ticket.status === 'closed' ? 'line-through' : ''">{{ ticket.key }}</span>
            </span>

            <span class="flex shrink-0 items-center gap-2">
                <span v-if="ticket.comments_count > 0" class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400" :title="`${ticket.comments_count} comments`">
                    <AppIcon name="comment" class="size-3.5" />
                    {{ ticket.comments_count }}
                </span>
                <PriorityIcon :priority="ticket.priority" />
            </span>
        </span>
    </Link>
</template>
