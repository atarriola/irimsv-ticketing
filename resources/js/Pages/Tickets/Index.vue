<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Pagination from '@/Components/Pagination.vue';
import PriorityIcon from '@/Components/PriorityIcon.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TicketCard from '@/Components/TicketCard.vue';
import TicketTypeIcon from '@/Components/TicketTypeIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    view: String,
    group: String,
    groups: Array,
    filters: Object,
    statuses: Array,
    priorities: Array,
    columns: Array,
    tickets: Object,
    can: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// The heading matches the sidebar entry that leads to each group.
const headings = { all: 'All tickets', issues: 'Tickets', feature_requests: 'Feature requests' };

const form = reactive({ ...props.filters });
const hasFilters = computed(() => Boolean(form.q || form.status || form.priority));
const createHref = computed(() => `/tickets/create?type=${props.group === 'feature_requests' ? 'feature_request' : 'bug_report'}`);

/**
 * Build the address for the tickets page, leaving out anything that is at its default so links stay short.
 */
function ticketsUrl(overrides = {}) {
    const params = { view: props.view, group: props.group, ...form, ...overrides };
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        const isDefault = (key === 'view' && value === 'board') || (key === 'group' && value === 'issues') || (key === 'status' && params.view !== 'list');

        if (value && !isDefault) {
            query.set(key, value);
        }
    });

    return query.size > 0 ? `/tickets?${query}` : '/tickets';
}

function applyFilters() {
    router.get(ticketsUrl(), {}, { preserveState: true, preserveScroll: true, replace: true });
}

let searchTimer = null;

function searchSoon() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function clearFilters() {
    Object.assign(form, { q: '', status: null, priority: null });
    applyFilters();
}

const dragged = ref(null);
const hoveredColumn = ref(null);

function startDrag(event, ticket) {
    dragged.value = ticket;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', ticket.key);
}

function endDrag() {
    dragged.value = null;
    hoveredColumn.value = null;
}

function dropOn(status) {
    const ticket = dragged.value;

    endDrag();

    if (!ticket || ticket.status === status) {
        return;
    }

    router.patch(
        `/tickets/${ticket.id}/status`,
        { status },
        {
            preserveScroll: true,
            preserveState: true,
            optimistic: (current) => ({
                columns: current.columns.map((column) => {
                    if (column.status === ticket.status) {
                        return { ...column, total: column.total - 1, tickets: column.tickets.filter((card) => card.id !== ticket.id) };
                    }

                    if (column.status === status) {
                        return { ...column, total: column.total + 1, tickets: [{ ...ticket, status }, ...column.tickets] };
                    }

                    return column;
                }),
            }),
        },
    );
}

const statusIcons = {
    open: { icon: 'inbox', classes: 'text-gray-500' },
    in_progress: { icon: 'progress', classes: 'text-blue-600 dark:text-blue-400' },
    resolved: { icon: 'check-circle', classes: 'text-green-600 dark:text-green-400' },
    closed: { icon: 'archive', classes: 'text-gray-400' },
};

const controlClasses =
    'rounded-lg border border-gray-300 bg-white py-1.5 text-sm text-gray-900 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-100 dark:focus:ring-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
const segmentClasses = 'flex items-center gap-2 px-3 py-1.5 text-sm font-medium transition';
const activeSegmentClasses = 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900';
const idleSegmentClasses = 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100';
</script>

<template>
    <div class="flex min-w-0 flex-col gap-5">
        <Head title="Tickets" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">{{ headings[group] }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ user.is_admin ? 'Every ticket raised across the system.' : 'The tickets you have raised.' }}
                </p>
            </div>

            <div class="flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-700" role="group" aria-label="View">
                <Link :href="ticketsUrl({ view: 'board' })" :class="[segmentClasses, view === 'board' ? activeSegmentClasses : idleSegmentClasses]" :aria-current="view === 'board' ? 'page' : undefined">
                    <AppIcon name="board" class="size-4" />
                    Board
                </Link>
                <Link
                    :href="ticketsUrl({ view: 'list' })"
                    class="border-l border-gray-300 dark:border-gray-700"
                    :class="[segmentClasses, view === 'list' ? activeSegmentClasses : idleSegmentClasses]"
                    :aria-current="view === 'list' ? 'page' : undefined"
                >
                    <AppIcon name="list" class="size-4" />
                    List
                </Link>
            </div>
        </header>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <label for="ticket-search" class="sr-only">Search tickets</label>
                <AppIcon name="search" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-gray-400" />
                <input id="ticket-search" v-model="form.q" type="search" placeholder="Search or TKT-12" class="w-52 pr-3 pl-8" :class="controlClasses" @input="searchSoon" />
            </div>

            <div class="flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-700" role="group" aria-label="Kind of ticket">
                <Link
                    v-for="(item, index) in groups"
                    :key="item.key"
                    :href="ticketsUrl({ group: item.key })"
                    :class="[segmentClasses, item.key === group ? activeSegmentClasses : idleSegmentClasses, index > 0 ? 'border-l border-gray-300 dark:border-gray-700' : '']"
                    :aria-current="item.key === group ? 'page' : undefined"
                >
                    {{ item.label }}
                    <span class="rounded-full px-1.5 text-xs font-semibold tabular-nums" :class="item.key === group ? 'bg-white/20 dark:bg-gray-900/15' : 'bg-gray-100 dark:bg-gray-800'">{{ item.count }}</span>
                </Link>
            </div>

            <label for="filter-priority" class="sr-only">Priority</label>
            <select id="filter-priority" v-model="form.priority" class="pr-8 pl-3" :class="controlClasses" @change="applyFilters">
                <option :value="null">Any priority</option>
                <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
            </select>

            <template v-if="view === 'list'">
                <label for="filter-status" class="sr-only">Status</label>
                <select id="filter-status" v-model="form.status" class="pr-8 pl-3" :class="controlClasses" @change="applyFilters">
                    <option :value="null">Any status</option>
                    <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
            </template>

            <button v-if="hasFilters" type="button" class="cursor-pointer text-sm font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100" @click="clearFilters">Clear filters</button>
        </div>

        <div v-if="view === 'board'" class="-mx-4 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="grid min-w-[60rem] grid-cols-4 items-start gap-3">
                <section
                    v-for="column in columns"
                    :key="column.status"
                    class="flex flex-col gap-2 rounded-lg bg-gray-100 p-2 transition dark:bg-gray-900"
                    :class="hoveredColumn === column.status && dragged?.status !== column.status ? 'ring-2 ring-gray-900 dark:ring-gray-100' : ''"
                    :aria-label="`${column.label}, ${column.total} tickets`"
                    @dragover.prevent="hoveredColumn = column.status"
                    @dragleave="hoveredColumn = hoveredColumn === column.status ? null : hoveredColumn"
                    @drop.prevent="dropOn(column.status)"
                >
                    <h2 class="flex items-center gap-2 px-2 pt-1.5 pb-1 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
                        <AppIcon :name="statusIcons[column.status].icon" class="size-4" :class="statusIcons[column.status].classes" />
                        {{ column.label }}
                        <span class="ml-auto rounded-full bg-gray-200 px-2 py-0.5 text-[0.6875rem] tabular-nums dark:bg-gray-800">{{ column.total }}</span>
                    </h2>

                    <div
                        v-for="ticket in column.tickets"
                        :key="ticket.id"
                        :draggable="can.moveCards"
                        :class="[can.moveCards ? 'cursor-grab active:cursor-grabbing' : '', dragged?.id === ticket.id ? 'opacity-40' : '']"
                        @dragstart="startDrag($event, ticket)"
                        @dragend="endDrag"
                    >
                        <TicketCard :ticket="ticket" :show-requester="user.is_admin" draggable="false" />
                    </div>

                    <p v-if="column.tickets.length === 0" class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-gray-300 px-2 py-8 text-center text-xs text-gray-500 dark:border-gray-700 dark:text-gray-500">
                        <AppIcon :name="statusIcons[column.status].icon" class="size-6 text-gray-400 dark:text-gray-600" />
                        {{ can.moveCards && dragged ? 'Drop here' : 'No tickets' }}
                    </p>

                    <Link
                        v-if="column.total > column.tickets.length"
                        :href="ticketsUrl({ view: 'list', status: column.status })"
                        class="px-2 py-1.5 text-xs font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100"
                    >
                        View all {{ column.total }} in the list &rarr;
                    </Link>
                </section>
            </div>

            <p v-if="can.moveCards" class="pt-3 text-xs text-gray-500 dark:text-gray-400">Drag a card to another column to change its status.</p>
        </div>

        <template v-else>
            <div
                v-if="tickets.data.length === 0"
                class="flex flex-col items-center gap-4 rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-900"
            >
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ hasFilters ? 'No tickets match these filters.' : 'No tickets here yet.' }}</p>
                <Link :href="createHref" class="text-sm font-semibold text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100">Create a ticket &rarr;</Link>
            </div>

            <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="py-2.5 pr-2 pl-4 font-semibold">Type</th>
                            <th scope="col" class="px-2 py-2.5 font-semibold">Key</th>
                            <th scope="col" class="px-2 py-2.5 font-semibold">Summary</th>
                            <th scope="col" class="px-2 py-2.5 font-semibold">Status</th>
                            <th scope="col" class="px-2 py-2.5 font-semibold">Priority</th>
                            <th v-if="user.is_admin" scope="col" class="px-2 py-2.5 font-semibold">Reporter</th>
                            <th scope="col" class="py-2.5 pr-4 pl-2 font-semibold">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-2.5 pr-2 pl-4"><TicketTypeIcon :type="ticket.type_key" :label="ticket.type" /></td>
                            <td class="px-2 py-2.5 whitespace-nowrap">
                                <Link :href="`/tickets/${ticket.id}`" class="font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100">{{ ticket.key }}</Link>
                            </td>
                            <td class="w-full max-w-0 min-w-56 px-2 py-2.5">
                                <Link :href="`/tickets/${ticket.id}`" prefetch class="block truncate underline-offset-4 hover:underline">{{ ticket.subject }}</Link>
                            </td>
                            <td class="px-2 py-2.5"><StatusBadge :status="ticket.status" /></td>
                            <td class="px-2 py-2.5">
                                <span class="flex items-center gap-1.5 whitespace-nowrap capitalize"><PriorityIcon :priority="ticket.priority" aria-hidden="true" /> {{ ticket.priority }}</span>
                            </td>
                            <td v-if="user.is_admin" class="px-2 py-2.5 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                <span class="block">{{ ticket.requester }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-500">{{ ticket.requester_position }}</span>
                            </td>
                            <td class="py-2.5 pr-4 pl-2 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ ticket.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="tickets" />
        </template>
    </div>
</template>
