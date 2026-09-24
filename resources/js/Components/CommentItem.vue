<script setup>
import { Link, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import CommentBox from '@/Components/CommentBox.vue';
import ReactionBar from '@/Components/ReactionBar.vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    comment: { type: Object, required: true },
    threadId: { type: Number, required: true },
    canReply: Boolean,
    reactionTypes: { type: Array, required: true },
    isNested: Boolean,
});

// "totals" carries the thread's new reply counts; "reply" asks the parent comment to open its reply box.
const emit = defineEmits(['deleted', 'totals', 'reply']);

const http = useHttp({});
const children = ref([...(props.comment.children ?? [])]);
const areChildrenShown = ref(false);
const isReplying = ref(false);
const replyPrefill = ref('');
const replyBoxKey = ref(0);

const linkClasses = 'cursor-pointer font-semibold text-gray-600 hover:underline dark:text-gray-400';

function openReplyBox(mention = '') {
    replyPrefill.value = mention ? `@${mention} ` : '';
    replyBoxKey.value++;
    isReplying.value = true;
}

function startReply() {
    if (props.isNested) {
        emit('reply', props.comment.author);
    } else {
        openReplyBox();
    }
}

function onReplyCreated(response) {
    children.value.push(response.reply);
    areChildrenShown.value = true;
    isReplying.value = false;
    emit('totals', response);
}

function onChildDeleted({ id, totals }) {
    children.value = children.value.filter((child) => child.id !== id);
    emit('totals', totals);
}

function deleteComment() {
    const warning = children.value.length > 0 ? 'Delete this comment and the replies under it?' : 'Delete this comment?';

    if (!confirm(warning)) {
        return;
    }

    http.delete(`/forum/replies/${props.comment.id}`, {
        onSuccess: (totals) => emit('deleted', { id: props.comment.id, totals }),
    });
}
</script>

<template>
    <article class="flex gap-2">
        <UserAvatar :name="comment.author" :photo-url="comment.author_photo_url" :is-admin="comment.author_is_admin" small />

        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <div class="w-fit max-w-full rounded-2xl bg-gray-100 px-3.5 py-2 dark:bg-gray-800">
                <span class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                    {{ comment.author }}
                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ comment.author_position }}</span>
                    <span v-if="comment.author_is_admin" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Admin</span>
                </span>
                <p class="text-sm leading-relaxed break-words whitespace-pre-line text-gray-800 dark:text-gray-200">{{ comment.body }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pl-2 text-xs text-gray-500 dark:text-gray-400">
                <span>{{ comment.created_at }}</span>
                <ReactionBar :url="`/forum/replies/${comment.id}/reactions`" :reactions="comment.reactions" :types="reactionTypes" compact />
                <button v-if="canReply" type="button" :class="linkClasses" @click="startReply">Reply</button>
                <Link v-if="comment.can.update" :href="`/forum/replies/${comment.id}/edit`" :class="linkClasses">Edit</Link>
                <button v-if="comment.can.delete" type="button" :disabled="http.processing" class="cursor-pointer font-semibold text-gray-600 hover:text-red-600 hover:underline dark:text-gray-400 dark:hover:text-red-400" @click="deleteComment">
                    Delete
                </button>
            </div>

            <button
                v-if="children.length > 0"
                type="button"
                class="flex w-fit cursor-pointer items-center gap-1.5 pt-1 pl-2 text-sm font-semibold text-gray-600 hover:underline dark:text-gray-400"
                :aria-expanded="areChildrenShown"
                @click="areChildrenShown = !areChildrenShown"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.49 12 3.75 3.75m0 0-3.75 3.75m3.75-3.75H3.74V4.499" />
                </svg>
                <template v-if="areChildrenShown">Hide replies</template>
                <template v-else>View {{ children.length }} {{ children.length === 1 ? 'reply' : 'replies' }}</template>
            </button>

            <div v-if="areChildrenShown && children.length > 0" class="flex flex-col gap-3 pt-2">
                <CommentItem
                    v-for="child in children"
                    :key="child.id"
                    :comment="child"
                    :thread-id="threadId"
                    :can-reply="canReply"
                    :reaction-types="reactionTypes"
                    is-nested
                    @reply="openReplyBox"
                    @deleted="onChildDeleted"
                />
            </div>

            <CommentBox
                v-if="isReplying"
                :key="replyBoxKey"
                class="pt-2"
                :url="`/forum/threads/${threadId}/replies`"
                :box-id="`reply-${comment.id}`"
                :parent-id="comment.id"
                :placeholder="`Reply to ${comment.author}…`"
                :initial-text="replyPrefill"
                autofocus
                @created="onReplyCreated"
            />
        </div>
    </article>
</template>
