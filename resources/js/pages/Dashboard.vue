<script setup lang="ts">
import WeatherWidget from '@/components/WeatherWidget.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronRight, CreditCard, TrendingUp, Trophy, Users, Wallet } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    total_players: number;
    active_members: number;
    upcoming_bookings: any[];
    top_players: any[];
    weather: Record<string, any>;
    today_revenue: { total: number; bookings: number; reclub: number; walkins: number; memberships: number };
    weekly_revenue: { total: number; bookings: number; reclub: number; walkins: number; memberships: number };
    monthly_revenue: { total: number; bookings: number; reclub: number; walkins: number; memberships: number };
}>();

const formatPeso = (v: number) => {
    const num = Number(v);
    if (isNaN(num)) return '₱0';
    return '₱' + num.toLocaleString();
};

const page = usePage();
const isScheduler = computed(() => {
    const role = (page.props as any).auth?.user?.role;
    return role === 'scheduler';
});

const computedTodayTotal = computed(() => {
    if (isScheduler.value) {
        return (props.today_revenue?.bookings ?? 0) + (props.today_revenue?.walkins ?? 0);
    }
    return props.today_revenue?.total ?? 0;
});

const computedWeeklyTotal = computed(() => {
    if (isScheduler.value) {
        return (props.weekly_revenue?.bookings ?? 0) + (props.weekly_revenue?.walkins ?? 0);
    }
    return props.weekly_revenue?.total ?? 0;
});

const computedMonthlyTotal = computed(() => {
    if (isScheduler.value) {
        return (props.monthly_revenue?.bookings ?? 0) + (props.monthly_revenue?.walkins ?? 0);
    }
    return props.monthly_revenue?.total ?? 0;
});
let pollInterval: ReturnType<typeof setInterval> | null = null;
const POLL_RELOAD = ['total_players', 'active_members', 'upcoming_bookings', 'top_players', 'today_revenue', 'weekly_revenue', 'monthly_revenue'];

const startPoll = () => {
    if (pollInterval) return;
    pollInterval = setInterval(() => {
        router.reload({ only: POLL_RELOAD });
    }, 5000);
};

const stopPoll = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const handlePollVisibility = () => {
    if (document.visibilityState === 'visible') {
        router.reload({ only: POLL_RELOAD });
        startPoll();
    } else {
        stopPoll();
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handlePollVisibility);
    startPoll();
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handlePollVisibility);
    stopPoll();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="space-y-6 p-3 sm:space-y-8 sm:p-6 lg:p-8">
            <!-- Header -->
            <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h1 class="text-heading text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                    <p class="text-body mt-2">Welcome back to Pickleball Management System</p>
                </div>
                <WeatherWidget :weather="weather" />
            </div>

            <!-- Stats Grid -->
            <div
                class="mb-8 grid gap-4 sm:gap-6"
                :class="isScheduler ? 'grid-cols-1 md:grid-cols-3 lg:grid-cols-3' : 'grid-cols-2 md:grid-cols-2 lg:grid-cols-4'"
            >
                <div class="clean-card-hover flex items-center gap-4 p-6">
                    <div class="flex-shrink-0 rounded-lg bg-primary p-3 text-white">
                        <Users class="h-6 w-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-small">Total Players</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ total_players }}</p>
                    </div>
                </div>

                <div v-if="!isScheduler" class="clean-card-hover flex items-center gap-4 p-6">
                    <div class="flex-shrink-0 rounded-lg bg-green-500 p-3 text-white">
                        <CreditCard class="h-6 w-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-small">Active Members</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ active_members }}</p>
                    </div>
                </div>

                <div class="clean-card-hover flex items-center gap-4 p-6">
                    <div class="flex-shrink-0 rounded-lg bg-purple-500 p-3 text-white">
                        <Wallet class="h-6 w-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-small">Today's Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatPeso(computedTodayTotal) }}</p>
                    </div>
                </div>

                <div class="clean-card-hover flex items-center gap-4 p-6">
                    <div class="flex-shrink-0 rounded-lg bg-amber-500 p-3 text-white">
                        <Trophy class="h-6 w-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-small">Top Player</p>
                        <p class="truncate text-xl font-semibold text-gray-900 dark:text-white">{{ top_players[0]?.name || 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- Revenue Breakdown -->
            <div class="clean-card p-5 sm:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-subheading flex items-center gap-2 font-bold text-slate-900 dark:text-white">
                        <TrendingUp class="h-4 w-4 text-indigo-500" /> Revenue Breakdown
                    </h2>
                    <Link href="/sales-report" class="flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700"
                        ><span>View Report</span><ChevronRight class="h-3 w-3"
                    /></Link>
                </div>
                <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2" :class="isScheduler ? 'lg:grid-cols-3' : 'lg:grid-cols-4'">
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 dark:border-emerald-800/40 dark:bg-emerald-900/20">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Bookings</p>
                        <p class="text-lg font-black text-emerald-700 dark:text-emerald-300">{{ formatPeso(today_revenue?.bookings ?? 0) }}</p>
                        <p class="mt-1 text-[10px] text-emerald-500">This week: {{ formatPeso(weekly_revenue?.bookings ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 dark:border-indigo-800/40 dark:bg-indigo-900/20">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Reclub</p>
                        <p class="text-lg font-black text-indigo-700 dark:text-indigo-300">{{ formatPeso(today_revenue?.reclub ?? 0) }}</p>
                        <p class="mt-1 text-[10px] text-indigo-500">This week: {{ formatPeso(weekly_revenue?.reclub ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 dark:border-amber-800/40 dark:bg-amber-900/20">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400">Walk-ins</p>
                        <p class="text-lg font-black text-amber-700 dark:text-amber-300">{{ formatPeso(today_revenue?.walkins ?? 0) }}</p>
                        <p class="mt-1 text-[10px] text-amber-500">This week: {{ formatPeso(weekly_revenue?.walkins ?? 0) }}</p>
                    </div>
                    <div v-if="!isScheduler" class="rounded-xl border border-rose-100 bg-rose-50 p-4 dark:border-rose-800/40 dark:bg-rose-900/20">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-rose-600 dark:text-rose-400">Memberships</p>
                        <p class="text-lg font-black text-rose-700 dark:text-rose-300">{{ formatPeso(today_revenue?.memberships ?? 0) }}</p>
                        <p class="mt-1 text-[10px] text-rose-500">This week: {{ formatPeso(weekly_revenue?.memberships ?? 0) }}</p>
                    </div>
                </div>
                <!-- Compact stacked bar -->
                <div class="flex h-3 w-full overflow-hidden rounded-full">
                    <div
                        v-if="computedTodayTotal > 0"
                        class="h-full bg-emerald-500 transition-all"
                        :style="{ width: computedTodayTotal ? (today_revenue.bookings / computedTodayTotal) * 100 + '%' : '0%' }"
                    ></div>
                    <div
                        v-if="computedTodayTotal > 0"
                        class="h-full bg-indigo-500 transition-all"
                        :style="{ width: computedTodayTotal ? (today_revenue.reclub / computedTodayTotal) * 100 + '%' : '0%' }"
                    ></div>
                    <div
                        v-if="computedTodayTotal > 0"
                        class="h-full bg-amber-400 transition-all"
                        :style="{ width: computedTodayTotal ? (today_revenue.walkins / computedTodayTotal) * 100 + '%' : '0%' }"
                    ></div>
                    <div
                        v-if="!isScheduler && computedTodayTotal > 0"
                        class="h-full bg-rose-500 transition-all"
                        :style="{ width: computedTodayTotal ? (today_revenue.memberships / computedTodayTotal) * 100 + '%' : '0%' }"
                    ></div>
                    <div v-if="computedTodayTotal === 0" class="h-full w-full bg-slate-100 dark:bg-slate-800"></div>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Today</span>
                    <span class="text-xs font-black text-slate-700 dark:text-slate-300">{{ formatPeso(computedTodayTotal) }}</span>
                </div>
            </div>

            <!-- Monthly Financial Snapshot -->
            <div class="clean-card p-5 sm:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                        <TrendingUp class="h-4 w-4 text-indigo-500" /> Financial Snapshot
                    </h2>
                    <Link href="/sales-report" class="flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700"
                        ><span>View Full Ledger</span><ChevronRight class="h-3 w-3"
                    /></Link>
                </div>
                <p class="mb-4 text-[11px] text-slate-500 dark:text-slate-400">This month overview</p>
                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 sm:gap-4"
                >
                    <div class="rounded-xl bg-indigo-600 p-4 text-white">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-indigo-100">Total Revenue</p>
                        <p class="text-xl font-black">{{ formatPeso(computedMonthlyTotal) }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 dark:border-emerald-800/40 dark:bg-emerald-900/20">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Paid Bookings</p>
                        <p class="text-lg font-black text-emerald-700 dark:text-emerald-300">{{ formatPeso(monthly_revenue?.bookings ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 dark:border-indigo-800/40 dark:bg-indigo-900/20">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Reclub</p>
                        <p class="text-lg font-black text-indigo-700 dark:text-indigo-300">{{ formatPeso(monthly_revenue?.reclub ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 dark:border-amber-800/40 dark:bg-amber-900/20">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400">Walk-in Games</p>
                        <p class="text-lg font-black text-amber-700 dark:text-amber-300">{{ formatPeso(monthly_revenue?.walkins ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-rose-100 bg-rose-50 p-4 dark:border-rose-800/40 dark:bg-rose-900/20">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-rose-600 dark:text-rose-400">Memberships</p>
                        <p class="text-lg font-black text-rose-700 dark:text-rose-300">{{ formatPeso(monthly_revenue?.memberships ?? 0) }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:gap-8 lg:grid-cols-2">
                <!-- Upcoming Bookings -->
                <div class="clean-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 p-6 dark:border-slate-700 dark:bg-slate-800">
                        <h2 class="text-heading dark:text-white">Upcoming Bookings</h2>
                        <Link
                            href="/bookings"
                            class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                            >View all</Link
                        >
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                        <div
                            v-for="booking in upcoming_bookings"
                            :key="booking.id"
                            class="p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50 sm:p-6"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-white">{{ booking.booking_date }}</p>
                                    <p class="text-sm text-slate-500">{{ booking.start_time }} - {{ booking.end_time }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-slate-900 dark:text-white">₱{{ booking.total_cost }}</p>
                                    <div class="mt-2 flex -space-x-2">
                                        <div
                                            v-for="player in booking.players"
                                            :key="player.id"
                                            class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-slate-200 text-xs font-bold dark:border-slate-900"
                                            :title="player.name"
                                        >
                                            {{ player.name.charAt(0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="upcoming_bookings.length === 0" class="p-12 text-center text-slate-500">No upcoming bookings found.</div>
                    </div>
                </div>

                <!-- Leaderboard -->
                <div class="clean-card overflow-hidden">
                    <div
                        class="flex items-center justify-between border-b border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6"
                    >
                        <h2 class="text-subheading flex items-center gap-2 font-bold text-slate-900 dark:text-white">
                            <Trophy class="h-4 w-4 text-amber-500" /> Top Players
                        </h2>
                        <Link
                            href="/scoring"
                            class="flex items-center gap-1 text-xs font-black uppercase tracking-widest text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            Leaderboard <ChevronRight class="h-3 w-3" />
                        </Link>
                    </div>
                    <div class="space-y-2 p-3 sm:p-5">
                        <div
                            v-for="(player, idx) in top_players"
                            :key="player.id"
                            class="group flex items-center gap-3 rounded-xl border border-transparent p-3 transition-all hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700/50 dark:hover:bg-slate-800/50"
                        >
                            <!-- Rank -->
                            <div
                                class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-xs font-black"
                                :class="
                                    idx === 0
                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                        : idx === 1
                                          ? 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                                          : idx === 2
                                            ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                                            : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'
                                "
                            >
                                {{ idx + 1 }}
                            </div>
                            <!-- Avatar -->
                            <div
                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-sm font-bold text-white shadow-md"
                            >
                                {{ player.name.charAt(0).toUpperCase() }}
                            </div>
                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ player.name }}</p>
                                <p class="text-[10px] font-semibold text-slate-400">{{ player.wins }} wins</p>
                            </div>
                            <!-- Win Rate Bar -->
                            <div class="flex w-36 flex-shrink-0 items-center gap-2.5 sm:w-40">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="player.win_rate >= 60 ? 'bg-emerald-500' : player.win_rate >= 40 ? 'bg-blue-500' : 'bg-amber-500'"
                                        :style="{ width: player.win_rate + '%' }"
                                    ></div>
                                </div>
                                <span class="w-10 text-right text-xs font-black text-slate-700 dark:text-slate-300">{{ player.win_rate }}%</span>
                            </div>
                        </div>
                        <div v-if="top_players.length === 0" class="py-12 text-center text-slate-400">
                            <Trophy class="mx-auto mb-3 h-10 w-10 opacity-20" />
                            <p class="text-sm font-bold">No matches played yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped></style>
