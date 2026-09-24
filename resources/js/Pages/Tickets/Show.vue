<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PriorityLabel from '@/Components/PriorityLabel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TicketConversation from '@/Components/TicketConversation.vue';
import TicketTypeIcon from '@/Components/TicketTypeIcon.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    ticket: Object,
    comments: Array,
    statuses: Array,
    can: Object,
});

const actionClasses =
    'cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800';
const termClasses = 'text-gray-500 dark:text-gray-400';
const rowClasses = 'grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-3';

function changeStatus(event) {
    router.patch(`/tickets/${props.ticket.id}/status`, { status: event.target.value }, { preserveScroll: true });
}

function deleteTicket() {
    router.delete(`/tickets/${props.ticket.id}`, {
        onBefore: () => confirm(`Delete ${props.ticket.key} and its whole conversation?`),
    });
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

                <div v-if="can.update || can.delete" class="flex flex-wrap gap-2">
                    <Link v-if="can.update" :href="`/tickets/${ticket.id}/edit`" :class="actionClasses">Edit</Link>
                    <button
                        v-if="can.delete"
                        type="button"
                        class="cursor-pointer rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-400/30 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
                        @click="deleteTicket"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <h1 class="text-2xl font-semibold tracking-tight break-words">{{ ticket.subject }}</h1>
        </header>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
            <div class="flex min-w-0 flex-col gap-6">
                <section class="flex flex-col gap-2">
                    <h2 class="text-sm font-semibold">Description</h2>
                    <p class="text-sm leading-relaxed break-words whitespace-pre-line text-gray-800 dark:text-gray-200">{{ ticket.description }}</p>
                </section>

                <TicketConversation :ticket-id="ticket.id" :initial-messages="comments" :can-comment="can.comment" />
            </div>

            <aside class="flex flex-col gap-4">
                <div v-if="can.changeStatus" class="flex flex-col gap-1.5">
                    <label for="status" class="sr-only">Status</label>
                    <select id="status" class="w-fit cursor-pointer rounded-lg border-0 bg-gray-900 py-2 pr-9 pl-3.5 text-sm font-semibold text-white outline-none hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300 dark:focus-visible:outline-gray-100" @change="changeStatus">
                        <option v-for="status in statuses" :key="status.value" :value="status.value" :selected="status.value === ticket.status" class="bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
                <StatusBadge v-else :status="ticket.status" class="w-fit" />

                <section class="flex flex-col rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">Details</h2>

                    <dl class="flex flex-col gap-4 p-4 text-sm">
                        <div :class="rowClasses">
                            <dt :class="termClasses">Reporter</dt>
                            <dd class="flex min-w-0 items-center gap-2">
                                <UserAvatar :name="ticket.requester.name" tiny />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ ticket.requester.name }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ ticket.requester.email }}</span>
                                </span>
                            </dd>
                        </div>
                        <div :class="rowClasses">
                            <dt :class="termClasses">Priority</dt>
                            <dd><PriorityLabel :priority="ticket.priority" /></dd>
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
                    </dl>
                </section>

                <dl class="flex flex-col gap-1 px-1 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex gap-1"><dt>Created</dt><dd>{{ ticket.created_at }}</dd></div>
                    <div class="flex gap-1"><dt>Updated</dt><dd>{{ ticket.updated_at }}</dd></div>
                    <div v-if="ticket.resolved_at" class="flex gap-1"><dt>Resolved</dt><dd>{{ ticket.resolved_at }}</dd></div>
                    <div v-if="ticket.closed_at" class="flex gap-1"><dt>Closed</dt><dd>{{ ticket.closed_at }}</dd></div>
                </dl>
            </aside>
        </div>
    </div>
</template>
