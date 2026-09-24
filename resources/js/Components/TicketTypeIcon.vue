<script setup>
defineProps({
    type: { type: String, required: true },
    label: { type: String, default: null },
});

// These glyphs are drawn by hand for a 14px box: the detailed library icons blur into blobs at this size.
const types = {
    bug_report: {
        name: 'Bug report',
        classes: 'bg-red-500',
        // A round body with two antennae and a pair of legs.
        filled: ['M12 8a5 5 0 0 0-5 5v1.5a5 5 0 0 0 10 0V13a5 5 0 0 0-5-5Z'],
        stroked: ['m8.75 4.25 1.75 2.75m4.75-2.75L13.5 7', 'M3.75 13.75h2.5m11.5 0h2.5'],
    },
    problem: {
        name: 'Problem',
        classes: 'bg-amber-500',
        filled: ['M12 16.25a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Z'],
        stroked: ['M12 5.75v7.5'],
    },
    feature_request: {
        name: 'Feature request',
        classes: 'bg-green-600',
        // A light bulb: the glass, then the screw base.
        filled: [],
        stroked: ['M12 4.75a5 5 0 0 0-2.75 9.18v1.82h5.5v-1.82A5 5 0 0 0 12 4.75Z', 'M10 19.25h4'],
    },
};
</script>

<template>
    <span class="inline-flex size-5 shrink-0 items-center justify-center rounded text-white" :class="types[type].classes" :title="label ?? types[type].name" role="img" :aria-label="label ?? types[type].name">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" aria-hidden="true">
            <path v-for="path in types[type].filled" :key="path" :d="path" fill="currentColor" />
            <path v-for="path in types[type].stroked" :key="path" :d="path" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
</template>
