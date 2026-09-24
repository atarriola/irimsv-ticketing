<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: Number, required: true },
    hint: String,
    icon: { type: String, required: true },
    tone: { type: String, default: 'gray' },
});

// Colour is kept to the icon alone so the status reads at a glance without tinting the whole tile.
const toneClasses = {
    blue: 'text-blue-600 dark:text-blue-400',
    amber: 'text-amber-600 dark:text-amber-400',
    green: 'text-green-600 dark:text-green-400',
    gray: 'text-gray-400 dark:text-gray-500',
};

const countUpDuration = 700;
const displayedValue = ref(props.value);
let animationFrame = null;

// Counts up to the value like a readout settling, easing out so the last digits land softly.
function countUpTo(target) {
    cancelAnimationFrame(animationFrame);

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        displayedValue.value = target;

        return;
    }

    const start = performance.now();

    function step(now) {
        const progress = Math.min((now - start) / countUpDuration, 1);

        displayedValue.value = Math.round(target * (1 - (1 - progress) ** 3));

        if (progress < 1) {
            animationFrame = requestAnimationFrame(step);
        }
    }

    step(start);
}

onMounted(() => countUpTo(props.value));
watch(() => props.value, countUpTo);
onBeforeUnmount(() => cancelAnimationFrame(animationFrame));
</script>

<template>
    <div class="hud-corners flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <span class="flex items-center justify-between gap-2">
            <span class="font-mono text-xs font-medium tracking-widest text-gray-600 uppercase dark:text-gray-400">{{ label }}</span>
            <AppIcon :name="icon" :class="toneClasses[tone]" />
        </span>
        <div class="flex min-w-0 flex-col gap-0.5">
            <span class="sr-only">{{ value.toLocaleString() }}</span>
            <span class="text-3xl leading-tight font-semibold tracking-tight tabular-nums" aria-hidden="true">{{ displayedValue.toLocaleString() }}</span>
            <span v-if="hint" class="truncate text-xs text-gray-500 dark:text-gray-500">{{ hint }}</span>
        </div>
    </div>
</template>
