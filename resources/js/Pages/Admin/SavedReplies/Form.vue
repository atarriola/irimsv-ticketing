<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import TextAreaField from '@/Components/TextAreaField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    // The saved reply being edited, or null when writing a new one: { id, title, body }.
    reply: Object,
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <Head :title="reply ? `Edit ${reply.title}` : 'New saved reply'" />

        <header class="flex flex-col gap-1">
            <Link href="/admin/saved-replies" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Back to saved replies</Link>
            <h1 class="text-2xl font-semibold tracking-tight">{{ reply ? `Edit ${reply.title}` : 'New saved reply' }}</h1>
        </header>

        <Form
            :action="reply ? `/admin/saved-replies/${reply.id}` : '/admin/saved-replies'"
            :method="reply ? 'put' : 'post'"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <FormField id="title" name="title" label="Title" placeholder="How it appears in the picker, such as “Ask for a screenshot”" :initial-value="reply?.title ?? ''" :error="errors.title" required />

            <TextAreaField id="body" name="body" label="Message" placeholder="The message as it will be sent." :rows="8" :initial-value="reply?.body ?? ''" :error="errors.body" required />

            <div class="flex items-center justify-end gap-3">
                <Link href="/admin/saved-replies" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">Cancel</Link>
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : reply ? 'Save changes' : 'Create saved reply' }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
