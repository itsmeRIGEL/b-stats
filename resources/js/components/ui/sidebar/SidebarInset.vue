<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <main
        :class="
            cn(
                'relative flex min-h-svh flex-1 flex-col bg-background',
                'peer-data-[variant=inset]:min-h-[calc(100svh-theme(spacing.4))] md:peer-data-[variant=inset]:m-0 md:peer-data-[state=collapsed]:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-none md:peer-data-[variant=inset]:border-l md:peer-data-[variant=inset]:border-l-slate-200 dark:md:peer-data-[variant=inset]:border-l-[#1a1a1a]',
                props.class,
            )
        "
    >
        <slot />
    </main>
</template>
