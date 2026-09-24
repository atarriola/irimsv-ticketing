<script setup>
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    title: { type: String, required: true },
    description: String,
});
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center bg-white px-4 py-12 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        <ThemeToggle class="absolute top-4 right-4" />

        <main class="flex w-full max-w-sm flex-1 flex-col items-center justify-center gap-6">
            <Link href="/" class="rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gray-900 dark:focus-visible:outline-gray-100">
                <AppLogo />
            </Link>

            <header class="flex flex-col gap-1.5 text-center">
                <h1 class="text-2xl font-light tracking-tight">{{ title }}</h1>
                <p v-if="description" class="text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>
            </header>

            <div class="w-full rounded-lg border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                <slot />
            </div>
        </main>

        <p class="pt-10 text-center text-xs text-gray-500">Secure sign-in &middot; Your tickets stay private to you and the support team.</p>
    </div>
</template>
