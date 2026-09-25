<script setup>
import { useHttp } from '@inertiajs/vue3';
import { echoIsConfigured, useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CommentBox from '@/Components/CommentBox.vue';
import CommentItem from '@/Components/CommentItem.vue';

const props = defineProps({
    threadId: { type: Number, required: true },
    initialComments: { type: Array, required: true },
    commentsCount: { type: Number, required: true },
    // The page to request when "View more comments" is pressed; null when every comment is already shown.
    nextPage: { type: Number, default: null },
    canReply: Boolean,
    isLocked: Boolean,
    reactionTypes: { type: Array, required: true },
    // When live, the section keeps asking the server what changed, so other people's comments appear without a refresh.
    live: Boolean,
    // The server time the initial comments were rendered at, so the first live check catches every edit made since.
    syncedAt: { type: String, default: null },
});

// "thread" carries the thread's own live state (lock, pin, reactions, message); "gone" fires once the thread has been deleted.
const emit = defineEmits(['totals', 'thread', 'gone']);

// How often a live section asks the server for changes it has not seen yet while no WebSocket is connected.
const REFRESH_EVERY_MS = 8000;
// With a WebSocket connected the server pushes every change, so the timer only resyncs now and then in case one was missed.
const RESYNC_OVER_SOCKET_EVERY_MS = 60000;

const http = useHttp({});
const refresher = useHttp({});
// The section keeps its own copy of the conversation so that live updates never touch the page props.
const comments = ref(props.initialComments.map((comment) => asComment({ ...comment, children: (comment.children ?? []).map((child) => ({ ...child })) })));
const total = ref(props.commentsCount);
const pageToLoad = ref(props.nextPage);
const commentBox = ref(null);
const isGone = ref(false);
const hasSocket = props.live && echoIsConfigured();
const socket = hasSocket ? useConnectionStatus() : ref('disconnected');
let since = props.syncedAt;
let lastSyncedAt = 0;
let isSyncQueued = false;
let timer = null;

const hiddenCount = computed(() => Math.max(total.value - comments.value.length, 0));

function asComment(reply) {
    return { ...reply, children: reply.children ?? [] };
}

function withoutChildren(reply) {
    const { children, ...fields } = reply;

    return fields;
}

function allReplies() {
    return comments.value.flatMap((comment) => [comment, ...comment.children]);
}

function findReply(id) {
    return allReplies().find((reply) => reply.id === id);
}

function applyTotals(totals) {
    total.value = totals.comments_count;
    emit('totals', totals);
}

function loadMore() {
    http.get(`/forum/threads/${props.threadId}/replies?page=${pageToLoad.value}`, {
        onSuccess: (response) => {
            const incoming = new Set(response.data.map((comment) => comment.id));

            // Comments are shown oldest first, and ids grow over time, so sorting by id keeps a freshly posted comment at the end.
            comments.value = [...comments.value.filter((comment) => !incoming.has(comment.id)), ...response.data.map(asComment)].sort((a, b) => a.id - b.id);
            pageToLoad.value = response.next_page;
        },
    });
}

function addReply(reply) {
    if (reply.parent_id === null) {
        if (!comments.value.some((comment) => comment.id === reply.id)) {
            comments.value.push(asComment(reply));
        }

        return;
    }

    // A reply to a comment on a page that has not been loaded yet stays hidden until that page is.
    const parent = comments.value.find((comment) => comment.id === reply.parent_id);

    if (parent && !parent.children.some((child) => child.id === reply.id)) {
        parent.children.push(withoutChildren(reply));
    }
}

function removeReplies(ids) {
    const gone = new Set(ids);

    comments.value = comments.value.filter((comment) => !gone.has(comment.id));
    comments.value.forEach((comment) => {
        comment.children = comment.children.filter((child) => !gone.has(child.id));
    });
}

function updateReplies(replies) {
    replies.forEach((incoming) => {
        const reply = findReply(incoming.id);

        if (reply) {
            Object.assign(reply, withoutChildren(incoming));
        }
    });
}

function refreshReactions(summaries) {
    Object.entries(summaries).forEach(([id, summary]) => {
        const reply = findReply(Number(id));

        if (reply && JSON.stringify(reply.reactions) !== JSON.stringify(summary)) {
            reply.reactions = summary;
        }
    });
}

function applyChanges(response) {
    since = response.synced_at;
    lastSyncedAt = Date.now();
    removeReplies(response.deleted);
    updateReplies(response.updated);
    response.created.forEach(addReply);
    refreshReactions(response.reactions);
    emit('thread', response.thread);
    applyTotals(response);
}

function sync() {
    if (isGone.value) {
        return;
    }

    // A change announced while a request is in flight is picked up by one more request straight after it.
    if (refresher.processing) {
        isSyncQueued = true;

        return;
    }

    const replies = allReplies();
    const query = new URLSearchParams({
        after: replies.reduce((highest, reply) => Math.max(highest, reply.id), 0),
        known: replies.map((reply) => reply.id).join(','),
    });

    if (since) {
        query.set('since', since);
    }

    refresher.get(`/forum/threads/${props.threadId}/changes?${query}`, {
        onSuccess: applyChanges,
        onHttpException: (response) => {
            // The thread has been deleted, or the session has ended; either way there is nothing left to follow.
            if ([401, 403, 404].includes(response.status)) {
                stopSyncing();
            }

            if (response.status === 404) {
                isGone.value = true;
                emit('gone');
            }

            return false;
        },
        onFinish: () => {
            if (isSyncQueued) {
                isSyncQueued = false;
                sync();
            }
        },
    });
}

// The timer stays quiet in a hidden tab and, over a live socket, only resyncs now and then; a change announced
// over the socket is always fetched straight away, hidden tab or not, so it is there the moment the reader looks.
function syncIfDue() {
    if (document.hidden || (socket.value === 'connected' && Date.now() - lastSyncedAt < RESYNC_OVER_SOCKET_EVERY_MS)) {
        return;
    }

    sync();
}

function syncWhenVisible() {
    if (!document.hidden) {
        sync();
    }
}

function startSyncing() {
    timer = setInterval(syncIfDue, REFRESH_EVERY_MS);
    document.addEventListener('visibilitychange', syncWhenVisible);
}

function stopSyncing() {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', syncWhenVisible);
}

if (hasSocket) {
    // The server announces every change to the thread; the announcement itself carries nothing, the sync fetches the details.
    useEcho(`forum.thread.${props.threadId}`, 'ForumThreadChanged', sync);

    // Whatever happened while the socket was down is caught up on as soon as it is back.
    watch(socket, (state) => {
        if (state === 'connected') {
            sync();
        }
    });
}

function onReplyCreated(response) {
    addReply(response.reply);
    applyTotals(response);
}

function onReplyDeleted({ id, totals }) {
    removeReplies([id]);
    applyTotals(totals);
}

onMounted(() => {
    if (props.live) {
        startSyncing();
    }
});

onBeforeUnmount(stopSyncing);

// "sync" lets a parent that hears about the thread elsewhere, such as the feed, ask for the changes on demand.
defineExpose({ focusCommentBox: () => commentBox.value?.focus(), sync });
</script>

<template>
    <section class="flex flex-col gap-3" aria-label="Comments">
        <CommentItem
            v-for="comment in comments"
            :key="comment.id"
            :comment="comment"
            :thread-id="threadId"
            :can-reply="canReply"
            :reaction-types="reactionTypes"
            @created="onReplyCreated"
            @deleted="onReplyDeleted"
        />

        <button
            v-if="pageToLoad !== null && hiddenCount > 0"
            type="button"
            :disabled="http.processing"
            class="w-fit cursor-pointer text-sm font-semibold text-gray-600 hover:underline disabled:opacity-60 dark:text-gray-400"
            @click="loadMore"
        >
            {{ http.processing ? 'Loading comments…' : `View ${hiddenCount} more ${hiddenCount === 1 ? 'comment' : 'comments'}` }}
        </button>

        <CommentBox v-if="canReply" ref="commentBox" :url="`/forum/threads/${threadId}/replies`" :box-id="`thread-${threadId}`" @created="onReplyCreated" />

        <p v-else-if="isLocked" class="text-sm text-gray-500 dark:text-gray-400">This thread is locked. New comments are turned off.</p>
    </section>
</template>
