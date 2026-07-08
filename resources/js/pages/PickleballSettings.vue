<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import {
    CheckCircle,
    Clock,
    CreditCard,
    DollarSign,
    ExternalLink,
    Image,
    LayoutGrid,
    Link,
    RotateCcw,
    Save,
    Settings,
    Shuffle,
    Trophy,
    Type,
    Trash2,
    Calendar,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    settings: Record<string, string>;
    weeklyAvailabilities: Array<{ id: number; day_of_week: number; is_closed: boolean; opening_time: string | null; closing_time: string | null; close_reason?: string | null }>;
    dateOverrides: Array<{ id: number; date: string; is_closed: boolean; opening_time: string | null; closing_time: string | null; close_reason?: string | null }>;
}>();

const page = usePage();
const currentRole = computed(() => page.props.auth?.user?.role);
const isAdminUser = computed(() => currentRole.value === 'admin');
const showToast = ref(false);
const toastMessage = ref('');
let toastTimer: ReturnType<typeof setTimeout> | null = null;

const flashSuccess = computed(() => (page.props as any).flash?.success);

const triggerToast = (msg: string) => {
    if (!msg || showToast.value) return;
    toastMessage.value = msg;
    showToast.value = true;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        showToast.value = false;
    }, 4000);
};

watch(
    flashSuccess,
    (msg) => {
        if (msg) triggerToast(msg);
    },
    { immediate: true },
);

onMounted(() => {
    const msg = flashSuccess.value;
    if (msg) triggerToast(msg);
});

const logoPreview = ref(props.settings.app_logo || '');
const paymentQrPreview = ref(props.settings.payment_qr_photo || '');

const bookingUrl = computed(() => {
    if (typeof window !== 'undefined') return window.location.origin + '/book';
    return '/book';
});

const form = useForm({
    venue_name: props.settings.venue_name || '',
    venue_address: props.settings.venue_address || '',
    default_hourly_rate: props.settings.default_hourly_rate || '180',
    member_booking_fee: props.settings.member_booking_fee || '180',
    non_member_booking_fee: props.settings.non_member_booking_fee || '200',
    opening_time: props.settings.opening_time || '08:00',
    closing_time: props.settings.closing_time || '22:00',
    membership_monthly_fee: props.settings.membership_monthly_fee || '15',
    membership_yearly_fee: props.settings.membership_yearly_fee || '50',
    walkin_member_fee: props.settings.walkin_member_fee || '15',
    walkin_non_member_fee: props.settings.walkin_non_member_fee || '20',
    walkin_ball_surcharge: props.settings.walkin_ball_surcharge || '5',
    court_count: props.settings.court_count || '1',
    allow_past_edits: props.settings.allow_past_edits === '1' || props.settings.allow_past_edits === 'true',
    refund_full_hours: props.settings.refund_full_hours || '48',
    refund_full_mins: props.settings.refund_full_mins || '0',
    refund_full_pct: props.settings.refund_full_pct || '100',
    refund_partial_hours: props.settings.refund_partial_hours || '24',
    refund_partial_mins: props.settings.refund_partial_mins || '0',
    refund_partial_pct: props.settings.refund_partial_pct || '50',
    refund_no_pct: props.settings.refund_no_pct || '0',
    scoring_win_points: props.settings.scoring_win_points || '10',
    scoring_loss_penalty: props.settings.scoring_loss_penalty || '5',
    scoring_randomize_loss: props.settings.scoring_randomize_loss === '1' || props.settings.scoring_randomize_loss === 'true',
    app_name: props.settings.app_name || 'Dink-AO Pickleball',
    app_logo: null as File | null,
    payment_account_name: props.settings.payment_account_name || '',
    payment_qr_photo: null as File | null,
    booking_expiration_grace_minutes: props.settings.booking_expiration_grace_minutes || '10',
});

const originalSubmit = () => {
    form.post(route('pickleball-settings.update'), {
        forceFormData: true,
    });
};

const activePreset = ref<'booking' | 'operational' | 'payment' | 'refund' | 'scoring' | 'branding'>('booking');

const presets = computed(() => {
    const role = currentRole.value;
    if (role === 'scheduler' || role === 'scheduler_scorer') {
        return [
            { key: 'booking' as const, label: 'Booking', icon: LayoutGrid },
            { key: 'operational' as const, label: 'Operational Hours', icon: Clock },
            { key: 'payment' as const, label: 'Payment Reference', icon: CreditCard },
            { key: 'refund' as const, label: 'Refund Policy', icon: RotateCcw },
        ];
    }
    return [
        { key: 'booking' as const, label: 'Booking', icon: LayoutGrid },
        { key: 'operational' as const, label: 'Operational Hours', icon: Clock },
        { key: 'refund' as const, label: 'Refund Policy', icon: RotateCcw },
        { key: 'scoring' as const, label: 'Leaderboard', icon: Trophy },
        ...(isAdminUser.value ? [{ key: 'branding' as const, label: 'Branding', icon: Image }] : []),
    ];
});

watch(
    presets,
    (availablePresets) => {
        if (!availablePresets.some((preset) => preset.key === activePreset.value)) {
            activePreset.value = availablePresets[0]?.key ?? 'booking';
        }
    },
    { immediate: true },
);

const handleLogoChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.app_logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const handlePaymentQrChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.payment_qr_photo = file;
        paymentQrPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    if (activePreset.value === 'operational' && activeOperationalSubTab.value === 'weekly') {
        submitWeekly();
    } else {
        triggerToast('Settings updated successfully.');
        originalSubmit();
    }
};

// ── Operational Hours Sub-tabs ──────────────────────────────────────────────────
const activeOperationalSubTab = ref<'general' | 'weekly' | 'override'>('general');

const daysOfWeekNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const weeklySchedules = ref(
    Array.from({ length: 7 }, (_, i) => {
        const existing = props.weeklyAvailabilities.find((w) => w.day_of_week === i);
        return {
            day_of_week: i,
            day_name: daysOfWeekNames[i],
            is_enabled: existing ? Boolean(existing.is_enabled) : false,
            is_closed: existing ? Boolean(existing.is_closed) : false,
            opening_time: existing && existing.opening_time ? existing.opening_time.substring(0, 5) : '08:00',
            closing_time: existing && existing.closing_time ? existing.closing_time.substring(0, 5) : '22:00',
            close_reason: existing ? existing.close_reason || '' : '',
        };
    })
);

const weeklyForm = useForm({
    schedules: [] as any[],
});

const submitWeekly = () => {
    weeklyForm.schedules = weeklySchedules.value;
    weeklyForm.post(route('pickleball-settings.update-weekly'), {
        onSuccess: () => triggerToast('Weekly availability saved successfully.'),
    });
};

const overrideForm = useForm({
    date: '',
    is_closed: false,
    opening_time: '08:00',
    closing_time: '22:00',
    close_reason: '',
});

const submitOverride = () => {
    overrideForm.post(route('pickleball-settings.update-override'), {
        onSuccess: () => {
            triggerToast('Date override saved successfully.');
            overrideForm.reset('date', 'is_closed', 'close_reason');
        },
    });
};

const deleteOverride = (id: number) => {
    router.delete(route('pickleball-settings.delete-override', id), {
        onSuccess: () => triggerToast('Date override deleted successfully.'),
    });
};

const formatTime12hShort = (timeStr: string | null) => {
    if (!timeStr) return '';
    const [h24, m] = timeStr.split(':');
    let h = parseInt(h24);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12;
    return `${h}:${m} ${ampm}`;
};
</script>

<template>
    <Head title="System Settings" />

    <AppLayout>
        <!-- Toast Notification -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-4"
        >
            <div
                v-if="showToast"
                class="fixed right-4 top-4 z-[200] flex items-center gap-3 rounded-xl bg-emerald-500 px-4 py-3 text-white shadow-lg shadow-emerald-500/20"
            >
                <CheckCircle class="h-5 w-5 shrink-0" />
                <span class="text-sm font-bold">{{ toastMessage }}</span>
            </div>
        </Transition>

        <div class="overflow-x-hidden p-3 sm:p-6 lg:p-8">
            <div class="flex w-full flex-col gap-4 lg:h-full lg:flex-row lg:gap-6">
                <!-- Sidebar Presets -->
                <div class="flex flex-col lg:w-56 lg:shrink-0">
                    <div class="mb-3 sm:mb-6">
                        <h1 class="text-xl font-black text-slate-900 dark:text-white sm:text-2xl">Settings</h1>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Configure system presets</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pb-1 md:grid-cols-4 lg:flex lg:flex-col lg:overflow-visible lg:pb-0">
                        <button
                            v-for="preset in presets"
                            :key="preset.key"
                            @click="activePreset = preset.key"
                            class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-2xl px-3 py-2.5 text-xs font-bold transition-all sm:gap-3 sm:px-4 sm:py-3 sm:text-sm lg:w-full lg:justify-start"
                            :class="
                                activePreset === preset.key
                                    ? 'bg-slate-900 text-white shadow-lg dark:bg-white dark:text-slate-900'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                            "
                        >
                            <component :is="preset.icon" class="h-4 w-4 sm:h-5 sm:w-5" />
                            {{ preset.label }}
                        </button>
                    </div>
                    <div class="mt-auto hidden rounded-2xl border border-blue-100 bg-blue-50 p-4 dark:border-green-800 dark:bg-green-900/20 lg:block">
                        <p class="text-[10px] leading-relaxed text-blue-600 dark:text-green-400">Changes apply to new bookings and sessions only.</p>
                    </div>
                </div>

                <!-- Content Panel -->
                <div class="clean-card flex min-w-0 flex-1 flex-col overflow-hidden">
                    <form @submit.prevent="submit" class="flex flex-col lg:h-full">
                        <!-- Header -->
                        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-200 p-3 dark:border-[#1a1a1a] sm:p-6">
                            <div class="flex min-w-0 items-center gap-2">
                                <Settings class="h-4 w-4 flex-shrink-0 text-slate-400 sm:h-5 sm:w-5" />
                                <h2 class="truncate text-sm font-bold text-slate-900 dark:text-white sm:text-lg">
                                    {{ presets.find((p) => p.key === activePreset)?.label }}
                                </h2>
                            </div>
                            <button
                                type="submit"
                                :disabled="form.processing || (activePreset === 'operational' && activeOperationalSubTab === 'weekly' && weeklyForm.processing)"
                                class="flex min-h-[44px] shrink-0 items-center rounded-xl bg-blue-600 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-blue-500/20 transition-all hover:bg-blue-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500 sm:px-5"
                            >
                                <Save class="h-4 w-4 sm:mr-2" />
                                <span class="hidden sm:inline">Save</span>
                            </button>
                        </div>

                        <!-- Scrollable Content -->
                        <div class="flex-1 space-y-4 overflow-y-auto p-3 sm:space-y-6 sm:p-6 lg:max-h-[calc(100vh-13rem)]">
                            <!-- BOOKING PRESET (Financials) -->
                            <div v-if="activePreset === 'booking'" class="space-y-4">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Configure rates, fees, and membership dues.</p>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-600">Venue Details</p>
                                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                                        <div class="space-y-2">
                                            <label class="block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Venue Name</label>
                                            <input v-model="form.venue_name" type="text" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" placeholder="Example: C One Sports Center" />
                                        </div>
                                        <div class="space-y-2 lg:col-span-2">
                                            <label class="block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Venue Address</label>
                                            <textarea v-model="form.venue_address" rows="3" class="w-full rounded-xl border-slate-200 bg-white/70 px-3 py-2 text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" placeholder="Venue location" />
                                        </div>
                                        <div class="space-y-3 rounded-2xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20 xl:col-span-1">
                                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-600">Booking Rates</p>
                                            <div>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Member Rate (₱)</label>
                                                <input v-model="form.member_booking_fee" type="number" step="0.01" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Non-member Rate (₱)</label>
                                                <input v-model="form.non_member_booking_fee" type="number" step="0.01" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                            </div>
                                        </div>
                                        <div class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20 xl:col-span-1">
                                            <p class="text-[10px] font-black uppercase tracking-widest text-amber-600">Walk-in Fees</p>
                                            <div>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Member Fee (₱)</label>
                                                <input v-model="form.walkin_member_fee" type="number" step="0.01" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Non-member Fee (₱)</label>
                                                <input v-model="form.walkin_non_member_fee" type="number" step="0.01" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Ball Surcharge (₱)</label>
                                                <p class="mb-1 text-[9px] text-slate-400">Added when player has no ball</p>
                                                <input v-model="form.walkin_ball_surcharge" type="number" step="0.01" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                            </div>
                                        </div>
                                        <div class="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20 xl:col-span-1">
                                            <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Membership Dues</p>
                                            <div class="grid grid-cols-1 gap-4">
                                                <div>
                                                    <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Monthly Due (₱)</label>
                                                    <input v-model="form.membership_monthly_fee" type="number" step="0.01" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Yearly Due (₱)</label>
                                                    <input v-model="form.membership_yearly_fee" type="number" step="0.01" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OPERATIONAL HOURS PRESET -->
                            <div v-if="activePreset === 'operational'" class="space-y-4">
                                <!-- Sub-tabs -->
                                <div class="flex gap-2 border-b border-slate-200 pb-2 dark:border-[#1a1a1a]">
                                    <button
                                        type="button"
                                        @click="activeOperationalSubTab = 'general'"
                                        class="rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                        :class="activeOperationalSubTab === 'general' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-[#1a1a1a]'"
                                    >
                                        General Settings
                                    </button>
                                    <button
                                        type="button"
                                        @click="activeOperationalSubTab = 'weekly'"
                                        class="rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                        :class="activeOperationalSubTab === 'weekly' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-[#1a1a1a]'"
                                    >
                                        Weekly Schedule
                                    </button>
                                    <button
                                        type="button"
                                        @click="activeOperationalSubTab = 'override'"
                                        class="rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                        :class="activeOperationalSubTab === 'override' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-[#1a1a1a]'"
                                    >
                                        Date Overrides
                                    </button>
                                </div>

                                <!-- General sub-tab -->
                                <div v-if="activeOperationalSubTab === 'general'" class="space-y-4">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Configure court operations and scheduling rules.</p>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div class="space-y-3 rounded-2xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-600">Default Operating Hours</p>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Opening</label>
                                                    <input v-model="form.opening_time" type="time" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Closing</label>
                                                    <input v-model="form.closing_time" type="time" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
                                            <p class="text-[10px] font-black uppercase tracking-widest text-amber-600">Court Settings</p>
                                            <div>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Total Number of Courts</label>
                                                <input v-model="form.court_count" type="number" min="1" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Booking Expiration Grace Period (Minutes)</label>
                                                <input v-model="form.booking_expiration_grace_minutes" type="number" min="0" class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                            </div>
                                        </div>
                                    </div>
                                    <label v-if="page.props.auth?.user?.role !== 'scheduler' && page.props.auth?.user?.role !== 'scheduler_scorer'" class="group flex cursor-pointer items-center rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                                        <div class="relative">
                                            <input type="checkbox" v-model="form.allow_past_edits" class="sr-only" />
                                            <div class="h-6 w-10 rounded-full bg-slate-200 shadow-inner transition-colors dark:bg-[#1a1a1a]" :class="{ 'bg-blue-600 dark:bg-green-600': form.allow_past_edits }"></div>
                                            <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow transition-transform" :class="{ 'translate-x-4': form.allow_past_edits }"></div>
                                        </div>
                                        <div class="ml-3">
                                            <span class="text-sm font-bold text-slate-900 dark:text-white">Allow editing past bookings</span>
                                            <p class="text-[10px] text-slate-500">Modify schedules that have already passed.</p>
                                        </div>
                                    </label>
                                </div>

                                <!-- Weekly availability sub-tab -->
                                <div v-if="activeOperationalSubTab === 'weekly'" class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Configure default availability for each day of the week.</p>
                                    </div>
                                    
                                    <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                        <div v-for="(day, idx) in weeklySchedules" :key="idx" class="flex flex-col gap-3 py-3 border-b border-slate-200 last:border-b-0 dark:border-[#1a1a1a] sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-[180px] flex items-center gap-3">
                                                <!-- Toggle Switch for enabling custom schedule for this day -->
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" v-model="day.is_enabled" class="sr-only" />
                                                    <div class="h-5 w-9 rounded-full bg-slate-200 shadow-inner transition-colors dark:bg-[#1a1a1a]" :class="{ 'bg-blue-600 dark:bg-green-600': day.is_enabled }"></div>
                                                    <div class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform" :class="{ 'translate-x-4': day.is_enabled }"></div>
                                                </label>
                                                <span class="text-sm font-bold text-slate-900 dark:text-white" :class="!day.is_enabled ? 'opacity-50' : ''">{{ day.day_name }}</span>
                                            </div>
                                            
                                            <div class="flex flex-wrap items-center gap-4">
                                                <div v-if="day.is_enabled" class="flex flex-wrap items-center gap-4 transition-all duration-200">
                                                    <label class="flex cursor-pointer items-center">
                                                        <input type="checkbox" v-model="day.is_closed" class="rounded border-slate-300 dark:border-slate-800 text-blue-600 focus:ring-blue-500" />
                                                        <span class="ml-2 text-xs font-semibold text-slate-600 dark:text-slate-400">Closed</span>
                                                    </label>
                                                    <div v-if="!day.is_closed" class="flex items-center gap-2">
                                                        <input type="time" v-model="day.opening_time" class="rounded-xl border-slate-200 bg-white px-2 py-1 text-xs font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                                        <span class="text-xs text-slate-400">to</span>
                                                        <input type="time" v-model="day.closing_time" class="rounded-xl border-slate-200 bg-white px-2 py-1 text-xs font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                                    </div>
                                                    <div v-else class="flex items-center gap-2">
                                                        <span class="text-xs font-black text-rose-500">Facility Closed</span>
                                                        <input type="text" v-model="day.close_reason" placeholder="Reason (e.g. Holiday)" class="rounded-xl border-slate-200 bg-white px-2 py-1 text-xs font-semibold dark:border-[#1a1a1a] dark:bg-[#0a0a0a] w-48" />
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs text-slate-400 italic">
                                                    Default: {{ formatTime12hShort(form.opening_time) }} to {{ formatTime12hShort(form.closing_time) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Date overrides sub-tab -->
                                <div v-if="activeOperationalSubTab === 'override'" class="space-y-6">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Exclude certain calendar dates from default hours (e.g., holidays or maintenance).</p>
                                    
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] space-y-4">
                                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400">Add Date Override</h4>
                                        <div class="flex flex-wrap items-end gap-4">
                                            <div>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Date</label>
                                                <input type="date" v-model="overrideForm.date" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-xs font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                            </div>
                                            <div class="pt-2">
                                                <label class="flex cursor-pointer items-center">
                                                    <input type="checkbox" v-model="overrideForm.is_closed" class="rounded border-slate-300 dark:border-slate-800 text-blue-600 focus:ring-blue-500" />
                                                    <span class="ml-2 text-xs font-semibold text-slate-600 dark:text-slate-400">Mark as Closed</span>
                                                </label>
                                            </div>
                                            <div v-if="!overrideForm.is_closed" class="flex items-center gap-2">
                                                <div>
                                                    <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Opening</label>
                                                    <input type="time" v-model="overrideForm.opening_time" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-xs font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                                </div>
                                                <span class="text-xs text-slate-400 pt-5">to</span>
                                                <div>
                                                    <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Closing</label>
                                                    <input type="time" v-model="overrideForm.closing_time" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-xs font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                                </div>
                                            </div>
                                            <div v-else>
                                                <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Reason</label>
                                                <input type="text" v-model="overrideForm.close_reason" placeholder="Reason (e.g. Holiday)" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-xs font-semibold dark:border-[#1a1a1a] dark:bg-[#0a0a0a] w-48" />
                                            </div>
                                            <button
                                                type="button"
                                                @click="submitOverride"
                                                :disabled="overrideForm.processing || !overrideForm.date"
                                                class="min-h-[38px] rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                                            >
                                                Apply Override
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400">Active Date Overrides</h4>
                                        <div v-if="dateOverrides.length === 0" class="text-center py-6 border border-dashed border-slate-200 rounded-2xl dark:border-[#1a1a1a]">
                                            <Calendar class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600 mb-2" />
                                            <span class="text-xs font-bold text-slate-400">No date overrides set.</span>
                                        </div>
                                        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div
                                                v-for="override in dateOverrides"
                                                :key="override.id"
                                                class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3.5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                            >
                                                <div>
                                                    <p class="text-xs font-bold text-slate-900 dark:text-white">
                                                        {{ new Date(override.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }) }}
                                                    </p>
                                                    <p class="text-[10px] font-semibold mt-0.5" :class="override.is_closed ? 'text-rose-500' : 'text-emerald-500'">
                                                        {{ override.is_closed ? (override.close_reason ? `Closed: ${override.close_reason}` : 'Closed') : `Open: ${formatTime12hShort(override.opening_time)} – ${formatTime12hShort(override.closing_time)}` }}
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="deleteOverride(override.id)"
                                                    class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/20"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PAYMENT REFERENCE PRESET -->
                            <div v-if="activePreset === 'payment'" class="space-y-4">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Configure payment account details for client reference.</p>
                                <div class="max-w-md space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Account Name</label>
                                        <input v-model="form.payment_account_name" type="text" placeholder="e.g. John Doe - BDO" class="w-full rounded-xl border-slate-200 bg-white/70 px-3 py-2 text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">QR / Reference Photo</label>
                                        <input type="file" accept="image/*" @change="handlePaymentQrChange" class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-600 hover:file:bg-blue-100 dark:file:bg-green-900/30 dark:file:text-green-400 dark:hover:file:bg-green-900/50" />
                                        <img v-if="paymentQrPreview" :src="paymentQrPreview" class="mt-2 h-24 w-auto rounded-xl border border-slate-200 dark:border-[#1a1a1a]" />
                                    </div>
                                </div>
                            </div>

                            <!-- REFUND PRESET -->
                            <div v-if="activePreset === 'refund'" class="space-y-4">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Configure refund percentages for each cancellation tier.</p>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Tier 1 — Before the Day</p>
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Refund %</label>
                                            <input
                                                v-model="form.refund_full_pct"
                                                type="number"
                                                min="0"
                                                max="100"
                                                class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                            />
                                        </div>
                                        <p class="text-[10px] text-slate-400">
                                            Cancelled before the booking day &rarr;
                                            <strong class="text-emerald-600">{{ form.refund_full_pct }}% refund</strong>
                                        </p>
                                    </div>
                                    <div class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-600">Tier 2 — Same Day</p>
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Refund %</label>
                                            <input
                                                v-model="form.refund_partial_pct"
                                                type="number"
                                                min="0"
                                                max="100"
                                                class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                            />
                                        </div>
                                        <p class="text-[10px] text-slate-400">
                                            Same day cancellation, before scheduled time &rarr;
                                            <strong class="text-amber-600">{{ form.refund_partial_pct }}% refund</strong>
                                        </p>
                                    </div>
                                    <div class="space-y-3 rounded-2xl border border-rose-200 bg-rose-50/50 p-4 dark:border-rose-900/50 dark:bg-rose-950/20">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-rose-600">Tier 3 — Past Time</p>
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold text-slate-600 dark:text-slate-400">Refund %</label>
                                            <input
                                                v-model="form.refund_no_pct"
                                                type="number"
                                                min="0"
                                                max="100"
                                                class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                            />
                                        </div>
                                        <p class="text-[10px] text-slate-400">
                                            At or past the scheduled booking time &rarr;
                                            <strong class="text-rose-600">{{ form.refund_no_pct }}% refund</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- SCORING PRESET -->
                            <div v-if="activePreset === 'scoring' && isAdminUser" class="space-y-4">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Define leaderboard point values.</p>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div
                                        class="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Win Points</p>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Points per Win</label>
                                            <input
                                                v-model="form.scoring_win_points"
                                                type="number"
                                                min="1"
                                                class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                            />
                                        </div>
                                        <p class="text-[10px] text-slate-400">
                                            Each victory earns <strong class="text-emerald-600">+{{ form.scoring_win_points }} pts</strong>
                                        </p>
                                    </div>
                                    <div
                                        class="space-y-3 rounded-2xl border border-rose-200 bg-rose-50/50 p-4 dark:border-rose-900/50 dark:bg-rose-950/20"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-widest text-rose-600">Loss Penalty</p>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400"
                                                >Max Penalty per Loss</label
                                            >
                                            <input
                                                v-model="form.scoring_loss_penalty"
                                                type="number"
                                                min="1"
                                                class="w-full rounded-xl border-slate-200 bg-white/70 py-2 text-center text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                            />
                                        </div>
                                        <label class="group flex cursor-pointer items-center pt-1">
                                            <div class="relative">
                                                <input type="checkbox" v-model="form.scoring_randomize_loss" class="sr-only" />
                                                <div
                                                    class="h-6 w-10 rounded-full bg-slate-200 shadow-inner transition-colors dark:bg-[#1a1a1a]"
                                                    :class="{ 'bg-rose-600 dark:bg-rose-600': form.scoring_randomize_loss }"
                                                ></div>
                                                <div
                                                    class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow transition-transform"
                                                    :class="{ 'translate-x-4': form.scoring_randomize_loss }"
                                                ></div>
                                            </div>
                                            <div class="ml-3">
                                                <span class="flex items-center text-sm font-bold text-slate-900 dark:text-white"
                                                    ><Shuffle class="mr-1 h-3.5 w-3.5" /> Randomize Loss</span
                                                >
                                                <p class="text-[10px] text-slate-500">
                                                    Each loss costs random &minus;1 to &minus;{{ form.scoring_loss_penalty }} pts.
                                                </p>
                                            </div>
                                        </label>
                                        <p v-if="form.scoring_randomize_loss" class="text-[10px] text-slate-400">
                                            Each defeat: <strong class="text-rose-600">&minus;1 to &minus;{{ form.scoring_loss_penalty }} pts</strong>
                                        </p>
                                        <p v-else class="text-[10px] text-slate-400">
                                            Each defeat: <strong class="text-rose-600">&minus;{{ form.scoring_loss_penalty }} pts</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- BRANDING PRESET -->
                            <div v-if="activePreset === 'branding' && isAdminUser" class="space-y-4">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Customize your app name and logo.</p>
                                <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                    <p class="flex items-center text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-green-400">
                                        <Type class="mr-1.5 h-3.5 w-3.5" /> App Name
                                    </p>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Display Name</label>
                                        <input
                                            v-model="form.app_name"
                                            type="text"
                                            class="w-full rounded-xl border-slate-200 bg-white/70 px-3 py-2 text-sm font-bold dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                        />
                                    </div>
                                    <p class="text-[10px] text-slate-400">Shown in sidebar, header, and page titles.</p>
                                </div>
                                <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                    <p class="flex items-center text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-green-400">
                                        <Image class="mr-1.5 h-3.5 w-3.5" /> App Logo
                                    </p>
                                    <div class="flex flex-wrap items-center gap-4">
                                        <div
                                            class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl border-2 border-slate-200 bg-white dark:border-[#1a1a1a] dark:bg-[#1a1a1a]"
                                        >
                                            <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="h-full w-full object-cover" />
                                            <span v-else class="text-xs font-bold text-slate-400">No logo</span>
                                        </div>
                                        <div>
                                            <label
                                                for="app_logo"
                                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium transition-colors hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:hover:bg-[#2a2a2a]"
                                            >
                                                <span>Change logo</span>
                                            </label>
                                            <input id="app_logo" type="file" accept="image/*" class="hidden" @change="handleLogoChange" />
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-slate-400">Recommended: square PNG, at least 128&times;128px.</p>
                                </div>
                                <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                    <p class="flex items-center text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-green-400">
                                        <Link class="mr-1.5 h-3.5 w-3.5" /> Public Booking Page
                                    </p>
                                    <div
                                        class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p class="mb-1 text-xs text-slate-500 dark:text-slate-400">Share this link with clients</p>
                                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ bookingUrl }}</p>
                                        </div>
                                        <a
                                            href="/book"
                                            target="_blank"
                                            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white transition-colors hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                                        >
                                            <ExternalLink class="h-3.5 w-3.5" /> Open
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped></style>
