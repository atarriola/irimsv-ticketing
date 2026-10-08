<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MaintenanceNotice from '@/Components/MaintenanceNotice.vue';
import TicketForm from '@/Components/TicketForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    types: Array,
    priorities: Array,
    categories: Array,
    defaultType: String,
    canSetPriority: Boolean,
    // The forum thread the ticket is raised out of, if any: { id, excerpt, body }.
    thread: Object,
    maintenanceNotice: Object,
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <Head title="Create ticket" />

        <header class="flex flex-col gap-1">
            <Link href="/tickets" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Back to tickets</Link>
            <h1 class="text-2xl font-semibold tracking-tight">Create ticket</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Tell us what is happening and how urgent it is.</p>
        </header>

        <MaintenanceNotice :notice="maintenanceNotice" />

        <p v-if="thread" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            This ticket is raised from the forum thread
            <Link :href="`/forum/threads/${thread.id}`" class="font-medium underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:decoration-gray-600 dark:hover:decoration-gray-100">“{{ thread.excerpt }}”</Link>.
            The thread's message is filled in below; edit it as needed.
        </p>

        <TicketForm
            action="/tickets"
            cancel-href="/tickets"
            submit-label="Create"
            :types="types"
            :priorities="priorities"
            :categories="categories"
            :can-set-priority="canSetPriority"
            :thread-id="thread?.id ?? null"
            suggest-similar
            :ticket="{ type: defaultType, priority: 'medium', category_id: null, subject: thread?.excerpt ?? '', description: thread?.body ?? '', is_shared: false }"
        />
    </div>
</template>
