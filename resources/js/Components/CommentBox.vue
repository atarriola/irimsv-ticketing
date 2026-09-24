<script setup>
import { useHttp, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    // Where the message is posted, and a name that keeps this box's field id unique on the page.
    url: { type: String, required: true },
    boxId: { type: String, required: true },
    parentId: { type: Number, default: null },
    placeholder: { type: String, default: 'Write a comment…' },
    initialText: { type: String, default: '' },
    autofocus: Boolean,
});

const emit = defineEmits(['created']);

const page = usePage();
const user = computed(() => page.props.auth.user);
const http = useHttp({ body: props.initialText, parent_id: props.parentId });
const textarea = ref(null);

function focus() {
    nextTick(() => {
        textarea.value?.focus();
        textarea.value?.setSelectionRange(http.body.length, http.body.length);
    });
}

function submit() {
    if (http.processing || http.body.trim() === '') {
        return;
    }

    http.post(props.url, {
        onSuccess: (response) => {
            http.body = '';
            emit('created', response);
        },
    });
}

onMounted(() => {
    if (props.autofocus) {
        focus();
    }
});

defineExpose({ focus });
</script>

<template>
    <form class="flex gap-2" @submit.prevent="submit">
        <UserAvatar :name="user.name" :photo-url="user.photo_url" :is-admin="user.is_admin" small />

        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <div class="flex items-end gap-1 rounded-2xl bg-gray-100 py-1 pr-1.5 pl-3.5 focus-within:ring-1 focus-within:ring-gray-900 dark:bg-gray-800 dark:focus-within:ring-gray-100">
                <label :for="`comment-box-${boxId}`" class="sr-only">{{ placeholder }}</label>
                <textarea
                    :id="`comment-box-${boxId}`"
                    ref="textarea"
                    v-model="http.body"
                    rows="1"
                    :placeholder="placeholder"
                    class="field-sizing-content max-h-40 min-h-7 w-full resize-none bg-transparent py-1 text-sm text-gray-900 outline-none placeholder:text-gray-500 dark:text-gray-100 dark:placeholder:text-gray-400"
                    @keydown.enter.exact.prevent="submit"
                ></textarea>
                <button
                    type="submit"
                    :disabled="http.processing || http.body.trim() === ''"
                    class="flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-full text-gray-900 transition hover:bg-gray-200 disabled:cursor-default disabled:text-gray-400 disabled:hover:bg-transparent dark:text-gray-100 dark:hover:bg-gray-700 dark:disabled:text-gray-600"
                    aria-label="Send"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4.5" aria-hidden="true">
                        <path
                            d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z"
                        />
                    </svg>
                </button>
            </div>
            <p v-if="http.errors.body || http.errors.parent_id" class="pl-3.5 text-xs text-red-600 dark:text-red-400">{{ http.errors.body || http.errors.parent_id }}</p>
            <p v-else-if="http.body !== ''" class="pl-3.5 text-xs text-gray-400 dark:text-gray-500">Press Enter to send, Shift + Enter for a new line.</p>
        </div>
    </form>
</template>
