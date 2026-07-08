<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();

const toggleAppearance = () => {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <button
        @click="toggleAppearance"
        class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white/50 text-slate-600 shadow-sm transition-all hover:scale-105 hover:bg-slate-100 active:scale-95 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400 dark:hover:bg-slate-800"
    >
        <Sun v-if="appearance === 'dark'" class="h-5 w-5" />
        <Moon v-else class="h-5 w-5" />
        <span class="sr-only">Toggle appearance</span>
    </button>
</template>
