<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    users: Object,
});

function deleteUser(user) {
    router.delete(`/admin/users/${user.id}`, {
        preserveScroll: true,
        onBefore: () => confirm(`Delete ${user.name}'s account? Their tickets, comments, threads and replies will be deleted too.`),
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Users" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">Users</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Create accounts, reset passwords and choose who is an administrator.</p>
            </div>
            <Link
                href="/admin/users/create"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-gray-100 dark:text-gray-900 transition hover:bg-gray-700 dark:hover:bg-gray-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:focus-visible:outline-gray-100"
            >
                New user
            </Link>
        </header>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">User</th>
                        <th scope="col" class="px-5 py-3 font-medium">Role</th>
                        <th scope="col" class="px-5 py-3 font-medium">Tickets</th>
                        <th scope="col" class="px-5 py-3 font-medium">Joined</th>
                        <th scope="col" class="px-5 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="w-full max-w-0 min-w-48 px-5 py-3">
                            <div class="flex flex-col gap-0.5">
                                <span class="truncate font-medium">{{ user.name }}</span>
                                <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span
                                class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                :class="
                                    user.is_admin
                                        ? 'bg-gray-900 text-white ring-gray-900 dark:bg-gray-100 dark:text-gray-900 dark:ring-gray-100'
                                        : 'text-gray-600 ring-gray-300 dark:text-gray-400 dark:ring-gray-700'
                                "
                            >
                                {{ user.is_admin ? 'Administrator' : 'Member' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-600 tabular-nums dark:text-gray-400">{{ user.tickets_count }}</td>
                        <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ user.created_at }}</td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="flex justify-end gap-4 text-sm font-medium">
                                <Link :href="`/admin/users/${user.id}/edit`" class="text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100">Edit</Link>
                                <button v-if="user.can.delete" type="button" class="cursor-pointer text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400" @click="deleteUser(user)">
                                    Delete
                                </button>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="users" />
    </div>
</template>
