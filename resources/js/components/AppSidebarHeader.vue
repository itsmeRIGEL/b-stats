<script setup lang="ts">
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import NotificationCenter from '@/components/NotificationCenter.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage<SharedData>();
const currentVenueName = computed(() => page.props.currentVenue?.name?.trim() || '');
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-border bg-background px-6 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex flex-1 items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <SidebarTrigger class="-ml-1" />
                <div v-if="currentVenueName" class="hidden min-w-0 sm:block">
                    <p class="truncate text-sm font-black uppercase tracking-[0.2em] text-foreground">
                        {{ currentVenueName }}
                    </p>
                </div>
                <template v-if="breadcrumbs.length > 0">
                    <Breadcrumb>
                        <BreadcrumbList>
                            <template v-for="(item, index) in breadcrumbs" :key="index">
                                <BreadcrumbItem>
                                    <template v-if="index === breadcrumbs.length - 1">
                                        <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                                    </template>
                                    <template v-else>
                                        <BreadcrumbLink :href="item.href">
                                            {{ item.title }}
                                        </BreadcrumbLink>
                                    </template>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                            </template>
                        </BreadcrumbList>
                    </Breadcrumb>
                </template>
            </div>

            <div class="flex items-center gap-2">
                <div v-if="currentVenueName" class="min-w-0 sm:hidden">
                    <p class="max-w-[160px] truncate text-xs font-black uppercase tracking-[0.18em] text-foreground">
                        {{ currentVenueName }}
                    </p>
                </div>

                <NotificationCenter />

                <AppearanceToggle />
            </div>
        </div>
    </header>
</template>
