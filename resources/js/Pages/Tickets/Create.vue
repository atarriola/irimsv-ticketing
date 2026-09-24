<script setup>
import { Head, Link } from '@inertiajs/vue3';
import TicketForm from '@/Components/TicketForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    types: Array,
    priorities: Array,
    categories: Array,
    defaultType: String,
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

        <TicketForm
            action="/tickets"
            cancel-href="/tickets"
            submit-label="Create"
            :types="types"
            :priorities="priorities"
            :categories="categories"
            :ticket="{ type: defaultType, priority: 'medium', category_id: null, subject: '', description: '' }"
        />
    </div>
</template>
