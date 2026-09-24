<script setup>
import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
});

const emit = defineEmits(['totals']);

const http = useHttp({});
const comments = ref([...props.initialComments]);
const total = ref(props.commentsCount);
const pageToLoad = ref(props.nextPage);
const commentBox = ref(null);

const hiddenCount = computed(() => Math.max(total.value - comments.value.length, 0));

function applyTotals(totals) {
    total.value = totals.comments_count;
    emit('totals', totals);
}

function loadMore() {
    http.get(`/forum/threads/${props.threadId}/replies?page=${pageToLoad.value}`, {
        onSuccess: (response) => {
            const incoming = new Set(response.data.map((comment) => comment.id));

            // Comments are shown oldest first, and ids grow over time, so sorting by id keeps a freshly posted comment at the end.
            comments.value = [...comments.value.filter((comment) => !incoming.has(comment.id)), ...response.data].sort((a, b) => a.id - b.id);
            pageToLoad.value = response.next_page;
        },
    });
}

function onCommentCreated(response) {
    comments.value.push(response.reply);
    applyTotals(response);
}

function onCommentDeleted({ id, totals }) {
    comments.value = comments.value.filter((comment) => comment.id !== id);
    applyTotals(totals);
}

defineExpose({ focusCommentBox: () => commentBox.value?.focus() });
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
            @totals="applyTotals"
            @deleted="onCommentDeleted"
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

        <CommentBox v-if="canReply" ref="commentBox" :url="`/forum/threads/${threadId}/replies`" :box-id="`thread-${threadId}`" @created="onCommentCreated" />

        <p v-else-if="isLocked" class="text-sm text-gray-500 dark:text-gray-400">This thread is locked. New comments are turned off.</p>
    </section>
</template>
