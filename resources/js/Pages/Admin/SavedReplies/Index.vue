<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    replies: Array,
});

function deleteReply(reply) {
    router.delete(`/admin/saved-replies/${reply.id}`, {
        preserveScroll: true,
        onBefore: () => confirm(`Delete the saved reply "${reply.title}"?`),
    });
}

const linkClasses = 'text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100';
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Saved replies" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">Saved replies</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Messages the helpdesk sends often. Every administrator can drop them into a ticket conversation with one click.</p>
            </div>
            <Link
                href="/admin/saved-replies/create"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300 dark:focus-visible:outline-gray-100"
            >
                New saved reply
            </Link>
        </header>

        <p v-if="replies.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            No saved replies yet. Write the answers you type most often, such as how to reset a password or what to include in a bug report.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Title</th>
                        <th scope="col" class="px-5 py-3 font-medium">Written by</th>
                        <th scope="col" class="px-5 py-3 font-medium">Updated</th>
                        <th scope="col" class="px-5 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="reply in replies" :key="reply.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="w-full max-w-0 min-w-64 px-5 py-3">
                            <div class="flex flex-col gap-0.5">
                                <span class="truncate font-medium">{{ reply.title }}</span>
                                <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ reply.excerpt }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ reply.author ?? '—' }}</td>
                        <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ reply.updated_at }}</td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="flex justify-end gap-4 text-sm font-medium">
                                <Link :href="`/admin/saved-replies/${reply.id}/edit`" :class="linkClasses">Edit</Link>
                                <button type="button" class="cursor-pointer text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400" @click="deleteReply(reply)">Delete</button>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
