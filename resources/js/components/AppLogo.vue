<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { usePage } from '@inertiajs/vue3';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage<{ name: string; appLogo?: string }>();
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage :src="page.props.appLogo || '/logo.svg'" alt="App logo" />
        <AvatarFallback class="rounded-lg bg-primary text-xs font-black text-primary-foreground">
            {{ (page.props.name || 'AP').substring(0, 2).toUpperCase() }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-bold uppercase leading-none tracking-wider">{{ page.props.name }}</span>
    </div>
</template>
