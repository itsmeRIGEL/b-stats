<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { AppNotification, BookingInvitation, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Activity, Bell, Calendar, Check, Crown, ExternalLink, Gamepad2, Swords, Trophy, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();

const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const userRole = computed(() => page.props.auth?.user?.role || 'player');
const isScheduler = computed(() => ['admin', 'scheduler', 'scheduler_scorer'].includes(userRole.value));
const isScorer = computed(() => ['admin', 'scorer', 'scheduler_scorer'].includes(userRole.value));

const activeTab = ref<string>('all');
const storageKey = computed(() => `b_stats_read_notifications_${userId.value}`);

const getInitialReadIds = (): Set<string> => {
    try {
        const uId = page.props.auth?.user?.id ?? 'guest';
        const saved = localStorage.getItem(`b_stats_read_notifications_${uId}`);
        if (saved) {
            return new Set(JSON.parse(saved));
        }
    } catch (e) {}
    return new Set();
};

const readNotificationIds = ref<Set<string>>(getInitialReadIds());

const persistReadIds = () => {
    try {
        localStorage.setItem(storageKey.value, JSON.stringify(Array.from(readNotificationIds.value)));
    } catch (e) {}
};

const bookingInvitations = computed<BookingInvitation[]>(() => page.props.bookingInvitations ?? []);
const serverNotifications = computed<AppNotification[]>(() => page.props.notifications ?? []);

// Deduplicate server notifications and booking invitations seamlessly
const mergedNotifications = computed<AppNotification[]>(() => {
    const map = new Map<string, AppNotification>();

    (serverNotifications.value || []).forEach((n) => {
        if (!map.has(n.id)) {
            map.set(n.id, n);
        }
    });

    (bookingInvitations.value || []).forEach((inv) => {
        const id = `invite-${inv.booking_id}`;
        if (!map.has(id)) {
            map.set(id, {
                id,
                type: 'invitation',
                title: 'Booking Invitation',
                message: `${inv.invited_by} invited you to play at ${inv.venue_name || 'Venue'} (Court ${inv.court_number}) on ${inv.booking_date} (${inv.start_time.substring(0, 5)} - ${inv.end_time.substring(0, 5)})`,
                created_at: new Date().toISOString(),
                action_url: '/scoring',
                is_read: inv.status !== 'pending',
                meta: inv,
            });
        }
    });

    return Array.from(map.values());
});

const unreadCount = computed(() => {
    return mergedNotifications.value.filter((n) => !n.is_read && !readNotificationIds.value.has(n.id)).length;
});

const filteredNotifications = computed(() => {
    let list = mergedNotifications.value;
    if (activeTab.value === 'invites') {
        list = list.filter((n) => n.type === 'invitation');
    } else if (activeTab.value === 'bookings' || activeTab.value === 'requests') {
        list = list.filter((n) => n.type === 'booking');
    } else if (activeTab.value === 'tournaments' || activeTab.value === 'events') {
        list = list.filter((n) => n.type === 'tournament');
    } else if (activeTab.value === 'scoring') {
        list = list.filter((n) => n.type === 'scoring');
    }
    return list;
});

const markAllAsRead = () => {
    mergedNotifications.value.forEach((n) => readNotificationIds.value.add(n.id));
    persistReadIds();
};

const markAsRead = (id: string) => {
    readNotificationIds.value.add(id);
    persistReadIds();
};

const respondToInvitation = (bookingId: number, response: 'accepted' | 'declined') => {
    router.post('/scoring/invitations/' + bookingId + '/respond', { response }, { preserveScroll: true });
};

const approveBooking = (bookingId: number) => {
    router.post('/bookings/' + bookingId + '/approve', {}, { preserveScroll: true });
};

const rejectBooking = (bookingId: number) => {
    router.post('/bookings/' + bookingId + '/reject', {}, { preserveScroll: true });
};

const navigateToAction = (notification: AppNotification) => {
    markAsRead(notification.id);
    if (notification.action_url) {
        router.visit(notification.action_url);
    }
};

const formatTimeAgo = (dateStr: string) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const diff = Math.floor((new Date().getTime() - d.getTime()) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button variant="ghost" size="icon" class="relative h-9 w-9 cursor-pointer">
                <Bell class="size-5 opacity-80 transition-opacity hover:opacity-100" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-black text-white shadow-sm ring-2 ring-white dark:ring-[#0f0f0f]"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 sm:w-96 p-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-4 py-3 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-900 dark:text-white">Notifications</span>
                    <span
                        v-if="unreadCount > 0"
                        class="rounded-full bg-blue-50 px-2 py-0.5 text-[9px] font-bold text-blue-600 dark:bg-rose-500/20 dark:text-rose-400"
                    >
                        {{ unreadCount }} new
                    </span>
                </div>
                <button
                    v-if="unreadCount > 0"
                    @click="markAllAsRead"
                    class="text-[10px] font-bold text-slate-400 hover:text-blue-600 dark:hover:text-green-400 transition-colors"
                >
                    Mark all read
                </button>
            </div>

            <!-- Role-Tailored Category Filter Tabs -->
            <div class="flex items-center gap-1 border-b border-slate-100 bg-white p-1.5 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                <button
                    @click="activeTab = 'all'"
                    class="flex-1 rounded-lg py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                    :class="
                        activeTab === 'all'
                            ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                    "
                >
                    All
                </button>

                <!-- Scheduler / Admin Tab for Pending Requests -->
                <button
                    v-if="isScheduler"
                    @click="activeTab = 'requests'"
                    class="flex-1 rounded-lg py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                    :class="
                        activeTab === 'requests'
                            ? 'bg-blue-600 text-white shadow-sm dark:bg-emerald-600'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                    "
                >
                    Requests
                </button>

                <!-- Player Tab for Invites -->
                <button
                    v-else
                    @click="activeTab = 'invites'"
                    class="flex-1 rounded-lg py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                    :class="
                        activeTab === 'invites'
                            ? 'bg-purple-600 text-white shadow-sm'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                    "
                >
                    Invites
                </button>

                <!-- Scorer Tab for Active Scoring Sessions -->
                <button
                    v-if="isScorer"
                    @click="activeTab = 'scoring'"
                    class="flex-1 rounded-lg py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                    :class="
                        activeTab === 'scoring'
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                    "
                >
                    Scoring
                </button>

                <!-- Events / Tournaments Tab -->
                <button
                    @click="activeTab = 'events'"
                    class="flex-1 rounded-lg py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                    :class="
                        activeTab === 'events' || activeTab === 'tournaments'
                            ? 'bg-amber-500 text-white shadow-sm'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                    "
                >
                    Events
                </button>
            </div>

            <!-- List Content -->
            <div class="custom-scrollbar max-h-96 overflow-y-auto p-2 space-y-2">
                <div v-if="filteredNotifications.length === 0" class="px-4 py-8 text-center opacity-50">
                    <Bell class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-[#2a2a2a]" />
                    <p class="text-xs font-black uppercase tracking-widest text-slate-400">No notifications</p>
                </div>

                <div
                    v-for="item in filteredNotifications"
                    :key="item.id"
                    class="group relative rounded-xl border p-3 transition-all dark:bg-[#121212]"
                    :class="
                        readNotificationIds.has(item.id) || item.is_read
                            ? 'border-slate-100 bg-white opacity-70 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]'
                            : 'border-slate-200 bg-slate-50/60 shadow-sm dark:border-[#222] dark:bg-[#141414]'
                    "
                >
                    <div class="flex items-start gap-3">
                        <!-- Icon Badge -->
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-white shadow-sm"
                            :class="
                                item.type === 'invitation'
                                    ? 'bg-gradient-to-br from-purple-500 to-indigo-600'
                                    : item.type === 'booking'
                                    ? 'bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-emerald-500 dark:to-teal-600'
                                    : item.type === 'scoring'
                                    ? 'bg-gradient-to-br from-blue-500 to-indigo-600'
                                    : item.type === 'tournament'
                                    ? 'bg-gradient-to-br from-amber-400 to-orange-500'
                                    : item.type === 'membership'
                                    ? 'bg-gradient-to-br from-cyan-500 to-blue-600'
                                    : 'bg-slate-500'
                            "
                        >
                            <Swords v-if="item.type === 'invitation'" class="h-4 w-4" />
                            <Calendar v-else-if="item.type === 'booking'" class="h-4 w-4" />
                            <Gamepad2 v-else-if="item.type === 'scoring'" class="h-4 w-4" />
                            <Trophy v-else-if="item.type === 'tournament'" class="h-4 w-4" />
                            <Crown v-else-if="item.type === 'membership'" class="h-4 w-4" />
                            <Bell v-else class="h-4 w-4" />
                        </div>

                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-1">
                                <p class="truncate text-xs font-black text-slate-900 dark:text-white">{{ item.title }}</p>
                                <span class="shrink-0 text-[9px] font-bold text-slate-400">{{ formatTimeAgo(item.created_at) }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300 leading-snug break-words">{{ item.message }}</p>

                            <!-- Interactive Action Buttons for Player Invitations -->
                            <div v-if="item.type === 'invitation' && item.meta?.booking_id" class="mt-2.5 flex items-center gap-2">
                                <button
                                    @click.stop="
                                        respondToInvitation(item.meta.booking_id, 'accepted');
                                        markAsRead(item.id);
                                    "
                                    class="inline-flex items-center gap-1 rounded-lg bg-blue-600 dark:bg-emerald-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-blue-700 dark:hover:bg-emerald-700 active:scale-95"
                                >
                                    <Check class="h-3 w-3" />
                                    Accept
                                </button>
                                <button
                                    @click.stop="
                                        respondToInvitation(item.meta.booking_id, 'declined');
                                        markAsRead(item.id);
                                    "
                                    class="inline-flex items-center gap-1 rounded-lg bg-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-700 transition hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 active:scale-95"
                                >
                                    <X class="h-3 w-3" />
                                    Decline
                                </button>
                            </div>

                            <!-- Interactive Action Buttons for Scheduler Booking Approval -->
                            <div v-else-if="isScheduler && item.id.startsWith('pending-booking-') && item.meta?.booking_id" class="mt-2.5 flex items-center gap-2">
                                <button
                                    @click.stop="
                                        approveBooking(item.meta.booking_id);
                                        markAsRead(item.id);
                                    "
                                    class="inline-flex items-center gap-1 rounded-lg bg-blue-600 dark:bg-emerald-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-blue-700 dark:hover:bg-emerald-700 active:scale-95"
                                >
                                    <Check class="h-3 w-3" />
                                    Approve
                                </button>
                                <button
                                    @click.stop="
                                        rejectBooking(item.meta.booking_id);
                                        markAsRead(item.id);
                                    "
                                    class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-rose-700 active:scale-95"
                                >
                                    <X class="h-3 w-3" />
                                    Reject
                                </button>
                            </div>

                            <!-- Action Link for other notifications -->
                            <div v-else-if="item.action_url" class="mt-2 flex items-center">
                                <button
                                    @click.stop="navigateToAction(item)"
                                    class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-green-400 hover:underline"
                                >
                                    <span>{{ item.type === 'scoring' ? 'Open Scoring' : 'View Details' }}</span>
                                    <ExternalLink class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
