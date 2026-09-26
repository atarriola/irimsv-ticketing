<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
import AttachmentsField from '@/Components/AttachmentsField.vue';
import FormField from '@/Components/FormField.vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import TextAreaField from '@/Components/TextAreaField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    kinds: Array,
    post: Object,
});

// The first complaint about the files, whether about the set as a whole or about one of them.
function attachmentError(errors) {
    return errors.attachments ?? Object.entries(errors).find(([key]) => key.startsWith('attachments.'))?.[1];
}

const selectClasses =
    'w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 transition outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-100 dark:focus:ring-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <Head :title="post ? 'Edit post' : 'Write a post'" />

        <header class="flex flex-col gap-1">
            <Link :href="post ? `/news/${post.id}` : '/news'" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                &larr; {{ post ? 'Back to the post' : 'Back to news' }}
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">{{ post ? 'Edit post' : 'Write a post' }}</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Share an event, a new feature, an update, a maintenance notice or what is planned next with everyone.</p>
        </header>

        <Form
            :action="post ? `/news/${post.id}` : '/news'"
            method="post"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing, progress }"
        >
            <!-- Files travel as multipart form data, which only a POST can carry; Laravel reads the real method from _method. -->
            <input v-if="post" type="hidden" name="_method" value="put" />

            <FormField id="title" name="title" label="Title" placeholder="What is this about?" :initial-value="post?.title ?? ''" :error="errors.title" required autofocus />

            <div class="flex flex-col gap-1.5">
                <label for="kind" class="text-sm font-medium text-gray-700 dark:text-gray-300">Kind</label>
                <select id="kind" name="kind" required :class="selectClasses">
                    <option v-for="kind in kinds" :key="kind.value" :value="kind.value" :selected="kind.value === (post?.kind ?? 'announcement')">{{ kind.label }}</option>
                </select>
                <p v-if="errors.kind" class="text-sm text-red-600 dark:text-red-400">{{ errors.kind }}</p>
            </div>

            <TextAreaField id="body" name="body" label="Body" :rows="12" placeholder="Write the full story here." :initial-value="post?.body ?? ''" :error="errors.body" required />

            <AttachmentsField
                :existing="post?.attachments ?? []"
                :remove-base-url="post ? `/news/${post.id}/attachments` : null"
                :max="10"
                allow-videos
                label="Photos and videos"
                hint="Up to 10 files: JPG, PNG, GIF or WebP images of 5 MB each, and MP4 or WebM videos of 50 MB each."
                :error="attachmentError(errors)"
            />

            <div class="flex flex-col gap-1.5">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <input type="checkbox" name="is_published" value="1" :checked="post?.is_published ?? true" class="mt-0.5 size-4 accent-gray-900 dark:accent-white" />
                    <span class="flex flex-col gap-0.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Published</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Unticked, the post is kept as a draft that only administrators can see.</span>
                    </span>
                </label>
                <p v-if="errors.is_published" class="text-sm text-red-600 dark:text-red-400">{{ errors.is_published }}</p>
            </div>

            <!-- Videos take a while to send, so the upload shows how far along it is. -->
            <div v-if="progress" role="status" class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <span class="block h-full rounded-full bg-gray-900 transition-[width] dark:bg-white" :style="{ width: `${progress.percentage}%` }" />
                </span>
                <span class="shrink-0 tabular-nums">Uploading {{ progress.percentage }}%</span>
            </div>

            <div class="flex items-center justify-end gap-3">
                <Link :href="post ? `/news/${post.id}` : '/news'" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">Cancel</Link>
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : post ? 'Save changes' : 'Save post' }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
