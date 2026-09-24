<script setup>
import { useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    url: { type: String, required: true },
    reactions: { type: Object, default: () => ({ counts: {}, mine: null }) },
    types: { type: Array, required: true },
    compact: Boolean,
});

const counts = ref({ ...props.reactions.counts });
const mine = ref(props.reactions.mine);
const isPickerOpen = ref(false);
const http = useHttp({ type: '' });

watch(
    () => props.reactions,
    (reactions) => {
        counts.value = { ...reactions.counts };
        mine.value = reactions.mine;
    },
);

const heartCount = computed(() => counts.value.heart ?? 0);
const otherReactions = computed(() => props.types.filter((type) => type.value !== 'heart' && (counts.value[type.value] ?? 0) > 0));

function applyLocally(type) {
    const next = { ...counts.value };

    if (mine.value) {
        next[mine.value] = Math.max((next[mine.value] ?? 1) - 1, 0);
    }

    if (mine.value === type) {
        mine.value = null;
    } else {
        next[type] = (next[type] ?? 0) + 1;
        mine.value = type;
    }

    counts.value = next;
}

function react(type) {
    if (http.processing) {
        return;
    }

    const previous = { counts: { ...counts.value }, mine: mine.value };

    isPickerOpen.value = false;
    applyLocally(type);

    http.type = type;
    http.post(props.url, {
        onSuccess: (response) => {
            counts.value = { ...response.counts };
            mine.value = response.mine;
        },
        onError: () => {
            counts.value = previous.counts;
            mine.value = previous.mine;
        },
    });
}

function closePickerWhenFocusLeaves(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) {
        isPickerOpen.value = false;
    }
}

// Safari does not focus a button when it is clicked, so focus alone cannot tell us the user clicked away.
const bar = ref(null);

function closePickerOnOutsideClick(event) {
    if (!bar.value?.contains(event.target)) {
        isPickerOpen.value = false;
    }
}

watch(isPickerOpen, (isOpen) => {
    if (isOpen) {
        document.addEventListener('click', closePickerOnOutsideClick);
    } else {
        document.removeEventListener('click', closePickerOnOutsideClick);
    }
});

onBeforeUnmount(() => document.removeEventListener('click', closePickerOnOutsideClick));
</script>

<template>
    <div ref="bar" class="relative flex flex-wrap items-center gap-1.5" @focusout="closePickerWhenFocusLeaves" @keydown.esc="isPickerOpen = false">
        <button
            type="button"
            class="flex cursor-pointer items-center gap-1.5 rounded-full py-0.5 pr-2 text-sm transition"
            :class="mine === 'heart' ? 'text-red-600 dark:text-red-400' : 'text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400'"
            :aria-pressed="mine === 'heart'"
            :aria-label="`Love, ${heartCount}`"
            @click="react('heart')"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :fill="mine === 'heart' ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5" :class="compact ? 'size-4' : 'size-5'" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"
                />
            </svg>
            <span v-if="heartCount > 0" class="tabular-nums">{{ heartCount }}</span>
        </button>

        <button
            v-for="type in otherReactions"
            :key="type.value"
            type="button"
            class="flex cursor-pointer items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition"
            :class="
                mine === type.value
                    ? 'border-gray-900 bg-gray-100 text-gray-900 dark:border-gray-100 dark:bg-gray-800 dark:text-gray-100'
                    : 'border-gray-200 text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-600'
            "
            :aria-pressed="mine === type.value"
            :aria-label="`${type.label}, ${counts[type.value]}`"
            @click="react(type.value)"
        >
            <span aria-hidden="true">{{ type.emoji }}</span>
            <span class="tabular-nums">{{ counts[type.value] }}</span>
        </button>

        <button
            type="button"
            class="flex cursor-pointer items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-200"
            :class="compact ? 'size-6' : 'size-7'"
            aria-label="Add a reaction"
            :aria-expanded="isPickerOpen"
            @click="isPickerOpen = !isPickerOpen"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" :class="compact ? 'size-4' : 'size-5'" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z"
                />
            </svg>
        </button>

        <div
            v-if="isPickerOpen"
            class="absolute bottom-full left-0 z-10 mb-1 flex gap-0.5 rounded-full border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            role="group"
            aria-label="Choose a reaction"
        >
            <button
                v-for="type in types"
                :key="type.value"
                type="button"
                class="flex size-9 cursor-pointer items-center justify-center rounded-full text-xl transition hover:scale-125 hover:bg-gray-100 dark:hover:bg-gray-700"
                :class="mine === type.value ? 'bg-gray-100 dark:bg-gray-800' : ''"
                :aria-label="type.label"
                :aria-pressed="mine === type.value"
                :title="type.label"
                @click="react(type.value)"
            >
                {{ type.emoji }}
            </button>
        </div>
    </div>
</template>
