<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
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
        <Head title="Edit account" />

        <header class="flex flex-col gap-1">
            <Link href="/account" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Back to my account</Link>
            <h1 class="text-2xl font-semibold tracking-tight">Edit account</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">The helpdesk and LRMIS share the same accounts, so these changes apply in LRMIS as well.</p>
        </header>

        <Form
            action="/account"
            method="patch"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <div class="flex flex-col gap-1">
                <h2 class="text-sm font-semibold">Your details</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    You sign in as <span class="font-medium text-gray-900 dark:text-gray-100">{{ account.username }}</span>, which cannot be changed here.
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <FormField id="firstname" name="firstname" label="First name" autocomplete="given-name" :initial-value="account.firstname" :error="errors.firstname" required />

                <FormField id="middlename" name="middlename" label="Middle name" autocomplete="additional-name" :initial-value="account.middlename ?? ''" :error="errors.middlename" />

                <FormField id="lastname" name="lastname" label="Last name" autocomplete="family-name" :initial-value="account.lastname" :error="errors.lastname" required />

                <FormField id="extension_name" name="extension_name" label="Extension name" placeholder="Jr., Sr., III" :initial-value="account.extension_name ?? ''" :error="errors.extension_name" />
            </div>

            <FormField id="email" name="email" type="email" label="Email address" autocomplete="email" :initial-value="account.email" :error="errors.email" required />

            <FormField id="contact_number" name="contact_number" type="tel" label="Contact number" autocomplete="tel" :initial-value="account.contact_number ?? ''" :error="errors.contact_number" />

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
