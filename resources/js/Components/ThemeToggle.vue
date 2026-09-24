<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const isDark = ref(false);
let systemPreference = null;

function savedTheme() {
    try {
        return localStorage.getItem('theme');
    } catch {
        return null;
    }
}

function applyTheme(dark) {
    isDark.value = dark;
    document.documentElement.classList.toggle('dark', dark);
}

function toggleTheme() {
    applyTheme(!isDark.value);

    try {
        localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
    } catch {
        // Storage can be unavailable (private mode); the choice then only lasts for this page.
    }
}

// Until the user picks a theme, keep following the operating system.
function followSystemPreference(event) {
    if (savedTheme() === null) {
        applyTheme(event.matches);
    }
}

// The inline script in app.blade.php has already set the class before first paint; this only mirrors it.
onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    systemPreference = window.matchMedia('(prefers-color-scheme: dark)');
    systemPreference.addEventListener('change', followSystemPreference);
});

onBeforeUnmount(() => systemPreference?.removeEventListener('change', followSystemPreference));
</script>

<template>
    <button
        type="button"
        class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100 dark:focus-visible:outline-gray-100"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :aria-pressed="isDark"
        @click="toggleTheme"
    >
        <AppIcon :name="isDark ? 'sun' : 'moon'" />
    </button>
</template>
