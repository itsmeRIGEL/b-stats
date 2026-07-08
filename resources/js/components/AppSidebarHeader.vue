<script setup lang="ts">
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BookingInvitation, BreadcrumbItemType, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage<SharedData>();
const currentVenueName = computed(() => page.props.currentVenue?.name?.trim() || '');
const bookingInvitations = computed<BookingInvitation[]>(() => page.props.bookingInvitations ?? []);
const pendingInvitationCount = computed(() => bookingInvitations.value.filter((invitation) => invitation.status === 'pending').length);

const respondToInvitation = (bookingId: number, response: 'accepted' | 'declined') => {
    router.post('/scoring/invitations/' + bookingId + '/respond', { response }, { preserveScroll: true });
};
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

                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <Button variant="ghost" size="icon" class="relative h-9 w-9 cursor-pointer">
                            <Bell class="size-5 opacity-80 group-hover:opacity-100" />
                            <span
                                v-if="pendingInvitationCount > 0"
                                class="absolute -right-0.5 -top-0.5 inline-flex min-h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-black text-white"
                            >
                                {{ pendingInvitationCount }}
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-80 p-0">
                        <div class="border-b px-4 py-3 text-sm font-black uppercase tracking-widest text-slate-900 dark:text-white">Invitations</div>
                        <div v-if="bookingInvitations.length" class="max-h-96 space-y-3 overflow-y-auto p-3">
                            <div v-for="invitation in bookingInvitations" :key="`${invitation.booking_id}-${invitation.start_time}`" class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ invitation.invited_by }} invited you to play.</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ invitation.venue_name || invitation.lead_name }} • Court {{ invitation.court_number }} • {{ invitation.booking_date }} {{ invitation.start_time }}-{{ invitation.end_time }}
                                </p>
                                <div class="mt-3 flex items-center gap-2">
                                    <button
                                        @click="respondToInvitation(invitation.booking_id, 'accepted')"
                                        class="rounded-lg bg-emerald-600 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-emerald-700"
                                    >
                                        Accept
                                    </button>
                                    <button
                                        @click="respondToInvitation(invitation.booking_id, 'declined')"
                                        class="rounded-lg bg-slate-200 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 transition hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                    >
                                        Decline
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="px-4 py-6 text-sm text-slate-500 dark:text-slate-400">No pending invitations right now.</p>
                    </DropdownMenuContent>
                </DropdownMenu>

                <AppearanceToggle />
            </div>
        </div>
    </header>
</template>



