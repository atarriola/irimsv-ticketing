<script setup>
import { useHttp } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import CommentBox from '@/Components/CommentBox.vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    ticketId: { type: Number, required: true },
    initialMessages: { type: Array, required: true },
    canComment: Boolean,
});

// How often an open ticket asks the server for messages it has not seen yet.
const REFRESH_EVERY_MS = 8000;

const messages = ref([...props.initialMessages]);
const canPost = ref(props.canComment);
const list = ref(null);
const refresher = useHttp({});
const remover = useHttp({});

const lastId = computed(() => messages.value.reduce((highest, message) => Math.max(highest, message.id), 0));

// A day label is shown above the first message of each day, the way chat apps break up a long conversation.
const groupedMessages = computed(() =>
    messages.value.map((message, index) => ({
        ...message,
        startsDay: index === 0 || messages.value[index - 1].sent_on !== message.sent_on,
    })),
);

function isNearBottom() {
    return !list.value || list.value.scrollHeight - list.value.scrollTop - list.value.clientHeight < 80;
}

function scrollToBottom() {
    nextTick(() => {
        if (list.value) {
            list.value.scrollTop = list.value.scrollHeight;
        }
    });
}

function addMessages(incoming) {
    const known = new Set(messages.value.map((message) => message.id));
    const fresh = incoming.filter((message) => !known.has(message.id));

    if (fresh.length === 0) {
        return;
    }

    // Only follow the conversation down when the reader is already at the bottom, so reading older messages is not interrupted.
    const shouldFollow = isNearBottom();

    messages.value = [...messages.value, ...fresh];

    if (shouldFollow) {
        scrollToBottom();
    }
}

function onSent(response) {
    addMessages([response.comment]);
    scrollToBottom();
}

function refresh() {
    if (document.hidden || refresher.processing) {
        return;
    }

    refresher.get(`/tickets/${props.ticketId}/comments?after=${lastId.value}`, {
        onSuccess: (response) => {
            addMessages(response.data);
            canPost.value = response.can_comment;
        },
    });
}

function deleteMessage(message) {
    if (!confirm('Delete this message?')) {
        return;
    }

    remover.delete(`/ticket-comments/${message.id}`, {
        onSuccess: (response) => {
            messages.value = messages.value.filter((item) => item.id !== response.id);
        },
    });
}

let timer = null;

onMounted(() => {
    scrollToBottom();
    timer = setInterval(refresh, REFRESH_EVERY_MS);
    document.addEventListener('visibilitychange', refresh);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', refresh);
});
</script>

<template>
    <section class="flex flex-col rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" aria-label="Conversation">
        <h2 class="flex items-center gap-2 border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">
            Conversation
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 tabular-nums dark:bg-gray-800 dark:text-gray-400">{{ messages.length }}</span>
        </h2>

        <div ref="list" class="flex max-h-[32rem] min-h-40 flex-col gap-3 overflow-y-auto p-4" role="log" aria-live="polite" tabindex="0">
            <p v-if="messages.length === 0" class="m-auto max-w-xs text-center text-sm text-gray-500 dark:text-gray-400">
                No messages yet. {{ canPost ? 'Send the first one to start the conversation.' : '' }}
            </p>

            <template v-for="message in groupedMessages" :key="message.id">
                <p v-if="message.startsDay" class="flex items-center gap-3 py-1 text-xs font-medium text-gray-500 before:h-px before:flex-1 before:bg-gray-200 after:h-px after:flex-1 after:bg-gray-200 dark:text-gray-400 dark:before:bg-gray-800 dark:after:bg-gray-800">
                    {{ message.sent_on }}
                </p>

                <article class="flex items-end gap-2" :class="message.is_mine ? 'flex-row-reverse' : ''">
                    <UserAvatar :name="message.author" :is-admin="message.author_is_admin" small />

                    <div class="flex max-w-[80%] min-w-0 flex-col gap-1" :class="message.is_mine ? 'items-end' : 'items-start'">
                        <span class="flex items-center gap-2 px-1 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ message.is_mine ? 'You' : message.author }}</span>
                            <span v-if="message.author_is_admin" class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Support</span>
                        </span>

                        <p
                            class="rounded-2xl px-3.5 py-2 text-sm leading-relaxed break-words whitespace-pre-line"
                            :class="message.is_mine ? 'rounded-br-md bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'rounded-bl-md bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'"
                        >
                            {{ message.body }}
                        </p>

                        <span class="flex items-center gap-2 px-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ message.sent_at }}
                            <button
                                v-if="message.can.delete"
                                type="button"
                                :disabled="remover.processing"
                                class="cursor-pointer font-medium hover:text-red-600 hover:underline dark:hover:text-red-400"
                                @click="deleteMessage(message)"
                            >
                                Delete
                            </button>
                        </span>
                    </div>
                </article>
            </template>
        </div>

        <div class="border-t border-gray-200 p-4 dark:border-gray-800">
            <CommentBox v-if="canPost" :url="`/tickets/${ticketId}/comments`" :box-id="`ticket-${ticketId}`" placeholder="Write a message…" @created="onSent" />
            <p v-else class="text-center text-sm text-gray-600 dark:text-gray-400">This ticket is closed, so the conversation is read-only.</p>
        </div>
    </section>
</template>
