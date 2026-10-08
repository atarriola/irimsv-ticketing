<script setup>
import { useHttp, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    // Where the message is posted, and a name that keeps this box's field id unique on the page.
    url: { type: String, required: true },
    boxId: { type: String, required: true },
    parentId: { type: Number, default: null },
    placeholder: { type: String, default: 'Write a comment…' },
    initialText: { type: String, default: '' },
    autofocus: Boolean,
    // Images may be sent with the message, up to this many.
    allowAttachments: Boolean,
    maxAttachments: { type: Number, default: 3 },
    // The message may be marked as a note only administrators see.
    allowInternal: Boolean,
    // Ready-made messages that can be dropped into the box: { id, title, body }.
    savedReplies: { type: Array, default: () => [] },
});

const emit = defineEmits(['created']);

const page = usePage();
const user = computed(() => page.props.auth.user);
const http = useHttp({ body: props.initialText, parent_id: props.parentId, is_internal: false, attachments: [] });
const textarea = ref(null);
const fileInput = ref(null);
const previews = ref([]);

const canAddMore = computed(() => props.allowAttachments && http.attachments.length < props.maxAttachments);
const attachmentError = computed(() => http.errors.attachments ?? Object.entries(http.errors).find(([key]) => key.startsWith('attachments.'))?.[1]);

function focus() {
    nextTick(() => {
        textarea.value?.focus();
        textarea.value?.setSelectionRange(http.body.length, http.body.length);
    });
}

function addFiles(event) {
    const room = props.maxAttachments - http.attachments.length;

    Array.from(event.target.files)
        .slice(0, Math.max(room, 0))
        .forEach((file) => {
            http.attachments = [...http.attachments, file];
            previews.value.push(URL.createObjectURL(file));
        });

    event.target.value = '';
}

function removeFile(index) {
    URL.revokeObjectURL(previews.value[index]);
    previews.value.splice(index, 1);
    http.attachments = http.attachments.filter((file, position) => position !== index);
}

function clearFiles() {
    previews.value.forEach((url) => URL.revokeObjectURL(url));
    previews.value = [];
    http.attachments = [];
}

function insertSavedReply(event) {
    const reply = props.savedReplies.find((candidate) => String(candidate.id) === event.target.value);

    event.target.value = '';

    if (!reply) {
        return;
    }

    http.body = http.body.trim() === '' ? reply.body : `${http.body.trimEnd()}\n\n${reply.body}`;
    focus();
}

function submit() {
    if (http.processing || http.body.trim() === '') {
        return;
    }

    http.post(props.url, {
        onSuccess: (response) => {
            http.body = '';
            http.is_internal = false;
            clearFiles();
            emit('created', response);
        },
    });
}

onMounted(() => {
    if (props.autofocus) {
        focus();
    }
});

onBeforeUnmount(clearFiles);

defineExpose({ focus });

const toolClasses = 'flex size-7 cursor-pointer items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-100';
</script>

<template>
    <form class="flex gap-2" @submit.prevent="submit">
        <UserAvatar :name="user.name" :photo-url="user.photo_url" :is-admin="user.is_admin" small />

        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <div
                class="flex flex-col gap-1 rounded-2xl py-1 pr-1.5 pl-3.5 focus-within:ring-1"
                :class="http.is_internal ? 'bg-amber-50 focus-within:ring-amber-500 dark:bg-amber-500/10' : 'bg-gray-100 focus-within:ring-gray-900 dark:bg-gray-800 dark:focus-within:ring-gray-100'"
            >
                <div class="flex items-end gap-1">
                    <label :for="`comment-box-${boxId}`" class="sr-only">{{ placeholder }}</label>
                    <textarea
                        :id="`comment-box-${boxId}`"
                        ref="textarea"
                        v-model="http.body"
                        rows="1"
                        :placeholder="http.is_internal ? 'Write a note only the helpdesk will see…' : placeholder"
                        class="field-sizing-content max-h-40 min-h-7 w-full resize-none bg-transparent py-1 text-sm text-gray-900 outline-none placeholder:text-gray-500 dark:text-gray-100 dark:placeholder:text-gray-400"
                        @keydown.enter.exact.prevent="submit"
                    ></textarea>

                    <button v-if="canAddMore" type="button" :class="toolClasses" aria-label="Add an image" @click="fileInput.click()">
                        <AppIcon name="paperclip" class="size-4" />
                    </button>

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

                <ul v-if="previews.length > 0" class="flex flex-wrap gap-2 pb-1">
                    <li v-for="(preview, index) in previews" :key="preview" class="relative">
                        <img :src="preview" alt="" class="size-14 rounded-lg border border-gray-200 object-cover dark:border-gray-700" />
                        <button
                            type="button"
                            class="absolute -top-1.5 -right-1.5 flex size-5 cursor-pointer items-center justify-center rounded-full bg-gray-900 text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900"
                            aria-label="Remove image"
                            @click="removeFile(index)"
                        >
                            <AppIcon name="x-mark" class="size-3" />
                        </button>
                    </li>
                </ul>

                <div v-if="allowInternal || savedReplies.length > 0" class="flex flex-wrap items-center gap-x-4 gap-y-1 pb-1 text-xs text-gray-600 dark:text-gray-400">
                    <label v-if="allowInternal" class="flex cursor-pointer items-center gap-1.5">
                        <input v-model="http.is_internal" type="checkbox" class="size-3.5 rounded border-gray-300 accent-amber-600 dark:border-gray-600" />
                        <AppIcon name="lock" class="size-3.5" />
                        Internal note
                    </label>

                    <label v-if="savedReplies.length > 0" class="flex items-center gap-1.5">
                        <span class="sr-only">Insert a saved reply</span>
                        <select class="cursor-pointer rounded border border-gray-300 bg-white px-1.5 py-0.5 text-xs dark:border-gray-600 dark:bg-gray-900" @change="insertSavedReply">
                            <option value="">Insert a saved reply…</option>
                            <option v-for="reply in savedReplies" :key="reply.id" :value="reply.id">{{ reply.title }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <input v-if="allowAttachments" ref="fileInput" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple class="hidden" tabindex="-1" @change="addFiles" />

            <p v-if="http.errors.body || http.errors.parent_id || attachmentError" class="pl-3.5 text-xs text-red-600 dark:text-red-400">{{ http.errors.body || http.errors.parent_id || attachmentError }}</p>
            <p v-else-if="http.body !== ''" class="pl-3.5 text-xs text-gray-400 dark:text-gray-500">
                Press Enter to send, Shift + Enter for a new line.
                <template v-if="http.is_internal">Only administrators will see this note.</template>
            </p>
        </div>
    </form>
</template>
