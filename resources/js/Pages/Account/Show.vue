<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    account: Object,
    preferences: Object,
    can: Object,
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

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">My account</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ can.update ? 'Keep the details and password of your administrator account up to date here.' : 'These details come from your LRMIS account. Update them, or change your password, in LRMIS.' }}
                </p>
            </div>
            <Link
                v-if="can.update"
                href="/account/edit"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300 dark:focus-visible:outline-gray-100"
            >
                Edit account
            </Link>
        </header>

        <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <dl class="flex flex-col gap-4 text-sm">
                <div v-for="row in rows" :key="row.label" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-4">
                    <dt class="w-40 shrink-0 text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                    <dd class="min-w-0 font-medium break-words">{{ row.value || '—' }}</dd>
                </div>
            </dl>
        </section>

        <section v-if="!preferences.email_enabled" class="flex flex-col gap-1 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold">Notifications</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">You are told in the app, through the bell, when something happens to your tickets. Email notifications are not set up on this help desk yet.</p>
        </section>

        <Form v-else action="/account/notifications" method="put" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900" #default="{ errors, processing }">
            <div class="flex flex-col gap-1">
                <h2 class="text-sm font-semibold">Notifications</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">You are always told in the app, through the bell, when something happens to your tickets. You can get an email as well.</p>
            </div>

            <label class="flex cursor-pointer items-start gap-3">
                <input type="hidden" name="email_notifications" value="0" />
                <input type="checkbox" name="email_notifications" value="1" :checked="preferences.email_notifications" :disabled="!preferences.has_email" class="mt-0.5 size-4 rounded border-gray-300 accent-gray-900 dark:border-gray-600 dark:accent-gray-100" />
                <span class="flex flex-col gap-0.5">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Email me about my tickets</span>
                    <span class="text-xs text-gray-600 dark:text-gray-400">
                        {{ preferences.has_email ? `Sent to ${account.email} when someone replies or the status changes.` : 'Your LRMIS account has no email address, so emails cannot be sent.' }}
                    </span>
                </span>
            </label>
            <p v-if="errors.email_notifications" class="text-sm text-red-600 dark:text-red-400">{{ errors.email_notifications }}</p>

            <div class="flex justify-end">
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : 'Save' }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
