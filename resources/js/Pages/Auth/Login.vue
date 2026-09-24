<script setup>
import { Form, Head } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
</script>

<template>
    <AuthLayout title="Sign in with your LRMIS account">
        <Head title="Log in" />

        <Form action="/login" method="post" :reset-on-error="['password']" class="flex flex-col gap-5" #default="{ errors, processing }">
            <FormField id="username" name="username" label="LRMIS username" autocomplete="username" placeholder="your.username" :error="errors.username" required autofocus />

            <FormField id="password" name="password" type="password" label="Password" autocomplete="current-password" :error="errors.password" required />

            <label class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-gray-600 dark:text-gray-400">
                <input name="remember" type="checkbox" value="1" class="size-4 cursor-pointer rounded border-gray-300 accent-gray-900 dark:accent-gray-100 dark:border-gray-700" />
                Keep me signed in
            </label>

            <SubmitButton :processing="processing">{{ processing ? 'Signing in…' : 'Sign in' }}</SubmitButton>
        </Form>
    </AuthLayout>
</template>
