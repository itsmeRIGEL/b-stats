<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Calendar, ChevronLeft, ChevronRight, Clock, Crown, MapPin, Phone, Plus, Send, ShieldCheck, Swords, X, Upload, CheckCircle, User, Users, FileText, ArrowRight, ArrowLeft, DollarSign, Image as ImageIcon, CreditCard, Maximize2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    venues: Array<{
        id: number;
        name: string;
        address?: string | null;
        tagline?: string | null;
        description?: string | null;
        court_count?: number | null;
        covered_court_count?: number | null;
        cover_photo_url?: string | null;
        logo_url?: string | null;
        gallery_urls?: string[];
        amenities?: string[];
        default_hourly_rate?: number;
        contact_phone?: string | null;
        payment_account_name?: string | null;
        payment_qr_photo?: string | null;
        is_primary?: boolean;
        is_visited?: boolean;
    }>;
    requests: Array<{
        id: number;
        venue_id: number;
        name: string;
        category?: string | null;
        status: string;
        preferred_date?: string | null;
        preferred_start_time?: string | null;
        notes?: string | null;
        rejection_reason?: string | null;
        tournament?: { id: number; name: string; status: string } | null;
        tournamentDay?: { id: number; name: string; date?: string | null; status: string } | null;
        venue?: { id: number; name: string } | null;
        created_at?: string | null;
    }>;
    bookings: Array<{
        id: number;
        venue_name?: string | null;
        booking_date: string;
        start_time: string;
        end_time: string;
        court_number: number;
        player_count: number;
        status: string;
        payment_status?: string | null;
        player_username: string;
        total_cost: number;
        client_type?: string | null;
    }>;
}>();

const selectedVenueId = ref<number | null>(null);
const selectedVenue = computed(() => props.venues.find((venue) => venue.id === selectedVenueId.value) || null);

const showEnlargedImage = ref(false);
const enlargedImageUrl = ref('');
const openEnlargedImage = (url?: string | null) => {
    if (!url) return;
    enlargedImageUrl.value = url;
    showEnlargedImage.value = true;
};

const now = new Date();
const today = now.toISOString().slice(0, 10);
const currentTime = now.toTimeString().slice(0, 8);

const currentBooking = computed(() => {
    return props.bookings
        .filter((b) => {
            if (b.status === 'cancelled' || b.status === 'rejected') return false;
            if (b.booking_date > today) return true;
            if (b.booking_date < today) return false;
            return b.end_time > currentTime;
        })
        .sort((a, b) => {
            if (a.booking_date === b.booking_date) return a.start_time.localeCompare(b.start_time);
            return a.booking_date.localeCompare(b.booking_date);
        })[0] ?? null;
});

const previousBookings = computed(() => {
    if (!currentBooking.value) return props.bookings;
    return props.bookings.filter((b) => b.id !== currentBooking.value!.id);
});

const previousIndex = ref(0);
const visiblePreviousBooking = computed(() => previousBookings.value[previousIndex.value] ?? null);
const hasPrevious = computed(() => previousIndex.value > 0);
const hasNext = computed(() => previousIndex.value < previousBookings.value.length - 1);

const goNext = () => { if (hasNext.value) previousIndex.value++; };
const goPrev = () => { if (hasPrevious.value) previousIndex.value-- };

const requestIndex = ref(0);
const visibleRequest = computed(() => props.requests[requestIndex.value] ?? null);
const hasReqPrevious = computed(() => requestIndex.value > 0);
const hasReqNext = computed(() => requestIndex.value < props.requests.length - 1);
const goReqNext = () => { if (hasReqNext.value) requestIndex.value++; };
const goReqPrev = () => { if (hasReqPrevious.value) requestIndex.value-- };

const requestStep = ref(1);
const receiptPreviewUrl = ref<string | null>(null);

const form = useForm({
    venue_id: null as number | null,
    name: '',
    preferred_date: '',
    preferred_start_time: '',
    notes: '',
    total_cost: 0 as number,
    receipt_photo: null as File | null,
});

const handleReceiptFile = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        form.receipt_photo = file;
        receiptPreviewUrl.value = URL.createObjectURL(file);
    }
};

const totalCost = computed(() => {
    const hourlyRate = selectedVenue.value?.default_hourly_rate || 0;
    const hours = selectedSlots.value.length || 0;
    return hours * hourlyRate;
});

const nextStep = () => {
    if (requestStep.value === 1) {
        if (!form.name) {
            form.setError('name', 'Tournament name is required.');
            return;
        }
        form.clearErrors('name');
        requestStep.value = 2;
    } else if (requestStep.value === 2) {
        if (!form.preferred_date) {
            form.setError('preferred_date', 'Preferred date is required.');
            return;
        }
        if (selectedSlots.value.length === 0) {
            form.setError('preferred_start_time', 'Please select at least one time slot.');
            return;
        }
        form.clearErrors('preferred_date', 'preferred_start_time');
        requestStep.value = 3;
    }
};

const prevStep = () => {
    if (requestStep.value > 1) {
        requestStep.value--;
    }
};

const openTournamentRequest = (venueId: number) => {
    selectedVenueId.value = venueId;
    form.venue_id = venueId;
    requestStep.value = 1;
    if (!form.preferred_date) {
        form.preferred_date = new Date().toISOString().split('T')[0];
    }
    form.clearErrors();
};

const cancelTournamentRequest = () => {
    selectedVenueId.value = null;
    form.reset('name', 'preferred_date', 'preferred_start_time', 'notes', 'total_cost', 'receipt_photo');
    form.venue_id = null;
    venueAvail.value = null;
    selectedSlots.value = [];
    requestStep.value = 1;
    receiptPreviewUrl.value = null;
    form.clearErrors();
};

const submit = () => {
    form.venue_id = selectedVenueId.value;
    form.total_cost = totalCost.value;
    form.post(route('tournament-requests.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            cancelTournamentRequest();
        },
    });
};

const availabilityLoading = ref(false);
const venueAvail = ref<{
    is_closed: boolean;
    opening_time: string | null;
    closing_time: string | null;
    close_reason: string | null;
    court_count: number;
    bookings: any[];
} | null>(null);

const fetchVenueAvailability = async () => {
    if (!form.venue_id || !form.preferred_date) {
        venueAvail.value = null;
        return;
    }

    availabilityLoading.value = true;
    try {
        const response = await fetch(`/player/venue-availability?venue_id=${form.venue_id}&date=${form.preferred_date}`);
        if (response.ok) {
            venueAvail.value = await response.json();
        } else {
            venueAvail.value = null;
        }
    } catch (e) {
        venueAvail.value = null;
    } finally {
        availabilityLoading.value = false;
    }
};

watch(
    () => [form.venue_id, form.preferred_date],
    () => {
        fetchVenueAvailability();
    },
);

const formatTime12h = (timeStr: string) => {
    if (!timeStr) return '';
    const [hStr] = timeStr.split(':');
    let h = parseInt(hStr, 10);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    if (h === 0) h = 12;
    return `${h} ${ampm}`;
};

const setQuickDate = (offsetDays: number) => {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    form.preferred_date = d.toISOString().split('T')[0];
};

const formatDatePillLabel = (offsetDays: number) => {
    if (offsetDays === 0) return 'Today';
    if (offsetDays === 1) return 'Tomorrow';
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
};

const getIsoDateForOffset = (offsetDays: number) => {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    return d.toISOString().split('T')[0];
};

const calendarMonth = ref(new Date());

const calendarMonthName = computed(() => {
    return calendarMonth.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const prevCalendarMonth = () => {
    const d = new Date(calendarMonth.value);
    d.setMonth(d.getMonth() - 1);
    calendarMonth.value = d;
};

const nextCalendarMonth = () => {
    const d = new Date(calendarMonth.value);
    d.setMonth(d.getMonth() + 1);
    calendarMonth.value = d;
};

const calendarDays = computed(() => {
    const year = calendarMonth.value.getFullYear();
    const month = calendarMonth.value.getMonth();

    const firstDayIndex = new Date(year, month, 1).getDay();
    const totalDaysInMonth = new Date(year, month + 1, 0).getDate();

    const todayStr = new Date().toISOString().split('T')[0];

    const days: Array<{
        id: string;
        dayNumber?: number;
        isoDate?: string;
        isPast?: boolean;
        isSelected?: boolean;
        isToday?: boolean;
        isBlank: boolean;
    }> = [];

    for (let i = 0; i < firstDayIndex; i++) {
        days.push({ id: `blank-${i}`, isBlank: true });
    }

    for (let day = 1; day <= totalDaysInMonth; day++) {
        const isoString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isPast = isoString < todayStr;
        const isSelected = form.preferred_date === isoString;
        const isToday = isoString === todayStr;

        days.push({
            id: isoString,
            dayNumber: day,
            isoDate: isoString,
            isPast,
            isSelected,
            isToday,
            isBlank: false,
        });
    }

    return days;
});

const selectCalendarDate = (isoDate?: string, isPast?: boolean) => {
    if (!isoDate || isPast) return;
    form.preferred_date = isoDate;
};

const scheduleSlots = computed(() => {
    if (!venueAvail.value) return [];
    const avail = venueAvail.value;
    if (avail.is_closed || !avail.opening_time || !avail.closing_time) return [];

    const [oh] = avail.opening_time.split(':').map(Number);
    const [ch] = avail.closing_time.split(':').map(Number);

    let closingHour = ch;
    if (ch === 0) closingHour = 24;

    const slots = [];
    const todayStr = new Date().toISOString().split('T')[0];

    for (let h = oh; h < closingHour; h++) {
        const startStr = `${String(h).padStart(2, '0')}:00`;
        const endStr = `${String((h + 1) % 24).padStart(2, '0')}:00`;

        let isPast = false;
        if (form.preferred_date === todayStr) {
            const now = new Date();
            const slotStartTime = new Date(`${form.preferred_date}T${startStr}`);
            isPast = now > slotStartTime;
        } else if (form.preferred_date < todayStr) {
            isPast = true;
        }

        const bookingsToday = avail.bookings || [];
        const bookingsOnSlot = bookingsToday.filter((b: any) => {
            const bStart = b.start_time.substring(0, 5);
            const bEnd = b.end_time.substring(0, 5);
            return bStart < endStr && bEnd > startStr;
        });

        const isFullyBooked = bookingsOnSlot.length > 0;

        slots.push({
            start: startStr,
            end: endStr,
            isPast,
            bookedCount: bookingsOnSlot.length,
            isFullyBooked,
        });
    }

    return slots;
});

const selectedSlots = ref<string[]>([]);

const toggleTimeSlot = (slot: any) => {
    if (slot.isPast || slot.isFullyBooked) return;
    const idx = selectedSlots.value.indexOf(slot.start);
    if (idx >= 0) {
        selectedSlots.value.splice(idx, 1);
    } else {
        selectedSlots.value.push(slot.start);
    }
    updatePreferredStartTime();
};

const clearSelectedSlots = () => {
    selectedSlots.value = [];
    form.preferred_start_time = '';
};

const updatePreferredStartTime = () => {
    if (selectedSlots.value.length === 0) {
        form.preferred_start_time = '';
        return;
    }

    const sortedStarts = [...selectedSlots.value].sort();
    const earliestStart = sortedStarts[0];

    let maxEndHour = 0;
    sortedStarts.forEach((startStr) => {
        const [h] = startStr.split(':').map(Number);
        if (h + 1 > maxEndHour) {
            maxEndHour = h + 1;
        }
    });

    const latestEndStr = `${String(maxEndHour % 24).padStart(2, '0')}:00`;

    if (selectedSlots.value.length === 1) {
        form.preferred_start_time = `${formatTime12h(earliestStart)} to ${formatTime12h(latestEndStr)}`;
    } else {
        form.preferred_start_time = `${formatTime12h(earliestStart)} to ${formatTime12h(latestEndStr)} (${selectedSlots.value.length} hrs)`;
    }
};

watch(
    () => form.preferred_date,
    () => {
        selectedSlots.value = [];
        form.preferred_start_time = '';
    },
);

const settingPrimaryVenueId = ref<number | null>(null);

const setPrimaryVenue = (venueId: number) => {
    settingPrimaryVenueId.value = venueId;
    router.post(route('player.set-primary-venue'), { venue_id: venueId }, {
        preserveScroll: true,
        onFinish: () => {
            settingPrimaryVenueId.value = null;
        },
    });
};

const currency = (value?: number | null) => {
    if (!value) {
        return 'Ask venue';
    }

    return `PHP ${Number(value).toFixed(0)}/hr`;
};

const isBookingPast = (dateStr?: string, endTimeStr?: string) => {
    if (!dateStr) return false;
    const now = new Date();
    const todayStr = now.toISOString().split('T')[0];

    if (dateStr < todayStr) return true;

    if (dateStr === todayStr && endTimeStr) {
        const parts = endTimeStr.split(':').map(Number);
        if (parts.length >= 2) {
            const endDateTime = new Date();
            endDateTime.setHours(parts[0], parts[1], 0, 0);
            return now > endDateTime;
        }
    }

    return false;
};

const getBookingStatusText = (booking: any) => {
    if (!booking) return '';
    const status = String(booking.status || '').toLowerCase();
    if (status === 'approved' && isBookingPast(booking.booking_date, booking.end_time)) {
        return 'COMPLETED';
    }
    return status.toUpperCase();
};

const formatPreferredDate = (dateStr?: string) => {
    if (!dateStr) return '';
    return dateStr.includes('T') ? dateStr.split('T')[0] : dateStr;
};

const getRequestStatusText = (req: any) => {
    if (!req) return '';
    const status = String(req.status || '').toLowerCase();
    const cleanDate = formatPreferredDate(req.preferred_date);
    if (status === 'approved' && isBookingPast(cleanDate)) {
        return 'COMPLETED';
    }
    return status.toUpperCase();
};

const requestStatusClass = (req: any) => {
    if (!req) return '';
    const status = typeof req === 'string' ? req : req.status;
    const cleanDate = typeof req === 'object' ? formatPreferredDate(req.preferred_date) : '';
    const isPast = typeof req === 'object' && req.status === 'approved' && isBookingPast(cleanDate);

    if (isPast) {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300';
    }

    if (status === 'approved') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (status === 'rejected') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
};

const bookingStatusClass = (booking: any) => {
    if (!booking) return '';
    const status = typeof booking === 'string' ? booking : booking.status;
    const isPast = typeof booking === 'object' && booking.status === 'approved' && isBookingPast(booking.booking_date, booking.end_time);

    if (isPast) {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300';
    }

    if (status === 'approved') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (status === 'cancelled' || status === 'rejected') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
};
</script>

<template>
    <Head title="Venues" />

    <AppLayout :breadcrumbs="[{ title: 'Venues', href: '/venues' }]">
        <div class="space-y-6 p-4 sm:p-6">
            <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                <div class="bg-gradient-to-r from-blue-600 via-sky-500 to-emerald-500 px-6 py-8 text-white dark:from-green-700 dark:via-emerald-600 dark:to-lime-500 sm:px-8">
                    <h1 class="text-3xl font-black sm:text-4xl">Find your next venue</h1>
                    <p class="mt-3 max-w-3xl text-sm text-white/85 sm:text-base">
                        Browse active venues, check their photos and amenities, then go straight into booking. If you want to organize an event, you can also request a tournament from the same venue card.
                    </p>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="grid items-start gap-6 lg:grid-cols-2">
                    <article
                        v-for="venue in venues"
                        :key="venue.id"
                        class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                    >
                        <div class="relative h-56 overflow-hidden bg-slate-100 dark:bg-[#090909]">
                            <img
                                v-if="venue.cover_photo_url"
                                :src="venue.cover_photo_url"
                                :alt="`${venue.name} cover photo`"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full items-center justify-center bg-gradient-to-br from-slate-100 via-slate-200 to-slate-50 text-sm font-semibold text-slate-400 dark:from-[#101010] dark:via-[#171717] dark:to-[#0d0d0d]">
                                Venue cover photo
                            </div>

                            <div class="absolute inset-x-0 top-0 flex items-start justify-between p-4 z-10">
                                <!-- Top Left Primary / Visited Toggle Badge -->
                                <button
                                    type="button"
                                    @click.stop.prevent="setPrimaryVenue(venue.id)"
                                    :disabled="settingPrimaryVenueId === venue.id"
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black shadow-md transition cursor-pointer disabled:opacity-50"
                                    :class="venue.is_primary ? 'bg-emerald-600 text-white hover:bg-emerald-500 ring-2 ring-emerald-400/40' : 'bg-slate-900/85 backdrop-blur-md text-white hover:bg-emerald-600'"
                                    :title="venue.is_primary ? 'Primary Venue' : 'Click to set as Primary Venue'"
                                >
                                    <Crown v-if="venue.is_primary" class="h-3.5 w-3.5 text-amber-300 fill-amber-300" />
                                    <MapPin v-else-if="venue.is_visited" class="h-3.5 w-3.5 text-amber-400" />
                                    <Plus v-else class="h-3.5 w-3.5 text-slate-300" />
                                    <span>{{ venue.is_primary ? 'Primary' : (venue.is_visited ? 'Visited' : 'Set Primary') }}</span>
                                </button>

                                <span class="rounded-full bg-primary px-3 py-1 text-xs font-bold text-primary-foreground shadow-md">Available</span>
                            </div>
                        </div>

                        <div class="p-5">
                            <div class="flex items-start gap-4">
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-[#1a1a1a] dark:bg-[#090909]">
                                    <img v-if="venue.logo_url" :src="venue.logo_url" :alt="`${venue.name} logo`" class="h-full w-full object-cover" />
                                    <span v-else class="text-xs font-black text-slate-400">{{ venue.name.slice(0, 2).toUpperCase() }}</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h2 class="text-2xl font-black text-slate-900 dark:text-white">{{ venue.name }}</h2>
                                    <p v-if="venue.tagline" class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">{{ venue.tagline }}</p>
                                    <p class="mt-3 flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                                        <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-green-400" />
                                        <span>{{ venue.address || 'Address will be updated soon.' }}</span>
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 line-clamp-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                {{ venue.description || 'This venue is ready for bookings. Open the booking page to see the live schedule and choose your court.' }}
                            </p>


                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-[#0a0a0a]">
                                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Courts</p>
                                    <p class="mt-1 text-lg font-black text-slate-900 dark:text-white">{{ venue.court_count || 0 }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-[#0a0a0a]">
                                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Covered</p>
                                    <p class="mt-1 text-lg font-black text-slate-900 dark:text-white">{{ venue.covered_court_count || 0 }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-[#0a0a0a]">
                                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Rate</p>
                                    <p class="mt-1 text-lg font-black text-slate-900 dark:text-white">{{ currency(venue.default_hourly_rate) }}</p>
                                </div>
                            </div>

                            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                                <a
                                    :href="route('book.venue', { venue: venue.name })"
                                    class="inline-flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-500 dark:bg-green-600 dark:hover:bg-green-500"
                                >
                                    <Calendar class="h-4 w-4" />
                                    View Details &amp; Book
                                </a>
                                <button
                                    type="button"
                                    @click="openTournamentRequest(venue.id)"
                                    class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:hover:bg-[#141414]"
                                >
                                    <Swords class="h-4 w-4 text-blue-600 dark:text-green-400" />
                                    Request Tournament
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-black text-slate-900 dark:text-white">My Tournament Requests</h2>
                            <div v-if="requests.length > 1" class="flex items-center gap-1">
                                <button
                                    type="button"
                                    :disabled="!hasReqPrevious"
                                    @click="goReqPrev"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30 dark:border-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#171717]"
                                >
                                    <ChevronLeft class="h-3.5 w-3.5" />
                                </button>
                                <span class="min-w-[3rem] text-center text-[11px] font-bold text-slate-400 dark:text-slate-500">
                                    {{ requestIndex + 1 }}/{{ requests.length }}
                                </span>
                                <button
                                    type="button"
                                    :disabled="!hasReqNext"
                                    @click="goReqNext"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30 dark:border-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#171717]"
                                >
                                    <ChevronRight class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div
                                v-if="visibleRequest"
                                :key="visibleRequest.id"
                                class="rounded-2xl border border-slate-200 p-4 dark:border-[#1a1a1a]"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ visibleRequest.name }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ visibleRequest.venue?.name }}</p>
                                    </div>
                                    <span
                                        class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-wider"
                                        :class="requestStatusClass(visibleRequest)"
                                    >
                                        {{ getRequestStatusText(visibleRequest) }}
                                    </span>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                    <span v-if="visibleRequest.preferred_date" class="inline-flex items-center gap-1">
                                        <Calendar class="h-3.5 w-3.5" />
                                        {{ formatPreferredDate(visibleRequest.preferred_date) }}
                                    </span>
                                    <span v-if="visibleRequest.preferred_start_time">{{ visibleRequest.preferred_start_time }}</span>
                                </div>
                                <p v-if="visibleRequest.notes" class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ visibleRequest.notes }}</p>
                                <p v-if="visibleRequest.rejection_reason" class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ visibleRequest.rejection_reason }}</p>
                                <p v-if="visibleRequest.tournamentDay && !visibleRequest.tournament" class="mt-3 text-sm text-emerald-600 dark:text-emerald-300">
                                    Approved main folder ready: {{ visibleRequest.tournamentDay.name }} ({{ visibleRequest.tournamentDay.status }})
                                </p>
                                <p v-if="visibleRequest.tournament" class="mt-3 text-sm text-emerald-600 dark:text-emerald-300">
                                    Tournament access: {{ visibleRequest.tournament.name }} ({{ visibleRequest.tournament.status }})
                                </p>
                            </div>
                            <p v-if="requests.length === 0" class="text-sm text-slate-500 dark:text-slate-400">No tournament requests yet.</p>
                        </div>
                    </section>

                    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">My Booked Schedules</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            View your venue bookings, schedule details, and approval status.
                        </p>

                        <div class="mt-4 space-y-3">
                            <template v-if="currentBooking">
                                <p class="text-xs font-black uppercase tracking-wider text-emerald-500 dark:text-emerald-400">Current</p>
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-800/40 dark:bg-emerald-900/10">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-slate-900 dark:text-white">{{ currentBooking.venue_name || 'Venue booking' }}</p>
                                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ currentBooking.player_username }}</p>
                                        </div>
                                        <span
                                            class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-wider"
                                            :class="bookingStatusClass(currentBooking)"
                                        >
                                            {{ getBookingStatusText(currentBooking) }}
                                        </span>
                                    </div>

                                    <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                        <p class="inline-flex items-center gap-2">
                                            <Calendar class="h-4 w-4 text-blue-600 dark:text-green-400" />
                                            <span>{{ currentBooking.booking_date }} · {{ currentBooking.start_time }} - {{ currentBooking.end_time }}</span>
                                        </p>
                                        <p>Court {{ currentBooking.court_number }} · {{ currentBooking.player_count }} players · {{ currentBooking.client_type || 'booking' }}</p>
                                        <p>Total: PHP {{ Number(currentBooking.total_cost).toFixed(2) }}</p>
                                        <p v-if="currentBooking.payment_status" class="text-xs uppercase tracking-wider text-slate-400">
                                            Payment: {{ currentBooking.payment_status }}
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <template v-if="previousBookings.length > 0">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Previous</p>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            :disabled="!hasPrevious"
                                            @click="goPrev"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30 dark:border-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#171717]"
                                        >
                                            <ChevronLeft class="h-3.5 w-3.5" />
                                        </button>
                                        <span class="min-w-[3rem] text-center text-[11px] font-bold text-slate-400 dark:text-slate-500">
                                            {{ previousIndex + 1 }}/{{ previousBookings.length }}
                                        </span>
                                        <button
                                            type="button"
                                            :disabled="!hasNext"
                                            @click="goNext"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30 dark:border-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#171717]"
                                        >
                                            <ChevronRight class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-if="visiblePreviousBooking"
                                    :key="visiblePreviousBooking.id"
                                    class="rounded-2xl border border-slate-200 p-4 dark:border-[#1a1a1a]"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-slate-900 dark:text-white">{{ visiblePreviousBooking.venue_name || 'Venue booking' }}</p>
                                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ visiblePreviousBooking.player_username }}</p>
                                        </div>
                                        <span
                                            class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-wider"
                                            :class="bookingStatusClass(visiblePreviousBooking)"
                                        >
                                            {{ getBookingStatusText(visiblePreviousBooking) }}
                                        </span>
                                    </div>

                                    <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                        <p class="inline-flex items-center gap-2">
                                            <Calendar class="h-4 w-4 text-blue-600 dark:text-green-400" />
                                            <span>{{ visiblePreviousBooking.booking_date }} · {{ visiblePreviousBooking.start_time }} - {{ visiblePreviousBooking.end_time }}</span>
                                        </p>
                                        <p>Court {{ visiblePreviousBooking.court_number }} · {{ visiblePreviousBooking.player_count }} players · {{ visiblePreviousBooking.client_type || 'booking' }}</p>
                                        <p>Total: PHP {{ Number(visiblePreviousBooking.total_cost).toFixed(2) }}</p>
                                        <p v-if="visiblePreviousBooking.payment_status" class="text-xs uppercase tracking-wider text-slate-400">
                                            Payment: {{ visiblePreviousBooking.payment_status }}
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <p v-if="bookings.length === 0" class="text-sm text-slate-500 dark:text-slate-400">No booked schedules yet.</p>
                        </div>
                    </section>
                </aside>
            </div>
        </div>

        <div
            v-if="selectedVenue"
            class="fixed inset-0 z-[140] flex items-end justify-center bg-black/60 p-0 backdrop-blur-sm sm:items-center sm:p-6"
            @click.self="cancelTournamentRequest"
        >
            <div class="flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl dark:border dark:border-slate-800 dark:bg-[#0f0f0f] sm:rounded-[2rem]">
                <!-- Modal Header matching Reserve Court design -->
                <div class="shrink-0 bg-gradient-to-br from-emerald-600 to-green-700 p-4 text-white sm:p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <Swords class="h-5 w-5 text-white" />
                                <h2 class="text-xl font-black">Request a Tournament</h2>
                            </div>
                            <p class="mt-0.5 text-xs font-bold opacity-90">{{ selectedVenue.name }} <span v-if="form.preferred_date">· {{ form.preferred_date }}</span></p>
                        </div>
                        <button type="button" @click="cancelTournamentRequest" class="rounded-2xl bg-white/15 p-2.5 transition-colors hover:bg-white/25">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Stepper 1 - 2 - 3 -->
                <div class="shrink-0 px-5 pt-4 sm:px-6">
                    <div class="flex items-center">
                        <template v-for="s in 3" :key="s">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-black transition-all"
                                :class="
                                    requestStep >= s
                                        ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/25'
                                        : 'bg-slate-100 text-slate-400 dark:bg-[#1a1a1a]'
                                "
                            >
                                {{ s }}
                            </div>
                            <div
                                v-if="s < 3"
                                class="mx-2 h-[3px] flex-1 rounded-full transition-all"
                                :class="requestStep > s ? 'bg-emerald-600' : 'bg-slate-100 dark:bg-[#1a1a1a]'"
                            ></div>
                        </template>
                    </div>
                    <p class="pt-3 text-center text-xs font-black uppercase tracking-widest text-slate-400">
                        {{ requestStep === 1 ? 'Guest & Event Details' : requestStep === 2 ? 'Time & Schedule' : 'Payment Process' }}
                    </p>
                </div>

                <!-- Server Errors Banner -->
                <div
                    v-if="Object.keys(form.errors).length > 0"
                    class="mx-5 mt-3 shrink-0 rounded-xl bg-red-50 p-3 text-xs font-black text-red-700 dark:bg-red-950/20 dark:text-red-400"
                >
                    <div class="flex items-start gap-1.5">
                        <X class="h-4 w-4 shrink-0 mt-0.5" />
                        <div class="space-y-0.5">
                            <p class="font-bold text-[11px]">Validation Error:</p>
                            <ul class="list-inside list-disc font-medium opacity-90">
                                <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- STEP 1: Guest & Event Details -->
                <div v-if="requestStep === 1" class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                            <CheckCircle class="h-3.5 w-3.5 text-emerald-500" /> Client Type
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                disabled
                                class="rounded-xl border border-emerald-600 bg-emerald-600 px-3 py-2.5 text-sm font-black text-white cursor-default"
                            >
                                Player
                            </button>
                            <button
                                type="button"
                                disabled
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-black text-slate-400 opacity-60 dark:border-slate-800 dark:bg-[#0a0a0a]/50 cursor-default"
                            >
                                Client
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                            Client type is based on whether you are logged in with a player account.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                            <FileText class="h-3.5 w-3.5 text-emerald-500" /> Tournament Name
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="e.g. Summer Pickleball Championship"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/10 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-emerald-400"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                <User class="h-3.5 w-3.5 text-emerald-500" /> Lead Guest Name
                            </label>
                            <input
                                :value="props.authUser?.name || 'Player Account'"
                                type="text"
                                disabled
                                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 opacity-80 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-slate-300"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                <Phone class="h-3.5 w-3.5 text-emerald-500" /> Contact Phone
                            </label>
                            <input
                                :value="props.authUser?.phone || 'Provided via Profile'"
                                type="text"
                                disabled
                                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 opacity-80 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-slate-300"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Notes / Event Details
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Optional notes or details for the venue scheduler"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-emerald-400 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white"
                        ></textarea>
                    </div>
                </div>
                <!-- STEP 2: Time & Schedule -->
                <div v-else-if="requestStep === 2" class="flex-1 space-y-5 overflow-y-auto p-5 sm:p-6 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                    <!-- Custom System Calendar Card -->
                    <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-[#0a0a0a]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Calendar class="h-4 w-4 text-emerald-500" />
                                <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">
                                    Preferred Date: <span class="text-emerald-500 font-extrabold ml-1">{{ form.preferred_date }}</span>
                                </span>
                            </div>

                            <span v-if="venueAvail && !venueAvail.is_closed" class="text-[10px] font-bold text-slate-400">
                                Open {{ formatTime12h(venueAvail.opening_time ?? '') }} - {{ formatTime12h(venueAvail.closing_time ?? '') }}
                            </span>
                        </div>

                        <!-- System Custom Calendar Header -->
                        <div class="flex items-center justify-between rounded-xl bg-white p-2.5 dark:bg-[#121212] border border-slate-200 dark:border-slate-800">
                            <button
                                type="button"
                                @click="prevCalendarMonth"
                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#252525] cursor-pointer"
                            >
                                <ChevronLeft class="h-3.5 w-3.5" />
                            </button>
                            <span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">
                                {{ calendarMonthName }}
                            </span>
                            <button
                                type="button"
                                @click="nextCalendarMonth"
                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#252525] cursor-pointer"
                            >
                                <ChevronRight class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Days of Week Header -->
                        <div class="grid grid-cols-7 text-center text-[10px] font-black uppercase tracking-widest text-slate-400 pt-1">
                            <span v-for="d in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="d">{{ d }}</span>
                        </div>

                        <!-- Days Grid -->
                        <div class="grid grid-cols-7 gap-1">
                            <template v-for="cell in calendarDays" :key="cell.id">
                                <div v-if="cell.isBlank" class="h-8"></div>
                                <button
                                    v-else
                                    type="button"
                                    @click="selectCalendarDate(cell.isoDate, cell.isPast)"
                                    :disabled="cell.isPast"
                                    class="flex h-8 items-center justify-center rounded-xl text-xs font-bold transition-all cursor-pointer"
                                    :class="[
                                        cell.isSelected
                                            ? 'bg-emerald-500 text-white font-black shadow-md shadow-emerald-500/30'
                                            : cell.isPast
                                              ? 'text-slate-300 opacity-40 dark:text-slate-600 cursor-not-allowed'
                                              : cell.isToday
                                                ? 'border border-emerald-500/60 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20'
                                                : 'bg-white text-slate-800 hover:bg-slate-100 dark:bg-[#121212] dark:text-slate-200 dark:hover:bg-[#1f1f1f]'
                                    ]"
                                >
                                    {{ cell.dayNumber }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- TIME & SCHEDULE SLOT GRID -->
                    <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#090909]">
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-3 dark:border-slate-800/60">
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4 text-emerald-500" />
                                <span class="text-[11px] font-black uppercase tracking-[0.15em] text-slate-400">Available Time Slots</span>
                            </div>
                            <span class="text-[10px] font-medium text-slate-400">Tap slots to select multi-hour schedule</span>
                        </div>

                        <div v-if="!form.preferred_date" class="py-8 text-center text-xs font-bold text-slate-400">
                            Please select a preferred date above to load available time slots.
                        </div>

                        <div v-else-if="availabilityLoading" class="flex items-center justify-center py-8 text-xs font-bold text-slate-400">
                            <div class="flex items-center gap-2">
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-emerald-500 border-t-transparent"></div>
                                <span>Checking schedule availability...</span>
                            </div>
                        </div>

                        <div v-else-if="venueAvail?.is_closed" class="rounded-2xl border border-rose-500/20 bg-rose-500/10 p-5 text-center text-xs font-bold text-rose-400">
                            🚫 Venue is closed on this date{{ venueAvail.close_reason ? `: ${venueAvail.close_reason}` : '' }}
                        </div>

                        <div v-else-if="scheduleSlots.length > 0" class="space-y-4 pt-1">
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                <button
                                    v-for="slot in scheduleSlots"
                                    :key="slot.start"
                                    type="button"
                                    @click="toggleTimeSlot(slot)"
                                    :disabled="slot.isPast || slot.isFullyBooked"
                                    class="flex flex-col items-center justify-center py-3 px-2 rounded-2xl border text-center transition-all duration-200 min-h-[56px] cursor-pointer relative"
                                    :class="[
                                        selectedSlots.includes(slot.start)
                                            ? 'border-emerald-500 bg-emerald-500 text-white font-black shadow-lg shadow-emerald-500/30 scale-[1.02]'
                                            : slot.isFullyBooked || slot.isPast
                                              ? 'opacity-40 border-slate-200 bg-slate-100 text-slate-400 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-500 cursor-not-allowed'
                                              : 'border-slate-200 bg-white text-slate-900 hover:border-emerald-500 hover:bg-emerald-50/50 dark:border-[#1a1a1a] dark:bg-[#121212] dark:text-emerald-400 dark:hover:border-emerald-500 dark:hover:bg-emerald-950/20'
                                    ]"
                                >
                                    <span class="text-xs font-black tracking-tight leading-none">
                                        {{ formatTime12h(slot.start) }}
                                    </span>
                                    <span class="text-[9px] font-bold mt-1" :class="selectedSlots.includes(slot.start) ? 'text-white/90' : 'text-slate-400 dark:text-slate-500'">
                                        to {{ formatTime12h(slot.end) }}
                                    </span>
                                    <span v-if="slot.isFullyBooked" class="text-[8px] font-bold text-rose-400 leading-none mt-1">Booked</span>
                                </button>
                            </div>

                            <div v-if="selectedSlots.length > 0" class="flex items-center justify-between rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-3.5 py-2.5 text-xs font-bold text-emerald-400">
                                <span class="flex items-center gap-1.5">
                                    <CheckCircle class="h-4 w-4 shrink-0" />
                                    <span>Selected: <strong>{{ form.preferred_start_time }}</strong></span>
                                </span>
                                <button type="button" @click="clearSelectedSlots" class="text-[11px] font-black uppercase tracking-wider text-rose-400 hover:underline">Clear</button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Cost Summary Card -->
                    <div v-if="selectedSlots.length > 0" class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-gradient-to-r from-emerald-500/10 to-transparent p-4 dark:border-emerald-500/30">
                        <div>
                            <p class="text-xs font-bold text-slate-600 dark:text-slate-300">Estimated Total Fee</p>
                            <p class="text-[11px] font-medium text-slate-400">{{ selectedSlots.length }} hours × PHP {{ Number(selectedVenue?.default_hourly_rate || 0).toFixed(2) }}/hr</p>
                        </div>
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">PHP {{ totalCost.toFixed(2) }}</p>
                    </div>
                </div>

                <!-- STEP 3: Payment Process -->
                <div v-else-if="requestStep === 3" class="flex-1 space-y-4 overflow-y-auto p-5">
                    <!-- Payment Reference Details Card -->
                    <div
                        v-if="selectedVenue?.payment_account_name || selectedVenue?.payment_qr_photo"
                        class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-[#0a0a0a]"
                    >
                        <div class="flex items-center gap-2 mb-3">
                            <CreditCard class="h-4 w-4 text-emerald-500" />
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">
                                Payment Reference
                            </h4>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-4">
                            <div
                                v-if="selectedVenue?.payment_qr_photo"
                                @click="openEnlargedImage(selectedVenue.payment_qr_photo)"
                                class="relative group cursor-pointer shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-800 dark:bg-[#121212] shadow-sm"
                            >
                                <img
                                    :src="selectedVenue.payment_qr_photo"
                                    alt="Payment QR Code"
                                    class="h-36 w-36 max-w-full object-contain transition-transform duration-300 group-hover:scale-105"
                                />
                                <div class="absolute inset-0 flex flex-col items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <span class="text-[10px] font-black text-white bg-black/80 px-2 py-1 rounded-md flex items-center gap-1 shadow">
                                        <Maximize2 class="h-3 w-3" /> Enlarge QR
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 space-y-1.5 text-center sm:text-left">
                                <p v-if="selectedVenue?.payment_account_name" class="text-sm font-black text-slate-900 dark:text-white">
                                    {{ selectedVenue.payment_account_name }}
                                </p>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Please send your payment total of <strong class="text-emerald-500 font-bold">PHP {{ totalCost.toFixed(2) }}</strong> to the venue account details above and attach your payment receipt screenshot below.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-[#0a0a0a]"
                    >
                        <div class="flex items-center gap-2 mb-1.5">
                            <CreditCard class="h-4 w-4 text-emerald-500" />
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">
                                Payment Reference
                            </h4>
                        </div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                            Please send your payment total of <strong class="text-emerald-500 font-bold">PHP {{ totalCost.toFixed(2) }}</strong> to venue management via GCash or Maya and attach your payment receipt screenshot below.
                        </p>
                    </div>

                    <!-- Summary Breakdown Card -->
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-[#0a0a0a]">
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-500">Summary Breakdown</h4>
                        <div class="mt-3 space-y-2 text-xs text-slate-700 dark:text-slate-300">
                            <div class="flex justify-between"><span>Tournament:</span><span class="font-bold">{{ form.name }}</span></div>
                            <div class="flex justify-between"><span>Date & Schedule:</span><span class="font-bold">{{ form.preferred_date }} ({{ form.preferred_start_time }})</span></div>
                            <div class="flex justify-between"><span>Venue Rate:</span><span class="font-bold">{{ currency(selectedVenue?.default_hourly_rate) }}</span></div>
                            <div class="my-2 border-t border-slate-200 dark:border-slate-800"></div>
                            <div class="flex justify-between text-sm font-black text-emerald-600 dark:text-emerald-400">
                                <span>Total Fee Due:</span>
                                <span>PHP {{ totalCost.toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Receipt Section -->
                    <div class="space-y-2">
                        <label class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                            <Upload class="h-3.5 w-3.5 text-emerald-500" /> Upload Payment Receipt / Proof
                        </label>
                        <div class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-5 transition hover:border-emerald-500 dark:border-slate-800 dark:bg-[#0a0a0a]">
                            <input type="file" accept="image/*" @change="handleReceiptFile" class="absolute inset-0 cursor-pointer opacity-0" />
                            <div v-if="receiptPreviewUrl" class="flex flex-col items-center gap-2">
                                <img :src="receiptPreviewUrl" alt="Receipt preview" class="max-h-32 rounded-xl object-contain shadow-md" />
                                <span class="text-xs font-bold text-emerald-500">Receipt attached</span>
                            </div>
                            <div v-else class="flex flex-col items-center text-center">
                                <ImageIcon class="mb-2 h-8 w-8 text-slate-400" />
                                <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Click or drop payment receipt screenshot</p>
                                <p class="mt-1 text-[10px] text-slate-400">PNG, JPG up to 5MB</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-[#1a1a1a] sm:px-6">
                    <button
                        type="button"
                        @click="requestStep === 1 ? cancelTournamentRequest() : prevStep()"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-2xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-[#171717] cursor-pointer"
                    >
                        <ArrowLeft v-if="requestStep > 1" class="h-4 w-4" />
                        {{ requestStep === 1 ? 'Cancel' : 'Back' }}
                    </button>

                    <button
                        v-if="requestStep < 3"
                        type="button"
                        @click="nextStep"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-500 shadow-md shadow-emerald-600/25 cursor-pointer"
                    >
                        <span>Continue</span>
                        <ArrowRight class="h-4 w-4" />
                    </button>

                    <button
                        v-else
                        type="button"
                        @click="submit"
                        :disabled="form.processing"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-500 disabled:opacity-60 shadow-md shadow-emerald-600/25 cursor-pointer"
                    >
                        <Send class="h-4 w-4" />
                        <span>Submit Tournament Request</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ENLARGED QR CODE LIGHTBOX MODAL -->
        <Teleport to="body">
            <div
                v-if="showEnlargedImage"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/85 p-4 backdrop-blur-md transition-opacity"
                @click="showEnlargedImage = false"
            >
                <div
                    class="relative flex max-h-[90vh] max-w-sm flex-col items-center justify-center rounded-3xl border border-slate-700 bg-white p-4 shadow-2xl dark:bg-[#121212]"
                    @click.stop
                >
                    <button
                        type="button"
                        @click="showEnlargedImage = false"
                        class="absolute -top-3 -right-3 flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 text-white shadow-lg transition hover:bg-emerald-500 cursor-pointer"
                    >
                        <X class="h-5 w-5" />
                    </button>
                    <img
                        :src="enlargedImageUrl"
                        alt="Enlarged Payment QR Code"
                        class="max-h-[75vh] w-full rounded-2xl object-contain shadow-sm bg-white p-2"
                    />
                    <p class="mt-3 text-xs font-bold text-slate-400">Click anywhere outside to close</p>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
