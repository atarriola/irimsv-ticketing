<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import CommentSection from '@/Components/CommentSection.vue';
import ReactionBar from '@/Components/ReactionBar.vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    thread: { type: Object, required: true },
    reactionTypes: { type: Array, required: true },
});

const repliesCount = ref(props.thread.replies_count);
const commentSection = ref(null);

const tagClasses = 'rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400';
</script>

<template>
    <article class="flex gap-3 border-b border-gray-100 p-4 last:border-b-0 dark:border-gray-800">
        <UserAvatar :name="thread.author" :is-admin="thread.author_is_admin" />

        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
            <header class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span class="font-semibold">{{ thread.author }}</span>
                <span class="text-gray-500 dark:text-gray-400">{{ thread.author_position }}</span>
                <span v-if="thread.author_is_admin" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Admin</span>
                <span class="text-gray-500 dark:text-gray-400">&middot; {{ thread.created_at }}</span>
            </header>

            <Link :href="`/forum/threads/${thread.id}`" prefetch class="line-clamp-6 text-sm leading-relaxed break-words whitespace-pre-line text-gray-800 dark:text-gray-200">
                {{ thread.body }}
            </Link>

            <footer class="flex flex-wrap items-center gap-x-3 gap-y-2 pt-1">
                <ReactionBar :url="`/forum/threads/${thread.id}/reactions`" :reactions="thread.reactions" :types="reactionTypes" />

                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-1.5 rounded-full py-0.5 pr-2 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                    :aria-label="`Comment, ${repliesCount} so far`"
                    @click="commentSection?.focusCommentBox()"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z"
                        />
                    </svg>
                    <span class="tabular-nums">{{ repliesCount }}</span>
                </button>

                <span class="flex flex-wrap items-center gap-2">
                    <span :class="tagClasses">{{ thread.topic.name }}</span>
                    <span :class="tagClasses">{{ thread.type }}</span>
                    <span v-if="thread.is_pinned" :class="tagClasses">Pinned</span>
                    <span v-if="thread.is_locked" :class="tagClasses">Locked</span>
                </span>
            </footer>

            <CommentSection
                ref="commentSection"
                class="mt-2 border-t border-gray-100 pt-3 dark:border-gray-800"
                :thread-id="thread.id"
                :initial-comments="thread.preview_comments"
                :comments-count="thread.comments_count"
                :next-page="thread.comments_count > thread.preview_comments.length ? 1 : null"
                :can-reply="thread.can.reply"
                :is-locked="thread.is_locked"
                :reaction-types="reactionTypes"
                @totals="repliesCount = $event.replies_count"
            />
        </div>
    </article>
</template>
