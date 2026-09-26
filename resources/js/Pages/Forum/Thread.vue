<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CommentSection from '@/Components/CommentSection.vue';
import ReactionBar from '@/Components/ReactionBar.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    thread: Object,
    comments: Array,
    nextCommentsPage: Number,
    syncedAt: String,
    reactionTypes: Array,
    can: Object,
});

const repliesCount = ref(props.thread.replies_count);
// What the live comment section has learnt about the thread since the page was rendered, laid over the page props.
const liveChanges = ref({});
const post = computed(() => ({ ...props.thread, ...liveChanges.value }));
const isGone = ref(false);

// A fresh copy of the thread from the server, for example after moderating it, supersedes anything learnt live.
watch(
    () => props.thread,
    () => (liveChanges.value = {}),
);

const tagClasses = 'rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400';
const actionClasses = 'cursor-pointer text-xs font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100';
const dangerClasses = 'cursor-pointer text-xs font-medium text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400';

function moderate(changes) {
    router.patch(`/forum/threads/${props.thread.id}/moderation`, changes, { preserveScroll: true });
}

function deleteThread() {
    router.delete(`/forum/threads/${props.thread.id}`, {
        onBefore: () => confirm('Delete this thread and all of its comments?'),
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
        <Head :title="post.excerpt" />

        <Link href="/forum" class="w-fit text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Forum</Link>

        <p v-if="isGone" role="status" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-4 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            This thread has been deleted.
            <Link href="/forum" class="font-semibold text-gray-900 hover:underline dark:text-gray-100">Back to the forum</Link>
        </p>

        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <article class="flex flex-col gap-3 border-b border-gray-100 p-5 dark:border-gray-800">
                <header class="flex items-center gap-3">
                    <UserAvatar :name="thread.author" :photo-url="thread.author_photo_url" :is-admin="thread.author_is_admin" />
                    <div class="flex min-w-0 flex-col">
                        <span class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-semibold">{{ thread.author }}</span>
                            <span v-if="thread.author_is_admin" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Admin</span>
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ thread.author_position }} &middot; {{ thread.created_at }}</span>
                    </div>
                </header>

                <p class="text-base leading-relaxed break-words whitespace-pre-line text-gray-900 dark:text-gray-100">{{ post.body }}</p>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <ReactionBar :url="`/forum/threads/${thread.id}/reactions`" :reactions="post.reactions" :types="reactionTypes" />
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ repliesCount }} {{ repliesCount === 1 ? 'comment' : 'comments' }}</span>
                </div>

                <footer class="flex flex-wrap items-center justify-between gap-3">
                    <span class="flex flex-wrap items-center gap-2">
                        <Link v-if="thread.topic" :href="`/forum?topic=${thread.topic.slug}`" :class="tagClasses" class="hover:text-gray-900 dark:hover:text-gray-100">{{ thread.topic.name }}</Link>
                        <span :class="tagClasses">{{ thread.type }}</span>
                        <span v-if="post.is_pinned" :class="tagClasses">Pinned</span>
                        <span v-if="post.is_locked" :class="tagClasses">Locked</span>
                    </span>

                    <span v-if="can.moderate || can.update || can.delete" class="flex flex-wrap items-center gap-4">
                        <template v-if="can.moderate">
                            <button type="button" :class="actionClasses" @click="moderate({ is_pinned: !post.is_pinned })">{{ post.is_pinned ? 'Unpin' : 'Pin' }}</button>
                            <button type="button" :class="actionClasses" @click="moderate({ is_locked: !post.is_locked })">{{ post.is_locked ? 'Unlock' : 'Lock' }}</button>
                        </template>
                        <Link v-if="can.update" :href="`/forum/threads/${thread.id}/edit`" :class="actionClasses">Edit</Link>
                        <button v-if="can.delete" type="button" :class="dangerClasses" @click="deleteThread">Delete</button>
                    </span>
                </footer>
            </article>

            <!-- The section polls for changes and reports the thread's own live state (lock, pin, reactions, message) back up. -->
            <CommentSection
                :key="thread.id"
                class="p-5"
                :thread-id="thread.id"
                :initial-comments="comments"
                :comments-count="thread.comments_count"
                :next-page="nextCommentsPage"
                :can-reply="post.can.reply && !isGone"
                :is-locked="post.is_locked"
                :reaction-types="reactionTypes"
                live
                :synced-at="syncedAt"
                @totals="repliesCount = $event.replies_count"
                @thread="liveChanges = $event"
                @gone="isGone = true"
            />
        </div>
    </div>
</template>
