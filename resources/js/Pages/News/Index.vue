<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import NewsKindBadge from '@/Components/NewsKindBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    kinds: Array,
    currentKind: String,
    posts: Object,
    can: Object,
});

const filterClasses = (isCurrent) =>
    isCurrent
        ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900'
        : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-100';

const draftClasses = 'rounded bg-gray-100 px-1.5 py-0.5 text-[0.6875rem] font-bold tracking-wide text-gray-500 uppercase dark:bg-gray-800 dark:text-gray-400';
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5">
        <Head title="News" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">News</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Events, new features, updates, maintenance notices and what is planned next, posted by the administrators.</p>
            </div>
            <Link
                v-if="can.create"
                href="/news/create"
                class="flex items-center gap-2 rounded-lg bg-gray-900 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
            >
                <AppIcon name="plus" class="size-4" />
                Write a post
            </Link>
        </header>

        <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Kinds of news">
            <Link
                v-for="kind in [{ value: null, label: 'All' }, ...kinds]"
                :key="kind.value ?? 'all'"
                :href="kind.value ? `/news?kind=${kind.value}` : '/news'"
                class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                :class="filterClasses((kind.value ?? null) === (currentKind ?? null))"
                :aria-current="(kind.value ?? null) === (currentKind ?? null) ? 'page' : undefined"
            >
                {{ kind.label }}
            </Link>
        </nav>

        <p v-if="posts.data.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            Nothing announced yet.
        </p>

        <ul v-else class="flex flex-col gap-3">
            <li v-for="post in posts.data" :key="post.id">
                <Link
                    :href="`/news/${post.id}`"
                    prefetch
                    class="flex gap-4 rounded-lg border border-gray-200 bg-white p-5 transition hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-600"
                >
                    <span class="flex min-w-0 flex-1 flex-col gap-2">
                        <span class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <NewsKindBadge :kind="post.kind" />
                            <span v-if="!post.is_published" :class="draftClasses">Draft</span>
                            <span v-if="post.published_on">{{ post.published_on }}</span>
                        </span>
                        <span class="text-lg font-semibold tracking-tight break-words">{{ post.title }}</span>
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ post.excerpt }}</span>
                        <span class="flex items-center gap-2 pt-1 text-xs text-gray-500 dark:text-gray-400">
                            <UserAvatar :name="post.author" :photo-url="post.author_photo_url" :is-admin="post.author_is_admin" tiny />
                            {{ post.author }}
                        </span>
                    </span>
                    <img
                        v-if="post.cover_url"
                        :src="post.cover_url"
                        alt=""
                        loading="lazy"
                        class="size-20 shrink-0 rounded-lg border border-gray-200 bg-gray-100 object-cover sm:size-28 dark:border-gray-700 dark:bg-gray-800"
                    />
                </Link>
            </li>
        </ul>

        <Pagination :paginator="posts" />
    </div>
</template>
