<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    account: Object,
    roles: Array,
    canChangeRole: Boolean,
});

const selectClasses =
    'w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 transition outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-100 dark:focus:ring-gray-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <Head :title="account ? `Edit ${account.name}` : 'New user'" />

        <header class="flex flex-col gap-1">
            <Link href="/admin/users" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Back to users</Link>
            <h1 class="text-2xl font-semibold tracking-tight">{{ account ? `Edit ${account.name}` : 'New user' }}</h1>
        </header>

        <Form
            :action="account ? `/admin/users/${account.id}` : '/admin/users'"
            :method="account ? 'put' : 'post'"
            :reset-on-error="['password', 'password_confirmation']"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <FormField id="name" name="name" label="Full name" autocomplete="off" :initial-value="account?.name ?? ''" :error="errors.name" required />

            <FormField id="email" name="email" type="email" label="Email address" autocomplete="off" :initial-value="account?.email ?? ''" :error="errors.email" required />

            <div class="flex flex-col gap-1.5">
                <label for="role" class="text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                <select id="role" :name="canChangeRole ? 'role' : undefined" :disabled="!canChangeRole" :class="selectClasses">
                    <option v-for="role in roles" :key="role.value" :value="role.value" :selected="role.value === (account?.role ?? 'user')">{{ role.label }}</option>
                </select>
                <input v-if="!canChangeRole" type="hidden" name="role" :value="account.role" />
                <p v-if="!canChangeRole" class="text-xs text-gray-500 dark:text-gray-400">You cannot change your own role.</p>
                <p v-if="errors.role" class="text-sm text-red-600 dark:text-red-400">{{ errors.role }}</p>
            </div>

            <div class="flex flex-col gap-5 border-t border-gray-200 pt-5 dark:border-gray-800">
                <p v-if="account" class="text-sm text-gray-600 dark:text-gray-400">Leave the password fields empty to keep the current password.</p>

                <FormField id="password" name="password" type="password" :label="account ? 'New password' : 'Password'" autocomplete="new-password" :error="errors.password" :required="!account" />

                <FormField
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    :label="account ? 'Confirm new password' : 'Confirm password'"
                    autocomplete="new-password"
                    :required="!account"
                />
            </div>

            <div class="flex items-center justify-end gap-3">
                <Link href="/admin/users" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">Cancel</Link>
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : account ? 'Save changes' : 'Create user' }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
