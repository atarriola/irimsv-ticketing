<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import NewsKindBadge from '@/Components/NewsKindBadge.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    post: Object,
    can: Object,
});

const images = computed(() => (props.post.attachments ?? []).filter((attachment) => attachment.type === 'image'));
const videos = computed(() => (props.post.attachments ?? []).filter((attachment) => attachment.type === 'video'));

const actionClasses =
    'cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800';
const draftClasses = 'rounded bg-gray-100 px-1.5 py-0.5 text-[0.6875rem] font-bold tracking-wide text-gray-500 uppercase dark:bg-gray-800 dark:text-gray-400';
const imageClasses = 'w-full rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800';

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

        <!-- A lone photo is shown whole at its own proportions; several are tiled at a common size. -->
        <section v-if="images.length > 0" aria-label="Photos">
            <ul class="grid gap-3" :class="images.length === 1 ? 'grid-cols-1' : 'grid-cols-2 sm:grid-cols-3'">
                <li v-for="image in images" :key="image.id">
                    <a :href="image.url" target="_blank" rel="noopener" class="block transition hover:opacity-90">
                        <img :src="image.url" :alt="image.name" loading="lazy" :class="[imageClasses, images.length === 1 ? 'max-h-[32rem] object-contain' : 'aspect-video object-cover']" />
                    </a>
                </li>
            </ul>
        </section>

        <section v-if="videos.length > 0" class="flex flex-col gap-4" aria-label="Videos">
            <figure v-for="video in videos" :key="video.id" class="flex flex-col gap-1.5">
                <video controls preload="metadata" playsinline class="w-full rounded-lg border border-gray-200 bg-black dark:border-gray-700">
                    <source :src="video.url" :type="video.mime_type" />
                    Your browser cannot play this video.
                    <a :href="video.url" class="underline">Download it</a>
                    instead.
                </video>
                <figcaption class="flex items-baseline justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="truncate">{{ video.name }}</span>
                    <span class="shrink-0">{{ video.size }}</span>
                </figcaption>
            </figure>
        </section>
    </article>
</template>
