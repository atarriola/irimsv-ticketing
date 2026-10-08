<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import BarList from '@/Components/BarList.vue';
import MaintenanceNotice from '@/Components/MaintenanceNotice.vue';
import NewsKindBadge from '@/Components/NewsKindBadge.vue';
import PriorityIcon from '@/Components/PriorityIcon.vue';
import StatTile from '@/Components/StatTile.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TicketTypeIcon from '@/Components/TicketTypeIcon.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tiles: Array,
    metrics: Object,
    statusCounts: Array,
    priorityCounts: Array,
    typeCounts: Array,
    categoryCounts: Array,
    needsReply: Array,
    recentTickets: Array,
    recentThreads: Array,
    latestNews: Array,
    maintenanceNotice: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const firstName = computed(() => user.value.name.split(/\s+/)[0]);

const tileStyles = {
    needs_reply: { tone: 'amber', icon: 'inbox', hint: 'Waiting on the helpdesk' },
    active: { tone: 'blue', icon: 'progress', hint: 'Open or being worked on' },
    awaiting_confirmation: { tone: 'green', icon: 'check-circle', hint: 'Resolved, not yet confirmed' },
    closed: { tone: 'gray', icon: 'archive', hint: 'Completed and archived' },
};

const tiles = computed(() => props.tiles.map((tile) => ({ ...tile, ...tileStyles[tile.key] })));

function hours(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return value >= 48 ? `${Math.round(value / 24)} d` : `${value} h`;
}

const figures = computed(() => [
    { key: 'raised', label: 'Raised in 30 days', value: String(props.metrics.tickets_raised), hint: 'New tickets' },
    { key: 'first_reply', label: 'First reply', value: hours(props.metrics.first_response_hours), hint: 'Average time to the first answer' },
    { key: 'resolution', label: 'Resolution', value: hours(props.metrics.resolution_hours), hint: 'Average time to resolve' },
    { key: 'rating', label: 'Rating', value: props.metrics.rating_average === null ? '—' : `${props.metrics.rating_average} / 5`, hint: props.metrics.rated_count === 0 ? 'No ratings yet' : `From ${props.metrics.rated_count} ${props.metrics.rated_count === 1 ? 'rating' : 'ratings'}` },
]);

const panelClasses = 'hud-corners flex min-w-0 flex-col rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900';
const panelHeaderClasses = 'flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-3.5 dark:border-gray-800';
const panelLinkClasses = 'text-sm font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100';
const emptyClasses = 'flex flex-col items-center gap-3 px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400';
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Dashboard" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">Welcome back, {{ firstName }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Here is what is happening across the help desk.</p>
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

        <MaintenanceNotice :notice="maintenanceNotice" />

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Tickets by state">
            <StatTile v-for="tile in tiles" :key="tile.key" :label="tile.label" :value="tile.count" :hint="tile.hint" :icon="tile.icon" :tone="tile.tone" />
        </section>

        <section class="grid grid-cols-2 gap-4 xl:grid-cols-4" aria-label="How the helpdesk is doing">
            <div v-for="figure in figures" :key="figure.key" class="flex flex-col gap-1 rounded-lg border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-900">
                <span class="font-mono text-xs font-medium tracking-widest text-gray-600 uppercase dark:text-gray-400">{{ figure.label }}</span>
                <span class="text-2xl font-semibold tracking-tight tabular-nums">{{ figure.value }}</span>
                <span class="truncate text-xs text-gray-500 dark:text-gray-500">{{ figure.hint }}</span>
            </div>
        </section>

        <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-4">
                <section :class="panelClasses">
                    <header :class="panelHeaderClasses">
                        <h2 class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="inbox" class="text-amber-500" /> Needs a reply</h2>
                        <Link href="/tickets?view=list&group=all&waiting=support&sort=oldest" :class="panelLinkClasses">View all</Link>
                    </header>

                    <p v-if="needsReply.length === 0" :class="emptyClasses">
                        <AppIcon name="check-circle" class="size-8 text-gray-300 dark:text-gray-600" />
                        Every ticket has been answered.
                    </p>

                    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="ticket in needsReply" :key="ticket.id" class="flex items-center gap-3 px-5 py-3">
                            <TicketTypeIcon :type="ticket.type_key" :label="ticket.type" />
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <Link :href="`/tickets/${ticket.id}`" class="truncate text-sm font-medium underline-offset-4 hover:underline">{{ ticket.subject }}</Link>
                                <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ ticket.key }} &middot; {{ ticket.requester }} &middot; waiting since {{ ticket.last_activity_at }}</span>
                            </span>
                            <StatusBadge :status="ticket.status" />
                            <PriorityIcon :priority="ticket.priority" />
                        </li>
                    </ul>
                </section>

                <section :class="panelClasses">
                    <header :class="panelHeaderClasses">
                        <h2 class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="ticket" class="text-gray-400" /> Recent tickets</h2>
                        <Link href="/tickets?view=list&group=all" :class="panelLinkClasses">View all</Link>
                    </header>

                    <p v-if="recentTickets.length === 0" :class="emptyClasses">
                        <AppIcon name="inbox" class="size-8 text-gray-300 dark:text-gray-600" />
                        No tickets yet.
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
                                            <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ ticket.key }} &middot; {{ ticket.requester }} &middot; {{ ticket.created_at }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3"><StatusBadge :status="ticket.status" /></td>
                                    <td class="py-3 pr-5 pl-3"><PriorityIcon :priority="ticket.priority" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="flex min-w-0 flex-col gap-4">
                <BarList title="Active tickets by urgency" subtitle="Open and in progress, most urgent first" :items="priorityCounts" unit="active tickets" />
                <BarList title="Active tickets by type" subtitle="What people are asking for right now" :items="typeCounts" unit="active tickets" />
                <BarList title="Raised in the last 30 days by category" subtitle="Where the questions come from" :items="categoryCounts" unit="tickets" />
                <BarList title="All tickets by status" subtitle="Everything ever raised" :items="statusCounts" unit="tickets" />
            </div>
        </div>

        <section :class="panelClasses">
            <header :class="panelHeaderClasses">
                <h2 class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="news" class="text-gray-400" /> Latest news</h2>
                <Link href="/news" :class="panelLinkClasses">All news</Link>
            </header>

            <p v-if="latestNews.length === 0" :class="emptyClasses">
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

            <p v-if="recentThreads.length === 0" :class="emptyClasses">
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
