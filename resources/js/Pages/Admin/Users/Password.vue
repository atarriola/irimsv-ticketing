<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/Components/FormField.vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    account: Object,
});

const rows = computed(() => [
    { label: 'Full name', value: props.account.name },
    { label: 'Username', value: props.account.username },
    { label: 'Email address', value: props.account.email },
    { label: 'Position', value: props.account.position },
]);
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <Head :title="`Reset password for ${account.name}`" />

        <header class="flex flex-col gap-1">
            <Link href="/admin/users" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Back to users</Link>
            <h1 class="text-2xl font-semibold tracking-tight">Reset password</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Set a new password for this LRMIS account. It applies in LRMIS as well, and the account is signed out everywhere it is logged in.
            </p>
        </header>

        <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <dl class="flex flex-col gap-4 text-sm">
                <div v-for="row in rows" :key="row.label" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-4">
                    <dt class="w-40 shrink-0 text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                    <dd class="min-w-0 font-medium break-words">{{ row.value || '—' }}</dd>
                </div>
            </dl>
        </section>

        <Form
            :action="`/admin/users/${account.id}/password`"
            method="put"
            :reset-on-error="['password', 'password_confirmation']"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <FormField id="password" name="password" type="password" label="New password" autocomplete="new-password" :error="errors.password" required autofocus />

            <FormField id="password_confirmation" name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />

            <div class="flex items-center justify-end gap-3">
                <Link href="/admin/users" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">Cancel</Link>
                <SubmitButton :processing="processing">{{ processing ? 'Resetting…' : 'Reset password' }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
