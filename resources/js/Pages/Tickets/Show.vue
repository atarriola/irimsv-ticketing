<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { echoIsConfigured, useEcho } from '@laravel/echo-vue';
import { computed, reactive } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import PriorityLabel from '@/Components/PriorityLabel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TicketConversation from '@/Components/TicketConversation.vue';
import TicketTimeline from '@/Components/TicketTimeline.vue';
import TicketTypeIcon from '@/Components/TicketTypeIcon.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import WaitingBadge from '@/Components/WaitingBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    ticket: Object,
    comments: Array,
    timeline: Array,
    statuses: Array,
    priorities: Array,
    releasePosts: Array,
    savedReplies: Array,
    can: Object,
});

const isFeatureRequest = computed(() => props.ticket.type_key === 'feature_request');
const waitingLabel = computed(() => (props.ticket.waiting_on === 'requester' && props.ticket.is_mine ? 'Waiting on you' : props.ticket.waiting_label));

const rating = reactive({ score: props.ticket.rating ?? 0, hovered: 0, comment: props.ticket.rating_comment ?? '' });

function plural(count, singular, pluralForm) {
    return count === 1 ? singular : pluralForm;
}

const actionClasses =
    'cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800';
const primaryClasses =
    'cursor-pointer rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300';
const dangerClasses =
    'cursor-pointer rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-400/30 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10';
const selectClasses =
    'w-fit cursor-pointer rounded-lg border border-gray-300 bg-white py-1.5 pr-8 pl-3 text-sm font-medium text-gray-900 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:focus:border-gray-100 dark:focus:ring-gray-100';
const panelClasses = 'flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900';
const linkClasses = 'font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100';
const termClasses = 'text-gray-500 dark:text-gray-400';
const rowClasses = 'grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-3';

function changeStatus(status) {
    router.patch(`/tickets/${props.ticket.id}/status`, { status }, { preserveScroll: true });
}

function changePriority(event) {
    router.patch(`/tickets/${props.ticket.id}/priority`, { priority: event.target.value }, { preserveScroll: true });
}

function follow(isAffected) {
    router.put(`/tickets/${props.ticket.id}/watch`, { is_affected: isAffected }, { preserveScroll: true });
}

function unfollow() {
    router.delete(`/tickets/${props.ticket.id}/watch`, { preserveScroll: true });
}

function submitRating() {
    if (rating.score === 0) {
        return;
    }

    router.put(`/tickets/${props.ticket.id}/rating`, { rating: rating.score, rating_comment: rating.comment }, { preserveScroll: true });
}

function linkRelease(event) {
    router.put(`/tickets/${props.ticket.id}/release`, { news_post_id: event.target.value || null }, { preserveScroll: true });
}

function restoreTicket() {
    router.patch(`/tickets/${props.ticket.id}/restore`, {}, { preserveScroll: true });
}

function deleteTicket() {
    router.delete(`/tickets/${props.ticket.id}`, {
        onBefore: () => confirm(`Delete ${props.ticket.key}? An administrator can restore it for 30 days.`),
    });
}

// The ticket's own details are refreshed when the conversation hears, over the socket, that something changed.
function refreshDetails() {
    router.reload({ only: ['ticket', 'timeline', 'can', 'statuses'] });
}

if (echoIsConfigured()) {
    useEcho(`ticket.${props.ticket.id}`, 'TicketConversationChanged', refreshDetails);
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <Head :title="`${ticket.key} ${ticket.subject}`" />

        <header class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <nav class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-400" aria-label="Breadcrumb">
                    <Link :href="ticket.group === 'issues' ? '/tickets' : `/tickets?group=${ticket.group}`" class="font-medium hover:text-gray-900 hover:underline dark:hover:text-gray-100">Tickets</Link>
                    <span aria-hidden="true">/</span>
                    <span class="flex items-center gap-1.5 font-medium">
                        <TicketTypeIcon :type="ticket.type_key" :label="ticket.type" />
                        {{ ticket.key }}
                    </span>
                </nav>

                <div v-if="can.update || can.delete || can.restore" class="flex flex-wrap gap-2">
                    <Link v-if="can.update" :href="`/tickets/${ticket.id}/edit`" :class="actionClasses">Edit</Link>
                    <button v-if="can.restore" type="button" :class="primaryClasses" @click="restoreTicket">Restore</button>
                    <button v-if="can.delete" type="button" :class="dangerClasses" @click="deleteTicket">Delete</button>
                </div>
            </div>

            <h1 class="text-2xl font-semibold tracking-tight break-words">{{ ticket.subject }}</h1>

            <p v-if="ticket.is_deleted" role="alert" class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-400/20 dark:bg-red-500/10 dark:text-red-200">
                <AppIcon name="trash" class="size-4" />
                This ticket was deleted on {{ ticket.deleted_at }}. Only administrators can see it until it is restored.
            </p>
        </header>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
            <div class="flex min-w-0 flex-col gap-6">
                <p v-if="ticket.forum_thread" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <AppIcon name="forum" class="size-4" />
                    Raised from the forum thread
                    <Link :href="ticket.forum_thread.url" :class="linkClasses">“{{ ticket.forum_thread.excerpt }}”</Link>
                </p>

                <section class="flex flex-col gap-2">
                    <h2 class="text-sm font-semibold">Description</h2>
                    <p class="text-sm leading-relaxed break-words whitespace-pre-line text-gray-800 dark:text-gray-200">{{ ticket.description }}</p>
                </section>

                <section v-if="ticket.attachments.length > 0" class="flex flex-col gap-2">
                    <h2 class="text-sm font-semibold">Screenshots</h2>
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <li v-for="attachment in ticket.attachments" :key="attachment.id">
                            <a :href="attachment.url" target="_blank" rel="noopener" class="flex flex-col gap-1.5">
                                <img
                                    :src="attachment.url"
                                    :alt="attachment.name"
                                    loading="lazy"
                                    class="aspect-video w-full rounded-lg border border-gray-200 bg-gray-50 object-cover transition hover:opacity-90 dark:border-gray-700 dark:bg-gray-800"
                                />
                                <span class="flex items-baseline justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="truncate">{{ attachment.name }}</span>
                                    <span class="shrink-0">{{ attachment.size }}</span>
                                </span>
                            </a>
                        </li>
                    </ul>
                </section>

                <section v-if="can.confirmResolution" class="flex flex-col gap-3 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-400/20 dark:bg-green-500/10" aria-label="Confirm the fix">
                    <p class="text-sm font-semibold text-green-900 dark:text-green-200">The helpdesk marked this ticket as {{ ticket.status_label.toLowerCase() }}. Did that sort it out?</p>
                    <p class="text-sm text-green-800 dark:text-green-200/80">If you do nothing, it will be closed automatically in a few days.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" :class="primaryClasses" @click="changeStatus('closed')">Yes, close the ticket</button>
                        <button type="button" :class="actionClasses" @click="changeStatus('open')">No, reopen it</button>
                    </div>
                </section>

                <TicketConversation
                    :ticket-id="ticket.id"
                    :initial-messages="comments"
                    :can-comment="can.comment"
                    :can-add-internal-note="can.addInternalNote"
                    :saved-replies="savedReplies"
                    @changed="refreshDetails"
                />

                <TicketTimeline :events="timeline" />
            </div>

            <aside class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <template v-if="can.changeStatus">
                        <label for="status" class="sr-only">Status</label>
                        <select
                            id="status"
                            class="w-fit cursor-pointer rounded-lg border-0 bg-gray-900 py-2 pr-9 pl-3.5 text-sm font-semibold text-white outline-none hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300 dark:focus-visible:outline-gray-100"
                            @change="changeStatus($event.target.value)"
                        >
                            <option v-for="status in statuses" :key="status.value" :value="status.value" :selected="status.value === ticket.status" class="bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
                                {{ status.label }}
                            </option>
                        </select>
                    </template>
                    <StatusBadge v-else :status="ticket.status" />
                    <WaitingBadge :waiting-on="ticket.waiting_on" :label="waitingLabel" />
                </div>

                <section v-if="can.watch" :class="panelClasses" aria-label="Follow this ticket">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <template v-if="ticket.supporters_count > 0">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ ticket.supporters_count }}</span>
                            {{ plural(ticket.supporters_count, 'person', 'people') }} {{ isFeatureRequest ? plural(ticket.supporters_count, 'wants', 'want') + ' this too.' : plural(ticket.supporters_count, 'has', 'have') + ' this problem too.' }}
                        </template>
                        <template v-else>{{ isFeatureRequest ? 'Nobody has supported this request yet.' : 'Nobody else has reported this yet.' }}</template>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="!ticket.is_affected" type="button" :class="primaryClasses" class="flex items-center gap-1.5" @click="follow(true)">
                            <AppIcon name="thumb-up" class="size-4" />
                            {{ isFeatureRequest ? 'I want this too' : 'I have this too' }}
                        </button>
                        <button v-else type="button" :class="actionClasses" class="flex items-center gap-1.5" @click="follow(false)">
                            <AppIcon name="check" class="size-4" />
                            {{ isFeatureRequest ? 'Supported' : 'Affects me' }}
                        </button>
                        <button v-if="!ticket.is_watching" type="button" :class="actionClasses" class="flex items-center gap-1.5" @click="follow(false)">
                            <AppIcon name="eye" class="size-4" />
                            Follow
                        </button>
                        <button v-else type="button" :class="actionClasses" class="flex items-center gap-1.5" @click="unfollow">
                            <AppIcon name="x-mark" class="size-4" />
                            Unfollow
                        </button>
                    </div>
                </section>
                <p v-else-if="ticket.supporters_count > 0" class="flex items-center gap-1.5 px-1 text-sm text-gray-600 dark:text-gray-400">
                    <AppIcon name="thumb-up" class="size-4" />
                    {{ ticket.supporters_count }} {{ plural(ticket.supporters_count, 'person', 'people') }} {{ isFeatureRequest ? plural(ticket.supporters_count, 'wants', 'want') : plural(ticket.supporters_count, 'has', 'have') }} this too
                </p>

                <section v-if="can.rate" :class="panelClasses" aria-label="Rate the help">
                    <h2 class="text-sm font-semibold">{{ ticket.rating ? 'Your rating' : 'How was the help?' }}</h2>
                    <div class="flex items-center gap-1" role="radiogroup" aria-label="Rating out of five">
                        <button
                            v-for="score in [1, 2, 3, 4, 5]"
                            :key="score"
                            type="button"
                            role="radio"
                            :aria-checked="rating.score === score"
                            :aria-label="`${score} out of 5`"
                            class="cursor-pointer rounded p-0.5 transition"
                            :class="score <= (rating.hovered || rating.score) ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600'"
                            @mouseenter="rating.hovered = score"
                            @mouseleave="rating.hovered = 0"
                            @click="rating.score = score"
                        >
                            <AppIcon name="star" class="size-6" :class="score <= (rating.hovered || rating.score) ? 'fill-current' : ''" />
                        </button>
                    </div>
                    <label for="rating-comment" class="sr-only">Comment</label>
                    <textarea
                        id="rating-comment"
                        v-model="rating.comment"
                        rows="2"
                        placeholder="Anything we should know? (optional)"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:focus:border-gray-100 dark:focus:ring-gray-100"
                    ></textarea>
                    <button type="button" :disabled="rating.score === 0" :class="primaryClasses" class="w-fit disabled:cursor-not-allowed disabled:opacity-60" @click="submitRating">{{ ticket.rating ? 'Update rating' : 'Send rating' }}</button>
                </section>
                <section v-else-if="ticket.rating" :class="panelClasses" aria-label="Rating">
                    <h2 class="text-sm font-semibold">Requester's rating</h2>
                    <span class="flex items-center gap-0.5 text-amber-500" :aria-label="`${ticket.rating} out of 5`">
                        <AppIcon v-for="score in 5" :key="score" name="star" class="size-5" :class="score <= ticket.rating ? 'fill-current' : 'text-gray-300 dark:text-gray-600'" />
                    </span>
                    <p v-if="ticket.rating_comment" class="text-sm break-words whitespace-pre-line text-gray-700 dark:text-gray-300">{{ ticket.rating_comment }}</p>
                </section>

                <section v-if="isFeatureRequest && (ticket.release_post || can.linkRelease)" :class="panelClasses" aria-label="Release">
                    <h2 class="text-sm font-semibold">Delivered in</h2>
                    <Link v-if="ticket.release_post" :href="ticket.release_post.url" class="flex items-center gap-2 text-sm" :class="linkClasses">
                        <AppIcon name="rocket" class="size-4" />
                        {{ ticket.release_post.title }}
                    </Link>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">Not linked to a release yet.</p>
                    <template v-if="can.linkRelease">
                        <label for="release-post" class="sr-only">Release post</label>
                        <select id="release-post" :class="selectClasses" class="w-full" @change="linkRelease">
                            <option value="" :selected="!ticket.release_post">No release</option>
                            <option v-for="post in releasePosts" :key="post.id" :value="post.id" :selected="ticket.release_post?.id === post.id">{{ post.title }}</option>
                        </select>
                    </template>
                </section>

                <section class="flex flex-col rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">Details</h2>

                    <dl class="flex flex-col gap-4 p-4 text-sm">
                        <div :class="rowClasses">
                            <dt :class="termClasses">Reporter</dt>
                            <dd class="flex min-w-0 items-center gap-2">
                                <UserAvatar :name="ticket.requester.name" :photo-url="ticket.requester.photo_url" tiny />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ ticket.requester.name }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ ticket.requester.position }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ ticket.requester.email }}</span>
                                </span>
                            </dd>
                        </div>
                        <div :class="rowClasses">
                            <dt :class="termClasses">Priority</dt>
                            <dd>
                                <template v-if="can.changePriority">
                                    <label for="priority" class="sr-only">Priority</label>
                                    <select id="priority" :class="selectClasses" @change="changePriority">
                                        <option v-for="priority in priorities" :key="priority.value" :value="priority.value" :selected="priority.value === ticket.priority">{{ priority.label }}</option>
                                    </select>
                                </template>
                                <PriorityLabel v-else :priority="ticket.priority" />
                            </dd>
                        </div>
                        <div :class="rowClasses">
                            <dt :class="termClasses">Type</dt>
                            <dd class="flex items-center gap-2 font-medium"><TicketTypeIcon :type="ticket.type_key" :label="ticket.type" /> {{ ticket.type }}</dd>
                        </div>
                        <div :class="rowClasses">
                            <dt :class="termClasses">Category</dt>
                            <dd>
                                <span v-if="ticket.category" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ ticket.category }}</span>
                                <span v-else class="text-gray-500 dark:text-gray-400">None</span>
                            </dd>
                        </div>
                        <div :class="rowClasses">
                            <dt :class="termClasses">Visibility</dt>
                            <dd class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
                                <AppIcon :name="ticket.is_shared ? 'users' : 'lock'" class="size-4 text-gray-400" />
                                {{ ticket.is_shared ? 'Shared with everyone' : 'Requester and helpdesk only' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <dl class="flex flex-col gap-1 px-1 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex gap-1"><dt>Created</dt><dd>{{ ticket.created_at }}</dd></div>
                    <div class="flex gap-1"><dt>Last activity</dt><dd>{{ ticket.updated_at }}</dd></div>
                    <div v-if="ticket.resolved_at" class="flex gap-1"><dt>Resolved</dt><dd>{{ ticket.resolved_at }}</dd></div>
                    <div v-if="ticket.closed_at" class="flex gap-1"><dt>Closed</dt><dd>{{ ticket.closed_at }}</dd></div>
                </dl>
            </aside>
        </div>
    </div>
</template>
