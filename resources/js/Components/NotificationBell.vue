<script setup>
import { Link, router, useHttp, usePage, usePoll } from '@inertiajs/vue3';
import { echoIsConfigured, useEchoNotification } from '@laravel/echo-vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import NotificationItem from '@/Components/NotificationItem.vue';

// How often the unread count is refreshed: rarely when a WebSocket delivers notifications as they happen, otherwise often.
const hasSocket = echoIsConfigured();
const REFRESH_EVERY_MS = hasSocket ? 120000 : 30000;
// How long a freshly arrived notification stays on screen as a toast.
const TOAST_MS = 6000;

const tabs = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const unreadCount = ref(page.props.unreadNotifications ?? 0);
const isOpen = ref(false);
const filter = ref('all');
const notifications = ref([]);
const hasLoaded = ref(false);
const toast = ref(null);
const bell = ref(null);
const loader = useHttp({});
const marker = useHttp({});
let toastTimer = null;

// The shared prop is refreshed by the poll and by every page load; whatever it says is the truth.
watch(
    () => page.props.unreadNotifications,
    (count) => {
        if (count !== undefined) {
            unreadCount.value = count;
        }
    },
);

usePoll(REFRESH_EVERY_MS, { only: ['unreadNotifications'] }, { mode: 'rest' });

function load(onLoaded = null) {
    loader.get(`/notifications?filter=${filter.value}`, {
        headers: { Accept: 'application/json' },
        onSuccess: (response) => {
            notifications.value = response.data;
            unreadCount.value = response.unread_count;
            hasLoaded.value = true;
            onLoaded?.(response.data);
        },
    });
}

function toggle() {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        load();
    }
}

function show(tab) {
    filter.value = tab;
    load();
}

function markAllRead() {
    marker.post('/notifications/read', {
        onSuccess: () => {
            notifications.value = notifications.value.map((notification) => ({ ...notification, is_read: true }));
            unreadCount.value = 0;
        },
    });
}

function open(notification) {
    isOpen.value = false;
    hideToast();

    if (!notification.is_read) {
        notification.is_read = true;
        unreadCount.value = Math.max(unreadCount.value - 1, 0);
        marker.put(`/notifications/${notification.id}/read`, {
            onSuccess: (response) => (unreadCount.value = response.unread_count),
        });
    }

    if (notification.url) {
        router.visit(notification.url);
    }
}

function showToast(notification) {
    toast.value = notification;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, TOAST_MS);
}

function hideToast() {
    clearTimeout(toastTimer);
    toast.value = null;
}

if (hasSocket) {
    // The server pushes each notification as it is stored; the list is reloaded so the toast and the panel show it exactly as stored.
    useEchoNotification(`App.Models.User.${user.value.id}`, () => load((latest) => latest[0] && !latest[0].is_read && showToast(latest[0])));
}

function closeOnOutsideClick(event) {
    if (!bell.value?.contains(event.target)) {
        isOpen.value = false;
    }
}

watch(isOpen, (open) => {
    if (open) {
        document.addEventListener('click', closeOnOutsideClick);
    } else {
        document.removeEventListener('click', closeOnOutsideClick);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutsideClick);
    clearTimeout(toastTimer);
});
</script>

<template>
    <div ref="bell" class="relative shrink-0" @keydown.esc="isOpen = false">
        <button
            type="button"
            class="relative flex size-9 cursor-pointer items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
            :aria-label="unreadCount > 0 ? `Notifications, ${unreadCount} unread` : 'Notifications'"
            aria-haspopup="dialog"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white tabular-nums"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div
            v-if="isOpen"
            class="absolute right-0 mt-2 flex w-80 max-w-[calc(100vw-2rem)] flex-col rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
            role="dialog"
            aria-label="Notifications"
        >
            <div class="flex items-center justify-between px-3 pt-2.5 pb-1.5">
                <span class="text-base font-semibold">Notifications</span>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    :disabled="marker.processing"
                    class="cursor-pointer text-xs font-medium text-gray-600 hover:underline disabled:opacity-60 dark:text-gray-400"
                    @click="markAllRead"
                >
                    Mark all as read
                </button>
            </div>

            <div class="flex gap-1.5 border-b border-gray-100 px-3 pb-2 dark:border-gray-700" role="group" aria-label="Show">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition"
                    :class="
                        filter === tab.value
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                    "
                    :aria-pressed="filter === tab.value"
                    @click="show(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <p v-if="loader.processing && !hasLoaded" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Loading…</p>

            <p v-else-if="notifications.length === 0" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ filter === 'unread' ? 'No unread notifications.' : 'Nothing yet. You will hear about comments on your threads and replies to your comments here.' }}
            </p>

            <ul v-else class="max-h-96 overflow-y-auto py-1">
                <li v-for="notification in notifications" :key="notification.id">
                    <NotificationItem :notification="notification" @open="open" />
                </li>
            </ul>

            <Link
                href="/notifications"
                class="border-t border-gray-100 px-3 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="isOpen = false"
            >
                See all notifications
            </Link>
        </div>

        <div v-if="toast" role="status" class="fixed right-4 bottom-4 z-30 w-80 max-w-[calc(100vw-2rem)]">
            <button
                type="button"
                class="flex w-full cursor-pointer flex-col gap-0.5 rounded-lg border border-gray-200 bg-white px-4 py-3 text-left shadow-lg hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                @click="open(toast)"
            >
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ toast.message }}</span>
                <span v-if="toast.excerpt" class="truncate text-xs text-gray-600 dark:text-gray-400">{{ toast.excerpt }}</span>
            </button>
        </div>
    </div>
</template>
