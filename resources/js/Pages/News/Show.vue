<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import NewsKindBadge from '@/Components/NewsKindBadge.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    post: Object,
    can: Object,
});

const actionClasses =
    'cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800';
const draftClasses = 'rounded bg-gray-100 px-1.5 py-0.5 text-[0.6875rem] font-bold tracking-wide text-gray-500 uppercase dark:bg-gray-800 dark:text-gray-400';

function deletePost() {
    router.delete(`/news/${props.post.id}`, {
        onBefore: () => confirm(`Delete "${props.post.title}"?`),
    });
}
</script>

<template>
    <article class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <Head :title="post.title" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <Link href="/news" class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">&larr; Back to news</Link>

            <div v-if="can.update || can.delete" class="flex flex-wrap gap-2">
                <Link v-if="can.update" :href="`/news/${post.id}/edit`" :class="actionClasses">Edit</Link>
                <button
                    v-if="can.delete"
                    type="button"
                    class="cursor-pointer rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-400/30 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click="deletePost"
                >
                    Delete
                </button>
            </div>
        </div>

        <header class="flex flex-col gap-3">
            <span class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <NewsKindBadge :kind="post.kind" />
                <span v-if="!post.is_published" :class="draftClasses">Draft</span>
                <span v-if="post.published_on">{{ post.published_on }}</span>
            </span>
            <h1 class="text-3xl font-semibold tracking-tight break-words">{{ post.title }}</h1>
            <span class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <UserAvatar :name="post.author" :photo-url="post.author_photo_url" :is-admin="post.author_is_admin" small />
                {{ post.author }}
            </span>
        </header>

        <div class="text-base leading-relaxed break-words whitespace-pre-line text-gray-800 dark:text-gray-200">{{ post.body }}</div>
    </article>
</template>
