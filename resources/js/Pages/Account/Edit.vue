<script setup>
import { Form, Head } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    account: Object,
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <Head title="My account" />

        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">My account</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Update your details and change your password.</p>
        </header>

        <Form
            action="/account"
            method="patch"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <h2 class="text-sm font-semibold">Your details</h2>

            <FormField id="name" name="name" label="Full name" autocomplete="name" :initial-value="account.name" :error="errors.name" required />

            <FormField id="email" name="email" type="email" label="Email address" autocomplete="username" :initial-value="account.email" :error="errors.email" required />

            <div class="flex justify-end">
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : 'Save details' }}</SubmitButton>
            </div>
        </Form>

        <Form
            action="/account/password"
            method="put"
            reset-on-success
            :reset-on-error="['current_password', 'password', 'password_confirmation']"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <h2 class="text-sm font-semibold">Change password</h2>

            <FormField id="current_password" name="current_password" type="password" label="Current password" autocomplete="current-password" :error="errors.current_password" required />

            <FormField id="password" name="password" type="password" label="New password" autocomplete="new-password" :error="errors.password" required />

            <FormField id="password_confirmation" name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />

            <div class="flex justify-end">
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : 'Change password' }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
