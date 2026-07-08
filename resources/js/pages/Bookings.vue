<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar as CalendarIcon,
    CheckCircle,
    ChevronDown,
    ChevronLeft,
    Search,
    ChevronRight,
    Clock,
    DollarSign,
    Edit,
    Eye,
    Link2,
    MapPin,
    Phone,
    Plus,
    Trash2,
    Upload,
    User as UserIcon,
    Users,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    bookings: any[];
    players: any[];
    scorers: { id: number; name: string }[];
    courtAssignments: Record<number, { scorer_id: number | null; scorer_name: string | null }>;
    settings: Record<string, string>;
    weather: Record<string, { code: number; temp_max: number; temp_min: number }>;
    weeklyAvailabilities: Array<{ id: number; day_of_week: number; is_closed: boolean; opening_time: string | null; closing_time: string | null; close_reason?: string | null }>;
    dateOverrides: Array<{ id: number; date: string; is_closed: boolean; opening_time: string | null; closing_time: string | null; close_reason?: string | null }>;
}>();

const page = usePage<SharedData>();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const bookingPartyMode = ref<'player' | 'client'>('player');
const selectedPlayerLookup = ref('');
const selectedPlayerId = ref<number | null>(null);

const normalizedPlayers = computed(() =>
    [...props.players]
        .map((player: any) => {
            console.log('PLAYER:', player.full_name || player.name, 'USER:', player.user);
            const membershipLabel = player.is_member ? 'Member' : 'Non-member';
            const phone = player.phone || '';
            const address = player.address || '';
            const fullName = player.full_name || player.name || 'Unnamed player';
            const searchLabel = phone ? `${fullName} • ${phone} • ${membershipLabel}` : `${fullName} • ${membershipLabel}`;

            return {
                ...player,
                user: player.user,
                display_name: fullName,
                membershipLabel,
                phone,
                address,
                searchLabel,
            };
        })
        .sort((a, b) => a.display_name.localeCompare(b.display_name)),
);

const showPlayerDropdown = ref(false);
const playerSearchQuery = ref('');
const playerDropdownRef = ref<HTMLElement | null>(null);

const selectedPlayer = computed(() => {
    return normalizedPlayers.value.find((p) => p.id === selectedPlayerId.value) ?? null;
});

watch(selectedPlayer, (player) => {
    playerSearchQuery.value = player ? (player.user?.username || player.display_name) : '';
}, { immediate: true });

const handlePlayerDropdownClickOutside = (e: MouseEvent) => {
    if (playerDropdownRef.value && !playerDropdownRef.value.contains(e.target as Node)) {
        showPlayerDropdown.value = false;
        playerSearchQuery.value = selectedPlayer.value 
            ? (selectedPlayer.value.user?.username || selectedPlayer.value.display_name) 
            : '';
    }
};

onMounted(() => {
    document.addEventListener('click', handlePlayerDropdownClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handlePlayerDropdownClickOutside);
});

const filteredPlayers = computed(() => {
    const query = playerSearchQuery.value.toLowerCase().trim();
    const selectedName = selectedPlayer.value 
        ? (selectedPlayer.value.user?.username || selectedPlayer.value.display_name).toLowerCase()
        : '';
    
    if (!query || query === selectedName) {
        return normalizedPlayers.value;
    }
    
    return normalizedPlayers.value.filter((player) => {
        const username = (player.user?.username || '').toLowerCase();
        const displayName = (player.display_name || '').toLowerCase();
        return username.includes(query) || displayName.includes(query);
    });
});

const selectPlayer = (player: any) => {
    if (!player) {
        syncSelectedPlayer(null);
        playerSearchQuery.value = '';
    } else {
        syncSelectedPlayer(player.id);
        playerSearchQuery.value = player.user?.username || player.display_name;
    }
    showPlayerDropdown.value = false;
};

const displayedMembershipStatus = computed(() => (form.client_type === 'member' ? 'Member' : 'Non-member'));

const syncSelectedPlayer = (playerId: number | null) => {
    selectedPlayerId.value = playerId;
    const player = playerId ? normalizedPlayers.value.find((entry) => entry.id === playerId) ?? null : null;

    if (!player) {
        selectedPlayerLookup.value = '';
        form.player_ids = [];
        return;
    }

    selectedPlayerLookup.value = player.searchLabel;
    form.lead_name = player.display_name;
    form.lead_address = player.address;
    form.guest_phone = player.phone;
    form.client_type = player.is_member ? 'member' : 'non_member';
    form.player_ids = [player.id];
};

const setBookingPartyMode = (mode: 'player' | 'client') => {
    bookingPartyMode.value = mode;

    if (mode === 'player') {
        form.client_type = 'member';
        syncSelectedPlayer(selectedPlayerId.value);
        return;
    }

    selectedPlayerId.value = null;
    selectedPlayerLookup.value = '';
    form.player_ids = [];
    form.lead_name = '';
    form.lead_address = '';
    form.guest_phone = '';
    form.client_type = 'non_member';
};

const handlePlayerLookupChange = () => {
    const value = selectedPlayerLookup.value.trim();
    const matched = normalizedPlayers.value.find((player) => player.searchLabel === value || player.display_name === value) ?? null;

    if (matched) {
        syncSelectedPlayer(matched.id);
        return;
    }

    selectedPlayerId.value = null;
    form.player_ids = [];
    form.lead_name = value;
    form.lead_address = '';
    form.guest_phone = '';
    form.client_type = 'member';
};

// Auto-poll: refresh bookings, court assignments, and weather every 5s, but only when the tab is visible.
// On tab focus, refresh immediately so the user sees the latest state.
let pollIntervalId: ReturnType<typeof setInterval> | null = null;

const startPolling = () => {
    if (pollIntervalId !== null) return;
    pollIntervalId = setInterval(() => {
        if (document.visibilityState === 'visible') {
            router.reload({ only: ['bookings', 'courtAssignments', 'weather'] });
        }
    }, 5000);
};

const stopPolling = () => {
    if (pollIntervalId !== null) {
        clearInterval(pollIntervalId);
        pollIntervalId = null;
    }
};

const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        startPolling();
        router.reload({ only: ['bookings', 'courtAssignments', 'weather'] });
    } else {
        stopPolling();
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    if (document.visibilityState === 'visible') startPolling();
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    stopPolling();
});

const getCourtActiveType = (courtNum: number) => {
    const dateStr = formatDateToISO(new Date(form.booking_date));
    const isTodaySelected = dateStr === formatDateToISO(new Date());

    const dayBookings = props.bookings.filter(
        (b) => b.booking_date === dateStr && Number(b.court_number) === Number(courtNum) && b.status === 'approved'
    );

    if (isTodaySelected) {
        const now = new Date();
        const currentHrMin = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        const active = dayBookings.find(
            (b) => b.start_time <= currentHrMin && b.end_time >= currentHrMin
        );
        if (active) return active.type || 'booking';
    } else {
        if (dayBookings.length > 0) {
            return dayBookings[0].type || 'booking';
        }
    }
    return 'walk-in';
};

const courtIsBooking = (courtNumber: number) => getCourtActiveType(courtNumber) === 'booking';
const courtIsWalkin = (courtNumber: number) => getCourtActiveType(courtNumber) === 'walk-in';
const courtIsBoth = (courtNumber: number) => getCourtActiveType(courtNumber) === 'reclub';
const courtIsWalkinOnly = (courtNumber: number) => getCourtActiveType(courtNumber) === 'walk-in';
const isSelectedCourtWalkinOnly = computed(() => false);
const isSelectedCourtWalkin = computed(() => false);

const showToast = ref(false);
const toastMessage = ref('');
let toastTimer: ReturnType<typeof setTimeout> | null = null;

const triggerToast = (msg: string) => {
    if (!msg) return;
    toastMessage.value = msg;
    showToast.value = true;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        showToast.value = false;
    }, 4000);
};

const weatherInfo = (code: number): { emoji: string; label: string; color: string } => {
    if (code === 0) return { emoji: '☀️', label: 'Clear', color: 'text-amber-500' };
    if (code <= 3) return { emoji: '⛅', label: 'Partly Cloudy', color: 'text-slate-400' };
    if (code <= 48) return { emoji: '🌫️', label: 'Foggy', color: 'text-slate-400' };
    if (code <= 55) return { emoji: '🌦️', label: 'Drizzle', color: 'text-sky-400' };
    if (code <= 65) return { emoji: '🌧️', label: 'Rain', color: 'text-blue-500' };
    if (code <= 77) return { emoji: '❄️', label: 'Snow', color: 'text-sky-300' };
    if (code <= 82) return { emoji: '🌧️', label: 'Showers', color: 'text-blue-500' };
    if (code <= 99) return { emoji: '⛈️', label: 'Thunderstorm', color: 'text-violet-500' };
    return { emoji: '🌡️', label: 'Unknown', color: 'text-slate-400' };
};

const getWeatherForDate = (date: Date) => {
    const key = formatDateToISO(date);
    return props.weather?.[key] ?? null;
};

const formatDateToISO = (date: Date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const resolveAvailabilityForDate = (dateStr: string) => {
    const dateOverride = props.dateOverrides.find((d) => d.date === dateStr);
    if (dateOverride) {
        return {
            is_closed: Boolean(dateOverride.is_closed),
            opening_time: dateOverride.opening_time ? dateOverride.opening_time.substring(0, 5) : null,
            closing_time: dateOverride.closing_time ? dateOverride.closing_time.substring(0, 5) : null,
            close_reason: dateOverride.close_reason || null,
        };
    }

    const dateObj = new Date(dateStr);
    const dayOfWeek = dateObj.getUTCDay();
    const weeklySetting = props.weeklyAvailabilities.find((w) => w.day_of_week === dayOfWeek);
    if (weeklySetting && weeklySetting.is_enabled) {
        return {
            is_closed: Boolean(weeklySetting.is_closed),
            opening_time: weeklySetting.opening_time ? weeklySetting.opening_time.substring(0, 5) : null,
            closing_time: weeklySetting.closing_time ? weeklySetting.closing_time.substring(0, 5) : null,
            close_reason: weeklySetting.close_reason || null,
        };
    }

    return {
        is_closed: false,
        opening_time: props.settings.opening_time ? props.settings.opening_time.substring(0, 5) : '08:00',
        closing_time: props.settings.closing_time ? props.settings.closing_time.substring(0, 5) : '22:00',
        close_reason: null,
    };
};

const today = formatDateToISO(new Date());

// Helper to parse HH:mm into components for the custom picker
const parseTimeToPicker = (timeStr: string) => {
    if (!timeStr) return { h: '10', m: '00', ampm: 'AM' };
    const [h24, m] = timeStr.split(':');
    let h = parseInt(h24);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12;
    return { h: String(h).padStart(2, '0'), m, ampm };
};

const openingTime = parseTimeToPicker(props.settings.opening_time);
const defaultEndH = (parseInt(openingTime.h) % 12) + 1;
const defaultEndAmpm = parseInt(openingTime.h) === 11 && openingTime.ampm === 'AM' ? 'PM' : openingTime.ampm;

const form = useForm({
    booking_date: today,
    start_time: props.settings.opening_time || '10:00',
    duration_hours: 1,
    cost_per_hour: parseFloat(props.settings.default_hourly_rate) || 20,
    lead_name: '',
    lead_address: '',
    guest_phone: '',
    player_count: 1,
    client_type: 'member',
    player_ids: [],
    court_number: 1,
    courts: [1] as number[],
    scorer_id: null as number | null,
    hour: openingTime.h,
    minute: openingTime.m,
    ampm: openingTime.ampm,
    end_hour: String(defaultEndH),
    end_minute: openingTime.m,
    end_ampm: defaultEndAmpm,
    receipt_photo: null as File | null,
    type: 'booking',
});

const bookingRate = computed(() =>
    form.client_type === 'member'
        ? parseFloat(props.settings.member_booking_fee || '180')
        : parseFloat(props.settings.non_member_booking_fee || '200'),
);

watch(
    () => form.client_type,
    () => {
        form.cost_per_hour = bookingRate.value;
    },
);

const receiptPreview = ref<string | null>(null);
const receiptError = ref<string | null>(null);
const viewingReceipt = ref<string | null>(null);

const MAX_RECEIPT_SIZE = 5 * 1024 * 1024;
const handleReceiptChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    receiptError.value = null;
    if (!file) return;
    if (file.size > MAX_RECEIPT_SIZE) {
        receiptError.value = 'File too large. Maximum size is 5MB.';
        form.receipt_photo = null;
        receiptPreview.value = null;
        return;
    }
    form.receipt_photo = file;
    receiptPreview.value = URL.createObjectURL(file);
};

const currentMonth = ref(new Date());
const showModal = ref(false);
const showDeleteConfirm = ref(false);
const showUpdateConfirm = ref(false);
const editingBookingId = ref<number | null>(null);
const bookingToDelete = ref<any>(null);
const viewMode = ref<'time' | 'court'>('time');
const selectedCourt = ref<number | 'all'>('all');
const showCourtDropdown = ref(false);

/* Custom time dropdowns */
const openTimeDropdown = ref<'start-h' | 'start-m' | 'end-h' | 'end-m' | 'court' | null>(null);
const timeDropdownContainer = ref<HTMLElement | null>(null);
const toggleTimeDropdown = (name: 'start-h' | 'start-m' | 'end-h' | 'end-m' | 'court') => {
    openTimeDropdown.value = openTimeDropdown.value === name ? null : name;
};
const closeTimeDropdowns = () => {
    openTimeDropdown.value = null;
};
const handleTimeClickOutside = (e: MouseEvent) => {
    if (timeDropdownContainer.value && !timeDropdownContainer.value.contains(e.target as Node)) {
        closeTimeDropdowns();
    }
};

watch(showModal, (open) => {
    if (open) window.addEventListener('click', handleTimeClickOutside, true);
    else window.removeEventListener('click', handleTimeClickOutside, true);
});

const resetForm = () => {
    const opening = parseTimeToPicker(props.settings.opening_time);
    const endH = (parseInt(opening.h) % 12) + 1;
    const endAmpm = parseInt(opening.h) === 11 && opening.ampm === 'AM' ? 'PM' : opening.ampm;

    form.reset('lead_name', 'lead_address', 'guest_phone', 'player_count', 'player_ids', 'receipt_photo');
    receiptPreview.value = null;
    receiptError.value = null;
    form.court_number = 1;
    form.courts = [1];
    bookingPartyMode.value = 'player';
    selectedPlayerLookup.value = '';
    selectedPlayerId.value = null;
    form.client_type = 'member';
    form.scorer_id = null;
    form.start_time = props.settings.opening_time || '10:00';
    form.hour = opening.h;
    form.minute = opening.m;
    form.ampm = opening.ampm;
    form.end_hour = String(endH).padStart(2, '0');
    form.end_minute = opening.m;
    form.end_ampm = endAmpm;
    form.duration_hours = 1;
    form.cost_per_hour = bookingRate.value;
    form.type = 'booking';
    editingBookingId.value = null;
    form.clearErrors();
};

const daysInMonth = computed(() => {
    const year = currentMonth.value.getFullYear();
    const month = currentMonth.value.getMonth();
    const date = new Date(year, month, 1);
    const days = [];
    while (date.getMonth() === month) {
        days.push(new Date(date));
        date.setDate(date.getDate() + 1);
    }
    return days;
});

const prevMonth = () => {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1);
};

const getBookingsForDate = (date: Date) => {
    const dateStr = formatDateToISO(date);
    return props.bookings
        .filter((b) => b.booking_date === dateStr)
        .sort((a, b) => {
            if (a.status === 'pending' && b.status !== 'pending') return -1;
            if (a.status !== 'pending' && b.status === 'pending') return 1;
            return a.start_time.localeCompare(b.start_time);
        });
};

const getTopBookingsForDate = (date: Date) => {
    return getBookingsForDate(date);
};

const bookingsByCourt = computed(() => {
    const dateStr = formatDateToISO(new Date(form.booking_date));
    const dayBookings = props.bookings
        .filter((b) => b.booking_date === dateStr)
        .sort((a, b) => {
            if (a.status === 'pending' && b.status !== 'pending') return -1;
            if (a.status !== 'pending' && b.status === 'pending') return 1;
            return a.start_time.localeCompare(b.start_time);
        });
    const courtCount = parseInt(props.settings.court_count || '4');
    const result: Record<number, any[]> = {};
    for (let i = 1; i <= courtCount; i++) {
        result[i] = dayBookings.filter((b) => Number(b.court_number) === i);
    }
    return result;
});

const toggleCourtSelection = (c: number) => {
    if (form.courts.includes(c)) {
        if (form.courts.length > 1) {
            form.courts = form.courts.filter(item => item !== c);
        }
    } else {
        form.courts.push(c);
    }
    form.court_number = form.courts[0];
};

const selectDate = (date: Date) => {
    form.booking_date = formatDateToISO(date);
};

const openBookingModal = (date: Date) => {
    resetForm();
    selectDate(date);
    selectFirstAvailableSlot();
    showModal.value = true;
};

const editBooking = (booking: any) => {
    editingBookingId.value = booking.id;
    form.booking_date = booking.booking_date;
    form.start_time = booking.start_time;
    form.lead_name = booking.lead_name;
    form.lead_address = booking.lead_address;
    form.guest_phone = booking.guest_phone || '';
    form.player_count = booking.player_count;
    form.client_type = booking.client_type || 'member';
    form.cost_per_hour = booking.client_type === 'non_member'
        ? parseFloat(props.settings.non_member_booking_fee || String(booking.cost_per_hour || 200))
        : parseFloat(props.settings.member_booking_fee || String(booking.cost_per_hour || 180));
    form.court_number = booking.court_number || 1;
    form.courts = [booking.court_number || 1];
    form.scorer_id = booking.scorer_id ?? null;

    // Parse time for custom picker
    const [h24, m] = booking.start_time.split(':');
    let h = parseInt(h24);
    form.ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12;
    form.hour = String(h).padStart(2, '0');
    form.minute = m;

    // Parse end time for custom picker
    const [eh24, em] = booking.end_time.split(':');
    let eh = parseInt(eh24);
    form.end_ampm = eh >= 12 ? 'PM' : 'AM';
    eh = eh % 12;
    eh = eh ? eh : 12;
    form.end_hour = String(eh).padStart(2, '0');
    form.end_minute = em;

    // Calculate duration
    const start = new Date(`2000-01-01T${booking.start_time}`);
    const end = new Date(`2000-01-01T${booking.end_time}`);
    if (end <= start) end.setDate(end.getDate() + 1);
    const diffMs = end.getTime() - start.getTime();
    form.duration_hours = diffMs / (1000 * 60 * 60);

    form.player_ids = booking.players ? booking.players.map((p: any) => p.id) : [];
    const primaryPlayerId = booking.players?.[0]?.id ?? null;
    if (primaryPlayerId) {
        bookingPartyMode.value = 'player';
        syncSelectedPlayer(primaryPlayerId);
    } else {
        bookingPartyMode.value = 'client';
        selectedPlayerLookup.value = '';
        selectedPlayerId.value = null;
        form.client_type = booking.client_type || 'non_member';
    }
    form.receipt_photo = null;
    receiptPreview.value = booking.receipt_photo ? '/storage/' + booking.receipt_photo : null;
    receiptError.value = null;
    form.type = booking.type || 'booking';
    showModal.value = true;
};

const submit = () => {
    form.clearErrors();

    let hasErrors = false;
    if (!form.lead_name) {
        form.setError('lead_name', 'Guest name is required');
        hasErrors = true;
    }
    if (bookingPartyMode.value === 'player' && !selectedPlayerId.value) {
        form.setError('lead_name', 'Select a registered player from the list');
        hasErrors = true;
    }
    if (!form.player_count || form.player_count < 1) {
        form.setError('player_count', 'At least 1 player');
        hasErrors = true;
    }
    if (!form.cost_per_hour) {
        form.setError('cost_per_hour', 'Rate is required');
        hasErrors = true;
    }

    if (hasErrors || isSelectedCourtFull.value || !isEndTimeValid.value || !isStartTimeValid.value || isTimeSlotOverlapping.value || isSelectedTimeInPast.value) return;

    // Sync custom time picker to form.start_time
    let h = parseInt(form.hour);
    if (form.ampm === 'PM' && h < 12) h += 12;
    if (form.ampm === 'AM' && h === 12) h = 0;
    form.start_time = `${String(h).padStart(2, '0')}:${form.minute}`;

    // Sync custom end time
    let eh = parseInt(form.end_hour);
    if (form.end_ampm === 'PM' && eh < 12) eh += 12;
    if (form.end_ampm === 'AM' && eh === 12) eh = 0;

    // Calculate duration before submit
    const start = new Date(`2000-01-01T${form.start_time}`);
    const end = new Date(`2000-01-01T${String(eh).padStart(2, '0')}:${form.end_minute}`);
    if (end <= start) end.setDate(end.getDate() + 1);
    const diffMs = end.getTime() - start.getTime();
    form.duration_hours = diffMs / (1000 * 60 * 60);

    if (editingBookingId.value) {
        showUpdateConfirm.value = true;
    } else {
        processSubmit();
    }
};

const processSubmit = () => {
    if (editingBookingId.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('bookings.update', editingBookingId.value), {
            forceFormData: true,
            onSuccess: () => {
                triggerToast('Booking updated successfully.');
                resetForm();
                showModal.value = false;
                showUpdateConfirm.value = false;
            },
        });
    } else {
        form.post(route('bookings.store'), {
            forceFormData: true,
            onSuccess: () => {
                triggerToast('Reservation confirmed.');
                resetForm();
                showModal.value = false;
            },
        });
    }
};

const showCancelRefundModal = ref(false);
const showCourtAssignmentModal = ref(false);

watch(showCourtAssignmentModal, (open) => {
    if (open) {
        router.reload({ only: ['courtAssignments'], onFinish: () => initAssignments() });
    }
});
const showRescheduleModal = ref(false);
const rescheduleMonth = ref(new Date());

const refundInfo = computed(() => {
    if (!editingBookingId.value || !form.booking_date || !form.start_time) return null;
    const bookingDate = new Date(form.booking_date + 'T00:00:00');
    const bookingDateTime = new Date(`${form.booking_date}T${form.start_time}`);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const totalCost = parseFloat(form.total_cost || (form.duration_hours * bookingRate.value).toFixed(2));

    const fullPct = parseFloat(props.settings.refund_full_pct || '100');
    const partPct = parseFloat(props.settings.refund_partial_pct || '50');
    const noPct = parseFloat(props.settings.refund_no_pct || '0');

    let refundPct = 0;
    let policy = '';

    if (bookingDate > today) {
        refundPct = fullPct;
        policy = `Full refund (${fullPct}%) — cancelled before the booking day`;
    } else if (bookingDate.getTime() === today.getTime() && now < bookingDateTime) {
        refundPct = partPct;
        policy = `Partial refund (${partPct}%) — same day cancellation`;
    } else {
        refundPct = noPct;
        policy = noPct > 0 ? `No refund (${noPct}%) — booking time has passed` : 'No refund — booking time has passed';
    }

    return {
        refundPct,
        refundAmount: parseFloat(((totalCost * refundPct) / 100).toFixed(2)),
        totalCost,
        policy,
        eligible: refundPct > 0,
    };
});

const openRescheduleModal = () => {
    const currentDate = form.booking_date ? new Date(form.booking_date) : new Date();
    rescheduleMonth.value = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    showRescheduleModal.value = true;
};

const selectRescheduleDate = (date: Date) => {
    if (isLockedDate(date)) return;
    form.booking_date = formatDateToISO(date);
    showRescheduleModal.value = false;
};

const rescheduleMonthName = computed(() => {
    return rescheduleMonth.value.toLocaleString('default', { month: 'long', year: 'numeric' });
});

const rescheduleDaysInMonth = computed(() => {
    const year = rescheduleMonth.value.getFullYear();
    const month = rescheduleMonth.value.getMonth();
    const date = new Date(year, month, 1);
    const days = [];
    while (date.getMonth() === month) {
        days.push(new Date(date));
        date.setDate(date.getDate() + 1);
    }
    return days;
});

const prevRescheduleMonth = () => {
    rescheduleMonth.value = new Date(rescheduleMonth.value.getFullYear(), rescheduleMonth.value.getMonth() - 1, 1);
};

const nextRescheduleMonth = () => {
    rescheduleMonth.value = new Date(rescheduleMonth.value.getFullYear(), rescheduleMonth.value.getMonth() + 1, 1);
};

const isRescheduleSelected = (date: Date) => {
    return form.booking_date === formatDateToISO(date);
};

const confirmCancelRefund = () => {
    if (!editingBookingId.value) return;
    const bookingId = editingBookingId.value;
    cancelForm.post(route('bookings.cancel', bookingId), {
        onSuccess: () => {
            showCancelRefundModal.value = false;
            showModal.value = false;
            editingBookingId.value = null;
            resetForm();
        },
    });
};

const deleteBooking = (booking: any) => {
    bookingToDelete.value = booking;
    showDeleteConfirm.value = true;
};

const confirmDelete = () => {
    if (bookingToDelete.value) {
        form.delete(route('bookings.destroy', bookingToDelete.value.id), {
            onSuccess: () => {
                showDeleteConfirm.value = false;
                showModal.value = false;
                bookingToDelete.value = null;
            },
        });
    }
};

const approveForm = useForm({});
const rejectForm = useForm({});
const paymentForm = useForm({});
const cancelForm = useForm({});

const approveBooking = (id: number) => {
    approveForm.post(route('bookings.approve', id));
};

const rejectBooking = (id: number) => {
    rejectForm.post(route('bookings.reject', id));
};

const togglePaymentStatus = (id: number) => {
    paymentForm.post(route('bookings.toggle-payment', id));
};

const monthName = computed(() => {
    return currentMonth.value.toLocaleString('default', { month: 'long', year: 'numeric' });
});

const isToday = (date: Date) => {
    return formatDateToISO(new Date()) === formatDateToISO(date);
};

const isPastDate = (date: Date) => {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return d < now;
};

const isLockedDate = (date: Date) => {
    const allow = props.settings.allow_past_edits === '1' || props.settings.allow_past_edits === 'true';
    return isPastDate(date) && !allow;
};

const isSelected = (date: Date) => {
    return form.booking_date === formatDateToISO(date);
};

// Initialize with today's date
if (!form.booking_date) {
    form.booking_date = new Date().toISOString().split('T')[0];
}

const timeToMinutes = (timeStr: string, isEnd = false) => {
    if (!timeStr) return 0;
    const [h, m] = timeStr.split(':').map(Number);
    if (isEnd && h === 0 && m === 0) {
        return 1440;
    }
    return h * 60 + m;
};

// Slot-based Time selection helpers
const generatedSlots = computed(() => {
    if (!form.booking_date) return [];
    const avail = resolveAvailabilityForDate(form.booking_date);
    if (avail.is_closed || !avail.opening_time || !avail.closing_time) return [];

    const [oh, om] = avail.opening_time.split(':').map(Number);
    const [ch, cm] = avail.closing_time.split(':').map(Number);
    
    let closingHour = ch;
    if (ch === 0 && cm === 0) {
        closingHour = 24;
    }
    
    const slots = [];
    
    for (let h = oh; h < closingHour; h++) {
        const startStr = `${String(h).padStart(2, '0')}:00`;
        const endStr = `${String((h + 1) % 24).padStart(2, '0')}:00`;
        const displayLabel = `${formatTime12h(startStr)} - ${formatTime12h(endStr)}`;
        
        const [yStr, mStr, dStr] = form.booking_date.split('-').map(Number);
        const dateStr = formatDateToISO(new Date(yStr, mStr - 1, dStr));
        const todayStr = formatDateToISO(new Date());
        let isPast = false;
        if (dateStr === todayStr) {
            const now = new Date();
            const slotStartTime = new Date(`${dateStr}T${startStr}`);
            const gracePeriodMinutes = parseInt(props.settings.booking_expiration_grace_minutes || '20');
            const gracePeriodEnd = new Date(slotStartTime.getTime() + gracePeriodMinutes * 60 * 1000);
            isPast = now > gracePeriodEnd;
        } else if (dateStr < todayStr) {
            isPast = true;
        }

        const [y, m, d] = form.booking_date.split('-').map(Number);
        const bookingsToday = getBookingsForDate(new Date(y, m - 1, d));
        const bookingOnSlot = bookingsToday.find((b) => {
            if (!form.courts.includes(Number(b.court_number))) return false;
            if (b.status === 'rejected' || b.status === 'cancelled') return false;
            if (editingBookingId.value && b.id === editingBookingId.value) return false;
            
            const bStart = b.start_time.substring(0, 5);
            const bEnd = b.end_time.substring(0, 5);
            
            return bStart < endStr && bEnd > startStr;
        });

        const isBooked = !!bookingOnSlot;
        const isSelected = !!(
            form.hour &&
            form.end_hour &&
            timeToMinutes(startStr, false) >= timeToMinutes(sessionStartTime.value, false) &&
            timeToMinutes(endStr, true) <= timeToMinutes(sessionEndTime.value, true)
        );

        slots.push({
            start: startStr,
            end: endStr,
            label: displayLabel,
            isPast,
            isBooked,
            isSelected,
            booking: bookingOnSlot,
        });
    }
    return slots;
});

const selectSlot = (slot: any) => {
    if (slot.isPast || slot.isBooked) return;
    
    const startVal = form.hour && form.end_hour ? sessionStartTime.value : null;
    const endVal = form.hour && form.end_hour ? sessionEndTime.value : null;

    if (!startVal || !endVal) {
        setFormRange(slot.start, slot.end);
    } else {
        const slotStartMins = timeToMinutes(slot.start, false);
        const slotEndMins = timeToMinutes(slot.end, true);
        const startValMins = timeToMinutes(startVal, false);
        const endValMins = timeToMinutes(endVal, true);

        let newStart = startVal;
        let newEnd = endVal;

        if (slotStartMins < startValMins) {
            newStart = slot.start;
        } else if (slotEndMins > endValMins) {
            newEnd = slot.end;
        } else {
            if (slot.start === startVal && slot.end === endVal) {
                clearFormRange();
                return;
            } else {
                newStart = slot.start;
                newEnd = slot.end;
            }
        }

        const newStartMins = timeToMinutes(newStart, false);
        const newEndMins = timeToMinutes(newEnd, true);

        const hasUnavailable = generatedSlots.value.some(s => {
            const sStartMins = timeToMinutes(s.start, false);
            const sEndMins = timeToMinutes(s.end, true);
            if (sStartMins >= newStartMins && sEndMins <= newEndMins) {
                return s.isBooked || s.isPast;
            }
            return false;
        });

        if (hasUnavailable) {
            setFormRange(slot.start, slot.end);
        } else {
            setFormRange(newStart, newEnd);
        }
    }
};

const setFormRange = (startStr: string, endStr: string) => {
    const startPicker = parseTimeToPicker(startStr);
    form.hour = startPicker.h;
    form.minute = startPicker.m;
    form.ampm = startPicker.ampm;
    
    const endPicker = parseTimeToPicker(endStr);
    form.end_hour = endPicker.h;
    form.end_minute = endPicker.m;
    form.end_ampm = endPicker.ampm;
};

const clearFormRange = () => {
    form.hour = '';
    form.minute = '';
    form.ampm = '';
    form.end_hour = '';
    form.end_minute = '';
    form.end_ampm = '';
};

const selectFirstAvailableSlot = () => {
    const slots = generatedSlots.value;
    const available = slots.find(s => !s.isBooked && !s.isPast);
    if (available) {
        selectSlot(available);
    } else {
        form.hour = '';
        form.minute = '';
        form.ampm = '';
        form.end_hour = '';
        form.end_minute = '';
        form.end_ampm = '';
    }
};

watch(
    [() => form.booking_date, () => form.courts],
    () => {
        if (showModal.value && !editingBookingId.value) {
            selectFirstAvailableSlot();
        }
    },
    { deep: true }
);


const formatTime12h = (timeStr: string) => {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    let h = parseInt(parts[0]);
    const m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12; // the hour '0' should be '12'
    return `${h}:${m} ${ampm}`;
};

const calculatedDuration = computed(() => {
    if (!form.hour || !form.end_hour) return 1;

    const start = new Date(`2000-01-01T${sessionStartTime.value}`);
    const end = new Date(`2000-01-01T${sessionEndTime.value}`);

    if (end <= start) end.setDate(end.getDate() + 1);
    const diffMs = end.getTime() - start.getTime();
    return diffMs / (1000 * 60 * 60);
});

const sessionStartTime = computed(() => {
    let h = parseInt(form.hour);
    if (form.ampm === 'PM' && h < 12) h += 12;
    if (form.ampm === 'AM' && h === 12) h = 0;
    return `${String(h).padStart(2, '0')}:${form.minute}`;
});

const sessionEndTime = computed(() => {
    let h = parseInt(form.end_hour);
    if (form.end_ampm === 'PM' && h < 12) h += 12;
    if (form.end_ampm === 'AM' && h === 12) h = 0;
    return `${String(h).padStart(2, '0')}:${form.end_minute}`;
});

const isStartTimeValid = computed(() => {
    if (!form.booking_date) return true;
    const avail = resolveAvailabilityForDate(form.booking_date);
    if (avail.is_closed || !avail.opening_time) return false;
    const start = sessionStartTime.value;
    return start >= avail.opening_time;
});

const isSelectedTimeInPast = computed(() => {
    const dateStr = formatDateToISO(new Date(form.booking_date));
    const todayStr = formatDateToISO(new Date());
    if (dateStr !== todayStr) return false;

    const now = new Date();
    const slotStartTime = new Date(`${form.booking_date}T${sessionStartTime.value}`);
    const gracePeriodMinutes = parseInt(props.settings.booking_expiration_grace_minutes || '20');
    const gracePeriodEnd = new Date(slotStartTime.getTime() + gracePeriodMinutes * 60 * 1000);
    return now > gracePeriodEnd;
});

const isEndTimeValid = computed(() => {
    if (!form.booking_date) return true;
    const avail = resolveAvailabilityForDate(form.booking_date);
    if (avail.is_closed || !avail.closing_time || !avail.opening_time) return false;

    // Convert everything to absolute minutes from 00:00 for easier comparison
    const [sh, sm] = sessionStartTime.value.split(':').map(Number);
    const [eh, em] = sessionEndTime.value.split(':').map(Number);
    const [oh, om] = avail.opening_time.split(':').map(Number);
    const [ch, cm] = avail.closing_time.split(':').map(Number);
    const closingHour = ch === 0 && cm === 0 ? 24 : ch;

    const startMins = sh * 60 + sm;
    let endMins = eh * 60 + em;
    const openMins = oh * 60 + om;
    const closeMins = closingHour * 60 + cm;

    // If end is smaller than start, it means it's the next day
    if (endMins <= startMins) endMins += 1440;

    // If closing is smaller than opening, it means business closes the next day
    let adjustedCloseMins = closeMins;
    if (closeMins <= openMins) adjustedCloseMins += 1440;

    return endMins <= adjustedCloseMins;
});

const formatDateText = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'long',
        day: '2-digit',
        year: 'numeric',
    });
};

const formatApprovalDateTime = (value?: string | null) => {
    if (!value) return null;
    const dt = new Date(value);
    if (isNaN(dt.getTime())) return null;
    return dt.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const formatApprovalTimeOnly = (value?: string | null) => {
    if (!value) return null;
    const dt = new Date(value);
    if (isNaN(dt.getTime())) return null;
    return dt.toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit',
    });
};

const isTimeSlotOverlapping = computed(() => {
    if (!form.booking_date || form.courts.length === 0 || !sessionStartTime.value || !sessionEndTime.value) return false;

    // We need to check:
    // 1. Bookings on the current day
    // 2. Bookings on the PREVIOUS day that might spill into today
    // 3. Bookings on the NEXT day if our current booking spills into tomorrow

    const targetDate = new Date(form.booking_date);
    const prevDate = new Date(targetDate);
    prevDate.setDate(prevDate.getDate() - 1);
    const nextDate = new Date(targetDate);
    nextDate.setDate(nextDate.getDate() + 1);

    const relevantBookings = [...getBookingsForDate(prevDate), ...getBookingsForDate(targetDate), ...getBookingsForDate(nextDate)].filter(
        (b) => form.courts.includes(Number(b.court_number)) && b.status !== 'rejected',
    );

    const newStart = sessionStartTime.value;
    const newEnd = sessionEndTime.value;

    // Normalize new booking to absolute timestamps
    const sA = new Date(`${form.booking_date}T${newStart}`);
    const eA = new Date(`${form.booking_date}T${newEnd}`);
    if (eA <= sA) eA.setDate(eA.getDate() + 1);

    return relevantBookings.some((b) => {
        if (editingBookingId.value && b.id === editingBookingId.value) return false;

        const existingStart = b.start_time.substring(0, 5);
        const existingEnd = b.end_time.substring(0, 5);

        const sB = new Date(`${b.booking_date}T${existingStart}`);
        const eB = new Date(`${b.booking_date}T${existingEnd}`);
        if (eB <= sB) eB.setDate(eB.getDate() + 1);

        return sA < eB && eA > sB;
    });
});

const isDayFullyBooked = (date: Date) => {
    const dateStr = formatDateToISO(date);
    const avail = resolveAvailabilityForDate(dateStr);
    if (avail.is_closed) return true;
    const fullCourts = getFullCourtsForDate(date);
    const courtCount = parseInt(props.settings.court_count || '4');
    return fullCourts.length >= courtCount;
};

const getFullCourtsForDate = (date: Date) => {
    const dateStr = formatDateToISO(date);
    const avail = resolveAvailabilityForDate(dateStr);
    const courtCount = parseInt(props.settings.court_count || '4');
    if (avail.is_closed || !avail.opening_time || !avail.closing_time) {
        return Array.from({ length: courtCount }, (_, idx) => idx + 1);
    }
    const dayBookings = getBookingsForDate(date).filter((b) => b.status !== 'rejected');

    // Calculate operational window for one court
    const [oh, om] = avail.opening_time.split(':').map(Number);
    const [ch, cm] = avail.closing_time.split(':').map(Number);
    let closingHour = ch;
    if (ch < oh || (ch === oh && cm < om)) {
        closingHour += 24;
    } else if (ch === 0 && cm === 0) {
        closingHour = 24;
    }
    const windowHours = Math.max(0, closingHour + cm / 60 - (oh + om / 60));

    const fullCourts = [];

    for (let i = 1; i <= courtCount; i++) {
        const courtBookings = dayBookings.filter((b) => Number(b.court_number) === i);
        const bookedHours = courtBookings.reduce((total, booking) => {
            const start = new Date(`2000-01-01T${booking.start_time}`);
            const end = new Date(`2000-01-01T${booking.end_time}`);
            if (end <= start) end.setDate(end.getDate() + 1);
            return total + (end.getTime() - start.getTime()) / (1000 * 60 * 60);
        }, 0);

        if (bookedHours >= windowHours) {
            fullCourts.push(i);
        }
    }

    return fullCourts;
};
const isSelectedCourtFull = computed(() => {
    if (!form.booking_date || form.courts.length === 0) return false;
    const fullCourts = getFullCourtsForDate(new Date(form.booking_date));
    // During editing, we shouldn't block the court the booking is already on
    if (editingBookingId.value) {
        const originalBooking = props.bookings.find((b) => b.id === editingBookingId.value);
        if (originalBooking && form.courts.includes(Number(originalBooking.court_number))) {
            return false;
        }
    }
    return form.courts.some(c => fullCourts.includes(c));
});


// Court Scorer Assignments
const courtCount = computed(() => parseInt(props.settings.court_count || '4') || 4);
const allCourtNumbers = computed(() => Array.from({ length: courtCount.value }, (_, i) => i + 1));
const localAssignments = ref<Record<number, number | null>>({});
const savingCourt = ref<number | null>(null);

const initAssignments = () => {
    const init: Record<number, number | null> = {};
    allCourtNumbers.value.forEach((n) => {
        init[n] = props.courtAssignments?.[n]?.scorer_id ?? null;
    });
    localAssignments.value = init;
};
initAssignments();

const assignScorer = (courtNumber: number) => {
    savingCourt.value = courtNumber;
    router.post(
        '/court-assignments',
        {
            court_number: courtNumber,
            scorer_id: localAssignments.value[courtNumber] ?? null,
            assignment_date: new Date().toISOString().slice(0, 10),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                const scorerName = props.scorers?.find((s) => s.id === localAssignments.value[courtNumber])?.name;
                if (scorerName) {
                    triggerToast(`Court ${courtNumber} assigned to ${scorerName} successfully!`);
                } else {
                    triggerToast(`Court ${courtNumber} assignment cleared successfully!`);
                }
            },
            onError: () => {
                triggerToast('Failed to assign scorer. Please try again.');
            },
            onFinish: () => {
                savingCourt.value = null;
            },
        },
    );
};

const copyBookingLink = async () => {
    const venueName = page.props.currentVenue?.name;
    const bookingUrl = venueName ? route('book.venue', { venue: venueName }) : route('book');

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(bookingUrl);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = bookingUrl;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }

        triggerToast('Booking page link copied.');
    } catch {
        triggerToast('Failed to copy link.');
    }
};
</script>

<template>
    <Head title="Court Bookings" />

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

        <div class="flex flex-col bg-slate-50/50 dark:bg-[#0a0a0a] xl:h-[calc(100vh-64px)] xl:overflow-hidden">
            <div class="flex-1 p-3 sm:p-4 lg:p-6 xl:overflow-hidden">
                <div class="grid grid-cols-1 gap-4 sm:gap-6 xl:h-full xl:grid-cols-12 xl:overflow-hidden">
                    <!-- LEFT SIDE: Header + Schedule/Agenda (4 Cols) -->
                    <div class="flex min-h-0 flex-col xl:col-span-4 xl:h-full xl:overflow-hidden">
                        <!-- Header moved inside -->
                        <div>
                            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white sm:text-3xl">Court Bookings</h1>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                Manage your sessions and track court availability.
                            </p>
                        </div>

                        <!-- Action Triggers -->
                        <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button
                                @click="showCourtAssignmentModal = true"
                                class="btn-heading flex w-full items-center justify-between gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-xs tracking-wide text-blue-700 transition-all hover:bg-blue-100 dark:border-green-800 dark:bg-green-900/15 dark:text-green-300 dark:hover:bg-green-900/25"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <UserIcon class="h-3.5 w-3.5 shrink-0" />
                                    <span class="truncate">Court Scorer Assignments — Today</span>
                                </div>
                                <ChevronRight class="h-3.5 w-3.5 shrink-0 opacity-60" />
                            </button>

                            <button
                                @click="copyBookingLink"
                                class="btn-heading flex w-full items-center justify-center gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-xs tracking-wide text-indigo-700 transition-all hover:bg-indigo-100 dark:border-green-800 dark:bg-green-900/15 dark:text-green-300 dark:hover:bg-green-900/25"
                            >
                                <div class="flex min-w-0 items-center justify-center gap-2">
                                    <Link2 class="h-3.5 w-3.5 shrink-0" />
                                    <span class="whitespace-nowrap">Booking Page Link</span>
                                </div>
                            </button>
                        </div>

                        <div class="mb-4">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-heading flex items-center text-xl font-bold text-slate-900 dark:text-white">
                                    <Clock class="mr-3 h-5 w-5 text-blue-600 dark:text-green-400" />
                                    {{ formatDateText(form.booking_date) }}
                                </h3>
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <button
                                            @click="showCourtDropdown = !showCourtDropdown"
                                            class="flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wider text-slate-700 transition-all hover:border-blue-300 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-slate-300 dark:hover:border-green-500/40"
                                            :class="
                                                viewMode === 'court'
                                                    ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-green-700 dark:bg-green-900/20 dark:text-green-400'
                                                    : ''
                                            "
                                        >
                                            <MapPin class="mr-2 h-4 w-4" />
                                            <span v-if="selectedCourt === 'all'">Court</span>
                                            <span v-else>C{{ selectedCourt }}</span>
                                            <ChevronDown class="ml-2 h-3.5 w-3.5" />
                                        </button>
                                        <div
                                            v-if="showCourtDropdown"
                                            class="absolute right-0 z-50 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg dark:border-slate-800 dark:bg-[#0f0f0f]"
                                        >
                                            <button
                                                @click="
                                                    viewMode = 'time';
                                                    selectedCourt = 'all';
                                                    showCourtDropdown = false;
                                                "
                                                class="w-full px-4 py-2.5 text-left text-xs font-bold text-slate-600 transition-all hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]"
                                            >
                                                All (Time View)
                                            </button>
                                            <div class="border-t border-slate-100 dark:border-slate-700"></div>
                                            <button
                                                v-for="n in parseInt(props.settings.court_count || '4')"
                                                :key="n"
                                                @click="
                                                    viewMode = 'court';
                                                    selectedCourt = n;
                                                    showCourtDropdown = false;
                                                "
                                                class="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-xs font-bold transition-all hover:bg-blue-50 dark:hover:bg-green-900/20"
                                                :class="
                                                    selectedCourt === n
                                                        ? 'bg-blue-50 text-blue-600 dark:bg-green-900/20 dark:text-green-400'
                                                        : 'text-slate-600 dark:text-slate-300'
                                                "
                                            >
                                                <span>Court {{ n }}</span>
                                                <span class="flex items-center gap-1.5">
                                                    <span
                                                        v-if="courtIsBooking(n)"
                                                        class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide text-blue-700 dark:bg-green-900/30 dark:text-green-300"
                                                    >
                                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500 dark:bg-green-500"></span>Booked
                                                    </span>
                                                    <span
                                                        v-if="courtIsWalkin(n)"
                                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                                    >
                                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Walk-in
                                                    </span>
                                                    <span
                                                        v-if="courtIsBoth(n)"
                                                        class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide text-violet-700 dark:bg-violet-900/30 dark:text-violet-300"
                                                    >
                                                        <span class="h-1.5 w-1.5 rounded-full bg-violet-500"></span>Reclub
                                                    </span>
                                                </span>
                                            </button>
                                            <button
                                                @click="
                                                    viewMode = 'court';
                                                    selectedCourt = 'all';
                                                    showCourtDropdown = false;
                                                "
                                                class="w-full px-4 py-2.5 text-left text-xs font-bold transition-all hover:bg-blue-50 dark:hover:bg-green-900/20"
                                                :class="
                                                    selectedCourt === 'all' && viewMode === 'court'
                                                        ? 'bg-blue-50 text-blue-600 dark:bg-green-900/20 dark:text-green-400'
                                                        : 'text-slate-600 dark:text-slate-300'
                                                "
                                            >
                                                All Courts
                                            </button>
                                        </div>
                                    </div>
                                    <button
                                        v-if="!isLockedDate(new Date(form.booking_date))"
                                        @click="openBookingModal(new Date(form.booking_date))"
                                        class="flex items-center rounded-2xl bg-blue-600 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white transition-all hover:bg-blue-700 active:scale-95 dark:bg-green-600 dark:hover:bg-green-500"
                                    >
                                        <Plus class="mr-2 h-4 w-4" /> Book
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="custom-scrollbar min-h-0 flex-1 space-y-4 overflow-y-auto pr-2">
                            <!-- Empty state -->
                            <div
                                v-if="viewMode === 'time' && getTopBookingsForDate(new Date(form.booking_date)).length === 0"
                                class="clean-card border-2 border-dashed border-gray-300 p-10 text-center"
                            >
                                <CalendarIcon class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                                <p class="text-body">No bookings recorded for this day.</p>
                            </div>
                            <div
                                v-if="viewMode === 'court' && getTopBookingsForDate(new Date(form.booking_date)).length === 0"
                                class="clean-card border-2 border-dashed border-gray-300 p-10 text-center"
                            >
                                <CalendarIcon class="mx-auto mb-4 h-16 w-16 text-gray-400" />
                                <p class="text-body">No bookings recorded for this day.</p>
                            </div>

                            <!-- Time View -->
                            <template v-if="viewMode === 'time'">
                                <div
                                    v-for="booking in getTopBookingsForDate(new Date(form.booking_date))"
                                    :key="booking.id"
                                    class="clean-card-hover group relative mb-4 cursor-pointer border-l-4 p-4 transition-all last:mb-0"
                                    :class="[
                                        booking.status === 'pending'
                                            ? 'border-l-amber-500'
                                            : booking.status === 'approved'
                                              ? 'border-l-emerald-500'
                                              : booking.status === 'cancelled'
                                                ? 'border-l-slate-300 opacity-60'
                                                : booking.status === 'rejected'
                                                  ? 'border-l-rose-500'
                                                  : 'border-l-primary',
                                    ]"
                                >
                                    <!-- Top row: info + price -->
                                    <div class="mb-3 flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-1.5 flex min-w-0 items-center gap-2 text-blue-600 dark:text-green-400">
                                                <Clock class="h-4 w-4 shrink-0" />
                                                <span class="truncate text-sm font-black"
                                                    >{{ formatTime12h(booking.start_time) }} – {{ formatTime12h(booking.end_time) }}</span
                                                >
                                            </div>
                                            <p class="mb-1.5 text-sm font-bold text-slate-900 dark:text-white">{{ booking.lead_name }}</p>
                                            <div class="space-y-1">
                                                <div class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
                                                    <span
                                                        class="flex items-center rounded-lg bg-blue-50 px-2 py-0.5 text-[10px] font-black uppercase text-blue-600 dark:bg-green-900/20 dark:text-green-400"
                                                        >C{{ booking.court_number }}</span
                                                    >
                                                    <span
                                                        class="flex items-center rounded-lg px-2 py-0.5 text-[10px] font-black uppercase"
                                                        :class="
                                                            booking.type === 'walk-in'
                                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                                                : booking.type === 'reclub'
                                                                  ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'
                                                                  : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                                        "
                                                        >{{ booking.type || 'booking' }}</span
                                                    >
                                                    <span
                                                        class="flex items-center rounded-lg px-2 py-0.5 text-[10px] font-black uppercase"
                                                        :class="
                                                            booking.client_type === 'member'
                                                                ? 'bg-blue-100 text-blue-700 dark:bg-green-900/30 dark:text-green-300'
                                                                : 'bg-slate-200 text-slate-700 dark:bg-[#1a1a1a] dark:text-slate-300'
                                                        "
                                                        >{{ booking.client_type === 'member' ? 'Member' : 'Non-member' }}</span
                                                    >
                                                    <span
                                                        v-if="booking.status === 'pending'"
                                                        class="flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase text-amber-600 dark:border-amber-800 dark:bg-amber-900/20"
                                                        ><AlertCircle class="h-3 w-3" /> Pending</span
                                                    >
                                                    <span
                                                        v-if="booking.status === 'approved'"
                                                        class="flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-600 dark:border-emerald-800 dark:bg-emerald-900/20"
                                                        ><CheckCircle class="h-3 w-3" /> Approved</span
                                                    >
                                                    <span
                                                        v-if="booking.status === 'approved' && booking.approver?.name"
                                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 dark:text-emerald-300 sm:text-[11px]"
                                                    >
                                                        By {{ booking.approver.name }}
                                                        <span v-if="formatApprovalDateTime(booking.approved_at)" class="hidden opacity-80 sm:inline"
                                                            >• {{ formatApprovalDateTime(booking.approved_at) }}</span
                                                        >
                                                        <span v-if="formatApprovalTimeOnly(booking.approved_at)" class="opacity-80 sm:hidden"
                                                            >• {{ formatApprovalTimeOnly(booking.approved_at) }}</span
                                                        >
                                                    </span>
                                                    <span
                                                        v-if="booking.status === 'rejected'"
                                                        class="flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-black uppercase text-rose-600 dark:border-rose-800 dark:bg-rose-900/20"
                                                        ><XCircle class="h-3 w-3" /> Rejected</span
                                                    >
                                                    <span
                                                        v-if="booking.status === 'cancelled'"
                                                        class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-400 line-through dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500"
                                                        ><XCircle class="h-3 w-3" /> Cancelled</span
                                                    >
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                                                    <span
                                                        v-if="booking.payment_status === 'paid'"
                                                        class="flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-black uppercase text-indigo-600 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                                                        ><DollarSign class="h-3 w-3" /> Paid</span
                                                    >
                                                    <span class="flex items-center text-xs"
                                                        ><Users class="mr-1 h-3.5 w-3.5" /> {{ booking.player_count }} players</span
                                                    >
                                                    <span class="flex items-center whitespace-nowrap text-xs"
                                                        ><MapPin class="mr-1 h-3.5 w-3.5" /> {{ booking.lead_address || 'N/A' }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        <div class="w-20 shrink-0 text-right sm:w-auto">
                                            <div class="text-lg font-black text-slate-900 dark:text-white sm:text-xl">₱{{ booking.total_cost }}</div>
                                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Fee</span>
                                            <div v-if="booking.receipt_photo" class="mt-1.5">
                                                <button
                                                    @click.stop="viewingReceipt = '/storage/' + booking.receipt_photo"
                                                    class="flex items-center gap-1 rounded-lg bg-blue-50 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-blue-600 transition-all hover:bg-blue-600 hover:text-white dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-600"
                                                >
                                                    <Eye class="h-3 w-3" /> Receipt
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Bottom row: action buttons (full width) -->
                                    <div v-if="!isLockedDate(new Date(booking.booking_date))" class="flex flex-wrap gap-2">
                                        <template v-if="booking.status === 'pending'">
                                            <button
                                                @click="
                                                    $event.stopPropagation();
                                                    approveBooking(booking.id);
                                                "
                                                class="flex items-center gap-1.5 rounded-xl bg-emerald-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-emerald-700 transition-all hover:bg-emerald-600 hover:text-white dark:bg-emerald-900/30 dark:text-emerald-400"
                                            >
                                                <CheckCircle class="h-3.5 w-3.5" /> Approve
                                            </button>
                                            <button
                                                @click="
                                                    $event.stopPropagation();
                                                    rejectBooking(booking.id);
                                                "
                                                class="flex items-center gap-1.5 rounded-xl bg-rose-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-rose-700 transition-all hover:bg-rose-600 hover:text-white dark:bg-rose-900/30 dark:text-rose-400"
                                            >
                                                <XCircle class="h-3.5 w-3.5" /> Reject
                                            </button>
                                        </template>
                                        <template v-if="booking.status === 'approved' && isAdmin">
                                            <button
                                                @click="
                                                    $event.stopPropagation();
                                                    togglePaymentStatus(booking.id);
                                                "
                                                class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                                :class="
                                                    booking.payment_status === 'paid'
                                                        ? 'bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-600'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-indigo-600 hover:text-white dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-green-600'
                                                "
                                            >
                                                <DollarSign class="h-3.5 w-3.5" /> {{ booking.payment_status === 'paid' ? 'Mark Unpaid' : 'Mark Paid' }}
                                            </button>
                                        </template>
                                        <div class="ml-auto flex gap-2">
                                            <button
                                                @click="editBooking(booking)"
                                                class="rounded-xl bg-slate-100 p-1.5 text-slate-600 transition-all hover:bg-blue-600 hover:text-white dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-green-600"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </button>
                                            <button
                                                v-if="isAdmin"
                                                @click="deleteBooking(booking)"
                                                class="rounded-xl bg-slate-100 p-1.5 text-slate-600 transition-all hover:bg-red-600 hover:text-white dark:bg-slate-800 dark:text-slate-400"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Court View -->
                            <template v-if="viewMode === 'court'">
                                <div
                                    v-for="(courtBookings, courtNum) in bookingsByCourt"
                                    :key="courtNum"
                                    v-show="selectedCourt === 'all' || Number(courtNum) === selectedCourt"
                                    class="mb-4"
                                >
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-lg bg-blue-600 px-3 py-1 text-[11px] font-black uppercase tracking-widest text-white dark:bg-green-600"
                                            >C{{ courtNum }}</span
                                        >
                                        <span class="text-xs font-bold text-slate-400"
                                            >{{ courtBookings.length }} booking{{ courtBookings.length !== 1 ? 's' : '' }}</span
                                        >
                                        <span
                                            v-if="courtIsBooking(Number(courtNum))"
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-blue-700 dark:bg-green-900/30 dark:text-green-300"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500 dark:bg-green-500"></span>Booking
                                        </span>
                                        <span
                                            v-if="courtIsWalkin(Number(courtNum))"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Walk-in
                                        </span>
                                        <span
                                            v-if="courtIsBoth(Number(courtNum))"
                                            class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-violet-700 dark:bg-violet-900/30 dark:text-violet-300"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-violet-500"></span>Reclub
                                        </span>
                                    </div>
                                    <div v-if="courtBookings.length === 0" class="pl-1 text-xs italic text-slate-400">No bookings on this court.</div>
                                    <div
                                        v-for="booking in courtBookings"
                                        :key="booking.id"
                                        class="clean-card-hover group relative mb-3 cursor-pointer border-l-4 p-3 transition-all last:mb-0"
                                        :class="[
                                            booking.status === 'pending'
                                                ? 'border-l-amber-500'
                                                : booking.status === 'approved'
                                                  ? 'border-l-emerald-500'
                                                  : booking.status === 'cancelled'
                                                    ? 'border-l-slate-300 opacity-60'
                                                    : booking.status === 'rejected'
                                                      ? 'border-l-rose-500'
                                                      : 'border-l-primary',
                                        ]"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="mb-1 flex items-center gap-2 text-blue-600 dark:text-green-400">
                                                    <Clock class="h-4 w-4 shrink-0" />
                                                    <span class="whitespace-nowrap text-sm font-black"
                                                        >{{ formatTime12h(booking.start_time) }} - {{ formatTime12h(booking.end_time) }}</span
                                                    >
                                                </div>
                                                <p class="mb-1 text-sm font-bold text-slate-900 dark:text-white">{{ booking.lead_name }}</p>
                                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                                    <span
                                                        class="flex items-center rounded-lg px-2 py-0.5 text-[10px] font-black uppercase"
                                                        :class="
                                                            booking.type === 'walk-in'
                                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                                                : booking.type === 'reclub'
                                                                  ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'
                                                                  : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                                        "
                                                        >{{ booking.type || 'booking' }}</span
                                                    >
                                                    <span
                                                        class="flex items-center rounded-lg px-2 py-0.5 text-[10px] font-black uppercase"
                                                        :class="
                                                            booking.client_type === 'member'
                                                                ? 'bg-blue-100 text-blue-700 dark:bg-green-900/30 dark:text-green-300'
                                                                : 'bg-slate-200 text-slate-700 dark:bg-[#1a1a1a] dark:text-slate-300'
                                                        "
                                                        >{{ booking.client_type === 'member' ? 'Member' : 'Non-member' }}</span
                                                    >
                                                    <span
                                                        v-if="booking.payment_status === 'paid'"
                                                        class="flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-black uppercase text-indigo-600 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                                                        ><DollarSign class="h-3 w-3" /> Paid</span
                                                    >
                                                    <span
                                                        v-if="booking.status === 'pending'"
                                                        class="flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase text-amber-600 dark:border-amber-800 dark:bg-amber-900/20"
                                                        ><AlertCircle class="h-3 w-3" /> Pending</span
                                                    >
                                                    <span
                                                        v-if="booking.status === 'approved'"
                                                        class="flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-600 dark:border-emerald-800 dark:bg-emerald-900/20"
                                                        ><CheckCircle class="h-3 w-3" /> Approved</span
                                                    >
                                                    <span
                                                        v-if="booking.status === 'approved' && booking.approver?.name"
                                                        class="flex items-center gap-1 rounded-lg border border-emerald-200/70 bg-emerald-50/70 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-900/20 dark:text-emerald-300"
                                                    >
                                                        By {{ booking.approver.name }}
                                                        <span v-if="formatApprovalDateTime(booking.approved_at)" class="opacity-80"
                                                            >• {{ formatApprovalDateTime(booking.approved_at) }}</span
                                                        >
                                                    </span>
                                                    <span
                                                        v-if="booking.status === 'rejected'"
                                                        class="flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-black uppercase text-rose-600 dark:border-rose-800 dark:bg-rose-900/20"
                                                        ><XCircle class="h-3 w-3" /> Rejected</span
                                                    >
                                                    <span
                                                        v-if="booking.status === 'cancelled'"
                                                        class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-400 line-through dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500"
                                                        ><XCircle class="h-3 w-3" /> Cancelled</span
                                                    >
                                                    <span class="flex items-center"
                                                        ><Users class="mr-1 h-3.5 w-3.5" /> {{ booking.player_count }}</span
                                                    >
                                                    <span class="flex items-center whitespace-nowrap"
                                                        ><MapPin class="mr-1 h-3.5 w-3.5" /> {{ booking.lead_address || 'N/A' }}</span
                                                    >
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 flex-col items-end text-right">
                                                <div class="text-lg font-black text-slate-900 dark:text-white">₱{{ booking.total_cost }}</div>
                                                <button
                                                    v-if="booking.receipt_photo"
                                                    @click.stop="viewingReceipt = '/storage/' + booking.receipt_photo"
                                                    class="mt-1 flex items-center gap-1 rounded-lg bg-blue-50 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-blue-600 transition-all hover:bg-blue-600 hover:text-white dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-600"
                                                >
                                                    <Eye class="h-3 w-3" /> Receipt
                                                </button>
                                                <div
                                                    v-if="!isLockedDate(new Date(booking.booking_date))"
                                                    class="mt-2 flex flex-wrap justify-end gap-1.5"
                                                >
                                                    <template v-if="booking.status === 'pending'">
                                                        <button
                                                            @click="
                                                                $event.stopPropagation();
                                                                approveBooking(booking.id);
                                                            "
                                                            class="rounded-lg bg-emerald-100 p-1.5 text-emerald-700 transition-all hover:bg-emerald-600 hover:text-white dark:bg-emerald-900/30 dark:text-emerald-400"
                                                        >
                                                            <CheckCircle class="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            @click="
                                                                $event.stopPropagation();
                                                                rejectBooking(booking.id);
                                                            "
                                                            class="rounded-lg bg-rose-100 p-1.5 text-rose-700 transition-all hover:bg-rose-600 hover:text-white dark:bg-rose-900/30 dark:text-rose-400"
                                                        >
                                                            <XCircle class="h-3.5 w-3.5" />
                                                        </button>
                                                    </template>
                                                    <template v-if="booking.status === 'approved' && isAdmin">
                                                        <button
                                                            @click="
                                                                $event.stopPropagation();
                                                                togglePaymentStatus(booking.id);
                                                            "
                                                            class="rounded-lg p-1.5 transition-all"
                                                            :class="
                                                                booking.payment_status === 'paid'
                                                                    ? 'bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-600'
                                                                    : 'bg-slate-100 text-slate-600 hover:bg-indigo-600 hover:text-white dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-green-600'
                                                            "
                                                            :title="booking.payment_status === 'paid' ? 'Mark Unpaid' : 'Mark Paid'"
                                                        >
                                                            <DollarSign class="h-3.5 w-3.5" />
                                                        </button>
                                                    </template>
                                                    <button
                                                        @click="editBooking(booking)"
                                                        class="rounded-lg bg-slate-100 p-1.5 text-slate-600 transition-all hover:bg-blue-600 hover:text-white dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-green-600"
                                                    >
                                                        <Edit class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        v-if="isAdmin"
                                                        @click="deleteBooking(booking)"
                                                        class="rounded-lg bg-slate-100 p-1.5 text-slate-600 transition-all hover:bg-red-600 hover:text-white dark:bg-slate-800 dark:text-slate-400"
                                                    >
                                                        <Trash2 class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- RIGHT SIDE: Calendar (8 Cols) -->
                    <div class="flex flex-col xl:col-span-8 xl:h-full xl:overflow-hidden">
                        <div
                            class="flex flex-1 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-clean transition-all duration-200 dark:border-slate-800 dark:bg-[#0f0f0f]"
                        >
                            <!-- Calendar Header -->
                            <div
                                class="flex flex-col justify-between gap-4 border-b border-gray-200 bg-gray-50 p-4 dark:border-slate-800 dark:bg-[#0a0a0a] sm:flex-row sm:items-center"
                            >
                                <h2 class="text-heading flex items-center">
                                    <CalendarIcon class="mr-2 h-5 w-5 text-primary sm:mr-3 sm:h-6 sm:w-6" />
                                    {{ monthName }}
                                </h2>
                                <div class="flex items-center gap-4">
                                    <div class="flex space-x-2">
                                        <button @click="prevMonth" class="btn-clean p-2">
                                            <ChevronLeft class="h-4 w-4" />
                                        </button>
                                        <button @click="nextMonth" class="btn-clean p-2">
                                            <ChevronRight class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Calendar Grid -->
                            <div class="custom-scrollbar flex-1 overflow-y-auto p-2 sm:p-3">
                                <!-- Day Headers: single letter on mobile, full on desktop -->
                                <div class="mb-2 grid grid-cols-7 gap-1 sm:mb-4 sm:gap-2">
                                    <div
                                        v-for="(day) in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
                                        :key="day"
                                        class="py-1 text-center text-[10px] font-black uppercase tracking-widest text-slate-400 sm:py-2 sm:text-xs"
                                    >
                                        <span class="sm:hidden">{{ day.charAt(0) }}</span>
                                        <span class="hidden sm:inline">{{ day }}</span>
                                    </div>

                                    <!-- Empty cells for month start offset -->
                                    <div v-for="n in daysInMonth[0].getDay()" :key="'empty-' + n" class="min-h-[52px] sm:min-h-[60px]"></div>

                                    <!-- Day Cells -->
                                    <div
                                        v-for="date in daysInMonth"
                                        :key="date.toISOString()"
                                        @click="resolveAvailabilityForDate(formatDateToISO(date)).is_closed ? null : selectDate(date)"
                                        class="group relative min-h-[52px] rounded border border-gray-200 p-1 transition-all dark:border-slate-700 sm:min-h-[60px] sm:p-1.5"
                                        :class="[
                                            resolveAvailabilityForDate(formatDateToISO(date)).is_closed
                                                ? 'bg-rose-50/40 border-rose-100 cursor-not-allowed dark:bg-rose-950/10 dark:border-rose-900/30'
                                                : 'cursor-pointer dark:hover:border-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:border-primary',
                                            isSelected(date) && !resolveAvailabilityForDate(formatDateToISO(date)).is_closed
                                                ? 'bg-primary-100 dark:bg-primary-900/30 dark:border-primary-600 border-primary ring-2 ring-primary'
                                                : 'bg-white dark:bg-[#0a0a0a]',
                                            isToday(date) && !isSelected(date)
                                                ? 'border-accent ring-1 ring-accent dark:border-green-500 dark:ring-green-500'
                                                : '',
                                            isDayFullyBooked(date) && !resolveAvailabilityForDate(formatDateToISO(date)).is_closed ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' : '',
                                            isLockedDate(date) ? 'opacity-60 grayscale' : '',
                                        ]"
                                    >
                                        <div class="flex items-start justify-between">
                                            <span
                                                class="text-sm font-black sm:text-base"
                                                :class="isToday(date) ? 'text-blue-600 dark:text-green-400' : 'text-slate-800 dark:text-slate-200'"
                                            >
                                                {{ date.getDate() }}
                                            </span>
                                            <!-- Today badge on desktop, dot on mobile -->
                                            <div
                                                v-if="isToday(date)"
                                                class="hidden rounded-full bg-blue-600 px-2 py-0.5 text-[8px] font-black uppercase tracking-tighter text-white dark:bg-green-600 sm:block"
                                            >
                                                Today
                                            </div>
                                            <div
                                                v-else-if="isDayFullyBooked(date) && !resolveAvailabilityForDate(formatDateToISO(date)).is_closed"
                                                class="hidden rounded-full bg-red-600 px-2 py-0.5 text-[8px] font-black uppercase tracking-tighter text-white sm:block"
                                            >
                                                Full
                                            </div>
                                        </div>

                                        <!-- If closed, show a centered big "CLOSE" label and hide other details -->
                                        <div v-if="resolveAvailabilityForDate(formatDateToISO(date)).is_closed" class="mt-2 flex flex-col items-center justify-center text-center px-1 overflow-hidden w-full">
                                            <span class="rounded bg-rose-100 dark:bg-rose-950 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-rose-600 dark:text-rose-400 max-w-full overflow-hidden whitespace-nowrap block w-full relative">
                                                <marquee scrollamount="3" behavior="scroll" direction="left" class="w-full">
                                                    {{ resolveAvailabilityForDate(formatDateToISO(date)).close_reason ? `CLOSE: ${resolveAvailabilityForDate(formatDateToISO(date)).close_reason}` : 'CLOSE' }}
                                                </marquee>
                                            </span>
                                        </div>

                                        <template v-else>
                                            <!-- Court Status Badges (desktop only) -->
                                            <div class="mt-2 hidden flex-row flex-wrap gap-1 sm:flex">
                                                <div
                                                    v-for="courtNum in getFullCourtsForDate(date)"
                                                    :key="courtNum"
                                                    class="w-fit rounded-lg bg-red-600 px-1.5 py-0.5 text-[7px] font-black uppercase text-white"
                                                >
                                                    C{{ courtNum }} FULL
                                                </div>
                                            </div>

                                            <!-- Weather: compact on mobile, full on desktop -->
                                            <div v-if="getWeatherForDate(date)" class="mt-0.5 flex items-center gap-0.5 sm:mt-1">
                                                <span class="text-[9px] leading-none sm:text-[11px]">{{
                                                    weatherInfo(getWeatherForDate(date)!.code).emoji
                                                }}</span>
                                                <span
                                                    class="text-[8px] font-black leading-none sm:text-[9px]"
                                                    :class="weatherInfo(getWeatherForDate(date)!.code).color"
                                                    >{{ getWeatherForDate(date)!.temp_max }}°</span
                                                >
                                            </div>

                                            <!-- Booking status dots -->
                                            <div v-if="getBookingsForDate(date).length > 0" class="mt-auto flex flex-col gap-0.5 pt-1">
                                                <!-- Desktop: count + per-booking dots -->
                                                <div class="hidden items-center gap-1.5 sm:flex">
                                                    <span class="text-[10px] font-black text-slate-500">{{ getBookingsForDate(date).length }}</span>
                                                    <div class="flex flex-wrap gap-[3px]">
                                                        <div
                                                            v-for="booking in getBookingsForDate(date)"
                                                            :key="booking.id"
                                                            class="h-2 w-2 rounded-full"
                                                            :class="[
                                                                booking.status === 'approved'
                                                                    ? 'bg-emerald-500'
                                                                    : booking.status === 'pending'
                                                                      ? 'bg-amber-400'
                                                                      : booking.status === 'rejected'
                                                                        ? 'bg-rose-500'
                                                                        : 'bg-slate-400',
                                                            ]"
                                                        ></div>
                                                    </div>
                                                </div>
                                                <!-- Mobile: one dot per status present -->
                                                <div class="flex items-center gap-1 sm:hidden">
                                                    <div
                                                        v-if="getBookingsForDate(date).some((b) => b.status === 'approved')"
                                                        class="h-2 w-2 rounded-full bg-emerald-500"
                                                    ></div>
                                                    <div
                                                        v-if="getBookingsForDate(date).some((b) => b.status === 'pending')"
                                                        class="h-2 w-2 rounded-full bg-amber-400"
                                                    ></div>
                                                    <div
                                                        v-if="getBookingsForDate(date).some((b) => b.status === 'rejected')"
                                                        class="h-2 w-2 rounded-full bg-rose-500"
                                                    ></div>
                                                    <span class="text-[9px] font-black text-slate-500">{{ getBookingsForDate(date).length }}</span>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Hover Plus Button (desktop only) -->
                                        <button
                                            v-if="!isLockedDate(date) && !resolveAvailabilityForDate(formatDateToISO(date)).is_closed"
                                            @click.stop="openBookingModal(date)"
                                            class="absolute bottom-3 right-3 hidden scale-90 transform rounded-xl bg-blue-600 p-2 text-white opacity-0 transition-all hover:bg-blue-700 group-hover:scale-100 group-hover:opacity-100 dark:bg-green-600 dark:hover:bg-green-500 sm:block"
                                        >
                                            <Plus class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Transition name="modal">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center sm:p-6">
                <div class="modal-backdrop" @click="showModal = false"></div>

                <div
                    class="relative flex max-h-[100dvh] w-full flex-col overflow-hidden rounded-t-[2.5rem] shadow-2xl duration-300 animate-in zoom-in-95 sm:max-h-[96vh] sm:max-w-md md:max-w-4xl sm:rounded-[2.5rem]"
                >
                    <!-- Header -->
                    <div
                        class="relative shrink-0 overflow-hidden bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 px-5 py-4 dark:from-green-600 dark:via-green-700 dark:to-emerald-800"
                    >
                        <div
                            class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 select-none text-[90px] font-black leading-none tracking-tighter text-white/10"
                        >
                            C{{ form.court_number }}
                        </div>
                        <div class="relative z-10 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border border-white/25 bg-white/20">
                                    <Plus v-if="!editingBookingId" class="h-4 w-4 text-white" />
                                    <Edit v-else class="h-4 w-4 text-white" />
                                </div>
                                <div>
                                    <h2 class="text-base font-black leading-tight tracking-tight text-white">
                                        {{ editingBookingId ? 'Update Session' : 'New Session' }}
                                    </h2>
                                    <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-widest text-blue-200 dark:text-green-200">
                                        {{ formatDateText(form.booking_date) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button
                                    v-if="editingBookingId"
                                    type="button"
                                    @click="openRescheduleModal"
                                    class="flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/15 px-2.5 py-1.5 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:bg-white/25"
                                >
                                    <CalendarIcon class="h-3.5 w-3.5" /><span class="hidden sm:inline">Reschedule</span>
                                </button>
                                <button
                                    @click="showModal = false"
                                    class="flex h-8 w-8 items-center justify-center rounded-xl border border-white/20 bg-white/15 text-white transition-all hover:bg-white/25"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="flex flex-1 flex-col overflow-hidden bg-white dark:bg-[#0f0f0f]">
                        <div class="flex-1 overflow-y-auto overflow-x-hidden p-3 sm:p-5">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                                <!-- COLUMN 1: TIME SLOT GRID (5 COLS ON DESKTOP) -->
                                <div class="space-y-3 md:col-span-6 lg:col-span-5">
                                    <div class="flex items-center gap-2">
                                        <Clock class="h-3.5 w-3.5 text-blue-500 dark:text-green-500" />
                                        <span class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Time & Schedule</span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-2">
                                        <button
                                            v-for="slot in generatedSlots"
                                            :key="slot.start"
                                            type="button"
                                            @click="selectSlot(slot)"
                                            :disabled="slot.isPast || slot.isBooked"
                                            class="flex flex-col items-center justify-center py-2 px-1 rounded-xl border text-center transition-all duration-150 relative h-auto min-h-[48px]"
                                            :class="[
                                                slot.isSelected
                                                    ? 'bg-blue-600 border-blue-600 text-white shadow-md shadow-blue-500/20 dark:bg-green-600 dark:border-green-600 dark:shadow-green-500/20'
                                                    : slot.isBooked
                                                      ? 'border-rose-100 bg-rose-50/30 text-rose-500 cursor-not-allowed opacity-75 dark:border-rose-950/20 dark:bg-rose-950/5 dark:text-rose-400/70'
                                                      : slot.isPast
                                                        ? 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed opacity-50 dark:border-slate-800/40 dark:bg-slate-900/10 dark:text-slate-500'
                                                        : 'border-emerald-200 bg-emerald-50/50 hover:bg-emerald-50 text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-950/10 dark:hover:bg-emerald-950/20 dark:text-emerald-400',
                                            ]"
                                        >
                                            <span class="text-[10px] font-black tracking-tight leading-none">{{ formatTime12h(slot.start).replace(':00', '') }}</span>
                                            <span class="text-[8px] font-bold text-slate-400 dark:text-slate-500 mt-0.5" :class="{'text-white/80 dark:text-white/80': slot.isSelected}">to {{ formatTime12h(slot.end).replace(':00', '') }}</span>
                                            
                                            <span 
                                                v-if="slot.isBooked && slot.booking && isAdmin" 
                                                class="text-[8px] font-black uppercase tracking-wider text-rose-600 dark:text-rose-400 mt-0.5 truncate max-w-full"
                                            >
                                                {{ slot.booking.lead_name }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <!-- COLUMN 2: OTHER FIELDS (7 COLS ON DESKTOP) -->
                                <div class="space-y-3.5 md:col-span-6 lg:col-span-7">
                                    <!-- Session Type Selection -->
                                    <div class="space-y-1">
                                        <p class="ml-0.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Session Type</p>
                                        <div class="grid grid-cols-3 gap-2">
                                            <button
                                                v-for="t in ['booking', 'walk-in', 'reclub']"
                                                :key="t"
                                                type="button"
                                                @click="form.type = t"
                                                class="rounded-xl border py-2 text-xs font-bold capitalize transition-all"
                                                :class="
                                                    form.type === t
                                                        ? t === 'walk-in'
                                                            ? 'bg-amber-600 border-amber-600 text-white dark:bg-amber-600'
                                                            : t === 'reclub'
                                                              ? 'bg-violet-600 border-violet-600 text-white dark:bg-violet-600'
                                                              : 'bg-blue-600 border-blue-600 text-white dark:bg-green-600 dark:border-green-600'
                                                        : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-slate-300'
                                                "
                                            >
                                                {{ t }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Court & Receipt -->
                                    <div class="grid grid-cols-1 items-start gap-2 min-[375px]:grid-cols-2">
                                        <div class="space-y-1">
                                            <p class="ml-0.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Court</p>
                                            <div class="relative">
                                                <button
                                                    type="button"
                                                    @click.stop="toggleTimeDropdown('court')"
                                                    class="flex h-[40px] w-full cursor-pointer items-center rounded-2xl border border-blue-200 bg-blue-50 pl-10 pr-3 text-left text-base font-black text-blue-600 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400 dark:focus:ring-green-500/20"
                                                >
                                                    <span class="mr-1 text-[9px] font-black uppercase text-blue-500 dark:text-green-500">Unit</span>
                                                    <span>{{ form.courts.map(c => 'C' + c).join(', ') }}</span>
                                                </button>
                                                <div
                                                    v-if="openTimeDropdown === 'court'"
                                                    class="absolute left-0 top-full z-50 mt-1 w-full min-w-[120px] rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-[#1a1a1a]"
                                                >
                                                    <div class="grid grid-cols-4 gap-1">
                                                        <button
                                                            v-for="n in parseInt(props.settings.court_count || '4')"
                                                            :key="n"
                                                            type="button"
                                                            @click.stop="toggleCourtSelection(n)"
                                                            class="rounded-lg py-1.5 text-center text-sm font-bold text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-[#2a2a2a]"
                                                            :class="form.courts.includes(n) ? 'bg-blue-600 text-white dark:bg-green-600' : ''"
                                                        >
                                                            C{{ n }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <p class="ml-0.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Proof of Receipt</p>
                                            <input type="file" accept="image/*" @change="handleReceiptChange" class="sr-only" id="admin-receipt-upload" />
                                            <label
                                                for="admin-receipt-upload"
                                                class="flex h-[40px] w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed transition-all"
                                                :class="
                                                    receiptError
                                                        ? 'border-red-400 bg-red-50 dark:bg-red-950/20'
                                                        : receiptPreview
                                                          ? 'border-blue-400 bg-blue-50 dark:bg-green-900/20'
                                                          : 'border-slate-200 bg-slate-50 hover:border-blue-400 dark:border-slate-700 dark:bg-[#0a0a0a] dark:hover:border-green-400'
                                                "
                                            >
                                                <template v-if="!receiptPreview">
                                                    <Upload class="mb-0.5 h-3 w-3 text-slate-400" />
                                                    <span class="text-[9px] font-bold text-slate-400">Upload photo</span>
                                                </template>
                                                <img v-else :src="receiptPreview" class="h-full w-full object-cover" />
                                            </label>
                                            <p v-if="receiptError" class="text-[9px] font-semibold leading-snug text-red-500">{{ receiptError }}</p>
                                        </div>
                                    </div>

                                    <div class="border-t border-dashed border-slate-200 dark:border-slate-800"></div>

                                    <!-- Cancellation & Refund (edit mode only) -->
                                    <div
                                        v-if="editingBookingId && form.status !== 'cancelled'"
                                        class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3"
                                        :class="
                                            refundInfo?.eligible
                                                ? 'border-rose-200 bg-rose-50 dark:border-rose-900/40 dark:bg-rose-950/20'
                                                : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-[#0a0a0a]'
                                        "
                                    >
                                        <div class="min-w-0 space-y-0.5">
                                            <p
                                                class="text-[9px] font-black uppercase tracking-widest"
                                                :class="refundInfo?.eligible ? 'text-rose-500' : 'text-slate-400'"
                                            >
                                                Cancellation &amp; Refund
                                            </p>
                                            <p v-if="refundInfo" class="text-[10px] leading-snug text-slate-500 dark:text-slate-400">
                                                {{ refundInfo.policy }}
                                            </p>
                                            <p
                                                v-if="refundInfo && refundInfo.eligible"
                                                class="text-[10px] font-bold"
                                                :class="refundInfo.refundPct === 100 ? 'text-emerald-500' : 'text-amber-500'"
                                            >
                                                {{ refundInfo.refundPct }}% &mdash; ₱{{ refundInfo.refundAmount }} refundable
                                            </p>
                                            <p
                                                v-if="refundInfo && !refundInfo.eligible"
                                                class="text-[9px] font-black uppercase tracking-widest text-slate-400"
                                            >
                                                &#x1F512; Window passed — no refund
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            :disabled="!refundInfo?.eligible"
                                            @click="refundInfo?.eligible && (showCancelRefundModal = true)"
                                            class="shrink-0 rounded-xl px-3 py-1.5 text-[9px] font-black uppercase tracking-widest transition-all"
                                            :class="
                                                refundInfo?.eligible
                                                    ? 'cursor-pointer bg-rose-500 text-white hover:bg-rose-600 active:scale-95'
                                                    : 'cursor-not-allowed bg-slate-200 text-slate-400 opacity-60 dark:bg-[#1a1a1a]'
                                            "
                                        >
                                            {{ refundInfo?.eligible ? 'Cancel Booking' : 'Not Eligible' }}
                                        </button>
                                    </div>

                                    <!-- Guest Details -->
                                    <div class="space-y-2.5">
                                        <div class="flex items-center gap-2">
                                            <UserIcon class="h-3.5 w-3.5 text-blue-500 dark:text-green-500" />
                                            <span class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Guest Details</span>
                                        </div>
                                        <div class="space-y-1.5">
                                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Booking For</p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <button
                                                    type="button"
                                                    @click="setBookingPartyMode('player')"
                                                    class="rounded-2xl border px-3 py-2.5 text-sm font-black transition-all"
                                                    :class="
                                                        bookingPartyMode === 'player'
                                                            ? 'border-blue-600 bg-blue-600 text-white dark:border-green-600 dark:bg-green-600'
                                                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-300 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-slate-300'
                                                    "
                                                >
                                                    Player
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="setBookingPartyMode('client')"
                                                    class="rounded-2xl border px-3 py-2.5 text-sm font-black transition-all"
                                                    :class="
                                                        bookingPartyMode === 'client'
                                                            ? 'border-blue-600 bg-blue-600 text-white dark:border-green-600 dark:bg-green-600'
                                                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-300 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-slate-300'
                                                    "
                                                >
                                                    Client
                                                </button>
                                            </div>
                                        </div>
                                        <div v-if="bookingPartyMode === 'player'" class="space-y-2">
                                            <div class="relative w-full" ref="playerDropdownRef">
                                                <input
                                                    v-model="playerSearchQuery"
                                                    type="text"
                                                    placeholder="Search registered player"
                                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm font-semibold text-slate-900 transition-all focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white"
                                                    :class="{ 'border-red-400 bg-red-50': form.errors.lead_name }"
                                                    @focus="showPlayerDropdown = true"
                                                />
                                                <UserIcon class="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                                <button
                                                    type="button"
                                                    @click.stop="showPlayerDropdown = !showPlayerDropdown"
                                                    class="absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 focus:outline-none"
                                                >
                                                    <ChevronDown
                                                        class="h-4 w-4 transition-transform duration-200"
                                                        :class="showPlayerDropdown ? 'rotate-180' : ''"
                                                    />
                                                </button>

                                                <!-- Dropdown Menu -->
                                                <div
                                                    v-if="showPlayerDropdown"
                                                    class="absolute left-0 right-0 z-50 mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-800 dark:bg-[#0a0a0a] w-full"
                                                >
                                                    <!-- Players List -->
                                                    <div class="max-h-60 overflow-y-auto custom-scrollbar">

                                                        <button
                                                            v-for="player in filteredPlayers"
                                                            :key="player.id"
                                                            type="button"
                                                            @click="selectPlayer(player)"
                                                            class="w-full rounded-xl px-3 py-2 text-left text-xs font-semibold transition-all hover:bg-slate-50 dark:hover:bg-[#111]"
                                                            :class="selectedPlayerId === player.id ? 'bg-blue-50 text-blue-600 dark:bg-green-950/20 dark:text-green-400' : 'text-slate-700 dark:text-slate-300'"
                                                        >
                                                            {{ player.user?.username || player.display_name }}
                                                        </button>
                                                        <div v-if="filteredPlayers.length === 0" class="px-3 py-2 text-center text-xs text-slate-400">
                                                            No players found
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-[10px] font-semibold text-slate-400">
                                                Select a registered player to auto-fill contact details and apply the correct rate.
                                            </p>
                                        </div>
                                        <div v-else class="relative">
                                            <UserIcon class="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                            <input
                                                v-model="form.lead_name"
                                                type="text"
                                                placeholder="Lead Guest Name"
                                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm font-semibold text-slate-900 transition-all placeholder:text-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/10"
                                                :class="{ 'border-red-400 bg-red-50': form.errors.lead_name }"
                                            />
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="relative">
                                                <Users class="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                                <input
                                                    v-model="form.player_count"
                                                    type="number"
                                                    min="1"
                                                    placeholder="Players"
                                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm font-semibold text-slate-900 transition-all placeholder:text-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/10"
                                                    :class="{ 'border-red-400': form.errors.player_count }"
                                                />
                                            </div>
                                            <div class="relative">
                                                <DollarSign class="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                                <input
                                                    v-model="form.cost_per_hour"
                                                    type="number"
                                                    placeholder="Rate / hr"
                                                    readonly
                                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm font-semibold text-slate-900 transition-all placeholder:text-slate-400 focus:outline-none dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white"
                                                    :class="[
                                                        { 'border-red-400': form.errors.cost_per_hour },
                                                        'cursor-not-allowed select-none opacity-70',
                                                    ]"
                                                />
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="relative">
                                                <MapPin class="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                                <input
                                                    v-model="form.lead_address"
                                                    type="text"
                                                    placeholder="Address"
                                                    :readonly="bookingPartyMode === 'player'"
                                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm font-semibold text-slate-900 transition-all placeholder:text-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/10"
                                                    :class="[bookingPartyMode === 'player' ? 'cursor-not-allowed opacity-80' : '', form.errors.lead_address ? 'border-red-400' : '']"
                                                />
                                            </div>
                                            <div class="relative">
                                                <Phone class="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                                <input
                                                    v-model="form.guest_phone"
                                                    type="tel"
                                                    inputmode="numeric"
                                                    pattern="[0-9]*"
                                                    maxlength="11"
                                                    placeholder="Phone"
                                                    :readonly="bookingPartyMode === 'player'"
                                                    @input="form.guest_phone = (form.guest_phone as string).replace(/\D/g, '').slice(0, 11)"
                                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm font-semibold text-slate-900 transition-all placeholder:text-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/10"
                                                    :class="[bookingPartyMode === 'player' ? 'cursor-not-allowed opacity-80' : '', form.errors.guest_phone ? 'border-red-400' : '']"
                                                />
                                            </div>
                                        </div>
                                        <InputError :message="form.errors.lead_name" />
                                        <InputError :message="form.errors.player_count" />
                                        <InputError :message="form.errors.cost_per_hour" />
                                        <InputError :message="form.errors.lead_address" />
                                        <InputError :message="form.errors.guest_phone" />
                                        <InputError :message="form.errors.start_time" />
                                        <InputError :message="form.errors.duration_hours" />
                                        <InputError :message="form.errors.receipt_photo" />
                                        <InputError :message="form.errors.type" />
                                        <InputError :message="form.errors.court_number" />
                                        <InputError :message="form.errors.booking_date" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Validation chips -->
                        <div
                            v-if="!isStartTimeValid || !isEndTimeValid || isSelectedCourtFull || isTimeSlotOverlapping || isSelectedCourtWalkin || isSelectedTimeInPast"
                            class="mb-3 space-y-1.5 px-3 sm:px-4"
                        >
                            <p
                                v-if="!isStartTimeValid"
                                class="flex items-center gap-1.5 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-[10px] font-bold text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-400"
                            >
                                <X class="h-3 w-3 shrink-0" /> Before opening ({{ formatTime12h(props.settings.opening_time) }})
                            </p>
                            <p
                                v-if="!isEndTimeValid"
                                class="flex items-center gap-1.5 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-[10px] font-bold text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-400"
                            >
                                <X class="h-3 w-3 shrink-0" /> Exceeds closing ({{ formatTime12h(props.settings.closing_time) }})
                            </p>
                            <p
                                v-if="isSelectedCourtFull"
                                class="flex items-center gap-1.5 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-[10px] font-bold text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-400"
                            >
                                <X class="h-3 w-3 shrink-0" /> Selected court(s) fully booked
                            </p>
                            <p
                                v-if="isTimeSlotOverlapping"
                                class="flex items-center gap-1.5 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-[10px] font-bold text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-400"
                            >
                                <X class="h-3 w-3 shrink-0" /> Time slot taken on selected court(s)
                            </p>
                            <p
                                v-if="isSelectedCourtWalkin"
                                class="flex items-center gap-1.5 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-[10px] font-bold text-amber-600 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-400"
                            >
                                <AlertCircle class="h-3 w-3 shrink-0" /> C{{ form.court_number }} is walk-in only
                            </p>
                            <p
                                v-if="isSelectedTimeInPast"
                                class="flex items-center gap-1.5 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-[10px] font-bold text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-400"
                            >
                                <X class="h-3 w-3 shrink-0" /> Error: Selected time slot has already passed for today.
                            </p>
                        </div>

                        <!-- Summary Footer -->
                        <div class="border-t border-slate-200 bg-slate-50 px-3 pb-4 pt-3 dark:border-slate-800 dark:bg-[#0a0a0a] sm:px-4">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div class="space-y-0.5">
                                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-blue-600 dark:text-green-400">Live Summary</p>
                                    <p class="text-lg font-black leading-none tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                                        {{ formatTime12h(sessionStartTime) }} <span class="font-bold text-slate-400">→</span>
                                        {{ formatTime12h(sessionEndTime) }}
                                    </p>
                                    <p class="text-[10px] font-semibold text-slate-400">{{ calculatedDuration }}h × ₱{{ bookingRate }}/hr × {{ form.courts.length }} court(s)</p>
                                    <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                                        {{ bookingPartyMode === 'player' ? 'Registered Player' : 'Client' }} • {{ displayedMembershipStatus }}
                                    </p>
                                </div>
                                <div
                                    class="shrink-0 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-right shadow-sm dark:border-slate-700 dark:bg-[#0f0f0f] sm:px-4 sm:py-2.5"
                                >
                                    <p class="mb-0.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Total Fee</p>
                                    <p class="text-xl font-black tracking-tighter text-slate-900 dark:text-white sm:text-2xl">
                                        ₱{{ calculatedDuration * bookingRate * form.courts.length }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="submit"
                                :disabled="
                                    form.processing ||
                                    !isEndTimeValid ||
                                    !isStartTimeValid ||
                                    isSelectedCourtFull ||
                                    isTimeSlotOverlapping ||
                                    isSelectedCourtWalkin ||
                                    isSelectedTimeInPast ||
                                    (bookingPartyMode === 'player' && !selectedPlayerId) ||
                                    !form.guest_phone ||
                                    (!form.receipt_photo && !receiptPreview) ||
                                    !form.lead_address
                                "
                                class="group relative flex w-full items-center justify-center overflow-hidden rounded-2xl py-3.5 text-xs font-black uppercase tracking-[0.15em] transition-all"
                                :class="
                                    form.processing ||
                                    !isEndTimeValid ||
                                    !isStartTimeValid ||
                                    isSelectedCourtFull ||
                                    isTimeSlotOverlapping ||
                                    isSelectedCourtWalkin ||
                                    isSelectedTimeInPast ||
                                    (bookingPartyMode === 'player' && !selectedPlayerId) ||
                                    !form.guest_phone ||
                                    (!form.receipt_photo && !receiptPreview) ||
                                    !form.lead_address
                                        ? 'cursor-not-allowed bg-slate-200 text-slate-400 dark:bg-[#1a1a1a]'
                                        : 'bg-blue-600 text-white hover:bg-blue-500 active:scale-[0.98] dark:bg-green-600 dark:hover:bg-green-500'
                                "
                            >
                                <div
                                    class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-white/0 via-white/10 to-white/0 transition-transform duration-700 group-hover:translate-x-full"
                                ></div>
                                <span v-if="form.processing" class="flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>
                                    Processing...
                                </span>
                                <span v-else class="relative z-10 flex items-center gap-2">
                                    {{ editingBookingId ? 'Update Reservation' : 'Confirm Reservation' }}
                                    <ChevronRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Court Scorer Assignment Modal -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div v-if="showCourtAssignmentModal" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/50 p-4">
                <div
                    class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-[#0f0f0f]"
                >
                    <!-- Modal Header -->
                    <div
                        class="shrink-0 bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 px-5 py-4 dark:from-green-600 dark:via-green-700 dark:to-emerald-800"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border border-white/25 bg-white/20">
                                    <UserIcon class="h-4 w-4 text-white" />
                                </div>
                                <div>
                                    <h2 class="text-sm font-black uppercase tracking-wider text-white">Court Scorer Assignments</h2>
                                    <p class="text-[10px] font-semibold text-white/60">Assign scorers to each court for today</p>
                                </div>
                            </div>
                            <button
                                @click="showCourtAssignmentModal = false"
                                class="flex h-8 w-8 items-center justify-center rounded-xl border border-white/20 bg-white/15 text-white transition-all hover:bg-white/25"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 space-y-1 overflow-y-auto p-5">
                        <div
                            v-for="n in allCourtNumbers"
                            :key="n"
                            class="flex items-center gap-3 rounded-2xl border p-3 transition-all border-slate-200 bg-slate-50/50 dark:border-slate-800/60 dark:bg-[#111111]"
                        >
                            <!-- Court badge -->
                            <div class="flex w-28 shrink-0 items-center gap-2">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-full border text-[11px] font-black border-blue-200 bg-blue-50 text-blue-700 dark:border-green-800 dark:bg-green-950/30 dark:text-green-400"
                                >
                                    C{{ n }}
                                </span>
                                <span
                                    class="text-[9px] font-black uppercase tracking-wider text-blue-600 dark:text-green-400"
                                >
                                    Assign by
                                </span>
                            </div>
                            <!-- Scorer dropdown -->
                            <select
                                v-model="localAssignments[n]"
                                class="min-w-0 flex-1 cursor-pointer appearance-none rounded-xl border bg-white px-3 py-2 text-sm transition-all focus:outline-none focus:ring-2 focus:ring-blue-400/30 dark:bg-[#0a0a0a] dark:focus:ring-green-400/30"
                                :class="
                                    localAssignments[n]
                                        ? 'border-blue-300 font-bold text-blue-700 dark:border-green-700 dark:text-green-300'
                                        : 'border-slate-200 font-medium text-slate-400 dark:border-slate-700'
                                "
                            >
                                <option :value="null">— Unassigned —</option>
                                <option v-for="scorer in props.scorers" :key="scorer.id" :value="scorer.id">{{ scorer.name }}</option>
                            </select>
                            <!-- Save button -->
                            <button
                                @click="assignScorer(n)"
                                :disabled="savingCourt === n"
                                class="shrink-0 rounded-xl px-4 py-2 text-[10px] font-black uppercase tracking-wider transition-all"
                                :class="
                                    savingCourt === n
                                        ? 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800'
                                        : 'bg-blue-500 text-white hover:bg-blue-600 dark:bg-green-600 dark:hover:bg-green-500'
                                "
                            >
                                {{ savingCourt === n ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                        <div v-if="props.scorers.length === 0" class="py-6 text-center text-sm font-medium text-slate-400">
                            No scorer users found. Register a scorer account first.
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Cancel & Refund Confirmation Modal -->
        <div v-if="showCancelRefundModal" class="fixed inset-0 z-[120] flex items-center justify-center bg-white/25 p-4 dark:bg-slate-950/50">
            <div
                class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-[#0f0f0f]"
            >
                <div class="border-b border-slate-100 p-5 dark:border-slate-800">
                    <h3 class="text-base font-black uppercase tracking-widest text-slate-900 dark:text-white">Cancel Booking</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This action is irreversible. Review the refund details below.</p>
                </div>
                <div v-if="refundInfo" class="space-y-1 p-5">
                    <div class="flex items-center justify-between border-b border-slate-100 py-2.5 dark:border-slate-800">
                        <span class="text-xs font-semibold text-slate-500">Total Paid</span>
                        <span class="font-black text-slate-800 dark:text-slate-200">₱{{ refundInfo.totalCost }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 py-2.5 dark:border-slate-800">
                        <span class="text-xs font-semibold text-slate-500">Refund Rate</span>
                        <span
                            class="text-lg font-black"
                            :class="
                                refundInfo.refundPct === 100 ? 'text-emerald-500' : refundInfo.refundPct === 50 ? 'text-amber-500' : 'text-rose-500'
                            "
                            >{{ refundInfo.refundPct }}%</span
                        >
                    </div>
                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs font-semibold text-slate-500">Refund Amount</span>
                        <span class="text-2xl font-black" :class="refundInfo.refundAmount > 0 ? 'text-emerald-500' : 'text-slate-400'"
                            >₱{{ refundInfo.refundAmount }}</span
                        >
                    </div>
                    <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-[10px] font-semibold text-slate-400 dark:bg-[#1a1a1a]">
                        {{ refundInfo.policy }}
                    </p>
                </div>
                <div class="flex gap-3 p-4">
                    <button
                        @click="showCancelRefundModal = false"
                        class="flex-1 rounded-xl bg-slate-100 py-2.5 text-xs font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                    >
                        Keep Booking
                    </button>
                    <button
                        @click="confirmCancelRefund"
                        :disabled="form.processing"
                        class="flex-1 rounded-xl bg-rose-500 py-2.5 text-xs font-black uppercase tracking-widest text-white transition-all hover:bg-rose-600 disabled:opacity-50"
                    >
                        Confirm Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- Reschedule Calendar Modal -->
        <Transition name="modal">
            <div v-if="showRescheduleModal" class="fixed inset-0 z-[125] flex items-center justify-center p-4 sm:p-6">
                <div class="modal-backdrop" @click="showRescheduleModal = false"></div>
                <div class="glass-card relative w-full max-w-md overflow-hidden border-0 duration-300 animate-in zoom-in-95">
                    <!-- Header -->
                    <div
                        class="relative flex items-center justify-between border-b border-slate-100 bg-gradient-to-br from-blue-600 to-blue-700 p-4 text-white dark:border-slate-800"
                    >
                        <div class="relative z-10 flex items-center space-x-3">
                            <div class="rounded-xl border border-white/30 bg-white/25 p-2">
                                <CalendarIcon class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-black tracking-tight">Reschedule</h2>
                                <p class="text-[10px] font-medium uppercase tracking-widest text-blue-100 opacity-80">Pick a new date</p>
                            </div>
                        </div>
                        <button
                            @click="showRescheduleModal = false"
                            class="relative z-10 rounded-xl border border-white/10 bg-white/10 p-2 transition-all hover:bg-white/20"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Calendar Body -->
                    <div class="bg-white p-4 dark:bg-[#0a0a0a]">
                        <!-- Month Navigation -->
                        <div class="mb-3 flex items-center justify-between">
                            <button
                                @click="prevRescheduleMonth"
                                class="rounded-xl border border-slate-200 bg-white p-1.5 transition-all hover:bg-slate-50 dark:border-slate-800 dark:bg-[#0a0a0a] dark:hover:bg-[#1a1a1a]"
                            >
                                <ChevronLeft class="h-4 w-4 text-slate-600 dark:text-slate-300" />
                            </button>
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 dark:text-white">{{ rescheduleMonthName }}</h3>
                            <button
                                @click="nextRescheduleMonth"
                                class="rounded-xl border border-slate-200 bg-white p-1.5 transition-all hover:bg-slate-50 dark:border-slate-800 dark:bg-[#0a0a0a] dark:hover:bg-[#1a1a1a]"
                            >
                                <ChevronRight class="h-4 w-4 text-slate-600 dark:text-slate-300" />
                            </button>
                        </div>

                        <!-- Day Headers -->
                        <div class="mb-2 grid grid-cols-7 gap-1">
                            <div
                                v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
                                :key="day"
                                class="py-1 text-center text-[10px] font-black uppercase tracking-widest text-slate-400"
                            >
                                {{ day }}
                            </div>
                        </div>

                        <!-- Day Cells -->
                        <div class="grid grid-cols-7 gap-1">
                            <div v-for="n in rescheduleDaysInMonth[0].getDay()" :key="'empty-' + n" class="min-h-[44px]"></div>
                            <button
                                v-for="date in rescheduleDaysInMonth"
                                :key="date.toISOString()"
                                @click="selectRescheduleDate(date)"
                                :disabled="isLockedDate(date)"
                                class="relative flex min-h-[44px] flex-col items-center justify-center gap-0.5 rounded-xl text-sm font-bold transition-all"
                                :class="[
                                    isRescheduleSelected(date)
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30 dark:bg-green-600 dark:shadow-green-500/30'
                                        : isLockedDate(date)
                                          ? 'cursor-not-allowed text-slate-300 dark:text-slate-600'
                                          : 'text-slate-700 hover:bg-blue-50 hover:text-blue-600 dark:text-slate-200 dark:hover:bg-green-900/20 dark:hover:text-green-400',
                                    isToday(date) && !isRescheduleSelected(date)
                                        ? 'ring-2 ring-blue-400 ring-offset-1 dark:ring-green-400 dark:ring-offset-slate-950'
                                        : '',
                                ]"
                            >
                                <span>{{ date.getDate() }}</span>
                                <span
                                    v-if="getBookingsForDate(date).length > 0 && !isRescheduleSelected(date)"
                                    class="text-[7px] font-black text-blue-500 dark:text-green-500"
                                    >{{ getBookingsForDate(date).length }}</span
                                >
                            </button>
                        </div>

                        <!-- Current date hint -->
                        <div class="mt-3 rounded-xl bg-blue-50 p-2 text-center dark:bg-green-900/20">
                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-600 dark:text-green-400">
                                Currently: {{ formatDateText(form.booking_date) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Delete Confirmation Modal -->
        <Transition name="modal">
            <div v-if="showDeleteConfirm" class="fixed inset-0 z-[110] flex items-center justify-center p-4 sm:p-6">
                <div class="modal-backdrop" @click="showDeleteConfirm = false"></div>
                <div class="glass-card relative w-full max-w-md overflow-hidden rounded-[2.5rem] duration-200 animate-in zoom-in-95">
                    <div class="p-5 text-center sm:p-8">
                        <div
                            class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 sm:mb-6 sm:h-20 sm:w-20"
                        >
                            <Trash2 class="h-8 w-8 sm:h-10 sm:w-10" />
                        </div>
                        <h2 class="mb-2 text-2xl font-black text-slate-900 dark:text-white">Delete Booking?</h2>
                        <p class="mb-8 font-medium text-slate-500">This action cannot be undone. Are you sure you want to remove this reservation?</p>

                        <div class="flex gap-4">
                            <button
                                @click="showDeleteConfirm = false"
                                class="flex-1 rounded-2xl bg-slate-100 py-4 font-black uppercase tracking-widest text-slate-600 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a]"
                            >
                                Cancel
                            </button>
                            <button
                                @click="confirmDelete"
                                class="flex-1 rounded-2xl bg-red-600 py-4 font-black uppercase tracking-widest text-white transition-all hover:bg-red-700"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Update Confirmation Modal -->
        <Transition name="modal">
            <div v-if="showUpdateConfirm" class="fixed inset-0 z-[110] flex items-center justify-center p-4 sm:p-6">
                <div class="modal-backdrop" @click="showUpdateConfirm = false"></div>
                <div class="glass-card relative w-full max-w-md overflow-hidden rounded-[2.5rem] duration-200 animate-in zoom-in-95">
                    <div class="p-5 text-center sm:p-8">
                        <div
                            class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-green-900/30 dark:text-green-400 sm:mb-6 sm:h-20 sm:w-20"
                        >
                            <Edit class="h-8 w-8 sm:h-10 sm:w-10" />
                        </div>
                        <h2 class="mb-2 text-2xl font-black text-slate-900 dark:text-white">Save Changes?</h2>
                        <p class="mb-8 font-medium text-slate-500">Are you sure you want to update this reservation with the new details?</p>

                        <div class="flex gap-4">
                            <button
                                @click="showUpdateConfirm = false"
                                class="flex-1 rounded-2xl bg-slate-100 py-4 font-black uppercase tracking-widest text-slate-600 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a]"
                            >
                                Cancel
                            </button>
                            <button
                                @click="processSubmit"
                                class="flex-1 rounded-2xl bg-blue-600 py-4 font-black uppercase tracking-widest text-white transition-all hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                            >
                                Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Receipt Photo Lightbox -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="viewingReceipt"
                class="fixed inset-0 z-[300] flex items-center justify-center bg-black/85 p-4"
                @click.self="viewingReceipt = null"
            >
                <div class="relative w-full max-w-2xl">
                    <button
                        @click="viewingReceipt = null"
                        class="absolute -right-3 -top-3 z-10 rounded-full bg-white p-2 text-slate-700 shadow-lg transition-all hover:bg-rose-500 hover:text-white"
                    >
                        <X class="h-4 w-4" />
                    </button>
                    <div class="overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
                        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
                            <Eye class="h-4 w-4 text-blue-600" />
                            <span class="text-xs font-black uppercase tracking-widest text-slate-500">Proof of Receipt</span>
                        </div>
                        <img :src="viewingReceipt" class="max-h-[75vh] w-full object-contain" />
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<style scoped>
.glass-card {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 2rem;
}

.dark .glass-card {
    background: rgba(15, 23, 42, 0.95);
    border-color: rgba(255, 255, 255, 0.05);
}

.mesh-gradient-header {
    background: linear-gradient(to bottom right, rgba(37, 99, 235, 0.03), rgba(147, 51, 234, 0.03));
    @apply border-b border-slate-100 dark:border-slate-800;
}

.input-glass {
    @apply border-slate-200 bg-slate-50 transition-colors duration-200 dark:border-slate-800 dark:bg-slate-900/80;
}

.input-glass:focus {
    @apply border-blue-500 bg-white ring-2 ring-blue-500/10 dark:bg-slate-900;
}

.summary-ticket {
    @apply overflow-hidden rounded-3xl border-l-4 border-l-blue-600 bg-slate-50 dark:border-l-blue-500 dark:bg-slate-900/50;
}

.btn-primary-vibrant {
    background: #2563eb;
    @apply text-white transition-colors hover:bg-blue-700 active:scale-[0.98];
}

.modal-backdrop {
    @apply absolute inset-0 bg-slate-900/45;
}

.modal-enter-active,
.modal-leave-active {
    transition:
        opacity 0.2s ease-out,
        transform 0.2s ease-out;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
    transform: scale(0.98) translateY(10px);
}

@keyframes modal-in {
    0% {
        transform: scale(0.98) translateY(10px);
        opacity: 0;
    }
    100% {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation: none !important;
        transition: none !important;
        scroll-behavior: auto !important;
    }
}
</style>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.2);
    border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(59, 130, 246, 0.4);
}
</style>
