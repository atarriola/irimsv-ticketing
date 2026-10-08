<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    // The ticket's events, oldest first: { id, kind, actor, actor_is_admin, description, happened_at, happened_on }.
    events: { type: Array, required: true },
});

// Only the latest few are shown until asked for the rest, so a long-running ticket stays readable.
const SHOWN_BY_DEFAULT = 5;

const isExpanded = ref(false);
const shown = computed(() => (isExpanded.value || props.events.length <= SHOWN_BY_DEFAULT ? props.events : props.events.slice(-SHOWN_BY_DEFAULT)));
const hiddenCount = computed(() => props.events.length - shown.value.length);

const icons = {
    created: 'plus',
    status_changed: 'progress',
    priority_changed: 'warning',
    edited: 'pencil',
    rated: 'star',
    release_linked: 'rocket',
    deleted: 'trash',
    restored: 'restore',
};
</script>

<template>
    <section class="flex flex-col rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" aria-label="Timeline">
        <h2 class="flex items-center gap-2 border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">
            Timeline
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 tabular-nums dark:bg-gray-800 dark:text-gray-400">{{ events.length }}</span>
        </h2>

        <p v-if="events.length === 0" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nothing has happened to this ticket yet.</p>

        <ol v-else class="flex flex-col gap-3 p-4">
            <li v-if="hiddenCount > 0">
                <button type="button" class="cursor-pointer text-xs font-medium text-gray-600 hover:underline dark:text-gray-400" @click="isExpanded = true">Show {{ hiddenCount }} earlier {{ hiddenCount === 1 ? 'event' : 'events' }}</button>
            </li>
            <li v-for="event in shown" :key="event.id" class="flex items-start gap-3 text-sm">
                <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <AppIcon :name="icons[event.kind] ?? 'clock'" class="size-3.5" />
                </span>
                <span class="flex min-w-0 flex-col">
                    <span class="break-words">
                        <span class="font-medium">{{ event.actor }}</span>
                        <span v-if="event.actor_is_admin" class="ml-1 rounded bg-gray-100 px-1 py-0.5 text-[0.625rem] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Support</span>
                        {{ event.description }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400" :title="event.happened_on">{{ event.happened_at }}</span>
                </span>
            </li>
        </ol>
    </section>
</template>
