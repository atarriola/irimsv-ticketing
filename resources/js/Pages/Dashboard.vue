<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import BarList from '@/Components/BarList.vue';
import NewsKindBadge from '@/Components/NewsKindBadge.vue';
import PriorityIcon from '@/Components/PriorityIcon.vue';
import StatTile from '@/Components/StatTile.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TicketTypeIcon from '@/Components/TicketTypeIcon.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    statusCounts: Array,
    priorityCounts: Array,
    typeCounts: Array,
    recentTickets: Array,
    recentThreads: Array,
    latestNews: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const firstName = computed(() => user.value.name.split(/\s+/)[0]);

const statusTiles = {
    open: { tone: 'blue', icon: 'inbox', hint: 'Waiting to be picked up' },
    in_progress: { tone: 'amber', icon: 'progress', hint: 'Being worked on' },
    resolved: { tone: 'green', icon: 'check-circle', hint: 'Fixed, awaiting closure' },
    closed: { tone: 'gray', icon: 'archive', hint: 'Completed and archived' },
};

const tiles = computed(() => props.statusCounts.map((status) => ({ ...status, ...statusTiles[status.key] })));

const panelClasses = 'hud-corners flex min-w-0 flex-col rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900';
const panelHeaderClasses = 'flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-3.5 dark:border-gray-800';
const panelLinkClasses = 'text-sm font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100';
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Dashboard" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">Welcome back, {{ firstName }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ user.is_admin ? 'Here is what is happening across the help desk.' : 'Here is where your tickets stand.' }}
                </p>
            </div>
            <Link
                href="/tickets"
                prefetch
                class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
            >
                <AppIcon name="board" class="size-4" />
                Open the board
            </Link>
        </header>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Tickets by status">
            <StatTile v-for="tile in tiles" :key="tile.key" :label="tile.label" :value="tile.count" :hint="tile.hint" :icon="tile.icon" :tone="tile.tone" />
        </section>

        <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section :class="panelClasses">
                <header :class="panelHeaderClasses">
                    <h2 class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="ticket" class="text-gray-400" /> Recent tickets</h2>
                    <Link href="/tickets?view=list&group=all" :class="panelLinkClasses">View all</Link>
                </header>

                <p v-if="recentTickets.length === 0" class="flex flex-col items-center gap-3 px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                    <AppIcon name="inbox" class="size-8 text-gray-300 dark:text-gray-600" />
                    No tickets yet.
                    <Link href="/tickets/create" :class="panelLinkClasses">Create the first one &rarr;</Link>
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sr-only">
                            <tr>
                                <th scope="col">Type</th>
                                <th scope="col">Ticket</th>
                                <th scope="col">Status</th>
                                <th scope="col">Priority</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="ticket in recentTickets" :key="ticket.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="py-3 pr-1 pl-5"><TicketTypeIcon :type="ticket.type_key" :label="ticket.type" /></td>
                                <td class="w-full max-w-0 min-w-48 px-3 py-3">
                                    <div class="flex flex-col gap-0.5">
                                        <Link :href="`/tickets/${ticket.id}`" class="truncate font-medium underline-offset-4 hover:underline">{{ ticket.subject }}</Link>
                                        <span class="truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ ticket.key }}<template v-if="user.is_admin"> &middot; {{ ticket.requester }}</template> &middot; {{ ticket.created_at }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3"><StatusBadge :status="ticket.status" /></td>
                                <td class="py-3 pr-5 pl-3"><PriorityIcon :priority="ticket.priority" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex min-w-0 flex-col gap-4">
                <BarList title="Active tickets by urgency" subtitle="Open and in progress, most urgent first" :items="priorityCounts" unit="active tickets" />
                <BarList title="Active tickets by type" subtitle="What people are asking for right now" :items="typeCounts" unit="active tickets" />
            </div>
        </div>

        <section :class="panelClasses">
            <header :class="panelHeaderClasses">
                <h2 class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="news" class="text-gray-400" /> Latest news</h2>
                <Link href="/news" :class="panelLinkClasses">All news</Link>
            </header>

            <p v-if="latestNews.length === 0" class="flex flex-col items-center gap-3 px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                <AppIcon name="news" class="size-8 text-gray-300 dark:text-gray-600" />
                Nothing announced yet.
            </p>

            <ul v-else class="grid grid-cols-1 divide-y divide-gray-100 md:grid-cols-3 md:divide-x md:divide-y-0 dark:divide-gray-800">
                <li v-for="post in latestNews" :key="post.id" class="flex flex-col gap-2 px-5 py-4">
                    <span class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400"><NewsKindBadge :kind="post.kind" /> {{ post.published_on }}</span>
                    <Link :href="`/news/${post.id}`" class="line-clamp-2 text-sm font-semibold underline-offset-4 hover:underline">{{ post.title }}</Link>
                    <span class="line-clamp-2 text-sm text-gray-600 dark:text-gray-400">{{ post.excerpt }}</span>
                </li>
            </ul>
        </section>

        <section :class="panelClasses">
            <header :class="panelHeaderClasses">
                <h2 class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="forum" class="text-gray-400" /> Latest from the forum</h2>
                <Link href="/forum" :class="panelLinkClasses">Open forum</Link>
            </header>

            <p v-if="recentThreads.length === 0" class="flex flex-col items-center gap-3 px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                <AppIcon name="forum" class="size-8 text-gray-300 dark:text-gray-600" />
                No discussions yet.
            </p>

            <ul v-else class="grid grid-cols-1 divide-y divide-gray-100 md:grid-cols-2 md:divide-y-0 dark:divide-gray-800">
                <li v-for="thread in recentThreads" :key="thread.id" class="flex items-start gap-3 px-5 py-3.5">
                    <UserAvatar :name="thread.author" :photo-url="thread.author_photo_url" :is-admin="thread.author_is_admin" small />
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <Link :href="`/forum/threads/${thread.id}`" class="truncate text-sm font-medium underline-offset-4 hover:underline">{{ thread.excerpt }}</Link>
                        <span class="flex flex-wrap items-center gap-x-1.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ thread.author }} &middot; {{ thread.author_position }} &middot;
                            <template v-if="thread.topic">{{ thread.topic.name }} &middot;</template>
                            <span class="flex items-center gap-1"><AppIcon name="comment" class="size-3.5" /> {{ thread.replies_count }}</span>
                            &middot; {{ thread.created_at }}
                        </span>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
