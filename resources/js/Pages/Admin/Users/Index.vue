<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = ref(props.filters.q ?? '');

function submitSearch() {
    router.get('/admin/users', search.value ? { q: search.value } : {}, { preserveState: true, replace: true });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Users" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">Users</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Accounts come from LRMIS. Its administrators run the helpdesk and everyone else is a member.</p>
            </div>

            <form class="flex w-full gap-2 sm:w-auto" role="search" @submit.prevent="submitSearch">
                <label for="q" class="sr-only">Search users</label>
                <input
                    id="q"
                    v-model="search"
                    type="search"
                    placeholder="Name, username or email"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm text-gray-900 transition outline-none placeholder:text-gray-400 focus:border-gray-900 focus:ring-1 focus:ring-gray-900 sm:w-72 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-gray-100 dark:focus:ring-gray-100"
                />
                <button
                    type="submit"
                    class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300 dark:focus-visible:outline-gray-100"
                >
                    Search
                </button>
            </form>
        </header>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">User</th>
                        <th scope="col" class="px-5 py-3 font-medium">Position</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium">Role</th>
                        <th scope="col" class="px-5 py-3 font-medium">Tickets</th>
                        <th scope="col" class="px-5 py-3 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-if="users.data.length === 0">
                        <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No LRMIS accounts match your search.</td>
                    </tr>
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="w-full max-w-0 min-w-48 px-5 py-3">
                            <div class="flex flex-col gap-0.5">
                                <span class="truncate font-medium">{{ user.name }}</span>
                                <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.username }} &middot; {{ user.email }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ user.position }}</td>
                        <td class="px-5 py-3 whitespace-nowrap" :class="user.is_active ? 'text-gray-600 dark:text-gray-400' : 'text-amber-700 dark:text-amber-400'">{{ user.status }}</td>
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
                        <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ user.created_at ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="users" />
    </div>
</template>
