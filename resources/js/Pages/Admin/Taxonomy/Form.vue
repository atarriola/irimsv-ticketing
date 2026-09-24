<script setup>
import { Form, Head, Link } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import SubmitButton from '@/Components/SubmitButton.vue';
import TextAreaField from '@/Components/TextAreaField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    resource: Object,
    item: Object,
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <Head :title="item ? `Edit ${item.name}` : `New ${resource.singular}`" />

        <header class="flex flex-col gap-1">
            <Link :href="resource.baseUrl" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                &larr; Back to {{ resource.plural.toLowerCase() }}
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">{{ item ? `Edit ${item.name}` : `New ${resource.singular}` }}</h1>
        </header>

        <Form
            :action="item ? `${resource.baseUrl}/${item.key}` : resource.baseUrl"
            :method="item ? 'put' : 'post'"
            class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <FormField id="name" name="name" label="Name" :initial-value="item?.name ?? ''" :error="errors.name" required />

            <TextAreaField id="description" name="description" label="Description (optional)" :rows="3" :initial-value="item?.description ?? ''" :error="errors.description" />

            <FormField
                v-if="resource.hasPosition"
                id="position"
                name="position"
                type="number"
                label="Order (lower numbers are listed first)"
                :initial-value="item?.position ?? 0"
                :error="errors.position"
                required
            />

            <div class="flex items-center justify-end gap-3">
                <Link :href="resource.baseUrl" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">Cancel</Link>
                <SubmitButton :processing="processing">{{ processing ? 'Saving…' : item ? 'Save changes' : `Create ${resource.singular}` }}</SubmitButton>
            </div>
        </Form>
    </div>
</template>
