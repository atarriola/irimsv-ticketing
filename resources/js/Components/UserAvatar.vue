<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    photoUrl: { type: String, default: null },
    isAdmin: Boolean,
    small: Boolean,
    tiny: Boolean,
});

// Show the LRMIS photo when there is one, and fall back to initials once it fails to load.
const photoFailed = ref(false);

watch(() => props.photoUrl, () => {
    photoFailed.value = false;
});

const showPhoto = computed(() => Boolean(props.photoUrl) && !photoFailed.value);

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const sizeClasses = computed(() => {
    if (props.tiny) {
        return 'size-6 text-[0.6rem]';
    }

    return props.small ? 'size-8 text-[0.65rem]' : 'size-10 text-xs';
});
</script>

<template>
    <img
        v-if="showPhoto"
        :src="photoUrl"
        alt=""
        loading="lazy"
        class="shrink-0 rounded-full object-cover"
        :class="sizeClasses"
        aria-hidden="true"
        @error="photoFailed = true"
    />
    <span v-else class="flex shrink-0 items-center justify-center rounded-full font-semibold" :class="[isAdmin ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200', sizeClasses]" aria-hidden="true">
        {{ initials }}
    </span>
</template>
