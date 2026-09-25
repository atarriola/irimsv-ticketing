<script setup>
import { Head, InfiniteScroll, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import NotificationItem from '@/Components/NotificationItem.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    filter: String,
    notifications: Object,
});

const page = usePage();
const unreadCount = computed(() => page.props.unreadNotifications ?? 0);
const marker = useHttp({});

const tabs = [
    { value: 'all', label: 'All', href: '/notifications' },
    { value: 'unread', label: 'Unread', href: '/notifications?filter=unread' },
];

function markAllRead() {
    marker.post('/notifications/read', {
        onSuccess: () => router.reload({ only: ['notifications', 'unreadNotifications'], reset: ['notifications'] }),
    });
}

function open(notification) {
    if (!notification.is_read) {
        marker.put(`/notifications/${notification.id}/read`);
    }

    if (notification.url) {
        router.visit(notification.url);
    }
}

// Return to wherever the bell was opened from; a page opened directly has nowhere to go back to, so it goes to the dashboard.
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/dashboard');
    }
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
        <Head title="Notifications" />

        <button type="button" class="w-fit cursor-pointer text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100" @click="goBack">&larr; Back</button>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">Notifications</h1>
            <button
                v-if="unreadCount > 0"
                type="button"
                :disabled="marker.processing"
                class="cursor-pointer text-sm font-medium text-gray-600 hover:underline disabled:opacity-60 dark:text-gray-400"
                @click="markAllRead"
            >
                Mark all as read
            </button>
        </div>

        <nav class="flex gap-2" aria-label="Show">
            <Link
                v-for="tab in tabs"
                :key="tab.value"
                :href="tab.href"
                class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                :class="
                    filter === tab.value
                        ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900'
                        : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-100'
                "
                :aria-current="filter === tab.value ? 'page' : undefined"
            >
                {{ tab.label }}
                <span v-if="tab.value === 'unread' && unreadCount > 0" class="ml-1 tabular-nums">({{ unreadCount }})</span>
            </Link>
        </nav>

        <p v-if="notifications.data.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            {{ filter === 'unread' ? 'No unread notifications.' : 'Nothing yet. You will hear about comments on your threads and replies to your comments here.' }}
        </p>

        <InfiniteScroll v-else data="notifications" :manual-after="3" class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
            <NotificationItem v-for="notification in notifications.data" :key="notification.id" :notification="notification" @open="open" />

            <template #next="{ loading, fetch, hasMore }">
                <div v-if="hasMore" class="flex justify-center p-3">
                    <button type="button" :disabled="loading" class="cursor-pointer rounded-full px-4 py-1.5 text-sm font-medium text-gray-900 hover:bg-gray-100 disabled:opacity-60 dark:text-gray-100 dark:hover:bg-gray-800" @click="fetch">
                        {{ loading ? 'Loading…' : 'Load more' }}
                    </button>
                </div>
            </template>
        </InfiniteScroll>
    </div>
</template>
