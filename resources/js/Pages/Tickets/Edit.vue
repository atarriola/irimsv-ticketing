<script setup>
import { Head, Link } from '@inertiajs/vue3';
import TicketForm from '@/Components/TicketForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    types: Array,
    priorities: Array,
    categories: Array,
    ticket: Object,
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <Head :title="`Edit ${ticket.key}`" />

        <header class="flex flex-col gap-1">
            <Link :href="`/tickets/${ticket.id}`" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Back to {{ ticket.key }}</Link>
            <h1 class="text-2xl font-semibold tracking-tight">Edit {{ ticket.key }}</h1>
        </header>

        <TicketForm
            :action="`/tickets/${ticket.id}`"
            method="put"
            :cancel-href="`/tickets/${ticket.id}`"
            submit-label="Save changes"
            :types="types"
            :priorities="priorities"
            :categories="categories"
            :ticket="ticket"
        />
    </div>
</template>
