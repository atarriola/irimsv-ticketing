<script setup>
import { Form, Head, InfiniteScroll, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { echoIsConfigured, useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { computed, ref, watch } from 'vue';
import ThreadFeedItem from '@/Components/ThreadFeedItem.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    topics: Array,
    types: Array,
    reactionTypes: Array,
    currentTopic: String,
    threads: Object,
    latestThreadId: Number,
    newThreadsCount: Number,
    threadActivity: Object,
    can: Object,
});

// How often an open feed asks whether anyone has posted since it was loaded: rarely when a WebSocket announces new posts, otherwise often.
const hasSocket = echoIsConfigured();
const CHECK_FOR_POSTS_EVERY_MS = hasSocket ? 30000 : 10000;

const page = usePage();
const user = computed(() => page.props.auth.user);
// A new post is filed under the topic the feed is narrowed to, and under none otherwise.
const defaultTopicId = computed(() => props.topics.find((topic) => topic.slug === props.currentTopic)?.id ?? null);
const feedUrl = computed(() => (props.currentTopic ? `/forum?topic=${props.currentTopic}` : '/forum'));

// The newest thread the feed showed when it was last loaded; the server counts what was posted after it.
const seenThreadId = ref(props.latestThreadId ?? 0);
const newThreadsCount = computed(() => props.newThreadsCount ?? 0);

watch(
    () => props.latestThreadId,
    (latestThreadId) => (seenThreadId.value = latestThreadId ?? 0),
);

// The feed items currently on screen, by thread id, so a change to a thread can be applied to the right one.
const feedItems = new Map();

function registerFeedItem(threadId, item) {
    if (item) {
        feedItems.set(threadId, item);
    } else {
        feedItems.delete(threadId);
    }
}

const socket = hasSocket ? useConnectionStatus() : ref('disconnected');

// With a WebSocket connected the server announces every change; without one the poll also asks which threads on screen changed.
const checkForNewThreads = () =>
    socket.value === 'connected'
        ? { data: { seen: seenThreadId.value }, only: ['newThreadsCount'], preserveUrl: true }
        : { data: { seen: seenThreadId.value, threads: [...feedItems.keys()] }, only: ['newThreadsCount', 'threadActivity'], preserveUrl: true };

usePoll(CHECK_FOR_POSTS_EVERY_MS, checkForNewThreads, { mode: 'rest' });

watch(
    () => props.threadActivity,
    (activity) => Object.entries(activity ?? {}).forEach(([threadId, item]) => feedItems.get(Number(threadId))?.applyActivity(item)),
);

if (hasSocket) {
    useEcho('forum', 'ForumThreadPosted', () => router.reload(checkForNewThreads()));
    useEcho('forum', 'ForumThreadChanged', ({ threadId }) => feedItems.get(threadId)?.syncConversation());
}

function showNewThreads() {
    router.visit(feedUrl.value, {
        only: ['threads', 'latestThreadId', 'newThreadsCount'],
        reset: ['threads'],
        preserveState: true,
        preserveScroll: true,
    });
}

const pillSelectClasses =
    'cursor-pointer rounded-full border border-gray-300 bg-white py-1.5 pr-7 pl-3 text-xs font-medium text-gray-700 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-100 dark:focus:ring-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
        <Head title="Forum" />

        <h1 class="text-2xl font-semibold tracking-tight">Forum</h1>

        <Form
            v-if="can.create"
            action="/forum/threads"
            method="post"
            reset-on-success
            class="flex gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
            #default="{ errors, processing }"
        >
            <UserAvatar :name="user.name" :photo-url="user.photo_url" :is-admin="user.is_admin" />

            <div class="flex min-w-0 flex-1 flex-col gap-3">
                <label for="body" class="sr-only">Start a thread</label>
                <textarea
                    id="body"
                    name="body"
                    rows="2"
                    required
                    placeholder="What's on your mind? Share a concern or ask a question…"
                    class="field-sizing-content max-h-72 min-h-12 w-full resize-none bg-transparent pt-2 text-sm text-gray-900 outline-none placeholder:text-gray-400 dark:text-gray-100 dark:placeholder:text-gray-500"
                ></textarea>
                <p v-if="errors.body || errors.forum_topic_id || errors.type" class="text-sm text-red-600 dark:text-red-400">
                    {{ errors.body || errors.forum_topic_id || errors.type }}
                </p>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <span class="flex flex-wrap gap-2">
                        <template v-if="topics.length > 0">
                            <label for="forum_topic_id" class="sr-only">Topic</label>
                            <select id="forum_topic_id" name="forum_topic_id" class="max-w-40 truncate" :class="pillSelectClasses">
                                <option value="" :selected="defaultTopicId === null">No topic</option>
                                <option v-for="topic in topics" :key="topic.id" :value="topic.id" :selected="topic.id === defaultTopicId">{{ topic.name }}</option>
                            </select>
                        </template>
                        <label for="type" class="sr-only">Kind of post</label>
                        <select id="type" name="type" :class="pillSelectClasses">
                            <option v-for="type in types" :key="type.value" :value="type.value" :selected="type.value === 'query'">{{ type.label }}</option>
                        </select>
                    </span>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="ml-auto cursor-pointer rounded-full bg-gray-900 px-5 py-1.5 text-sm font-semibold text-white transition hover:bg-gray-700 disabled:opacity-60 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
                    >
                        {{ processing ? 'Posting…' : 'Post' }}
                    </button>
                </div>
            </div>
        </Form>

        <nav v-if="topics.length > 0" class="flex gap-2 overflow-x-auto pb-1" aria-label="Topics">
            <Link
                v-for="topic in [{ slug: null, name: 'All' }, ...topics]"
                :key="topic.slug ?? 'all'"
                :href="topic.slug ? `/forum?topic=${topic.slug}` : '/forum'"
                class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                :class="
                    (topic.slug ?? null) === (currentTopic ?? null)
                        ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900'
                        : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-100'
                "
                :aria-current="(topic.slug ?? null) === (currentTopic ?? null) ? 'page' : undefined"
            >
                {{ topic.name }}
            </Link>
        </nav>

        <div role="status" class="flex justify-center empty:hidden">
            <button
                v-if="newThreadsCount > 0"
                type="button"
                class="cursor-pointer rounded-full bg-gray-900 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
                @click="showNewThreads"
            >
                {{ newThreadsCount }} new {{ newThreadsCount === 1 ? 'post' : 'posts' }} &middot; Show
            </button>
        </div>

        <p v-if="threads.data.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            Nothing here yet. Be the first to post.
        </p>

        <InfiniteScroll v-else data="threads" :manual-after="3" class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <ThreadFeedItem v-for="thread in threads.data" :key="thread.id" :ref="(item) => registerFeedItem(thread.id, item)" :thread="thread" :reaction-types="reactionTypes" />

            <template #next="{ loading, fetch, hasMore }">
                <div v-if="hasMore" class="flex justify-center border-t border-gray-100 p-3 dark:border-gray-800">
                    <button type="button" :disabled="loading" class="cursor-pointer rounded-full px-4 py-1.5 text-sm font-medium text-gray-900 hover:bg-gray-100 disabled:opacity-60 dark:text-gray-100 dark:hover:bg-gray-800" @click="fetch">
                        {{ loading ? 'Loading…' : 'Load more' }}
                    </button>
                </div>
            </template>
        </InfiniteScroll>
    </div>
</template>
