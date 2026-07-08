<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Calendar, ChevronDown, CreditCard, Filter, LayoutGrid, Printer, TrendingUp, Users, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    dateRange: {
        start_date: string;
        end_date: string;
    };
    summary: {
        total_revenue: number;
        booking_revenue: number;
        reclub_revenue: number;
        walkin_revenue: number;
        membership_revenue: number;
        chart_data: Array<{
            label: string;
            bookings: number;
            reclub: number;
            walkin: number;
            membership: number;
            total: number;
        }>;
        granularity: string;
    };
    bookings: any[];
    cancelled_bookings: any[];
    reclub_bookings: any[];
    walkin_by_date: any[];
    walkin_matches: any[];
    memberships: any[];
}>();

const activeTab = ref<'summary' | 'bookings' | 'reclub' | 'walkins' | 'memberships'>('summary');

const showBookings = ref(true);
const showReclub = ref(true);
const showWalkins = ref(true);
const showMemberships = ref(true);
const showTotal = ref(true);

const form = useForm({
    start_date: props.dateRange.start_date,
    end_date: props.dateRange.end_date,
    granularity: props.summary.granularity || 'auto',
});

const granularity = ref('auto');

const showFilterModal = ref(false);
const filterStartMonth = ref(0);
const filterStartYear = ref(new Date().getFullYear());
const filterEndMonth = ref(0);
const filterEndYear = ref(new Date().getFullYear());

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const min = current - 5;
    const max = current + 1;
    const list = [];
    for (let y = min; y <= max; y++) list.push(y);
    return list;
});

const applyGranularity = () => {
    form.granularity = granularity.value;
    localStorage.setItem('sales-report-granularity', granularity.value);
    form.get(route('sales-report.index'), {
        preserveScroll: true,
        preserveState: true,
    });
};

const initGranularity = () => {
    const saved = localStorage.getItem('sales-report-granularity');
    if (saved && ['auto', 'daily', 'weekly', 'monthly'].includes(saved)) {
        granularity.value = saved;
        form.granularity = saved;
    } else if (props.summary.granularity) {
        granularity.value = props.summary.granularity;
    }
};

initGranularity();

const initFilterModal = () => {
    const s = new Date(props.dateRange.start_date);
    filterStartMonth.value = s.getMonth();
    filterStartYear.value = s.getFullYear();
    filterError.value = '';
    showFilterModal.value = true;
};

const filterError = ref('');

const toYMD = (d: Date) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const applyFilterModal = () => {
    filterError.value = '';

    const start = new Date(filterStartYear.value, filterStartMonth.value, 1);
    const end = new Date(filterStartYear.value, filterStartMonth.value + 1, 0);

    form.start_date = toYMD(start);
    form.end_date = toYMD(end);
    form.granularity = granularity.value;
    localStorage.setItem('sales-report-granularity', granularity.value);
    showFilterModal.value = false;
    form.get(route('sales-report.index'), {
        preserveScroll: true,
        preserveState: true,
    });
};

const formatCurrency = (value: number) => {
    const num = Number(value);
    if (isNaN(num)) return '₱0.00';
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(num);
};

const formatCompactCurrency = (value: number) => {
    const num = Number(value);
    if (isNaN(num)) return '₱0';
    if (num >= 1000000) return '₱' + (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return '₱' + (num / 1000).toFixed(1) + 'k';
    return '₱' + num.toFixed(0);
};

const chartYTicks = computed(() => {
    if (!props.summary.chart_data || props.summary.chart_data.length === 0) return [];
    const max = maxChartValue.value;
    const steps = 4;
    return Array.from({ length: steps + 1 }, (_, i) => {
        const value = (max / steps) * (steps - i);
        return { value, label: formatCompactCurrency(value) };
    });
});

const chartW = 800;
const chartH = 360;

const chartX = (i: number) => {
    const count = props.summary.chart_data.length;
    if (count <= 1) return 0;
    return (i / (count - 1)) * chartW;
};

const chartY = (value: number) => {
    const max = maxChartValue.value || 1;
    return chartH - (value / max) * (chartH - 40) - 20;
};

const smoothLinePath = (values: number[]) => {
    if (!values || values.length === 0) return '';
    if (values.length === 1) return `M ${chartX(0)} ${chartY(values[0])}`;

    const points = values.map((v, i) => ({ x: chartX(i), y: chartY(v) }));
    const n = points.length;

    // 1. Calculate secant slopes
    const d = new Array(n - 1);
    for (let i = 0; i < n - 1; i++) {
        const dx = points[i + 1].x - points[i].x;
        d[i] = dx === 0 ? 0 : (points[i + 1].y - points[i].y) / dx;
    }

    // 2. Calculate tangents at each point (harmonic mean to ensure monotonicity)
    const m = new Array(n);
    m[0] = d[0];
    m[n - 1] = d[n - 2];

    for (let i = 1; i < n - 1; i++) {
        if (d[i - 1] * d[i] <= 0) {
            m[i] = 0; // Local extremum: flatten to prevent overshoot
        } else {
            m[i] = 2 / (1 / d[i - 1] + 1 / d[i]);
        }
    }

    // 3. Build path with control points
    let path = `M ${points[0].x} ${points[0].y}`;
    for (let i = 0; i < n - 1; i++) {
        const dx = points[i + 1].x - points[i].x;
        const cp1x = points[i].x + dx / 3;
        const cp1y = points[i].y + (m[i] * dx) / 3;
        const cp2x = points[i + 1].x - dx / 3;
        const cp2y = points[i + 1].y - (m[i + 1] * dx) / 3;

        path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${points[i + 1].x} ${points[i + 1].y}`;
    }
    return path;
};

const areaPath = (values: number[]) => {
    if (!values || values.length === 0) return '';
    const line = smoothLinePath(values);
    const lastX = chartX(values.length - 1);
    const firstX = chartX(0);
    const baselineY = chartH - 20; // Y coordinate for 0 value, which is chartY(0)
    return `${line} L ${lastX} ${baselineY} L ${firstX} ${baselineY} Z`;
};

const hovered = ref<{ series: string; label: string; value: number; xPct: number; yPct: number; dataIndex: number } | null>(null);

const hoveredData = computed(() => {
    if (!hovered.value || !props.summary.chart_data[hovered.value.dataIndex]) return null;
    return props.summary.chart_data[hovered.value.dataIndex];
});

const onColumnEnter = (i: number) => {
    const d = props.summary.chart_data[i];
    if (!d) return;

    const activeValues = [];
    if (showTotal.value) activeValues.push(d.total);
    if (showBookings.value) activeValues.push(d.bookings);
    if (showReclub.value) activeValues.push(d.reclub);
    if (showWalkins.value) activeValues.push(d.walkin);
    if (showMemberships.value) activeValues.push(d.membership);
    const maxVal = activeValues.length > 0 ? Math.max(...activeValues) : 0;

    hovered.value = {
        series: 'all',
        label: d.label,
        value: maxVal,
        xPct: (chartX(i) / chartW) * 100,
        yPct: (chartY(maxVal) / chartH) * 100,
        dataIndex: i,
    };
};

const onColumnLeave = () => {
    hovered.value = null;
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatDateWords = (dateStr: string) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const day = d.getDate().toString().padStart(2, '0');
    const month = d.toLocaleDateString('en-US', { month: 'short' });
    const year = d.getFullYear();
    return `${day} ${month} ${year}`;
};

const formatTime = (timeStr: string) => {
    if (!timeStr) return '';
    const [h24, m] = timeStr.split(':');
    let h = parseInt(h24);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m} ${ampm}`;
};

const maxChartValue = computed(() => {
    if (!props.summary.chart_data || props.summary.chart_data.length === 0) return 100;
    const max = Math.max(...props.summary.chart_data.map((d) => d.total));
    return max > 0 ? max : 100;
});

const selectAllSeries = () => {
    showBookings.value = true;
    showReclub.value = true;
    showWalkins.value = true;
    showMemberships.value = true;
    showTotal.value = true;
};

const deselectAllSeries = () => {
    showBookings.value = false;
    showReclub.value = false;
    showWalkins.value = false;
    showMemberships.value = false;
    showTotal.value = false;
};

// Membership plan filter
const membershipFilter = ref<'all' | 'monthly' | 'yearly'>('all');

const filteredMemberships = computed(() => {
    if (membershipFilter.value === 'all') return props.memberships;
    return props.memberships.filter((p: any) => p.billing_period === membershipFilter.value);
});

const membershipMonthlyTotal = computed(() =>
    props.memberships.filter((p: any) => p.billing_period === 'monthly').reduce((s: number, p: any) => s + Number(p.amount ?? 0), 0),
);
const membershipYearlyTotal = computed(() =>
    props.memberships.filter((p: any) => p.billing_period === 'yearly').reduce((s: number, p: any) => s + Number(p.amount ?? 0), 0),
);

const isDark = ref(false);
let darkObserver: MutationObserver | null = null;

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    darkObserver = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    darkObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onUnmounted(() => {
    darkObserver?.disconnect();
});

const totalColor = computed(() => (isDark.value ? '#3b82f6' : '#6366f1'));
const gridLineColor = computed(() => (isDark.value ? '#334155' : '#e2e8f0'));
const crosshairColor = computed(() => (isDark.value ? '#475569' : '#94a3b8'));
let pollInterval: ReturnType<typeof setInterval> | null = null;
const POLL_RELOAD = ['summary', 'bookings', 'cancelled_bookings', 'reclub_bookings', 'walkin_by_date', 'walkin_matches', 'memberships'];

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

const downloadPdf = () => {
    window.location.href = route('sales-report.download', {
        start_date: props.dateRange.start_date,
        end_date: props.dateRange.end_date,
    });
};
</script>

<template>
    <Head title="Sales & Revenue Report" />

    <AppLayout>
        <div class="h-dvh overflow-hidden bg-slate-50/50 p-3 dark:bg-[#0a0a0a] sm:p-6 lg:h-[calc(100vh-64px)] lg:overflow-hidden lg:p-8">
            <div class="flex h-full w-full flex-col gap-4 lg:gap-6">
                <!-- Header & Filters -->
                <div class="flex shrink-0 flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <h1 class="flex items-center gap-3 text-2xl font-black tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                            <TrendingUp class="h-8 w-8 text-indigo-600 dark:text-green-400" />
                            Financial Ledger
                        </h1>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Track revenue across bookings, walk-ins, and memberships.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div
                            class="flex flex-wrap items-center gap-1 rounded-full border border-slate-200 bg-white px-4 py-2 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:gap-2"
                        >
                            <Calendar class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-600" />
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ formatDateWords(form.start_date) }}</span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-600">to</span>
                            <Calendar class="hidden h-4 w-4 shrink-0 text-slate-400 dark:text-slate-600 sm:block" />
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ formatDateWords(form.end_date) }}</span>
                        </div>
                        <button
                            @click="initFilterModal"
                            :disabled="form.processing"
                            class="flex min-h-[44px] items-center justify-center gap-2 rounded-full bg-indigo-600 px-5 py-2 text-xs font-black uppercase tracking-widest text-white shadow-md shadow-indigo-500/20 transition-all hover:bg-indigo-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-700"
                        >
                            <Filter class="h-3.5 w-3.5" /> Filter
                        </button>
                        <button
                            @click="downloadPdf"
                            class="flex min-h-[44px] items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2 text-xs font-black uppercase tracking-widest text-slate-700 shadow-md transition-all hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-300 dark:hover:bg-[#161616]"
                        >
                            <Printer class="h-3.5 w-3.5" /> Export PDF
                        </button>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row lg:gap-6">
                    <!-- Sidebar Navigation -->
                    <div
                        class="grid grid-cols-2 gap-2 pb-1 lg:flex lg:flex-col lg:pb-0 md:grid-cols-3"
                    >
                        <button
                            @click="activeTab = 'summary'"
                            class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-2xl px-3 py-2.5 text-xs font-bold transition-all sm:gap-3 sm:px-4 sm:py-3 sm:text-sm lg:w-full lg:justify-start"
                            :class="
                                activeTab === 'summary'
                                    ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20 dark:bg-white dark:text-slate-900'
                                    : 'clean-card text-slate-600 hover:bg-white dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                            "
                        >
                            <LayoutGrid class="h-4 w-4" /> <span class="hidden sm:inline lg:inline">Overview</span
                            ><span class="sm:hidden lg:inline">Summary</span>
                        </button>
                        <button
                            @click="activeTab = 'bookings'"
                            class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-2xl px-3 py-2.5 text-xs font-bold transition-all sm:gap-3 sm:px-4 sm:py-3 sm:text-sm lg:w-full lg:justify-between"
                            :class="
                                activeTab === 'bookings'
                                    ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/20 dark:bg-white dark:text-slate-900'
                                    : 'clean-card text-slate-600 hover:bg-white dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                            "
                        >
                            <div class="flex items-center gap-2 sm:gap-3"><Calendar class="h-4 w-4" /> <span>Bookings</span></div>
                            <span
                                class="hidden rounded-full px-2 py-0.5 text-[10px] lg:block"
                                :class="activeTab === 'bookings' ? 'bg-white/20 dark:bg-slate-900/20' : 'bg-slate-100 dark:bg-[#1a1a1a]'"
                                >{{ bookings.length }}</span
                            >
                        </button>
                        <button
                            @click="activeTab = 'reclub'"
                            class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-2xl px-3 py-2.5 text-xs font-bold transition-all sm:gap-3 sm:px-4 sm:py-3 sm:text-sm lg:w-full lg:justify-between"
                            :class="
                                activeTab === 'reclub'
                                    ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20 dark:bg-white dark:text-slate-900'
                                    : 'clean-card text-slate-600 hover:bg-white dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                            "
                        >
                            <div class="flex items-center gap-2 sm:gap-3"><Users class="h-4 w-4" /> <span>Reclub</span></div>
                            <span
                                class="hidden rounded-full px-2 py-0.5 text-[10px] lg:block"
                                :class="activeTab === 'reclub' ? 'bg-white/20 dark:bg-slate-900/20' : 'bg-slate-100 dark:bg-[#1a1a1a]'"
                                >{{ reclub_bookings.length }}</span
                            >
                        </button>
                        <button
                            @click="activeTab = 'walkins'"
                            class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-2xl px-3 py-2.5 text-xs font-bold transition-all sm:gap-3 sm:px-4 sm:py-3 sm:text-sm lg:w-full lg:justify-between"
                            :class="
                                activeTab === 'walkins'
                                    ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/30 dark:bg-amber-500 dark:text-white'
                                    : 'clean-card text-slate-600 hover:bg-white dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                            "
                        >
                            <div class="flex items-center gap-2 sm:gap-3">
                                <Users class="h-4 w-4" /> <span class="hidden sm:inline lg:inline">Walk-in</span
                                ><span class="sm:hidden lg:inline">Courts</span>
                            </div>
                            <span
                                class="hidden rounded-full px-2 py-0.5 text-[10px] lg:block"
                                :class="activeTab === 'walkins' ? 'bg-white/20 dark:bg-slate-900/20' : 'bg-slate-100 dark:bg-[#1a1a1a]'"
                                >{{ walkin_matches.length }}</span
                            >
                        </button>
                        <button
                            @click="activeTab = 'memberships'"
                            class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-2xl px-3 py-2.5 text-xs font-bold transition-all sm:gap-3 sm:px-4 sm:py-3 sm:text-sm lg:w-full lg:justify-between"
                            :class="
                                activeTab === 'memberships'
                                    ? 'bg-rose-500 text-white shadow-lg shadow-rose-500/20 dark:bg-white dark:text-slate-900'
                                    : 'clean-card text-slate-600 hover:bg-white dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                            "
                        >
                            <div class="flex items-center gap-2 sm:gap-3"><CreditCard class="h-4 w-4" /> <span>Memberships</span></div>
                            <span
                                class="hidden rounded-full px-2 py-0.5 text-[10px] lg:block"
                                :class="activeTab === 'memberships' ? 'bg-white/20 dark:bg-slate-900/20' : 'bg-slate-100 dark:bg-[#1a1a1a]'"
                                >{{ memberships.length }}</span
                            >
                        </button>
                    </div>

                    <!-- Tab Content Area -->
                    <div class="clean-card flex flex-1 flex-col overflow-hidden lg:min-h-0">
                        <!-- TAB: SUMMARY -->
                        <div v-if="activeTab === 'summary'" class="custom-scrollbar flex-1 space-y-6 overflow-y-auto p-4 sm:p-6">
                            <!-- Revenue KPI Cards -->
                            <div class="space-y-4">
                                <!-- Total Revenue (Full Width Hero) -->
                                <div
                                    class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 p-6 shadow-lg shadow-indigo-500/20 dark:from-green-600 dark:to-emerald-800 dark:shadow-green-500/10 sm:p-8"
                                >
                                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[80px] font-black opacity-10 transition-transform group-hover:scale-110 sm:right-8">
                                        ₱
                                    </div>
                                    <p
                                        class="relative z-10 mb-2 text-[11px] font-black uppercase tracking-widest text-indigo-200 dark:text-green-100"
                                    >
                                        Total Revenue
                                    </p>
                                    <h3 class="relative z-10 text-3xl font-black text-white sm:text-4xl">{{ formatCurrency(summary.total_revenue) }}</h3>
                                </div>

                                <!-- Revenue Breakdown Cards -->
                                <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                                    <div
                                        class="group relative overflow-hidden rounded-2xl border border-emerald-200 bg-white p-4 transition-all hover:shadow-md dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:hover:border-emerald-800 sm:p-5"
                                    >
                                        <div
                                            class="absolute right-0 top-0 p-3 text-emerald-500 opacity-[0.07] transition-transform group-hover:scale-110 sm:p-4 sm:opacity-5"
                                        >
                                            <Calendar class="h-12 w-12 sm:h-14 sm:w-14" />
                                        </div>
                                        <p
                                            class="relative z-10 mb-1 text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-green-400 sm:text-[10px]"
                                        >
                                            Bookings
                                        </p>
                                        <h3 class="relative z-10 text-xl font-black text-slate-900 dark:text-white sm:text-2xl">
                                            {{ formatCurrency(summary.booking_revenue) }}
                                        </h3>
                                    </div>

                                    <div
                                        class="group relative overflow-hidden rounded-2xl border border-indigo-200 bg-white p-4 transition-all hover:shadow-md dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:hover:border-indigo-800 sm:p-5"
                                    >
                                        <div
                                            class="absolute right-0 top-0 p-3 text-indigo-500 opacity-[0.07] transition-transform group-hover:scale-110 sm:p-4 sm:opacity-5"
                                        >
                                            <Users class="h-12 w-12 sm:h-14 sm:w-14" />
                                        </div>
                                        <p
                                            class="relative z-10 mb-1 text-[9px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 sm:text-[10px]"
                                        >
                                            Reclub
                                        </p>
                                        <h3 class="relative z-10 text-xl font-black text-slate-900 dark:text-white sm:text-2xl">
                                            {{ formatCurrency(summary.reclub_revenue) }}
                                        </h3>
                                    </div>

                                    <div
                                        class="group relative overflow-hidden rounded-2xl border border-amber-200 bg-white p-4 transition-all hover:shadow-md dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:hover:border-amber-800 sm:p-5"
                                    >
                                        <div
                                            class="absolute right-0 top-0 p-3 text-amber-500 opacity-[0.07] transition-transform group-hover:scale-110 sm:p-4 sm:opacity-5"
                                        >
                                            <Users class="h-12 w-12 sm:h-14 sm:w-14" />
                                        </div>
                                        <p
                                            class="relative z-10 mb-1 text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 sm:text-[10px]"
                                        >
                                            Walk-in Games
                                        </p>
                                        <h3 class="relative z-10 text-xl font-black text-slate-900 dark:text-white sm:text-2xl">
                                            {{ formatCurrency(summary.walkin_revenue) }}
                                        </h3>
                                    </div>

                                    <div
                                        class="group relative overflow-hidden rounded-2xl border border-rose-200 bg-white p-4 transition-all hover:shadow-md dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:hover:border-rose-800 sm:p-5"
                                    >
                                        <div
                                            class="absolute right-0 top-0 p-3 text-rose-500 opacity-[0.07] transition-transform group-hover:scale-110 sm:p-4 sm:opacity-5"
                                        >
                                            <CreditCard class="h-12 w-12 sm:h-14 sm:w-14" />
                                        </div>
                                        <p
                                            class="relative z-10 mb-1 text-[9px] font-black uppercase tracking-widest text-rose-600 dark:text-rose-400 sm:text-[10px]"
                                        >
                                            Memberships
                                        </p>
                                        <h3 class="relative z-10 text-xl font-black text-slate-900 dark:text-white sm:text-2xl">
                                            {{ formatCurrency(summary.membership_revenue) }}
                                        </h3>
                                    </div>
                                </div>
                            </div>

                            <!-- Monthly Revenue Chart (Line Graph) -->
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:p-6">
                                <!-- Chart Header with Legend -->
                                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                    <h3 class="flex items-center text-sm font-bold text-slate-900 dark:text-white">
                                        <TrendingUp class="mr-2 h-4 w-4 text-indigo-500 dark:text-green-500" /> Revenue Timeline
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            @click="selectAllSeries"
                                            class="text-[9px] font-black uppercase tracking-wider text-slate-400 transition-colors hover:text-slate-600 dark:text-slate-600 dark:hover:text-slate-300"
                                        >
                                            All
                                        </button>
                                        <span class="text-slate-300 dark:text-slate-700">|</span>
                                        <button
                                            @click="deselectAllSeries"
                                            class="text-[9px] font-black uppercase tracking-wider text-slate-400 transition-colors hover:text-slate-600 dark:text-slate-600 dark:hover:text-slate-300"
                                        >
                                            None
                                        </button>
                                        <span class="text-slate-300 dark:text-slate-700">|</span>
                                        <button
                                            @click="showBookings = !showBookings"
                                            :class="
                                                showBookings
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400'
                                                    : 'border-slate-200 bg-slate-50 text-slate-400 opacity-60 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-500'
                                            "
                                            class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                                        >
                                            <div class="h-2.5 w-2.5 rounded-full bg-emerald-500"></div>
                                            Bookings
                                        </button>
                                        <button
                                            @click="showReclub = !showReclub"
                                            :class="
                                                showReclub
                                                    ? 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-400'
                                                    : 'border-slate-200 bg-slate-50 text-slate-400 opacity-60 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-500'
                                            "
                                            class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                                        >
                                            <div class="h-2.5 w-2.5 rounded-full bg-indigo-500"></div>
                                            Reclub
                                        </button>
                                        <button
                                            @click="showWalkins = !showWalkins"
                                            :class="
                                                showWalkins
                                                    ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400'
                                                    : 'border-slate-200 bg-slate-50 text-slate-400 opacity-60 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-500'
                                            "
                                            class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                                        >
                                            <div class="h-2.5 w-2.5 rounded-full bg-amber-400"></div>
                                            Walk-ins
                                        </button>
                                        <button
                                            @click="showMemberships = !showMemberships"
                                            :class="
                                                showMemberships
                                                    ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-400'
                                                    : 'border-slate-200 bg-slate-50 text-slate-400 opacity-60 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-500'
                                            "
                                            class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                                        >
                                            <div class="h-2.5 w-2.5 rounded-full bg-rose-500"></div>
                                            Memberships
                                        </button>
                                        <button
                                            @click="showTotal = !showTotal"
                                            :class="
                                                showTotal
                                                    ? 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400'
                                                    : 'border-slate-200 bg-slate-50 text-slate-400 opacity-60 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-500'
                                            "
                                            class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                                        >
                                            <div class="h-2.5 w-2.5 rounded-full bg-indigo-500 dark:bg-blue-500"></div>
                                            Total
                                        </button>
                                    </div>
                                </div>

                                <!-- Granularity Toggle -->
                                <div class="mb-4 flex w-fit items-center gap-1 rounded-lg bg-slate-100 p-1 dark:bg-[#1a1a1a]">
                                    <button
                                        v-for="g in ['auto', 'daily', 'weekly', 'monthly']"
                                        :key="g"
                                        @click="
                                            granularity = g;
                                            applyGranularity();
                                        "
                                        class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                        :class="
                                            granularity === g
                                                ? 'bg-white text-slate-900 shadow-sm dark:bg-[#0f0f0f] dark:text-white'
                                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                        "
                                    >
                                        {{ g }}
                                    </button>
                                </div>

                                <div class="relative h-96 select-none">
                                    <!-- Y-axis labels -->
                                    <div class="absolute bottom-8 left-0 top-0 flex w-14 flex-col items-end justify-between py-4 pr-3">
                                        <span
                                            v-for="tick in chartYTicks"
                                            :key="tick.label"
                                            class="text-[10px] font-semibold leading-none text-slate-400 dark:text-slate-600"
                                            >{{ tick.label }}</span
                                        >
                                    </div>

                                    <!-- Chart SVG -->
                                    <svg
                                        class="absolute bottom-8 left-14 right-0 top-0 h-[calc(100%-2rem)] w-[calc(100%-3.5rem)]"
                                        viewBox="0 0 800 360"
                                        preserveAspectRatio="none"
                                    >
                                        <defs>
                                            <!-- Total Gradient -->
                                            <linearGradient id="grad-total" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" :stop-color="totalColor" stop-opacity="0.25" />
                                                <stop offset="100%" :stop-color="totalColor" stop-opacity="0.0" />
                                            </linearGradient>
                                            <!-- Bookings Gradient -->
                                            <linearGradient id="grad-bookings" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.25" />
                                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.0" />
                                            </linearGradient>
                                            <!-- Reclub Gradient -->
                                            <linearGradient id="grad-reclub" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.25" />
                                                <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0" />
                                            </linearGradient>
                                            <!-- Walk-ins Gradient -->
                                            <linearGradient id="grad-walkins" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#fbbf24" stop-opacity="0.25" />
                                                <stop offset="100%" stop-color="#fbbf24" stop-opacity="0.0" />
                                            </linearGradient>
                                            <!-- Memberships Gradient -->
                                            <linearGradient id="grad-memberships" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.25" />
                                                <stop offset="100%" stop-color="#f43f5e" stop-opacity="0.0" />
                                            </linearGradient>

                                            <filter id="dot-shadow" x="-50%" y="-50%" width="200%" height="200%">
                                                <feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="#000" flood-opacity="0.15" />
                                            </filter>
                                        </defs>

                                        <!-- Vertical Grid Lines (subtle tick dividers) -->
                                        <line
                                            v-for="(d, i) in summary.chart_data"
                                            :key="'grid-v-' + i"
                                            :x1="chartX(i)"
                                            :y1="20"
                                            :x2="chartX(i)"
                                            :y2="chartH - 20"
                                            :stroke="gridLineColor"
                                            stroke-width="1"
                                            stroke-opacity="0.05"
                                        />

                                        <!-- Horizontal Grid Lines (clean subtle borders) -->
                                        <line
                                            v-for="n in 4"
                                            :key="n"
                                            x1="0"
                                            x2="800"
                                            :y1="n * 72"
                                            :y2="n * 72"
                                            :stroke="gridLineColor"
                                            stroke-width="1"
                                            stroke-opacity="0.1"
                                        />

                                        <!-- Area Gradients (draw below lines to the baseline) -->
                                        <path
                                            v-if="showTotal && summary.chart_data.length > 1"
                                            :d="areaPath(summary.chart_data.map((d) => d.total))"
                                            fill="url(#grad-total)"
                                            stroke="none"
                                        />
                                        <path
                                            v-if="showBookings && summary.chart_data.length > 1"
                                            :d="areaPath(summary.chart_data.map((d) => d.bookings))"
                                            fill="url(#grad-bookings)"
                                            stroke="none"
                                        />
                                        <path
                                            v-if="showReclub && summary.chart_data.length > 1"
                                            :d="areaPath(summary.chart_data.map((d) => d.reclub))"
                                            fill="url(#grad-reclub)"
                                            stroke="none"
                                        />
                                        <path
                                            v-if="showWalkins && summary.chart_data.length > 1"
                                            :d="areaPath(summary.chart_data.map((d) => d.walkin))"
                                            fill="url(#grad-walkins)"
                                            stroke="none"
                                        />
                                        <path
                                            v-if="showMemberships && summary.chart_data.length > 1"
                                            :d="areaPath(summary.chart_data.map((d) => d.membership))"
                                            fill="url(#grad-memberships)"
                                            stroke="none"
                                        />

                                        <!-- Glowing lines (thick, low opacity behind main line) -->
                                        <path
                                            v-if="showTotal && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.total))"
                                            fill="none"
                                            :stroke="totalColor"
                                            stroke-width="6"
                                            stroke-opacity="0.15"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            v-if="showBookings && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.bookings))"
                                            fill="none"
                                            stroke="#10b981"
                                            stroke-width="6"
                                            stroke-opacity="0.15"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            v-if="showReclub && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.reclub))"
                                            fill="none"
                                            stroke="#6366f1"
                                            stroke-width="6"
                                            stroke-opacity="0.15"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            v-if="showWalkins && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.walkin))"
                                            fill="none"
                                            stroke="#fbbf24"
                                            stroke-width="6"
                                            stroke-opacity="0.15"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            v-if="showMemberships && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.membership))"
                                            fill="none"
                                            stroke="#f43f5e"
                                            stroke-width="6"
                                            stroke-opacity="0.15"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />

                                        <!-- Main Smooth Lines -->
                                        <path
                                            v-if="showTotal && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.total))"
                                            fill="none"
                                            :stroke="totalColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            v-if="showBookings && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.bookings))"
                                            fill="none"
                                            stroke="#10b981"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            v-if="showReclub && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.reclub))"
                                            fill="none"
                                            stroke="#6366f1"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            v-if="showWalkins && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.walkin))"
                                            fill="none"
                                            stroke="#fbbf24"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            v-if="showMemberships && summary.chart_data.length > 1"
                                            :d="smoothLinePath(summary.chart_data.map((d) => d.membership))"
                                            fill="none"
                                            stroke="#f43f5e"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />

                                        <!-- Vertical Crosshair -->
                                        <line
                                            v-if="hovered"
                                            :x1="chartX(hovered.dataIndex)"
                                            :y1="20"
                                            :x2="chartX(hovered.dataIndex)"
                                            :y2="chartH - 20"
                                            :stroke="crosshairColor"
                                            stroke-width="1.2"
                                            stroke-dasharray="4,4"
                                            stroke-opacity="0.6"
                                        />

                                        <!-- Invisible vertical columns for column hover interactions -->
                                        <rect
                                            v-for="(d, i) in summary.chart_data"
                                            :key="'hover-col-' + i"
                                            :x="chartX(i) - (summary.chart_data.length > 1 ? chartW / (summary.chart_data.length - 1) / 2 : 0)"
                                            :y="0"
                                            :width="summary.chart_data.length > 1 ? chartW / (summary.chart_data.length - 1) : chartW"
                                            height="360"
                                            fill="transparent"
                                            class="cursor-pointer"
                                            @mouseenter="onColumnEnter(i)"
                                            @mouseleave="onColumnLeave"
                                        />

                                        <!-- Default and Hovered Indicator Dots -->
                                        <template v-for="(d, i) in summary.chart_data" :key="i">
                                            <!-- Default dots (only shown when not hovered) -->
                                            <circle
                                                v-if="showTotal && (!hovered || hovered.dataIndex !== i)"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.total)"
                                                r="3"
                                                fill="#fff"
                                                :stroke="totalColor"
                                                stroke-width="2"
                                                opacity="0.8"
                                            />
                                            <circle
                                                v-if="showBookings && (!hovered || hovered.dataIndex !== i)"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.bookings)"
                                                r="3"
                                                fill="#fff"
                                                stroke="#10b981"
                                                stroke-width="2"
                                                opacity="0.8"
                                            />
                                            <circle
                                                v-if="showReclub && (!hovered || hovered.dataIndex !== i)"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.reclub)"
                                                r="3"
                                                fill="#fff"
                                                stroke="#6366f1"
                                                stroke-width="2"
                                                opacity="0.8"
                                            />
                                            <circle
                                                v-if="showWalkins && (!hovered || hovered.dataIndex !== i)"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.walkin)"
                                                r="3"
                                                fill="#fff"
                                                stroke="#fbbf24"
                                                stroke-width="2"
                                                opacity="0.8"
                                            />
                                            <circle
                                                v-if="showMemberships && (!hovered || hovered.dataIndex !== i)"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.membership)"
                                                r="3"
                                                fill="#fff"
                                                stroke="#f43f5e"
                                                stroke-width="2"
                                                opacity="0.8"
                                            />

                                            <!-- Hovered dot (larger with filter shadow) -->
                                            <circle
                                                v-if="hovered && hovered.dataIndex === i && showTotal"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.total)"
                                                r="6"
                                                fill="#fff"
                                                :stroke="totalColor"
                                                stroke-width="3"
                                                filter="url(#dot-shadow)"
                                            />
                                            <circle
                                                v-if="hovered && hovered.dataIndex === i && showBookings"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.bookings)"
                                                r="6"
                                                fill="#fff"
                                                stroke="#10b981"
                                                stroke-width="3"
                                                filter="url(#dot-shadow)"
                                            />
                                            <circle
                                                v-if="hovered && hovered.dataIndex === i && showReclub"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.reclub)"
                                                r="6"
                                                fill="#fff"
                                                stroke="#6366f1"
                                                stroke-width="3"
                                                filter="url(#dot-shadow)"
                                            />
                                            <circle
                                                v-if="hovered && hovered.dataIndex === i && showWalkins"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.walkin)"
                                                r="6"
                                                fill="#fff"
                                                stroke="#fbbf24"
                                                stroke-width="3"
                                                filter="url(#dot-shadow)"
                                            />
                                            <circle
                                                v-if="hovered && hovered.dataIndex === i && showMemberships"
                                                :cx="chartX(i)"
                                                :cy="chartY(d.membership)"
                                                r="6"
                                                fill="#fff"
                                                stroke="#f43f5e"
                                                stroke-width="3"
                                                filter="url(#dot-shadow)"
                                            />
                                        </template>
                                    </svg>

                                    <!-- Enhanced Tooltip -->
                                    <div
                                        v-if="hovered && hoveredData"
                                        class="pointer-events-none absolute z-10 transition-opacity duration-150"
                                        :style="{
                                            left: `calc(3.5rem + ${hovered.xPct} * (100% - 3.5rem) / 100)`,
                                            top: `${hovered.yPct}%`,
                                            transform: 'translate(-50%, -150%)',
                                        }"
                                    >
                                        <div
                                            class="min-w-[140px] rounded-xl border border-slate-700 bg-slate-900 px-3.5 py-2.5 text-xs text-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                        >
                                            <p
                                                class="mb-1.5 border-b border-slate-700 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:border-[#1a1a1a] dark:text-slate-500"
                                            >
                                                {{ hovered.label }}
                                            </p>
                                            <div class="space-y-1">
                                                <p v-if="showTotal" class="flex items-center justify-between gap-4 text-[11px]">
                                                    <span class="flex items-center gap-1.5"
                                                        ><span class="h-2 w-2 rounded-full bg-indigo-500 dark:bg-blue-500"></span> Total</span
                                                    >
                                                    <span class="font-black">{{ formatCurrency(hoveredData.total) }}</span>
                                                </p>
                                                <p v-if="showBookings" class="flex items-center justify-between gap-4 text-[11px]">
                                                    <span class="flex items-center gap-1.5"
                                                        ><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Bookings</span
                                                    >
                                                    <span class="font-black">{{ formatCurrency(hoveredData.bookings) }}</span>
                                                </p>
                                                <p v-if="showReclub" class="flex items-center justify-between gap-4 text-[11px]">
                                                    <span class="flex items-center gap-1.5"
                                                        ><span class="h-2 w-2 rounded-full bg-indigo-500"></span> Reclub</span
                                                    >
                                                    <span class="font-black">{{ formatCurrency(hoveredData.reclub) }}</span>
                                                </p>
                                                <p v-if="showWalkins" class="flex items-center justify-between gap-4 text-[11px]">
                                                    <span class="flex items-center gap-1.5"
                                                        ><span class="h-2 w-2 rounded-full bg-amber-400"></span> Walk-ins</span
                                                    >
                                                    <span class="font-black">{{ formatCurrency(hoveredData.walkin) }}</span>
                                                </p>
                                                <p v-if="showMemberships" class="flex items-center justify-between gap-4 text-[11px]">
                                                    <span class="flex items-center gap-1.5"
                                                        ><span class="h-2 w-2 rounded-full bg-rose-500"></span> Memberships</span
                                                    >
                                                    <span class="font-black">{{ formatCurrency(hoveredData.membership) }}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="mx-auto -mt-1.5 h-2.5 w-2.5 rotate-45 border-b border-r border-slate-700 bg-slate-900 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                        ></div>
                                    </div>

                                    <!-- X-axis labels (Centered exactly under their coordinates) -->
                                    <div class="absolute bottom-0 left-14 right-0 h-8">
                                        <span
                                            v-for="(data, idx) in summary.chart_data"
                                            :key="idx"
                                            class="absolute top-2 -translate-x-1/2 whitespace-nowrap text-[10px] font-semibold text-slate-400 dark:text-slate-600"
                                            :class="
                                                summary.chart_data.length > 14
                                                    ? idx % 5 === 0
                                                        ? ''
                                                        : 'hidden'
                                                    : summary.chart_data.length > 7
                                                      ? idx % 2 === 0
                                                          ? ''
                                                          : 'hidden'
                                                      : ''
                                            "
                                            :style="{
                                                left: summary.chart_data.length > 1 ? `${(idx / (summary.chart_data.length - 1)) * 100}%` : '0%',
                                            }"
                                        >
                                            {{ data.label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: BOOKINGS -->
                        <div v-if="activeTab === 'bookings'" class="flex h-full flex-col">
                            <div
                                class="flex flex-col justify-between gap-2 border-b border-slate-200 bg-emerald-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:flex-row sm:items-center"
                            >
                                <h3 class="flex items-center text-sm font-black uppercase tracking-widest text-emerald-800 dark:text-green-400">
                                    <Calendar class="mr-2 h-4 w-4" /> Paid Bookings Ledger
                                </h3>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600/70 dark:text-green-400/70">Total</p>
                                    <p class="text-lg font-black text-emerald-700 dark:text-green-400">
                                        {{ formatCurrency(summary.booking_revenue) }}
                                    </p>
                                </div>
                            </div>
                            <div class="custom-scrollbar flex-1 overflow-auto p-0">
                                <div v-if="bookings.length > 0" class="overflow-x-auto">
                                    <table class="w-full min-w-[500px] border-collapse text-left">
                                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm dark:bg-[#0f0f0f]">
                                            <tr>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Date & Time
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Client / Lead
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Court
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-[#1a1a1a]/50">
                                            <tr
                                                v-for="booking in bookings"
                                                :key="booking.id"
                                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-[#1a1a1a]/50"
                                            >
                                                <td class="p-3">
                                                    <div class="text-sm font-bold text-slate-900 dark:text-white">
                                                        {{ formatDate(booking.booking_date) }}
                                                    </div>
                                                    <div class="text-xs text-slate-500">
                                                        {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}
                                                    </div>
                                                </td>
                                                <td class="p-3">
                                                    <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ booking.lead_name }}</div>
                                                    <div class="text-[10px] text-slate-500">{{ booking.player_count }} Players</div>
                                                </td>
                                                <td class="p-3">
                                                    <span
                                                        class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600 dark:bg-[#1a1a1a] dark:text-slate-400"
                                                        >C{{ booking.court_number }}</span
                                                    >
                                                </td>
                                                <td class="p-3 text-right">
                                                    <div class="text-sm font-black text-emerald-600 dark:text-green-400">
                                                        {{ formatCurrency(booking.total_cost) }}
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="flex h-full flex-col items-center justify-center p-12 text-slate-400">
                                    <Calendar class="mb-3 h-12 w-12 opacity-20" />
                                    <p class="text-sm font-bold">No paid bookings in this date range.</p>
                                </div>
                                <!-- Cancelled Bookings -->
                                <div v-if="cancelled_bookings.length > 0" class="mt-4 border-t border-dashed border-slate-200 pt-4 dark:border-[#1a1a1a]">
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Cancelled Bookings</p>
                                    <div class="overflow-x-auto">
                                        <table class="w-full min-w-[500px] border-collapse text-left">
                                            <thead>
                                                <tr>
                                                    <th class="p-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Date & Time</th>
                                                    <th class="p-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Client / Lead</th>
                                                    <th class="p-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Court</th>
                                                    <th class="p-2 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-[#1a1a1a]/50">
                                                <tr
                                                    v-for="booking in cancelled_bookings"
                                                    :key="booking.id"
                                                    class="opacity-60"
                                                >
                                                    <td class="p-2">
                                                        <div class="text-sm font-bold text-slate-500 line-through">{{ formatDate(booking.booking_date) }}</div>
                                                        <div class="text-xs text-slate-400">{{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</div>
                                                    </td>
                                                    <td class="p-2">
                                                        <div class="text-sm font-bold text-slate-500 line-through">{{ booking.lead_name }}</div>
                                                        <div class="text-[10px] text-slate-400">{{ booking.player_count }} Players</div>
                                                    </td>
                                                    <td class="p-2">
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-400 dark:bg-[#1a1a1a]">C{{ booking.court_number }}</span>
                                                    </td>
                                                    <td class="p-2 text-right">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-400 line-through dark:bg-[#1a1a1a]">₱{{ booking.total_cost }}</span>
                                                            <span class="rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-500 dark:bg-rose-900/30 dark:text-rose-400">Cancelled</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: RECLUB -->
                        <div v-if="activeTab === 'reclub'" class="flex h-full flex-col">
                            <div
                                class="flex flex-col justify-between gap-2 border-b border-slate-200 bg-indigo-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:flex-row sm:items-center"
                            >
                                <h3 class="flex items-center text-sm font-black uppercase tracking-widest text-indigo-800 dark:text-indigo-400">
                                    <Users class="mr-2 h-4 w-4" /> Reclub Revenue Ledger
                                </h3>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600/70 dark:text-indigo-400/70">Total</p>
                                    <p class="text-lg font-black text-indigo-700 dark:text-indigo-400">
                                        {{ formatCurrency(summary.reclub_revenue) }}
                                    </p>
                                </div>
                            </div>
                            <div class="custom-scrollbar flex-1 overflow-auto p-0">
                                <div v-if="reclub_bookings.length > 0" class="overflow-x-auto">
                                    <table class="w-full min-w-[500px] border-collapse text-left">
                                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm dark:bg-[#0f0f0f]">
                                            <tr>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Date & Time
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Client / Lead
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Court
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-[#1a1a1a]/50">
                                            <tr
                                                v-for="booking in reclub_bookings"
                                                :key="booking.id"
                                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-[#1a1a1a]/50"
                                            >
                                                <td class="p-3">
                                                    <div class="text-sm font-bold text-slate-900 dark:text-white">
                                                        {{ formatDate(booking.booking_date) }}
                                                    </div>
                                                    <div class="text-xs text-slate-500">
                                                        {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}
                                                    </div>
                                                </td>
                                                <td class="p-3">
                                                    <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ booking.lead_name }}</div>
                                                    <div class="text-[10px] text-slate-500">{{ booking.player_count }} Players</div>
                                                </td>
                                                <td class="p-3">
                                                    <span
                                                        class="rounded-md bg-indigo-100 px-2 py-1 text-[10px] font-black uppercase text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                                                        >C{{ booking.court_number }}</span
                                                    >
                                                </td>
                                                <td class="p-3 text-right">
                                                    <div class="text-sm font-black text-indigo-600 dark:text-indigo-400">
                                                        {{ formatCurrency(booking.total_cost) }}
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="flex h-full flex-col items-center justify-center p-12 text-slate-400">
                                    <Users class="mb-3 h-12 w-12 opacity-20" />
                                    <p class="text-sm font-bold">No Reclub revenue in this date range.</p>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: WALKINS -->
                        <div v-if="activeTab === 'walkins'" class="flex h-full flex-col">
                            <div
                                class="flex flex-col justify-between gap-2 border-b border-slate-200 bg-amber-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:flex-row sm:items-center"
                            >
                                <h3 class="flex items-center text-sm font-black uppercase tracking-widest text-amber-800 dark:text-green-400">
                                    <Users class="mr-2 h-4 w-4" /> Walk-in Revenue Daily Ledger
                                </h3>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-600/70 dark:text-green-400/70">Total</p>
                                    <p class="text-lg font-black text-amber-700 dark:text-green-400">{{ formatCurrency(summary.walkin_revenue) }}</p>
                                </div>
                            </div>
                            <div class="custom-scrollbar flex-1 overflow-auto p-0">
                                <div v-if="walkin_by_date.length > 0" class="overflow-x-auto">
                                    <table class="w-full min-w-[400px] border-collapse text-left">
                                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm dark:bg-[#0f0f0f]">
                                            <tr>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Date
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Matches Played
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Daily Total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-[#1a1a1a]/50">
                                            <tr
                                                v-for="day in walkin_by_date"
                                                :key="day.date"
                                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-[#1a1a1a]/50"
                                            >
                                                <td class="p-3">
                                                    <div class="text-sm font-bold text-slate-900 dark:text-white">{{ formatDate(day.date) }}</div>
                                                </td>
                                                <td class="p-3">
                                                    <span
                                                        class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-black uppercase text-amber-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                                                        >{{ day.games_count }} Games</span
                                                    >
                                                </td>
                                                <td class="p-3 text-right">
                                                    <div class="text-sm font-black text-amber-600 dark:text-green-400">
                                                        {{ formatCurrency(day.revenue) }}
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="flex h-full flex-col items-center justify-center p-12 text-slate-400">
                                    <Users class="mb-3 h-12 w-12 opacity-20" />
                                    <p class="text-sm font-bold">No walk-in revenue in this date range.</p>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: MEMBERSHIPS -->
                        <div v-if="activeTab === 'memberships'" class="flex h-full flex-col">
                            <div
                                class="flex flex-col justify-between gap-3 border-b border-slate-200 bg-rose-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:flex-row sm:items-center"
                            >
                                <h3 class="flex items-center text-sm font-black uppercase tracking-widest text-rose-800 dark:text-green-400">
                                    <CreditCard class="mr-2 h-4 w-4" /> Membership Payment Audit Log
                                </h3>
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm dark:border-[#1a1a1a] dark:bg-[#1a1a1a]"
                                    >
                                        <button
                                            @click="membershipFilter = 'all'"
                                            class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                            :class="
                                                membershipFilter === 'all'
                                                    ? 'bg-rose-500 text-white shadow-sm'
                                                    : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                            "
                                        >
                                            All
                                        </button>
                                        <button
                                            @click="membershipFilter = 'monthly'"
                                            class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                            :class="
                                                membershipFilter === 'monthly'
                                                    ? 'bg-emerald-500 text-white shadow-sm'
                                                    : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                            "
                                        >
                                            Monthly
                                        </button>
                                        <button
                                            @click="membershipFilter = 'yearly'"
                                            class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                            :class="
                                                membershipFilter === 'yearly'
                                                    ? 'bg-violet-500 text-white shadow-sm'
                                                    : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                            "
                                        >
                                            Yearly
                                        </button>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-rose-600/70 dark:text-green-400/70">Total</p>
                                        <p class="text-lg font-black text-rose-700 dark:text-green-400">
                                            {{ formatCurrency(summary.membership_revenue) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-slate-200 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-wider dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                            >
                                <span class="text-slate-400"
                                    >Monthly:
                                    <span class="text-emerald-600 dark:text-green-400">{{ formatCurrency(membershipMonthlyTotal) }}</span></span
                                >
                                <span class="hidden text-slate-300 sm:inline">|</span>
                                <span class="text-slate-400"
                                    >Yearly:
                                    <span class="text-violet-600 dark:text-green-400">{{ formatCurrency(membershipYearlyTotal) }}</span></span
                                >
                            </div>
                            <div class="custom-scrollbar flex-1 overflow-auto p-0">
                                <div v-if="filteredMemberships.length > 0" class="overflow-x-auto">
                                    <table class="w-full min-w-[400px] border-collapse text-left">
                                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm dark:bg-[#0f0f0f]">
                                            <tr>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Date Paid
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Player
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Plan
                                                </th>
                                                <th
                                                    class="border-b border-slate-200 p-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-[#1a1a1a]"
                                                >
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-[#1a1a1a]/50">
                                            <tr
                                                v-for="payment in filteredMemberships"
                                                :key="payment.id"
                                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-[#1a1a1a]/50"
                                            >
                                                <td class="p-3">
                                                    <div class="text-sm font-bold text-slate-900 dark:text-white">
                                                        {{ formatDate(payment.paid_at) }}
                                                    </div>
                                                </td>
                                                <td class="p-3">
                                                    <div class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                                        {{ payment.player?.name || 'Unknown Player' }}
                                                    </div>
                                                </td>
                                                <td class="p-3">
                                                    <span
                                                        class="rounded-md px-2 py-1 text-[10px] font-black uppercase"
                                                        :class="
                                                            payment.billing_period === 'yearly'
                                                                ? 'border border-violet-200 bg-violet-50 text-violet-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400'
                                                                : 'border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400'
                                                        "
                                                    >
                                                        {{ payment.billing_period }}
                                                    </span>
                                                </td>
                                                <td class="p-3 text-right">
                                                    <div class="text-sm font-black text-rose-600 dark:text-green-400">
                                                        {{ formatCurrency(payment.amount) }}
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="flex h-full flex-col items-center justify-center p-12 text-slate-400">
                                    <CreditCard class="mb-3 h-12 w-12 opacity-20" />
                                    <p class="text-sm font-bold">
                                        No {{ membershipFilter === 'all' ? '' : membershipFilter + ' ' }}membership payments recorded in this range.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Month Filter Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showFilterModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                    @click.self="showFilterModal = false"
                >
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-4"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 translate-y-4"
                    >
                        <div
                            v-if="showFilterModal"
                            class="mx-4 w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                        >
                            <div class="mb-5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 dark:bg-green-900/20">
                                        <Calendar class="h-5 w-5 text-indigo-500 dark:text-green-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-slate-900 dark:text-white">Filter by Month</p>
                                        <p class="text-[10px] font-medium text-slate-400">Select start and end month</p>
                                    </div>
                                </div>
                                <button
                                    @click="showFilterModal = false"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-[#1a1a1a]"
                                >
                                    <X class="h-5 w-5 text-slate-400" />
                                </button>
                            </div>
                            <div class="mb-5 space-y-4">
                                 <!-- Month Selection -->
                                 <div>
                                     <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Select Month</p>
                                     <div class="flex gap-2">
                                         <div class="relative flex-1">
                                             <select
                                                 v-model="filterStartMonth"
                                                 class="w-full cursor-pointer appearance-none rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-3 pr-8 text-sm font-bold text-slate-900 outline-none focus:ring-2 focus:ring-green-500 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-white"
                                             >
                                                 <option v-for="(name, i) in monthNames" :key="i" :value="i">{{ name }}</option>
                                             </select>
                                             <ChevronDown
                                                 class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                             />
                                         </div>
                                         <div class="relative w-24">
                                             <select
                                                 v-model="filterStartYear"
                                                 class="w-full cursor-pointer appearance-none rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-3 pr-8 text-sm font-bold text-slate-900 outline-none focus:ring-2 focus:ring-green-500 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-white"
                                             >
                                                 <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                             </select>
                                             <ChevronDown
                                                 class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                             />
                                         </div>
                                     </div>
                                 </div>
                             </div>

                            <div v-if="filterError" class="mb-3 text-center text-xs font-semibold text-rose-500">
                                {{ filterError }}
                            </div>
                            <button
                                @click="applyFilterModal"
                                class="w-full rounded-xl bg-indigo-600 py-3 text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-500/20 transition-all hover:bg-indigo-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-700"
                            >
                                Apply Filter
                            </button>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.5);
}
</style>
