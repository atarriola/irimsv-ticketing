<script setup>
const appName = import.meta.env.VITE_APP_NAME || 'iRIMSV Ticketing System';

// The first word is the brand; whatever follows becomes the small descriptor line beneath it.
const [brand, ...descriptorWords] = appName.split(' ');
const descriptor = descriptorWords.join(' ');

// A lowercase initial in front of capitals ("iRIMSV") is picked out in the accent colour.
const hasAccentInitial = /^[a-z][A-Z]/.test(brand);
</script>

<template>
    <span class="flex items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" stroke="currentColor" class="size-10 shrink-0 text-gray-900 dark:text-gray-100" aria-hidden="true">
            <circle cx="24" cy="24" r="22.5" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="47.1 23.6" class="origin-center animate-hud-spin text-hud" />
            <circle cx="24" cy="24" r="17.5" stroke-width="0.75" stroke-dasharray="1 4.5" class="origin-center animate-hud-spin-reverse text-gray-400 dark:text-gray-600" />
            <path
                stroke-width="1.75"
                stroke-linejoin="round"
                d="M17 18H31a2 2 0 0 1 2 2V22a2 2 0 0 0 0 4V28a2 2 0 0 1-2 2H17a2 2 0 0 1-2-2V26a2 2 0 0 0 0-4V20a2 2 0 0 1 2-2Z"
            />
            <path stroke-width="1.5" stroke-linecap="round" stroke-dasharray="0.5 2.5" d="M27.5 20.75v6.5" />
            <path stroke-width="1.5" stroke-linecap="round" d="M19 22.5h5M19 25.5h3" />
        </svg>

        <span class="flex min-w-0 flex-col gap-1 leading-none">
            <span class="text-lg leading-none font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                <template v-if="hasAccentInitial"><span class="text-hud">{{ brand.charAt(0) }}</span>{{ brand.slice(1) }}</template>
                <template v-else>{{ brand }}</template>
            </span>
            <span v-if="descriptor" class="truncate font-mono text-[0.625rem] leading-none font-medium tracking-[0.18em] text-gray-500 uppercase dark:text-gray-400">{{ descriptor }}</span>
        </span>
    </span>
</template>
