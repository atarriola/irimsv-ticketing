<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    // Images already on the ticket, each shown with a way to remove it.
    existing: { type: Array, default: () => [] },
    ticketId: { type: Number, default: null },
    max: { type: Number, default: 5 },
    error: String,
});

const input = ref(null);
const selected = ref([]);

const room = computed(() => props.max - props.existing.length - selected.value.length);

// Each pick replaces the input's own list, so the chosen files are kept here and written back to the input as one list.
function addFiles(event) {
    Array.from(event.target.files)
        .slice(0, Math.max(room.value, 0))
        .forEach((file) => selected.value.push({ file, previewUrl: URL.createObjectURL(file) }));
    syncInput();
}

function removeSelected(index) {
    URL.revokeObjectURL(selected.value[index].previewUrl);
    selected.value.splice(index, 1);
    syncInput();
}

function syncInput() {
    const transfer = new DataTransfer();
    selected.value.forEach(({ file }) => transfer.items.add(file));
    input.value.files = transfer.files;
}

function removeExisting(attachment) {
    router.delete(`/tickets/${props.ticketId}/attachments/${attachment.id}`, {
        preserveScroll: true,
        preserveState: true,
        onBefore: () => confirm(`Remove ${attachment.name}?`),
    });
}

onBeforeUnmount(() => selected.value.forEach(({ previewUrl }) => URL.revokeObjectURL(previewUrl)));

const thumbnailClasses = 'aspect-square w-full rounded-lg border border-gray-200 object-cover dark:border-gray-700';
const removeButtonClasses =
    'absolute top-1 right-1 flex size-6 cursor-pointer items-center justify-center rounded-full bg-gray-900/80 text-base leading-none text-white hover:bg-gray-900 dark:bg-white/90 dark:text-gray-900 dark:hover:bg-white';
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-col gap-0.5">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Screenshots <span class="font-normal text-gray-500">(optional)</span></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Up to {{ max }} images of 5 MB each, as JPG, PNG, GIF or WebP.</span>
        </div>

        <ul v-if="existing.length > 0 || selected.length > 0" class="grid grid-cols-3 gap-3 sm:grid-cols-5">
            <li v-for="attachment in existing" :key="`existing-${attachment.id}`" class="relative flex flex-col gap-1">
                <a :href="attachment.url" target="_blank" rel="noopener"><img :src="attachment.url" :alt="attachment.name" :class="thumbnailClasses" /></a>
                <span class="truncate text-xs text-gray-600 dark:text-gray-400">{{ attachment.name }}</span>
                <button type="button" :class="removeButtonClasses" :aria-label="`Remove ${attachment.name}`" @click="removeExisting(attachment)">&times;</button>
            </li>
            <li v-for="(item, index) in selected" :key="item.previewUrl" class="relative flex flex-col gap-1">
                <img :src="item.previewUrl" :alt="item.file.name" :class="thumbnailClasses" />
                <span class="truncate text-xs text-gray-600 dark:text-gray-400">{{ item.file.name }}</span>
                <button type="button" :class="removeButtonClasses" :aria-label="`Remove ${item.file.name}`" @click="removeSelected(index)">&times;</button>
            </li>
        </ul>

        <!-- Without a name the input stays out of the submission, so a form with no images picked sends none. -->
        <input
            ref="input"
            type="file"
            :name="selected.length > 0 ? 'attachments[]' : undefined"
            accept="image/jpeg,image/png,image/gif,image/webp"
            multiple
            class="hidden"
            tabindex="-1"
            @change="addFiles"
        />
        <button
            v-if="room > 0"
            type="button"
            class="flex w-fit cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:border-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-800"
            @click="input.click()"
        >
            <AppIcon name="plus" class="size-4" />
            Add images
        </button>

        <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
