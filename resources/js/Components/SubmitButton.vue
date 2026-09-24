<script setup>
defineProps({
    processing: Boolean,
});
</script>

<template>
    <button
        type="submit"
        :disabled="processing"
        class="flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white dark:bg-gray-100 dark:text-gray-900 transition hover:bg-gray-700 dark:hover:bg-gray-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:focus-visible:outline-gray-100 disabled:cursor-not-allowed disabled:opacity-70"
    >
        <svg v-if="processing" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="size-4 animate-spin" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <slot />
    </button>
</template>
