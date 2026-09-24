<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLogo from '@/Components/AppLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isMenuOpen = ref(false);
const isUserMenuOpen = ref(false);
const userMenu = ref(null);
const search = ref('');

const location = computed(() => new URL(page.url, 'http://localhost'));
const startsWith = (path) => location.value.pathname === path || location.value.pathname.startsWith(`${path}/`);

// The tickets page reports the group it is showing; a single ticket's pages are filed under the group its type belongs to.
const currentTicketGroup = computed(() => {
    if (!startsWith('/tickets')) {
        return null;
    }

    const ticketType = page.props.ticket?.type ?? page.props.defaultType;

    return page.props.group ?? page.props.ticket?.group ?? (ticketType === 'feature_request' ? 'feature_requests' : 'issues');
});

// Each entry is a group on the tickets page, so "current" follows the group whether it is shown as a board or a list.
const ticketGroups = computed(() => [
    { label: 'All tickets', href: '/tickets?view=list&group=all', icon: 'list', isCurrent: currentTicketGroup.value === 'all' },
    { label: 'Tickets', href: '/tickets', icon: 'ticket', isCurrent: currentTicketGroup.value === 'issues' },
    { label: 'Feature requests', href: '/tickets?group=feature_requests', icon: 'lightbulb', isCurrent: currentTicketGroup.value === 'feature_requests' },
]);

const sections = computed(() => [
    { heading: null, items: [{ label: 'Dashboard', href: '/dashboard', icon: 'dashboard', isCurrent: startsWith('/dashboard') }] },
    { heading: 'Tickets', items: ticketGroups.value },
    { heading: 'Community', items: [{ label: 'Forum', href: '/forum', icon: 'forum', isCurrent: startsWith('/forum') }] },
    ...(user.value.is_admin
        ? [
              {
                  heading: 'Administration',
                  items: [
                      { label: 'Users', href: '/admin/users', icon: 'users', isCurrent: startsWith('/admin/users') },
                      { label: 'Categories', href: '/admin/categories', icon: 'tag', isCurrent: startsWith('/admin/categories') },
                      { label: 'Forum topics', href: '/admin/forum-topics', icon: 'topics', isCurrent: startsWith('/admin/forum-topics') },
                  ],
              },
          ]
        : []),
]);

function searchTickets() {
    const term = search.value.trim();

    if (term !== '') {
        router.get('/tickets', { view: 'list', group: 'all', q: term });
        search.value = '';
    }
}

function closeUserMenuOnOutsideClick(event) {
    if (!userMenu.value?.contains(event.target)) {
        isUserMenuOpen.value = false;
    }
}

watch(isUserMenuOpen, (isOpen) => {
    if (isOpen) {
        document.addEventListener('click', closeUserMenuOnOutsideClick);
    } else {
        document.removeEventListener('click', closeUserMenuOnOutsideClick);
    }
});

onBeforeUnmount(() => document.removeEventListener('click', closeUserMenuOnOutsideClick));
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        <div v-if="isMenuOpen" class="fixed inset-0 z-30 bg-gray-950/50 lg:hidden" aria-hidden="true" @click="isMenuOpen = false"></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col gap-6 border-r border-gray-200 bg-white px-3 py-5 transition-transform lg:translate-x-0 dark:border-gray-800 dark:bg-gray-950"
            :class="isMenuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <Link href="/dashboard" class="w-fit rounded-lg px-3" @click="isMenuOpen = false">
                <AppLogo />
            </Link>

            <nav class="flex flex-1 flex-col gap-5 overflow-y-auto" aria-label="Main">
                <div v-for="section in sections" :key="section.heading ?? 'main'" class="flex flex-col gap-0.5">
                    <h2 v-if="section.heading" class="px-3 pb-1.5 font-mono text-[0.6875rem] font-medium tracking-widest text-gray-500 uppercase dark:text-gray-400">{{ section.heading }}</h2>
                    <Link
                        v-for="item in section.items"
                        :key="item.href"
                        :href="item.href"
                        prefetch
                        class="relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="
                            item.isCurrent
                                ? 'bg-gray-100 text-gray-900 before:absolute before:inset-y-2.5 before:left-0 before:w-px before:bg-hud before:shadow-[0_0_8px_var(--hud)] dark:bg-gray-800 dark:text-gray-100'
                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100'
                        "
                        :aria-current="item.isCurrent ? 'page' : undefined"
                        @click="isMenuOpen = false"
                    >
                        <AppIcon :name="item.icon" />
                        {{ item.label }}
                    </Link>
                </div>
            </nav>

            <p class="px-3 text-xs text-gray-400 dark:text-gray-500">Help desk &middot; Support &amp; community</p>
        </aside>

        <div class="flex min-h-screen flex-col lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/80 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-gray-800 dark:bg-gray-950/80">
                <button
                    type="button"
                    class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 lg:hidden dark:text-gray-400 dark:hover:bg-gray-800"
                    aria-label="Open navigation"
                    :aria-expanded="isMenuOpen"
                    @click="isMenuOpen = true"
                >
                    <AppIcon name="menu" class="size-6" />
                </button>

                <form class="relative min-w-0 flex-1 sm:max-w-sm" role="search" @submit.prevent="searchTickets">
                    <label for="global-search" class="sr-only">Search all tickets</label>
                    <AppIcon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400" />
                    <input
                        id="global-search"
                        v-model="search"
                        type="search"
                        placeholder="Search tickets or jump to TKT-12"
                        class="w-full rounded-lg border border-transparent bg-gray-100 py-2 pr-3 pl-9 text-sm text-gray-900 outline-none placeholder:text-gray-500 focus:border-gray-900 focus:bg-white dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-400 dark:focus:border-gray-100 dark:focus:bg-gray-950"
                    />
                </form>

                <Link
                    href="/tickets/create"
                    class="ml-auto flex shrink-0 items-center gap-1.5 rounded-lg bg-gray-900 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300 dark:focus-visible:outline-gray-100"
                >
                    <AppIcon name="plus" class="size-4 stroke-[2.25]" />
                    <span class="hidden sm:inline">New ticket</span>
                    <span class="sm:hidden">New</span>
                </Link>

                <ThemeToggle />

                <div ref="userMenu" class="relative shrink-0" @keydown.esc="isUserMenuOpen = false">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 rounded-lg py-1 pr-2 pl-1 hover:bg-gray-100 dark:hover:bg-gray-800"
                        aria-haspopup="menu"
                        :aria-expanded="isUserMenuOpen"
                        @click="isUserMenuOpen = !isUserMenuOpen"
                    >
                        <UserAvatar :name="user.name" :photo-url="user.photo_url" :is-admin="user.is_admin" small />
                        <span class="hidden min-w-0 flex-col text-left md:flex">
                            <span class="max-w-40 truncate text-sm leading-tight font-medium">{{ user.name }}</span>
                            <span class="max-w-40 truncate text-xs leading-tight text-gray-500 dark:text-gray-400">{{ user.position }}</span>
                        </span>
                        <AppIcon name="chevron-down" class="size-4 text-gray-500" />
                    </button>

                    <div
                        v-if="isUserMenuOpen"
                        class="absolute right-0 mt-2 flex w-60 flex-col rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        role="menu"
                    >
                        <p class="flex flex-col border-b border-gray-100 px-3 pt-1.5 pb-2.5 dark:border-gray-700">
                            <span class="truncate text-sm font-medium">{{ user.name }}</span>
                            <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.position }}</span>
                            <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</span>
                            <span v-if="user.is_admin" class="mt-1 w-fit rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">Helpdesk administrator</span>
                        </p>
                        <Link
                            href="/account"
                            role="menuitem"
                            class="mt-1.5 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="isUserMenuOpen = false"
                        >
                            <AppIcon name="user-circle" />
                            My account
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            role="menuitem"
                            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <AppIcon name="logout" />
                            Log out
                        </Link>
                    </div>
                </div>
            </header>

            <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <p
                    v-if="page.flash?.toast"
                    class="flex items-center gap-3 rounded-lg border px-4 py-3 text-sm font-medium"
                    :class="
                        page.flash.toast.type === 'error'
                            ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-400/20 dark:bg-red-500/10 dark:text-red-200'
                            : 'border-green-200 bg-green-50 text-green-900 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200'
                    "
                    :role="page.flash.toast.type === 'error' ? 'alert' : 'status'"
                >
                    <AppIcon :name="page.flash.toast.type === 'error' ? 'warning' : 'check-circle'" />
                    {{ page.flash.toast.message }}
                </p>

                <!-- Keyed by page component so the boot-in replays when the page changes, but not when only its query string does. -->
                <div :key="page.component" class="hud-stagger flex min-w-0 flex-1 flex-col">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
