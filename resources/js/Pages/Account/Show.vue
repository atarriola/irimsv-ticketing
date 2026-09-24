<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    account: Object,
});

const rows = computed(() => [
    { label: 'Full name', value: props.account.name },
    { label: 'Username', value: props.account.username },
    { label: 'Email address', value: props.account.email },
    { label: 'Contact number', value: props.account.contact_number },
    { label: 'Position', value: props.account.position },
    { label: 'Account status', value: props.account.status },
]);
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <Head title="My account" />

        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">My account</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">These details come from your LRMIS account. Update them, or change your password, in LRMIS.</p>
        </header>

        <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <dl class="flex flex-col gap-4 text-sm">
                <div v-for="row in rows" :key="row.label" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-4">
                    <dt class="w-40 shrink-0 text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                    <dd class="min-w-0 font-medium break-words">{{ row.value || '—' }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
