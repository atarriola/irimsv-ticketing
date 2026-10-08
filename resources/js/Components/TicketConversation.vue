<script setup>
import { useHttp } from '@inertiajs/vue3';
import { echoIsConfigured, useEcho } from '@laravel/echo-vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import CommentBox from '@/Components/CommentBox.vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    ticketId: { type: Number, required: true },
    initialMessages: { type: Array, required: true },
    canComment: Boolean,
    canAddInternalNote: Boolean,
    savedReplies: { type: Array, default: () => [] },
});

// "changed" tells the page that something arrived over the socket, so it can refresh the ticket's own details too.
const emit = defineEmits(['changed']);

// How often an open ticket asks the server for messages it has not seen yet. Over a WebSocket the server
// announces every change, so the timer only resyncs now and then in case one was missed.
const hasSocket = echoIsConfigured();
const REFRESH_EVERY_MS = hasSocket ? 60000 : 8000;

const messages = ref([...props.initialMessages]);
const canPost = ref(props.canComment);
const list = ref(null);
const refresher = useHttp({});
const remover = useHttp({});
const editor = useHttp({ body: '' });
const editingId = ref(null);

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

// A full copy from the server replaces what is shown: edits and deletions by other people are picked up along with new messages.
function replaceMessages(incoming) {
    const shouldFollow = isNearBottom();
    const hadNew = incoming.some((message) => !messages.value.some((known) => known.id === message.id));

    messages.value = incoming;

    if (hadNew && shouldFollow) {
        scrollToBottom();
    }
}

function onSent(response) {
    addMessages([response.comment]);
    scrollToBottom();
}

function fetchMessages(everything) {
    if (refresher.processing) {
        return;
    }

    refresher.get(`/tickets/${props.ticketId}/comments${everything ? '' : `?after=${lastId.value}`}`, {
        onSuccess: (response) => {
            if (everything) {
                replaceMessages(response.data);
            } else {
                addMessages(response.data);
            }

            canPost.value = response.can_comment;
        },
    });
}

function refresh() {
    if (!document.hidden) {
        fetchMessages(false);
    }
}

function sync() {
    fetchMessages(true);
    emit('changed');
}

function startEditing(message) {
    editingId.value = message.id;
    editor.body = message.body;
    editor.clearErrors();
}

function saveEdit(message) {
    if (editor.processing || editor.body.trim() === '') {
        return;
    }

    editor.put(`/ticket-comments/${message.id}`, {
        onSuccess: (response) => {
            messages.value = messages.value.map((item) => (item.id === response.comment.id ? response.comment : item));
            editingId.value = null;
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

if (hasSocket) {
    // The server announces every change to the ticket; the announcement carries nothing, the sync fetches the details.
    useEcho(`ticket.${props.ticketId}`, 'TicketConversationChanged', sync);
}

const bubbleClasses = {
    mine: 'rounded-br-md bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900',
    theirs: 'rounded-bl-md bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    internal: 'rounded-bl-md border border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100',
};
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

                <article class="flex items-end gap-2" :class="message.is_mine && !message.is_internal ? 'flex-row-reverse' : ''">
                    <UserAvatar :name="message.author" :photo-url="message.author_photo_url" :is-admin="message.author_is_admin" small />

                    <div class="flex max-w-[80%] min-w-0 flex-col gap-1" :class="message.is_mine && !message.is_internal ? 'items-end' : 'items-start'">
                        <span class="flex flex-wrap items-center gap-2 px-1 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ message.is_mine ? 'You' : message.author }}</span>
                            <span>{{ message.author_position }}</span>
                            <span v-if="message.author_is_admin" class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Support</span>
                            <span v-if="message.is_internal" class="flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                <AppIcon name="lock" class="size-3" />
                                Internal note
                            </span>
                        </span>

                        <form v-if="editingId === message.id" class="flex w-full flex-col gap-1.5" @submit.prevent="saveEdit(message)">
                            <label :for="`edit-${message.id}`" class="sr-only">Edit message</label>
                            <textarea
                                :id="`edit-${message.id}`"
                                v-model="editor.body"
                                rows="3"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:focus:border-gray-100 dark:focus:ring-gray-100"
                                @keydown.esc="editingId = null"
                            ></textarea>
                            <p v-if="editor.errors.body" class="text-xs text-red-600 dark:text-red-400">{{ editor.errors.body }}</p>
                            <span class="flex gap-3 text-xs font-medium">
                                <button type="submit" :disabled="editor.processing" class="cursor-pointer text-gray-900 hover:underline dark:text-gray-100">Save</button>
                                <button type="button" class="cursor-pointer text-gray-500 hover:underline dark:text-gray-400" @click="editingId = null">Cancel</button>
                            </span>
                        </form>

                        <p
                            v-else
                            class="rounded-2xl px-3.5 py-2 text-sm leading-relaxed break-words whitespace-pre-line"
                            :class="message.is_internal ? bubbleClasses.internal : message.is_mine ? bubbleClasses.mine : bubbleClasses.theirs"
                        >
                            {{ message.body }}
                        </p>

                        <ul v-if="message.attachments?.length > 0" class="flex flex-wrap gap-2">
                            <li v-for="attachment in message.attachments" :key="attachment.id">
                                <a :href="attachment.url" target="_blank" rel="noopener" :title="attachment.name">
                                    <img :src="attachment.url" :alt="attachment.name" loading="lazy" class="size-24 rounded-lg border border-gray-200 object-cover transition hover:opacity-90 dark:border-gray-700" />
                                </a>
                            </li>
                        </ul>

                        <span class="flex items-center gap-2 px-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ message.sent_at }}
                            <span v-if="message.is_edited">(edited)</span>
                            <button v-if="message.can.update && editingId !== message.id" type="button" class="cursor-pointer font-medium hover:underline" @click="startEditing(message)">Edit</button>
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
            <CommentBox
                v-if="canPost"
                :url="`/tickets/${ticketId}/comments`"
                :box-id="`ticket-${ticketId}`"
                placeholder="Write a message…"
                allow-attachments
                :allow-internal="canAddInternalNote"
                :saved-replies="savedReplies"
                @created="onSent"
            />
            <p v-else class="text-center text-sm text-gray-600 dark:text-gray-400">This ticket is closed, so the conversation is read-only.</p>
        </div>
    </section>
</template>
