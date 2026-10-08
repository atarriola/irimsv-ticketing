<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Pagination from '@/Components/Pagination.vue';
import PriorityIcon from '@/Components/PriorityIcon.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TicketCard from '@/Components/TicketCard.vue';
import TicketTypeIcon from '@/Components/TicketTypeIcon.vue';
import WaitingBadge from '@/Components/WaitingBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    view: String,
    group: String,
    groups: Array,
    filters: Object,
    statuses: Array,
    priorities: Array,
    categories: Array,
    waitingOptions: Array,
    sorts: Array,
    columns: Array,
    tickets: Object,
    can: Object,
});

// The heading matches the sidebar entry that leads to each group.
const headings = { all: 'All tickets', issues: 'Tickets', feature_requests: 'Feature requests' };

const form = reactive({ ...props.filters });
const hasFilters = computed(() => Boolean(form.q || form.status || form.priority || form.category || form.waiting || form.mine || form.trashed));
const createHref = computed(() => `/tickets/create?type=${props.group === 'feature_requests' ? 'feature_request' : 'bug_report'}`);

/**
 * Build the query for the tickets page, leaving out anything that is at its default so links stay short.
 */
function ticketsQuery(overrides = {}) {
    const params = { view: props.view, group: props.group, ...form, ...overrides };
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        const isDefault =
            (key === 'view' && value === 'board') ||
            (key === 'group' && value === 'issues') ||
            (key === 'sort' && value === 'newest') ||
            ((key === 'status' || key === 'sort' || key === 'trashed') && params.view !== 'list');

        if (value && !isDefault) {
            query.set(key, value === true ? '1' : value);
        }
    });

    return query;
}

function ticketsUrl(overrides = {}) {
    const query = ticketsQuery(overrides);

    return query.size > 0 ? `/tickets?${query}` : '/tickets';
}

// The export takes the same filters as the list, but not the page or the view.
const exportHref = computed(() => {
    const query = ticketsQuery();

    query.delete('view');
    query.delete('page');

    return query.size > 0 ? `/tickets/export?${query}` : '/tickets/export';
});

function applyFilters() {
    router.get(ticketsUrl(), {}, { preserveState: true, preserveScroll: true, replace: true });
}

let searchTimer = null;

function searchSoon() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function clearFilters() {
    Object.assign(form, { q: '', status: null, priority: null, category: null, waiting: null, mine: false, trashed: false, sort: 'newest' });
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

// Bulk changes: an administrator ticks rows in the list and applies one status or priority to all of them.
const selected = ref([]);
const bulk = reactive({ status: '', priority: '' });
const pageIds = computed(() => (props.tickets?.data ?? []).map((ticket) => ticket.id));
const allSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selected.value.includes(id)));

watch(
    () => props.tickets,
    () => (selected.value = []),
);

function toggleAll(event) {
    selected.value = event.target.checked ? [...pageIds.value] : [];
}

function applyBulk() {
    if (selected.value.length === 0 || (!bulk.status && !bulk.priority)) {
        return;
    }

    router.patch(
        '/tickets/bulk',
        { ids: selected.value, status: bulk.status || null, priority: bulk.priority || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                bulk.status = '';
                bulk.priority = '';
            },
        },
    );
}

const statusIcons = {
    open: { icon: 'inbox', classes: 'text-gray-500' },
    under_review: { icon: 'search', classes: 'text-purple-600 dark:text-purple-400' },
    planned: { icon: 'calendar', classes: 'text-indigo-600 dark:text-indigo-400' },
    in_progress: { icon: 'progress', classes: 'text-blue-600 dark:text-blue-400' },
    resolved: { icon: 'check-circle', classes: 'text-green-600 dark:text-green-400' },
    shipped: { icon: 'rocket', classes: 'text-emerald-600 dark:text-emerald-400' },
    closed: { icon: 'archive', classes: 'text-gray-400' },
};

const controlClasses =
    'rounded-lg border border-gray-300 bg-white py-1.5 text-sm text-gray-900 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-100 dark:focus:ring-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
const segmentClasses = 'flex items-center gap-2 px-3 py-1.5 text-sm font-medium transition';
const activeSegmentClasses = 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900';
const idleSegmentClasses = 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100';
const toggleClasses = 'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition';
const idleToggleClasses = 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800';
const linkClasses = 'text-sm font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900 dark:text-gray-100 dark:decoration-gray-700 dark:hover:decoration-gray-100';
</script>

<template>
    <div class="flex min-w-0 flex-col gap-5">
        <Head title="Tickets" />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">{{ form.mine ? 'My tickets' : headings[group] }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ form.mine ? 'The tickets you raised.' : 'Your tickets, the ones shared with everyone, and every ticket for the helpdesk.' }}
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
                    <span class="rounded-full px-1.5 text-xs font-semibold tabular-nums" :class="item.key === group ? 'bg-white/20 dark:bg-gray-900/15' : 'bg-gray-100 dark:bg-gray-800'" :title="`${item.count} active`">{{ item.count }}</span>
                </Link>
            </div>

            <label :class="[toggleClasses, form.mine ? activeSegmentClasses + ' border-gray-900 dark:border-gray-100' : idleToggleClasses]">
                <input v-model="form.mine" type="checkbox" class="sr-only" @change="applyFilters" />
                <AppIcon name="user" class="size-4" />
                My tickets
            </label>

            <label for="filter-priority" class="sr-only">Priority</label>
            <select id="filter-priority" v-model="form.priority" class="pr-8 pl-3" :class="controlClasses" @change="applyFilters">
                <option :value="null">Any priority</option>
                <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
            </select>

            <label for="filter-category" class="sr-only">Category</label>
            <select id="filter-category" v-model="form.category" class="pr-8 pl-3" :class="controlClasses" @change="applyFilters">
                <option :value="null">Any category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>

            <label for="filter-waiting" class="sr-only">Waiting on</label>
            <select id="filter-waiting" v-model="form.waiting" class="pr-8 pl-3" :class="controlClasses" @change="applyFilters">
                <option :value="null">Waiting on anyone</option>
                <option v-for="option in waitingOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>

            <template v-if="view === 'list'">
                <label for="filter-status" class="sr-only">Status</label>
                <select id="filter-status" v-model="form.status" class="pr-8 pl-3" :class="controlClasses" @change="applyFilters">
                    <option :value="null">Any status</option>
                    <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>

                <label for="filter-sort" class="sr-only">Sort</label>
                <select id="filter-sort" v-model="form.sort" class="pr-8 pl-3" :class="controlClasses" @change="applyFilters">
                    <option v-for="sort in sorts" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
                </select>

                <label v-if="can.viewTrashed" :class="[toggleClasses, form.trashed ? 'border-red-600 bg-red-600 text-white' : idleToggleClasses]">
                    <input v-model="form.trashed" type="checkbox" class="sr-only" @change="applyFilters" />
                    <AppIcon name="trash" class="size-4" />
                    Deleted
                </label>
            </template>

            <button v-if="hasFilters" type="button" class="cursor-pointer" :class="linkClasses" @click="clearFilters">Clear filters</button>

            <a v-if="view === 'list' && can.export" :href="exportHref" class="ml-auto flex items-center gap-1.5" :class="linkClasses">
                <AppIcon name="download" class="size-4" />
                Export CSV
            </a>
        </div>

        <div v-if="view === 'board'" class="-mx-4 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="grid items-start gap-3" :style="{ gridTemplateColumns: `repeat(${columns.length}, minmax(15rem, 1fr))`, minWidth: `${columns.length * 15}rem` }">
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
                        <TicketCard :ticket="ticket" show-requester draggable="false" />
                    </div>

                    <p v-if="column.tickets.length === 0" class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-gray-300 px-2 py-8 text-center text-xs text-gray-500 dark:border-gray-700 dark:text-gray-500">
                        <AppIcon :name="statusIcons[column.status].icon" class="size-6 text-gray-400 dark:text-gray-600" />
                        {{ can.moveCards && dragged ? 'Drop here' : 'No tickets' }}
                    </p>

                    <Link v-if="column.total > column.tickets.length" :href="ticketsUrl({ view: 'list', status: column.status })" class="px-2 py-1.5 text-xs" :class="linkClasses">
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
                <Link :href="createHref" class="text-sm font-semibold" :class="linkClasses">Create a ticket &rarr;</Link>
            </div>

            <template v-else>
                <div
                    v-if="can.bulkUpdate && selected.length > 0"
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-900 bg-gray-900 px-4 py-2.5 text-sm text-white dark:border-gray-100 dark:bg-gray-100 dark:text-gray-900"
                    role="region"
                    aria-label="Bulk actions"
                >
                    <span class="font-semibold tabular-nums">{{ selected.length }} selected</span>
                    <label for="bulk-status" class="sr-only">Status to apply</label>
                    <select id="bulk-status" v-model="bulk.status" class="rounded-lg border-0 bg-white px-3 py-1.5 text-sm text-gray-900 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Keep status</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                    <label for="bulk-priority" class="sr-only">Priority to apply</label>
                    <select id="bulk-priority" v-model="bulk.priority" class="rounded-lg border-0 bg-white px-3 py-1.5 text-sm text-gray-900 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Keep priority</option>
                        <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
                    </select>
                    <button type="button" :disabled="!bulk.status && !bulk.priority" class="cursor-pointer rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-700" @click="applyBulk">Apply</button>
                    <button type="button" class="cursor-pointer text-sm underline underline-offset-4" @click="selected = []">Clear</button>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <tr>
                                <th v-if="can.bulkUpdate" scope="col" class="py-2.5 pl-4">
                                    <input type="checkbox" :checked="allSelected" aria-label="Select every ticket on this page" class="size-4 rounded border-gray-300 accent-gray-900 dark:accent-gray-100" @change="toggleAll" />
                                </th>
                                <th scope="col" class="py-2.5 pr-2 pl-4 font-semibold">Type</th>
                                <th scope="col" class="px-2 py-2.5 font-semibold">Key</th>
                                <th scope="col" class="px-2 py-2.5 font-semibold">Summary</th>
                                <th scope="col" class="px-2 py-2.5 font-semibold">Status</th>
                                <th scope="col" class="px-2 py-2.5 font-semibold">Priority</th>
                                <th scope="col" class="px-2 py-2.5 font-semibold">Waiting on</th>
                                <th scope="col" class="px-2 py-2.5 font-semibold">Reporter</th>
                                <th scope="col" class="py-2.5 pr-4 pl-2 font-semibold">Activity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50" :class="selected.includes(ticket.id) ? 'bg-gray-50 dark:bg-gray-800/50' : ''">
                                <td v-if="can.bulkUpdate" class="py-2.5 pl-4">
                                    <input v-model="selected" type="checkbox" :value="ticket.id" :aria-label="`Select ${ticket.key}`" class="size-4 rounded border-gray-300 accent-gray-900 dark:accent-gray-100" />
                                </td>
                                <td class="py-2.5 pr-2 pl-4"><TicketTypeIcon :type="ticket.type_key" :label="ticket.type" /></td>
                                <td class="px-2 py-2.5 whitespace-nowrap">
                                    <Link :href="`/tickets/${ticket.id}`" class="font-medium" :class="linkClasses">{{ ticket.key }}</Link>
                                </td>
                                <td class="w-full max-w-0 min-w-56 px-2 py-2.5">
                                    <span class="flex items-center gap-2">
                                        <Link :href="`/tickets/${ticket.id}`" prefetch class="block min-w-0 truncate underline-offset-4 hover:underline">{{ ticket.subject }}</Link>
                                        <span v-if="ticket.is_deleted" class="shrink-0 rounded bg-red-100 px-1.5 py-0.5 text-[0.625rem] font-bold text-red-700 uppercase dark:bg-red-500/20 dark:text-red-300">Deleted</span>
                                        <span v-if="ticket.supporters_count > 0" class="flex shrink-0 items-center gap-1 text-xs text-gray-500 dark:text-gray-400" :title="`${ticket.supporters_count} ${ticket.type_key === 'feature_request' ? 'votes' : 'people affected'}`">
                                            <AppIcon name="thumb-up" class="size-3.5" />
                                            {{ ticket.supporters_count }}
                                        </span>
                                    </span>
                                </td>
                                <td class="px-2 py-2.5"><StatusBadge :status="ticket.status" /></td>
                                <td class="px-2 py-2.5">
                                    <span class="flex items-center gap-1.5 whitespace-nowrap capitalize"><PriorityIcon :priority="ticket.priority" aria-hidden="true" /> {{ ticket.priority }}</span>
                                </td>
                                <td class="px-2 py-2.5"><WaitingBadge :waiting-on="ticket.waiting_on" :label="ticket.waiting_label" /></td>
                                <td class="px-2 py-2.5 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                    <span class="block">{{ ticket.requester }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-500">{{ ticket.requester_position }}</span>
                                </td>
                                <td class="py-2.5 pr-4 pl-2 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                    <span class="block">{{ ticket.last_activity_at ?? ticket.created_at }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-500">raised {{ ticket.created_at }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <Pagination :paginator="tickets" />
        </template>
    </div>
</template>
