<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    name: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    initialValue: { type: [String, Number], default: '' },
    error: String,
    autocomplete: String,
    placeholder: String,
    required: Boolean,
    autofocus: Boolean,
});

const isRevealed = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && isRevealed.value ? 'text' : props.type));

// The value is set once on mount rather than bound, so re-renders (such as a validation error appearing) never overwrite what the user typed.
const input = ref(null);
onMounted(() => {
    input.value.value = props.initialValue;
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label }}</label>

        <div class="relative">
            <input
                :id="id"
                ref="input"
                :name="name"
                :type="inputType"
                :autocomplete="autocomplete"
                :placeholder="placeholder"
                :required="required"
                :autofocus="autofocus"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : undefined"
                class="w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 transition outline-none placeholder:text-gray-400 focus:ring-1 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                :class="[
                    error
                        ? 'border-red-400 focus:border-red-500 focus:ring-red-500 dark:border-red-500/70'
                        : 'border-gray-300 focus:border-gray-900 focus:ring-gray-900 dark:border-gray-700 dark:focus:border-gray-100 dark:focus:ring-gray-100',
                    isPassword ? 'pr-11' : '',
                ]"
            />

            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-0 flex w-11 cursor-pointer items-center justify-center rounded-r-lg text-gray-400 hover:text-gray-600 focus-visible:text-gray-900 focus-visible:outline-none dark:hover:text-gray-200 dark:focus-visible:text-gray-100"
                :aria-label="isRevealed ? 'Hide password' : 'Show password'"
                :aria-pressed="isRevealed"
                @click="isRevealed = !isRevealed"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
                    <template v-if="isRevealed">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"
                        />
                    </template>
                    <template v-else>
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"
                        />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </template>
                </svg>
            </button>
        </div>

        <p v-if="error" :id="`${id}-error`" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
