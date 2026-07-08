<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { ref, watch } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const getInitialSidebarState = () => {
    if (typeof window === 'undefined') {
        return true;
    }

    return window.localStorage.getItem('sidebar') !== 'false';
};

const isOpen = ref(getInitialSidebarState());

watch(
    isOpen,
    (open) => {
        if (typeof window === 'undefined') {
            return;
        }

        window.localStorage.setItem('sidebar', String(open));
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else v-model:open="isOpen" :default-open="isOpen">
        <slot />
    </SidebarProvider>
</template>
