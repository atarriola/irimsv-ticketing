<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
import SubmitButton from '@/Components/SubmitButton.vue';
import TextAreaField from '@/Components/TextAreaField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    reply: Object,
    thread: Object,
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <Head title="Edit reply" />

        <header class="flex flex-col gap-1">
            <Link :href="`/forum/threads/${thread.id}`" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                &larr; Back to thread
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">Edit reply</h1>
            <p class="truncate text-sm text-gray-600 dark:text-gray-400">In &ldquo;{{ thread.excerpt }}&rdquo;</p>
        </header>

        <Form
            :action="`/forum/replies/${reply.id}`"
            method="put"
            class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <TextAreaField id="body" name="body" label="Your reply" :initial-value="reply.body" :error="errors.body" required />

            <div class="flex items-center justify-end gap-3">
                <Link :href="`/forum/threads/${thread.id}`" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">Cancel</Link>
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : 'Save changes' }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
