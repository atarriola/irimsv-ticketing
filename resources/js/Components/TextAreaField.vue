<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    name: { type: String, required: true },
    label: { type: String, required: true },
    initialValue: { type: String, default: '' },
    rows: { type: Number, default: 6 },
    error: String,
    placeholder: String,
    required: Boolean,
});

// The value is set once on mount rather than bound, so re-renders never overwrite what the user typed.
const textarea = ref(null);
onMounted(() => {
    textarea.value.value = props.initialValue;
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label }}</label>
        <textarea
            :id="id"
            ref="textarea"
            :name="name"
            :rows="rows"
            :required="required"
            :placeholder="placeholder"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${id}-error` : undefined"
            class="w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 transition outline-none placeholder:text-gray-400 focus:ring-1 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
            :class="
                error
                    ? 'border-red-400 focus:border-red-500 focus:ring-red-500 dark:border-red-500/70'
                    : 'border-gray-300 focus:border-gray-900 focus:ring-gray-900 dark:border-gray-700 dark:focus:border-gray-100 dark:focus:ring-gray-100'
            "
        ></textarea>
        <p v-if="error" :id="`${id}-error`" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
