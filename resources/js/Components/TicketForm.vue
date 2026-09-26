<script setup>
import { Form, Link } from '@inertiajs/vue3';
import AttachmentsField from '@/Components/AttachmentsField.vue';
import FormField from '@/Components/FormField.vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import TextAreaField from '@/Components/TextAreaField.vue';

defineProps({
    action: { type: String, required: true },
    method: { type: String, default: 'post' },
    cancelHref: { type: String, required: true },
    submitLabel: { type: String, required: true },
    types: { type: Array, required: true },
    priorities: { type: Array, required: true },
    categories: { type: Array, required: true },
    ticket: { type: Object, default: () => ({ type: 'bug_report', priority: 'medium', category_id: null, subject: '', description: '' }) },
});

const typeDescriptions = {
    bug_report: 'Something is broken or behaves incorrectly.',
    problem: 'You are stuck or something is not working for you.',
    feature_request: 'An idea for something new or improved.',
};

// The first complaint about the images, whether about the set as a whole or about one file.
function attachmentError(errors) {
    return errors.attachments ?? Object.entries(errors).find(([key]) => key.startsWith('attachments.'))?.[1];
}

const selectClasses =
    'w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 transition outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-100 dark:focus:ring-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';

const choiceClasses =
    'cursor-pointer rounded-lg border border-gray-300 bg-white transition hover:border-gray-400 has-checked:border-gray-900 has-checked:bg-gray-50 has-focus-visible:ring-1 has-focus-visible:ring-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600 dark:has-checked:border-gray-100 dark:has-checked:bg-gray-800 dark:has-focus-visible:ring-gray-100';
</script>

<template>
    <Form
        :action="action"
        method="post"
        class="flex flex-col gap-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        #default="{ errors, processing }"
    >
        <!-- Images travel as multipart form data, which only a POST can carry; Laravel reads the real method from _method. -->
        <input v-if="method !== 'post'" type="hidden" name="_method" :value="method" />

        <fieldset class="flex flex-col gap-2">
            <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">What kind of ticket is this?</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <label v-for="type in types" :key="type.value" class="flex flex-col gap-1 p-4" :class="choiceClasses">
                    <input type="radio" name="type" :value="type.value" :checked="type.value === ticket.type" class="sr-only" />
                    <span class="text-sm font-semibold">{{ type.label }}</span>
                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ typeDescriptions[type.value] }}</span>
                </label>
            </div>
            <p v-if="errors.type" class="text-sm text-red-600 dark:text-red-400">{{ errors.type }}</p>
        </fieldset>

        <fieldset class="flex flex-col gap-2">
            <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">How urgent is it?</legend>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <label v-for="priority in priorities" :key="priority.value" class="px-4 py-2.5 text-center text-sm font-medium" :class="choiceClasses">
                    <input type="radio" name="priority" :value="priority.value" :checked="priority.value === ticket.priority" class="sr-only" />
                    {{ priority.label }}
                </label>
            </div>
            <p v-if="errors.priority" class="text-sm text-red-600 dark:text-red-400">{{ errors.priority }}</p>
        </fieldset>

        <div class="flex flex-col gap-1.5">
            <label for="category_id" class="text-sm font-medium text-gray-700 dark:text-gray-300">Category <span class="font-normal text-gray-500">(optional)</span></label>
            <select id="category_id" name="category_id" :class="selectClasses">
                <option value="" :selected="ticket.category_id === null">No category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id" :selected="category.id === ticket.category_id">{{ category.name }}</option>
            </select>
            <p v-if="errors.category_id" class="text-sm text-red-600 dark:text-red-400">{{ errors.category_id }}</p>
        </div>

        <FormField id="subject" name="subject" label="Subject" placeholder="A short summary" :initial-value="ticket.subject" :error="errors.subject" required />

        <TextAreaField
            id="description"
            name="description"
            label="Description"
            placeholder="What happened, what you expected, and any steps to reproduce it."
            :initial-value="ticket.description"
            :error="errors.description"
            required
        />

        <AttachmentsField
            :existing="ticket.attachments ?? []"
            :remove-base-url="ticket.id ? `/tickets/${ticket.id}/attachments` : null"
            label="Screenshots"
            hint="Up to 5 images of 5 MB each, as JPG, PNG, GIF or WebP."
            :error="attachmentError(errors)"
        />

        <div class="flex items-center justify-end gap-3">
            <Link :href="cancelHref" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">Cancel</Link>
            <SubmitButton :processing="processing">{{ processing ? 'Saving…' : submitLabel }}</SubmitButton>
        </div>
    </Form>
</template>
