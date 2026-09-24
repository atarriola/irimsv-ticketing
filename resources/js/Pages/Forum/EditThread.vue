<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
import SubmitButton from '@/Components/SubmitButton.vue';
import TextAreaField from '@/Components/TextAreaField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    topics: Array,
    types: Array,
    thread: Object,
});

const selectClasses =
    'w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 transition outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-100 dark:focus:ring-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
        <Head title="Edit thread" />

        <Link :href="`/forum/threads/${thread.id}`" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Back to thread</Link>

        <h1 class="text-2xl font-semibold tracking-tight">Edit thread</h1>

        <Form
            :action="`/forum/threads/${thread.id}`"
            method="put"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <TextAreaField id="body" name="body" label="Message" :rows="8" :initial-value="thread.body" :error="errors.body" required />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="forum_topic_id" class="text-sm font-medium text-gray-700 dark:text-gray-300">Topic</label>
                    <select id="forum_topic_id" name="forum_topic_id" required :class="selectClasses">
                        <option v-for="topic in topics" :key="topic.id" :value="topic.id" :selected="topic.id === thread.forum_topic_id">{{ topic.name }}</option>
                    </select>
                    <p v-if="errors.forum_topic_id" class="text-sm text-red-600 dark:text-red-400">{{ errors.forum_topic_id }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="type" class="text-sm font-medium text-gray-700 dark:text-gray-300">Kind of post</label>
                    <select id="type" name="type" required :class="selectClasses">
                        <option v-for="type in types" :key="type.value" :value="type.value" :selected="type.value === thread.type">{{ type.label }}</option>
                    </select>
                    <p v-if="errors.type" class="text-sm text-red-600 dark:text-red-400">{{ errors.type }}</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <Link :href="`/forum/threads/${thread.id}`" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">Cancel</Link>
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : 'Save changes' }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
