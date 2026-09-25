<script setup>
defineProps({
    notification: { type: Object, required: true },
});

defineEmits(['open']);
</script>

<template>
    <button
        type="button"
        class="flex w-full cursor-pointer items-start gap-3 px-3 py-2.5 text-left hover:bg-gray-100 dark:hover:bg-gray-700"
        :class="notification.is_read ? '' : 'bg-gray-50 dark:bg-gray-700/50'"
        @click="$emit('open', notification)"
    >
        <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="notification.is_read ? 'bg-transparent' : 'bg-red-600'" aria-hidden="true"></span>
        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="text-sm text-gray-900 dark:text-gray-100" :class="notification.is_read ? '' : 'font-semibold'">
                <span v-if="!notification.is_read" class="sr-only">Unread:</span>
                {{ notification.message }}
            </span>
            <span v-if="notification.excerpt" class="truncate text-xs text-gray-600 dark:text-gray-400">{{ notification.excerpt }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ notification.created_at }}</span>
        </span>
    </button>
</template>
