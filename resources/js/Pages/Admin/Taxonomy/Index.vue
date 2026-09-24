<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    resource: Object,
    items: Array,
});

function deleteItem(item) {
    router.delete(`${props.resource.baseUrl}/${item.key}`, {
        preserveScroll: true,
        onBefore: () => confirm(`Delete the ${item.name} ${props.resource.singular}? ${props.resource.deleteWarning}`),
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="resource.plural" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">{{ resource.plural }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ resource.description }}</p>
            </div>
            <Link
                :href="`${resource.baseUrl}/create`"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-gray-100 dark:text-gray-900 transition hover:bg-gray-700 dark:hover:bg-gray-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:focus-visible:outline-gray-100"
            >
                New {{ resource.singular }}
            </Link>
        </header>

        <p v-if="items.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            Nothing here yet.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th v-if="resource.hasPosition" scope="col" class="px-5 py-3 font-medium">Order</th>
                        <th scope="col" class="px-5 py-3 font-medium">Name</th>
                        <th scope="col" class="px-5 py-3 font-medium">{{ resource.countLabel }}</th>
                        <th scope="col" class="px-5 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="item in items" :key="item.key" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td v-if="resource.hasPosition" class="px-5 py-3 text-gray-600 tabular-nums dark:text-gray-400">{{ item.position }}</td>
                        <td class="w-full max-w-0 min-w-48 px-5 py-3">
                            <div class="flex flex-col gap-0.5">
                                <span class="truncate font-medium">{{ item.name }}</span>
                                <span v-if="item.description" class="truncate text-xs text-gray-500 dark:text-gray-400">{{ item.description }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600 tabular-nums dark:text-gray-400">{{ item.count }}</td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="flex justify-end gap-4 text-sm font-medium">
                                <Link :href="`${resource.baseUrl}/${item.key}/edit`" class="text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100">Edit</Link>
                                <button type="button" class="cursor-pointer text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400" @click="deleteItem(item)">Delete</button>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
