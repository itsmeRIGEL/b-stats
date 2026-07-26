<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Calendar as CalendarIcon,
    CheckCircle,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Clock,
    CreditCard,
    Crown,
    DollarSign,
    History,
    LayoutGrid,
    MapPin,
    Medal,
    Moon,
    Phone,
    Plus,
    Search,
    Sun,
    Trophy,
    Upload,
    User,
    Users,
    X,
    Mail,
    Facebook,
    Instagram,
    Youtube,
    Twitter,
    Globe,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue';
import type { SharedData } from '@/types';

const props = defineProps<{
    venue?:
        | {
              id: number;
              name: string;
              address?: string | null;
              tagline?: string | null;
              description?: string | null;
              court_count?: number | null;
              covered_court_count?: number | null;
              logo_url?: string | null;
              cover_photo_url?: string | null;
              gallery_urls?: string[];
              contact_email?: string | null;
              contact_phone?: string | null;
              facebook_url?: string | null;
              amenities?: string[];
              default_hourly_rate?: number;
          }
        | null;
    bookings: any[];
    settings: Record<string, string>;
    weather: Record<string, { code: number; temp_max: number; temp_min: number }>;
    pricing: { member_booking_rate: number; non_member_booking_rate: number };
    weeklyAvailabilities: Array<{ id: number; day_of_week: number; is_closed: boolean; opening_time: string | null; closing_time: string | null; close_reason?: string | null }>;
    dateOverrides: Array<{ id: number; date: string; is_closed: boolean; opening_time: string | null; closing_time: string | null; close_reason?: string | null }>;
    currentPlayerProfile?: {
        id: number;
        phone?: string | null;
        address?: string | null;
        is_member?: boolean;
        venue_id?: number | null;
    } | null;
    players: any[];
    matches: any[];
}>();
const page = usePage<SharedData>();
const currentUser = computed(() => page.props.auth?.user ?? null);
const authenticatedPlayerProfile = computed(() => props.currentPlayerProfile ?? null);
const currentPlayer = computed(() => {
    const user = currentUser.value;
    if (!user || user.role !== 'player') return null;
    if (authenticatedPlayerProfile.value) {
        return authenticatedPlayerProfile.value;
    }
    return (
        props.players.find(
            (player: any) => Number(player.user_id) === Number(user.id) && (!props.venue?.id || Number(player.venue_id) === Number(props.venue.id)),
        ) ?? null
    );
});
const isLoggedInPlayer = computed(() => currentUser.value?.role === 'player');
const defaultLeadGuestName = computed(() => {
    const user = currentUser.value;
    if (!user) return '';
    if (user.username && String(user.username).trim().length > 0) {
        return String(user.username).trim();
    }
    const fullName = [user.first_name, user.last_name].filter((part) => part && String(part).trim().length > 0).join(' ').trim();
    return fullName || user.name || '';
});
const defaultLeadAddress = computed(() => currentPlayer.value?.address?.trim?.() ?? '');
const defaultGuestPhone = computed(() => currentPlayer.value?.phone?.trim?.() ?? '');
const defaultGuestEmail = computed(() => currentUser.value?.email ?? '');
const defaultClientType = computed(() => (currentPlayer.value?.is_member ? 'member' : 'non_member'));

const applyLoggedInPlayerDefaults = () => {
    if (!isLoggedInPlayer.value) return;
    form.lead_name = defaultLeadGuestName.value;
    form.lead_address = defaultLeadAddress.value;
    form.guest_email = defaultGuestEmail.value;
    form.guest_phone = defaultGuestPhone.value;
    form.client_type = defaultClientType.value;
};
const venueName = computed(() => props.venue?.name?.trim() || 'Venue Calendar');
const bookingStoreRoute = computed(() => (props.venue?.name ? route('book.venue.store', { venue: props.venue.name }) : route('book.store')));
const venueAmenities = computed(() => (props.venue?.amenities ?? []).slice(0, 8));
const venueGallery = computed(() => props.venue?.gallery_urls ?? []);

const currentGalleryIndex = ref(0);
const nextGalleryImage = () => {
    if (!venueGallery.value.length) return;
    currentGalleryIndex.value = (currentGalleryIndex.value + 1) % venueGallery.value.length;
};
const prevGalleryImage = () => {
    if (!venueGallery.value.length) return;
    currentGalleryIndex.value = (currentGalleryIndex.value - 1 + venueGallery.value.length) % venueGallery.value.length;
};

const isLightboxOpen = ref(false);
const lightboxIndex = ref(0);
const lightboxImages = ref<string[]>([]);
const openLightbox = (index: number, customImages?: string[]) => {
    lightboxImages.value = customImages || venueGallery.value;
    lightboxIndex.value = index;
    isLightboxOpen.value = true;
};
const closeLightbox = () => {
    isLightboxOpen.value = false;
};
const nextLightboxImage = () => {
    if (!lightboxImages.value.length) return;
    lightboxIndex.value = (lightboxIndex.value + 1) % lightboxImages.value.length;
};
const prevLightboxImage = () => {
    if (!lightboxImages.value.length) return;
    lightboxIndex.value = (lightboxIndex.value - 1 + lightboxImages.value.length) % lightboxImages.value.length;
};
const getPlatformLabel = (platform: string) => {
    switch (platform) {
        case 'facebook': return 'Facebook Page';
        case 'instagram': return 'Instagram Profile';
        case 'youtube': return 'YouTube Channel';
        case 'tiktok': return 'TikTok Account';
        case 'twitter': return 'X / Twitter';
        default: return 'Official Website';
    }
};

const socialLinksList = computed(() => {
    const rawVal = props.venue?.facebook_url;
    if (!rawVal) return [];
    
    try {
        const parsed = JSON.parse(rawVal);
        if (Array.isArray(parsed)) {
            return parsed.map((item: any) => ({
                platform: item.platform || 'facebook',
                url: item.url || '',
                label: getPlatformLabel(item.platform)
            })).filter((item) => item.url.trim().length > 0);
        } else if (parsed && typeof parsed === 'object' && parsed.platform && parsed.url) {
            return [{
                platform: parsed.platform,
                url: parsed.url,
                label: getPlatformLabel(parsed.platform)
            }];
        }
    } catch (e) {
        // Fallback for legacy simple raw url strings
        return [{
            platform: 'facebook',
            url: rawVal,
            label: 'Facebook Page'
        }];
    }
    return [];
});


/* ─── Calendar helpers ─── */
const formatDateToISO = (date: Date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const currentMonth = ref(new Date());
const monthName = computed(() => currentMonth.value.toLocaleString('default', { month: 'long', year: 'numeric' }));

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

const isToday = (date: Date) => formatDateToISO(new Date()) === formatDateToISO(date);
const isPastDate = (date: Date) => {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return d < now;
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

const getBookingsForDate = (date: Date) => {
    const dateStr = formatDateToISO(date);
    return props.bookings.filter((b) => b.booking_date === dateStr).sort((a: any, b: any) => a.start_time.localeCompare(b.start_time));
};

const getFullCourtsForDate = (date: Date) => {
    const dateStr = formatDateToISO(date);
    const avail = resolveAvailabilityForDate(dateStr);
    const count = courtCount.value;
    if (avail.is_closed || !avail.opening_time || !avail.closing_time) {
        return Array.from({ length: count }, (_, idx) => idx + 1);
    }
    const dayBookings = getBookingsForDate(date).filter((b: any) => b.status !== 'rejected' && b.status !== 'cancelled');
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
    for (let i = 1; i <= count; i++) {
        const courtBookings = dayBookings.filter((b: any) => Number(b.court_number) === i);
        const bookedHours = courtBookings.reduce((total: number, booking: any) => {
            const start = new Date(`2000-01-01T${booking.start_time}`);
            const end = new Date(`2000-01-01T${booking.end_time}`);
            if (end <= start) end.setDate(end.getDate() + 1);
            return total + (end.getTime() - start.getTime()) / (1000 * 60 * 60);
        }, 0);
        if (bookedHours >= windowHours) fullCourts.push(i);
    }
    return fullCourts;
};

const isDayFullyBooked = (date: Date) => {
    const dateStr = formatDateToISO(date);
    const avail = resolveAvailabilityForDate(dateStr);
    if (avail.is_closed) return true;
    const fullCourts = getFullCourtsForDate(date);
    const count = courtCount.value;
    let bookableCount = 0;
    let fullBookableCount = 0;
    
    for (let i = 1; i <= count; i++) {
        if (courtIsBookable(i)) {
            bookableCount++;
            if (fullCourts.includes(i)) {
                fullBookableCount++;
            }
        }
    }
    
    if (bookableCount === 0) return true;
    return fullBookableCount >= bookableCount;
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

/* ─── Day Detail ─── */
const showDayDetail = ref(false);
const dayDetailDate = ref<Date | null>(null);
const dayDetailBookings = computed(() => {
    if (!dayDetailDate.value) return [];
    return getBookingsForDate(dayDetailDate.value);
});

const dayDetailSlots = computed(() => {
    if (!dayDetailDate.value) return [];
    
    const yStr = dayDetailDate.value.getFullYear();
    const mStr = String(dayDetailDate.value.getMonth() + 1).padStart(2, '0');
    const dStr = String(dayDetailDate.value.getDate()).padStart(2, '0');
    const dateStr = `${yStr}-${mStr}-${dStr}`;
    
    const avail = resolveAvailabilityForDate(dateStr);
    if (avail.is_closed || !avail.opening_time || !avail.closing_time) return [];

    const [oh, om] = avail.opening_time.split(':').map(Number);
    const [ch, cm] = avail.closing_time.split(':').map(Number);
    
    let closingHour = ch;
    if (ch === 0 && cm === 0) {
        closingHour = 24;
    }
    
    const slots = [];
    const todayStr = formatDateToISO(new Date());
    
    for (let h = oh; h < closingHour; h++) {
        const startStr = `${String(h).padStart(2, '0')}:00`;
        const endStr = `${String((h + 1) % 24).padStart(2, '0')}:00`;
        
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

        const bookingsToday = dayDetailBookings.value;
        const bookingOnSlot = bookingsToday.find((b) => {
            if (b.status === 'rejected' || b.status === 'cancelled') return false;
            const bStart = b.start_time.substring(0, 5);
            const bEnd = b.end_time.substring(0, 5);
            return bStart < endStr && bEnd > startStr;
        });

        slots.push({
            start: startStr,
            end: endStr,
            isPast,
            isBooked: !!bookingOnSlot,
        });
    }
    
    return slots;
});
const openDayDetail = (date: Date) => {
    if (isPastDate(date)) return;
    dayDetailDate.value = date;
    showDayDetail.value = true;
};
const closeDayDetail = () => {
    showDayDetail.value = false;
    dayDetailDate.value = null;
};

/* ─── Stepper & Form ─── */
const step = ref(1);
const showStepper = ref(false);
const selectedDate = ref('');
const receiptPreview = ref<string | null>(null);
const receiptError = ref<string | null>(null);
const submitted = ref(false);
const showEnlargedImage = ref(false);
const openDropdown = ref<'start-h' | 'start-m' | 'end-h' | 'end-m' | null>(null);
const dropdownContainer = ref<HTMLElement | null>(null);
const toggleDropdown = (name: 'start-h' | 'start-m' | 'end-h' | 'end-m') => {
    openDropdown.value = openDropdown.value === name ? null : name;
};
const closeDropdowns = () => {
    openDropdown.value = null;
};

const handleClickOutside = (e: MouseEvent) => {
    if (dropdownContainer.value && !dropdownContainer.value.contains(e.target as Node)) {
        closeDropdowns();
    }
};

/* ─── Theme (independent preference for booking page) ─── */
const isDark = ref(false);

const syncThemeWithApp = () => {
    const savedAppearance = localStorage.getItem('appearance');
    const mainAppDark = savedAppearance === 'dark' || ((savedAppearance === 'system' || !savedAppearance) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    if (currentUser.value) {
        isDark.value = mainAppDark;
    } else {
        isDark.value = localStorage.getItem('booking-theme') === 'dark';
    }
};

syncThemeWithApp();

const toggleTheme = () => {
    isDark.value = !isDark.value;
    const themeStr = isDark.value ? 'dark' : 'light';
    localStorage.setItem('booking-theme', themeStr);
    
    if (currentUser.value) {
        localStorage.setItem('appearance', themeStr);
    }
    
    const metaThemeColor = document.querySelector('meta[name="theme-color"]');
    if (metaThemeColor) {
        metaThemeColor.setAttribute('content', isDark.value ? '#16a34a' : '#3b82f6');
    }
};

// Isolate this page from the main app's dark mode by temporarily
// removing the "dark" class from <html> while this component is mounted.
let htmlHadDark = false;

// Auto-poll: refresh the bookings list every 5s, but only when the tab is visible.
// On tab focus, refresh immediately so the user sees the latest state.
let pollIntervalId: ReturnType<typeof setInterval> | null = null;

const startPolling = () => {
    if (pollIntervalId !== null) return;
    pollIntervalId = setInterval(() => {
        if (document.visibilityState === 'visible') {
            router.reload({ only: ['bookings'] });
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
        router.reload({ only: ['bookings'] });
    } else {
        stopPolling();
    }
};

onMounted(() => {
    syncThemeWithApp();
    
    htmlHadDark = document.documentElement.classList.contains('dark');
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark');
    }

    const metaThemeColor = document.querySelector('meta[name="theme-color"]');
    if (metaThemeColor) {
        metaThemeColor.setAttribute('content', isDark.value ? '#16a34a' : '#3b82f6');
    }

    document.addEventListener('visibilitychange', handleVisibilityChange);
    if (document.visibilityState === 'visible') startPolling();
});
onUnmounted(() => {
    const savedAppearance = localStorage.getItem('appearance');
    const mainAppDark = savedAppearance === 'dark' || ((savedAppearance === 'system' || !savedAppearance) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    
    if (mainAppDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    const metaThemeColor = document.querySelector('meta[name="theme-color"]');
    if (metaThemeColor) {
        metaThemeColor.setAttribute('content', mainAppDark ? '#16a34a' : '#3b82f6');
    }
});
onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    stopPolling();
});

const form = useForm({
    booking_date: '',
    start_time: '',
    end_time: '',
    cost_per_hour: parseFloat(props.settings.default_hourly_rate) || 20,
    total_cost: 0,
    lead_name: '',
    lead_address: '',
    guest_email: '',
    guest_phone: '',
    player_count: 1,
    court_number: 1,
    courts: [1] as number[],
    client_type: 'member',
    receipt_photo: null as File | null,
});

const hourlyRate = computed(() => parseFloat(props.settings.default_hourly_rate) || 20);
const courtCount = computed(() => Number(props.venue?.court_count ?? props.settings.court_count ?? 1) || 1);

const parseWalkinCourts = (val: string | undefined): number[] => {
    if (!val) return [];
    return val
        .split(',')
        .map((v) => parseInt(v.trim()))
        .filter((v) => !isNaN(v) && v > 0);
};
const walkinCourts = ref<number[]>(parseWalkinCourts(props.settings.walkin_courts));
const bothCourts = ref<number[]>(parseWalkinCourts(props.settings.both_courts));

const courtIsBookable = (courtNum: number) => !walkinCourts.value.includes(courtNum);
const courtIsWalkinOnly = (courtNum: number) => walkinCourts.value.includes(courtNum) && !bothCourts.value.includes(courtNum);
const courtIsBoth = (courtNum: number) => bothCourts.value.includes(courtNum);

const openStepper = (date: Date) => {
    if (isPastDate(date)) return;
    selectedDate.value = formatDateToISO(date);
    form.booking_date = selectedDate.value;
    const firstBookable = Array.from({ length: courtCount.value }, (_, i) => i + 1).find(c => courtIsBookable(c)) || 1;
    form.courts = [firstBookable];
    form.court_number = firstBookable;
    form.lead_name = '';
    form.lead_address = '';
    form.guest_email = '';
    form.guest_phone = '';
    form.player_count = 1;
    form.client_type = 'member';
    form.receipt_photo = null;
    form.cost_per_hour = hourlyRate.value;
    applyLoggedInPlayerDefaults();
    selectFirstAvailableSlot();
    receiptPreview.value = null;
    step.value = 1;
    showStepper.value = true;
    submitted.value = false;
};

const closeStepper = () => {
    closeDropdowns();
    showStepper.value = false;
    step.value = 1;
    form.reset();
    receiptPreview.value = null;
    receiptError.value = null;
    applyLoggedInPlayerDefaults();
};

const nextStep = () => {
    closeDropdowns();
    if (step.value < 3) step.value++;
};
const prevStep = () => {
    closeDropdowns();
    if (step.value > 1) step.value--;
};

watch(showStepper, (open) => {
    if (open) window.addEventListener('click', handleClickOutside, true);
    else window.removeEventListener('click', handleClickOutside, true);
});

const durationHours = computed(() => {
    if (!form.start_time || !form.end_time) return 1;
    const [sh, sm] = form.start_time.split(':').map(Number);
    const [eh, em] = form.end_time.split(':').map(Number);
    const startM = sh * 60 + sm;
    let endM = eh * 60 + em;
    if (endM <= startM) endM += 24 * 60;
    return (endM - startM) / 60;
});

const totalCost = computed(() => {
    const d = durationHours.value;
    const rate = form.client_type === 'member' ? props.pricing.member_booking_rate : props.pricing.non_member_booking_rate;
    return d * rate * form.courts.length;
});

const availableCourts = computed(() => {
    if (!form.booking_date || !form.start_time || !form.end_time) return [];
    const [y, m, d] = form.booking_date.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    const fullCourts = getFullCourtsForDate(date);
    const allCourts = Array.from({ length: courtCount.value }, (_, i) => i + 1);
    return allCourts.filter((c) => !fullCourts.includes(c) && courtIsBookable(c));
});

const isSelectedCourtWalkin = computed(() => form.courts.some(c => courtIsWalkinOnly(c)));

const isTimeOverlapping = computed(() => {
    if (!form.booking_date || form.courts.length === 0 || !form.start_time || !form.end_time) return false;
    const [y, m, d] = form.booking_date.split('-').map(Number);
    const targetDate = new Date(y, m - 1, d);
    const prevDate = new Date(targetDate);
    prevDate.setDate(prevDate.getDate() - 1);
    const nextDate = new Date(targetDate);
    nextDate.setDate(nextDate.getDate() + 1);
    const relevantBookings = [...getBookingsForDate(prevDate), ...getBookingsForDate(targetDate), ...getBookingsForDate(nextDate)].filter(
        (b: any) => form.courts.includes(Number(b.court_number)) && b.status !== 'rejected' && b.status !== 'cancelled',
    );
    const sA = new Date(`${form.booking_date}T${form.start_time}`);
    const eA = new Date(`${form.booking_date}T${form.end_time}`);
    if (eA <= sA) eA.setDate(eA.getDate() + 1);
    return relevantBookings.some((b: any) => {
        const sB = new Date(`${b.booking_date}T${b.start_time.substring(0, 5)}`);
        const eB = new Date(`${b.booking_date}T${b.end_time.substring(0, 5)}`);
        if (eB <= sB) eB.setDate(eB.getDate() + 1);
        return sA < eB && eA > sB;
    });
});

const isSelectedTimeInPast = computed(() => {
    if (!form.booking_date || !form.start_time) return false;
    const todayStr = formatDateToISO(new Date());
    if (form.booking_date !== todayStr) return false;

    const now = new Date();
    const slotStartTime = new Date(`${form.booking_date}T${form.start_time}`);
    const gracePeriodMinutes = parseInt(props.settings.booking_expiration_grace_minutes || '20');
    const gracePeriodEnd = new Date(slotStartTime.getTime() + gracePeriodMinutes * 60 * 1000);
    return now > gracePeriodEnd;
});

const isStartTimeValid = computed(() => {
    if (!form.booking_date || !form.start_time) return true;
    const avail = resolveAvailabilityForDate(form.booking_date);
    if (avail.is_closed || !avail.opening_time) return false;
    return form.start_time >= avail.opening_time;
});

const isEndAfterStart = computed(() => {
    if (!form.start_time || !form.end_time) return true;
    const [sh, sm] = form.start_time.split(':').map(Number);
    let [eh, em] = form.end_time.split(':').map(Number);
    if (eh < sh || (eh === sh && em <= sm)) {
        eh += 24;
    }
    const startVal = sh * 60 + sm;
    const endVal = eh * 60 + em;
    return endVal > startVal;
});

const isEndTimeValid = computed(() => {
    if (!form.booking_date || !form.start_time || !form.end_time) return true;
    const avail = resolveAvailabilityForDate(form.booking_date);
    if (avail.is_closed || !avail.closing_time || !avail.opening_time) return false;
    const [sh, sm] = form.start_time.split(':').map(Number);
    let [eh, em] = form.end_time.split(':').map(Number);
    if (eh < sh || (eh === sh && em <= sm)) {
        eh += 24;
    }
    const [ch, cm] = avail.closing_time.split(':').map(Number);
    let closingHour = ch;
    const [oh, om] = avail.opening_time.split(':').map(Number);
    if (ch < oh || (ch === oh && cm < om)) {
        closingHour += 24;
    } else if (ch === 0 && cm === 0) {
        closingHour = 24;
    }
    const endVal = eh * 60 + em;
    const closeVal = closingHour * 60 + cm;
    return endVal <= closeVal;
});

const timeToMinutes = (timeStr: string, isEnd = false) => {
    if (!timeStr) return 0;
    const [h, m] = timeStr.split(':').map(Number);
    if (isEnd && h === 0 && m === 0) {
        return 1440;
    }
    return h * 60 + m;
};

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
            
            const bStart = b.start_time.substring(0, 5);
            const bEnd = b.end_time.substring(0, 5);
            
            return bStart < endStr && bEnd > startStr;
        });

        const isBooked = !!bookingOnSlot;
        const isSelected = !!(
            form.start_time &&
            form.end_time &&
            timeToMinutes(startStr, false) >= timeToMinutes(form.start_time, false) &&
            timeToMinutes(endStr, true) <= timeToMinutes(form.end_time, true)
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
    
    const startVal = form.start_time || null;
    const endVal = form.end_time || null;

    if (!startVal || !endVal) {
        form.start_time = slot.start;
        form.end_time = slot.end;
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
                form.start_time = '';
                form.end_time = '';
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
            form.start_time = slot.start;
            form.end_time = slot.end;
        } else {
            form.start_time = newStart;
            form.end_time = newEnd;
        }
    }
};

const selectFirstAvailableSlot = () => {
    const slots = generatedSlots.value;
    const available = slots.find(s => !s.isBooked && !s.isPast);
    if (available) {
        form.start_time = available.start;
        form.end_time = available.end;
    } else {
        form.start_time = '';
        form.end_time = '';
    }
};

watch(
    [() => form.booking_date, () => form.courts],
    () => {
        if (showStepper.value && step.value === 2) {
            selectFirstAvailableSlot();
        }
    },
    { deep: true }
);

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

const canProceedStep1 = computed(() => form.lead_name.trim().length > 0 && form.guest_phone.trim().length > 0 && form.lead_address.trim().length > 0);
const canProceedStep2 = computed(() => {
    return (
        form.courts.length > 0 &&
        form.start_time &&
        form.end_time &&
        isEndAfterStart.value &&
        durationHours.value > 0 &&
        !isTimeOverlapping.value &&
        isStartTimeValid.value &&
        isEndTimeValid.value &&
        !isSelectedCourtWalkin.value &&
        !isSelectedTimeInPast.value
    );
});
const canSubmitStep3 = computed(
    () =>
        form.lead_name.trim().length > 0 &&
        form.guest_phone.trim().length > 0 &&
        form.lead_address.trim().length > 0 &&
        form.receipt_photo !== null &&
        receiptError.value === null,
);

const MAX_RECEIPT_SIZE = 5 * 1024 * 1024; // 5MB

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

const submitBooking = () => {
    form.total_cost = totalCost.value;
    const data = new FormData();
    data.append('booking_date', form.booking_date);
    data.append('start_time', form.start_time);
    data.append('end_time', form.end_time);
    data.append('cost_per_hour', String(form.cost_per_hour));
    data.append('total_cost', String(form.total_cost));
    data.append('lead_name', form.lead_name);
    data.append('lead_address', form.lead_address);
    data.append('guest_email', form.guest_email);
    data.append('guest_phone', form.guest_phone);
    data.append('player_count', String(form.player_count));
    data.append('court_number', String(form.court_number));
    data.append('client_type', form.client_type);
    if (form.receipt_photo) data.append('receipt_photo', form.receipt_photo);

    form.post(bookingStoreRoute.value, {
        forceFormData: true,
        onSuccess: () => {
            submitted.value = true;
            setTimeout(() => {
                closeStepper();
            }, 3000);
        },
    });
};

const formatTime12h = (timeStr: string) => {
    if (!timeStr) return '';
    const [h24, m] = timeStr.split(':');
    let h = parseInt(h24);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12;
    return `${h}:${m} ${ampm}`;
};

const operationalHours = computed(() => {
    const open = props.settings?.opening_time;
    const close = props.settings?.closing_time;
    if (!open || !close) return null;
    return `${formatTime12h(open)} – ${formatTime12h(close)}`;
});

const showHoursModal = ref(false);
const daysOfWeekNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const formattedWeeklyHours = computed(() => {
    return daysOfWeekNames.map((name, i) => {
        const setting = props.weeklyAvailabilities.find((w) => w.day_of_week === i);
        if (setting && setting.is_enabled) {
            if (setting.is_closed) {
                return { day_name: name, is_closed: true, hours: 'Facility Closed' };
            }
            return {
                day_name: name,
                is_closed: false,
                hours: `${formatTime12h(setting.opening_time || '')} to ${formatTime12h(setting.closing_time || '')}`
            };
        }
        // Fallback to default operating hours
        const defOpen = props.settings.opening_time || '08:00';
        const defClose = props.settings.closing_time || '22:00';
        return {
            day_name: name,
            is_closed: false,
            hours: `${formatTime12h(defOpen)} to ${formatTime12h(defClose)}`
        };
    });
});

const timeParts = (timeStr: string) => {
    if (!timeStr) return { h: '12', m: '00', ampm: 'AM' };
    const [h24, m] = timeStr.split(':').map(Number);
    const ampm = h24 >= 12 ? 'PM' : 'AM';
    const h = h24 % 12 || 12;
    return { h: String(h), m: String(m).padStart(2, '0'), ampm };
};

const setTimeFromParts = (target: 'start' | 'end', hStr: string, mStr: string, ampm: string) => {
    let h = parseInt(hStr) || 12;
    let m = parseInt(mStr) || 0;
    h = Math.max(1, Math.min(12, h));
    m = Math.max(0, Math.min(59, m));
    if (ampm === 'PM' && h < 12) h += 12;
    if (ampm === 'AM' && h === 12) h = 0;
    const timeVal = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    if (target === 'start') form.start_time = timeVal;
    else form.end_time = timeVal;
};

/* ─── All-Time Stats Modal Logic ─── */
const showStatsModal = ref(false);
const statsTab = ref<'leaderboard' | 'history'>('leaderboard');
const statsSearchQuery = ref('');
const statsHistorySearch = ref('');
const statsHistoryMonthFilter = ref('');
const statsShowMonthDropdown = ref(false);
const statsMonthDropdownRef = ref<HTMLElement | null>(null);

const statsExpandedDays = ref<Record<string, boolean>>({});
const toggleStatsDay = (date: string) => {
    statsExpandedDays.value = { ...statsExpandedDays.value, [date]: !statsExpandedDays.value[date] };
};

const getDaySessions = (matches: any[]) => {
    const map = new Map<string, { key: string; label: string; matches: any[] }>();

    for (const m of matches) {
        const key = m.booking_id ? String(m.booking_id) : 'walk-in-' + (m.updated_at ? m.updated_at.substring(0, 16) : 'none');
        if (!map.has(key)) {
            map.set(key, {
                key,
                label: '',
                matches: [],
            });
        }
        map.get(key)!.matches.push(m);
    }

    const result = Array.from(map.values());
    for (const r of result) {
        const firstMatch = r.matches[0];
        if (!firstMatch) continue;
        const typeLabel = firstMatch.booking_type === 'reclub' ? 'Reclub' : firstMatch.booking_type === 'booking' ? 'Booking' : 'Walk-in';
        
        if (firstMatch.booking_time) {
            r.label = `${firstMatch.booking_time} (${typeLabel})${firstMatch.booking_lead ? ' — ' + firstMatch.booking_lead : ''}`;
        } else {
            const times = r.matches
                .map(m => m.created_at ? new Date(m.created_at) : null)
                .filter((d): d is Date => d !== null && !isNaN(d.getTime()));
            if (times.length > 0) {
                times.sort((a, b) => a.getTime() - b.getTime());
                const formatTime = (d: Date) => {
                    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                };
                const start = formatTime(times[0]);
                const end = formatTime(times[times.length - 1]);
                r.label = start === end ? `${typeLabel} (${start})` : `${typeLabel} (${start} - ${end})`;
            } else {
                r.label = typeLabel;
            }
        }
    }

    result.sort((a, b) => {
        if (a.key === 'walk-in') return 1;
        if (b.key === 'walk-in') return -1;
        return (matches.find(m => String(m.booking_id) === a.key)?.booking_time || '').localeCompare(
            matches.find(m => String(m.booking_id) === b.key)?.booking_time || '',
        );
    });
    return result;
};

const getSessionLeaderboard = (matches: any[]) => {
    const winPoints = parseInt(props.settings.scoring_win_points || '10') || 10;
    const lossPenalty = parseInt(props.settings.scoring_loss_penalty || '5') || 5;
    const stats: Record<number, { name: string; wins: number; losses: number; points: number }> = {};

    for (const m of matches) {
        const team1Won = m.team1.won;
        for (let i = 0; i < m.team1.player_ids.length; i++) {
            const pid = m.team1.player_ids[i];
            const name = m.team1.players[i] || 'Unknown';
            if (!stats[pid]) stats[pid] = { name, wins: 0, losses: 0, points: 0 };
            team1Won ? stats[pid].wins++ : stats[pid].losses++;
        }
        for (let i = 0; i < m.team2.player_ids.length; i++) {
            const pid = m.team2.player_ids[i];
            const name = m.team2.players[i] || 'Unknown';
            if (!stats[pid]) stats[pid] = { name, wins: 0, losses: 0, points: 0 };
            team1Won ? stats[pid].losses++ : stats[pid].wins++;
        }
    }

    for (const pid of Object.keys(stats)) {
        const s = stats[pid];
        s.points = (s.wins * winPoints) - (s.losses * lossPenalty);
    }

    return Object.values(stats).sort((a, b) => b.points - a.points || b.wins - a.wins);
};

const allStatsSorted = computed(() =>
    [...props.players].sort((a, b) => {
        if (b.points !== a.points) return b.points - a.points;
        if (b.total_matches !== a.total_matches) return b.total_matches - a.total_matches;
        return b.win_rate - a.win_rate;
    }),
);

const statsSortedPlayers = computed(() => {
    if (!statsSearchQuery.value) return allStatsSorted.value;
    const q = statsSearchQuery.value.toLowerCase();
    return allStatsSorted.value.filter((p) => p.name.toLowerCase().includes(q));
});

const statsRankMap = computed(() => {
    const map = new Map<number, number>();
    allStatsSorted.value.forEach((p, i) => map.set(p.id, i));
    return map;
});

const statsAvailableMonths = computed(() => {
    if (!props.matches) return [];
    const months = new Set<string>();
    props.matches.forEach((m: any) => {
        const d = new Date(m.match_date);
        if (!isNaN(d.getTime())) {
            const y = d.getFullYear();
            const mo = String(d.getMonth() + 1).padStart(2, '0');
            months.add(`${y}-${mo}`);
        }
    });
    return Array.from(months).sort((a, b) => b.localeCompare(a));
});

const statsMonthLabel = (ym: string) => {
    const [y, m] = ym.split('-');
    const date = new Date(`${y}-${m}-01`);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
};

const statsGroupedMatches = computed(() => {
    if (!props.matches) return [];
    let filtered = props.matches;

    if (statsHistorySearch.value.trim()) {
        const q = statsHistorySearch.value.trim().toLowerCase();
        filtered = filtered.filter(
            (m) =>
                m.team1.players.some((n: string) => n.toLowerCase().includes(q)) || m.team2.players.some((n: string) => n.toLowerCase().includes(q)),
        );
    }

    if (statsHistoryMonthFilter.value) {
        const [fy, fm] = statsHistoryMonthFilter.value.split('-');
        filtered = filtered.filter((m: any) => {
            const d = new Date(m.match_date);
            return d.getFullYear() === Number(fy) && d.getMonth() + 1 === Number(fm);
        });
    }

    const groups = new Map<string, any[]>();
    filtered.forEach((m) => {
        const date = m.match_date;
        if (!groups.has(date)) groups.set(date, []);
        groups.get(date)!.push(m);
    });

    return Array.from(groups.entries())
        .sort((a, b) => new Date(b[0]).getTime() - new Date(a[0]).getTime())
        .map(([date, matches]) => ({
            date,
            walkinCount: matches.filter((m: any) => m.is_walkin).length,
            bookingCount: matches.filter((m: any) => !m.is_walkin && m.booking_type !== 'reclub').length,
            reclubCount: matches.filter((m: any) => !m.is_walkin && m.booking_type === 'reclub').length,
            matches,
        }));
});

const statsRankMeta = [
    { accent: 'from-amber-400 to-yellow-500', ring: 'ring-amber-400/60', text: 'text-amber-400', label: '1st' },
    { accent: 'from-slate-400 to-slate-500', ring: 'ring-slate-400/60', text: 'text-slate-400', label: '2nd' },
    { accent: 'from-orange-400 to-amber-600', ring: 'ring-orange-400/60', text: 'text-orange-400', label: '3rd' },
];

const handleStatsMonthDropdownClickOutside = (e: MouseEvent) => {
    if (statsMonthDropdownRef.value && !statsMonthDropdownRef.value.contains(e.target as Node)) {
        statsShowMonthDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleStatsMonthDropdownClickOutside);
});
onUnmounted(() => {
    document.removeEventListener('click', handleStatsMonthDropdownClickOutside);
});
</script>

<template>
    <Head title="Book a Court" />

    <div :class="['relative flex min-h-screen flex-col overflow-x-hidden', isDark ? 'bg-[#0a0a0a]' : 'bg-slate-50', { dark: isDark }]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                :class="['absolute -left-20 -top-24 h-72 w-72 rounded-full opacity-25 blur-3xl', isDark ? 'bg-emerald-600/20' : 'bg-sky-300/40']"
            ></div>
            <div
                :class="['absolute -bottom-20 -right-24 h-80 w-80 rounded-full opacity-20 blur-3xl', isDark ? 'bg-green-500/15' : 'bg-indigo-300/35']"
            ></div>
        </div>
        <main class="relative z-10 mx-auto w-full max-w-7xl flex-1 px-4 py-4 sm:px-6 sm:py-6">
            <div class="mb-5 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-[#0f0f0f] sm:mb-6">
                <div class="h-48 bg-slate-100 dark:bg-[#090909] sm:h-60 relative">
                    <img 
                        v-if="props.venue?.cover_photo_url" 
                        :src="props.venue.cover_photo_url" 
                        :alt="`${venueName} cover photo`" 
                        @click="openLightbox(0, [props.venue.cover_photo_url])"
                        class="h-full w-full object-cover cursor-zoom-in hover:brightness-95 transition-all" 
                    />
                    <div v-else class="flex h-full items-center justify-center bg-gradient-to-br from-slate-100 via-slate-200 to-slate-50 text-sm font-semibold text-slate-400 dark:from-[#111] dark:via-[#181818] dark:to-[#0b0b0b]">
                        Venue cover photo
                    </div>
                    
                    <!-- Floating Theme Switcher at Top Left -->
                    <div class="absolute left-4 top-4 z-10">
                        <button
                            type="button"
                            @click="toggleTheme"
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-black/30 text-white backdrop-blur-md transition-all hover:bg-black/50 hover:scale-105 active:scale-95 shadow-lg"
                            :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                        >
                            <Sun v-if="isDark" class="h-5 w-5" />
                            <Moon v-else class="h-5 w-5" />
                        </button>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 gap-4">
                            <div 
                                class="flex h-36 w-36 shrink-0 items-center justify-center overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-100 dark:border-[#1a1a1a] dark:bg-[#090909]"
                                :class="props.venue?.logo_url ? 'cursor-zoom-in hover:brightness-95 transition-all' : ''"
                                @click="props.venue?.logo_url ? openLightbox(0, [props.venue.logo_url]) : null"
                            >
                                <img v-if="props.venue?.logo_url" :src="props.venue.logo_url" :alt="`${venueName} logo`" class="h-full w-full object-cover" />
                                <span v-else class="text-sm font-black text-slate-400">{{ venueName.slice(0, 2).toUpperCase() }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white sm:text-3xl">{{ venueName }}</h2>
                                    <span class="rounded-full bg-primary px-3 py-1 text-xs font-bold text-primary-foreground shadow-sm">Available</span>
                                </div>
                                <p v-if="props.venue?.tagline" class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300">{{ props.venue.tagline }}</p>
                                <p class="mt-3 flex items-start gap-2 text-sm text-slate-500 dark:text-slate-400">
                                    <MapPin class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{{ props.venue?.address || 'Venue address will be shown here.' }}</span>
                                </p>
                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-slate-500 dark:text-slate-400">
                                    <p v-if="props.venue?.contact_phone" class="flex items-center gap-1.5">
                                        <Phone class="h-4 w-4 shrink-0 text-slate-400" />
                                        <span>{{ props.venue.contact_phone }}</span>
                                    </p>
                                    <p v-if="props.venue?.contact_email" class="flex items-center gap-1.5">
                                        <Mail class="h-4 w-4 shrink-0 text-slate-400" />
                                        <a :href="`mailto:${props.venue.contact_email}`" class="hover:text-primary transition-colors">{{ props.venue.contact_email }}</a>
                                    </p>
                                    
                                    <template v-if="socialLinksList.length">
                                        <p
                                            v-for="link in socialLinksList"
                                            :key="link.platform + link.url"
                                            class="flex items-center gap-1.5"
                                        >
                                            <Facebook v-if="link.platform === 'facebook'" class="h-4 w-4 shrink-0 text-slate-400" />
                                            <Instagram v-else-if="link.platform === 'instagram'" class="h-4 w-4 shrink-0 text-slate-400" />
                                            <Youtube v-else-if="link.platform === 'youtube'" class="h-4 w-4 shrink-0 text-slate-400" />
                                            <Twitter v-else-if="link.platform === 'twitter'" class="h-4 w-4 shrink-0 text-slate-400" />
                                            <Globe v-else class="h-4 w-4 shrink-0 text-slate-400" />
                                            <a :href="link.url" target="_blank" rel="noopener noreferrer" class="hover:text-primary transition-colors">{{ link.label }}</a>
                                        </p>
                                    </template>
                                </div>
                                <p class="mt-4 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {{ props.venue?.description || 'Reserve your court, explore the schedule, and enjoy a smooth booking flow from this venue page.' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-[#0a0a0a]">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Courts</p>
                            <p class="mt-1 text-lg font-black text-slate-900 dark:text-white">{{ props.venue?.court_count || courtCount }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-[#0a0a0a]">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Covered</p>
                            <p class="mt-1 text-lg font-black text-slate-900 dark:text-white">{{ props.venue?.covered_court_count || 0 }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-[#0a0a0a]">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Member rate</p>
                            <p class="mt-1 text-lg font-black text-slate-900 dark:text-white">PHP {{ props.pricing.member_booking_rate }}/hr</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-[#0a0a0a]">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Non-member rate</p>
                            <p class="mt-1 text-lg font-black text-slate-900 dark:text-white">PHP {{ props.pricing.non_member_booking_rate }}/hr</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="amenity in venueAmenities"
                            :key="amenity"
                            class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary dark:bg-primary/15 dark:text-primary/90"
                        >
                            {{ amenity }}
                        </span>
                    </div>

                    <div v-if="venueGallery.length" class="mt-5 relative group/gallery overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-[#1a1a1a] dark:bg-[#090909] h-60 sm:h-72 md:h-80 p-2 shadow-inner">
                        <!-- Current Slides (2 side-by-side) -->
                        <div class="h-full w-full grid gap-2.5" :class="venueGallery.length > 1 ? 'grid-cols-2' : 'grid-cols-1'">
                            <div class="flex h-full w-full items-center justify-center bg-slate-200/55 dark:bg-[#050505]/55 rounded-xl overflow-hidden p-1">
                                <img 
                                    :src="venueGallery[currentGalleryIndex]" 
                                    :alt="`${venueName} photo ${currentGalleryIndex + 1}`" 
                                    @click="openLightbox(currentGalleryIndex)"
                                    class="max-h-full max-w-full object-contain rounded-lg transition-all duration-350 cursor-zoom-in hover:scale-[1.02]" 
                                />
                            </div>
                            <div 
                                v-if="venueGallery.length > 1"
                                class="flex h-full w-full items-center justify-center bg-slate-200/55 dark:bg-[#050505]/55 rounded-xl overflow-hidden p-1"
                            >
                                <img 
                                    :src="venueGallery[(currentGalleryIndex + 1) % venueGallery.length]" 
                                    :alt="`${venueName} photo ${((currentGalleryIndex + 1) % venueGallery.length) + 1}`" 
                                    @click="openLightbox((currentGalleryIndex + 1) % venueGallery.length)"
                                    class="max-h-full max-w-full object-contain rounded-lg transition-all duration-350 cursor-zoom-in hover:scale-[1.02]" 
                                />
                            </div>
                        </div>
                        
                        <!-- Navigation Arrows -->
                        <div v-if="venueGallery.length > 1">
                            <button
                                type="button"
                                @click="prevGalleryImage"
                                class="absolute left-3 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 dark:bg-[#0f0f0f]/90 text-slate-800 dark:text-white shadow-md hover:bg-white dark:hover:bg-black transition-all opacity-0 group-hover/gallery:opacity-100 focus:opacity-100"
                            >
                                <ChevronLeft class="h-5 w-5" />
                            </button>
                            <button
                                type="button"
                                @click="nextGalleryImage"
                                class="absolute right-3 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 dark:bg-[#0f0f0f]/90 text-slate-800 dark:text-white shadow-md hover:bg-white dark:hover:bg-black transition-all opacity-0 group-hover/gallery:opacity-100 focus:opacity-100"
                            >
                                <ChevronRight class="h-5 w-5" />
                            </button>
                        </div>
                        
                        <!-- Counter Badge -->
                        <div class="absolute bottom-3 right-3 rounded-full bg-black/60 px-2.5 py-0.5 text-[11px] font-bold text-white backdrop-blur-sm">
                            {{ currentGalleryIndex + 1 }} / {{ venueGallery.length }}
                        </div>
                        
                        <!-- Mini Dot Indicators -->
                        <div v-if="venueGallery.length > 1" class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 px-2.5 py-1 rounded-full bg-black/35 backdrop-blur-sm">
                            <button
                                v-for="(_, idx) in venueGallery"
                                :key="idx"
                                type="button"
                                @click="currentGalleryIndex = idx"
                                class="h-1.5 rounded-full transition-all duration-300"
                                :class="currentGalleryIndex === idx ? 'bg-white w-3' : 'bg-white/50 w-1.5'"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div
                class="w-full rounded-3xl border border-slate-200 bg-white/95 shadow-lg shadow-slate-200/50 transition-all dark:border-slate-800 dark:bg-[#0f0f0f] dark:shadow-none"
            >
                <!-- Calendar Header -->
                <div
                    class="flex flex-col gap-4 border-b border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-[#0a0a0a] sm:p-5 md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap">
                        <h2 class="text-heading flex items-center text-base font-black text-slate-900 dark:text-[#EDEDEC] sm:text-lg">
                            <CalendarIcon class="mr-2 h-4 w-4 text-primary sm:mr-3 sm:h-6 sm:w-6" />
                            {{ venueName }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="showStatsModal = true"
                                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50 transition-all dark:border-slate-800 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                            >
                                <Trophy class="h-3.5 w-3.5 text-amber-500" />
                                <span>Leaderboard</span>
                            </button>
                            <div
                                v-if="operationalHours"
                                class="inline-flex items-center gap-1.5 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300"
                            >
                                <Clock class="h-3.5 w-3.5 shrink-0" />
                                <span>{{ operationalHours }}</span>
                            </div>
                            <div
                                class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300"
                            >
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Approved
                            </div>
                            <div
                                class="inline-flex items-center gap-1.5 rounded-full border border-amber-100 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                            >
                                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                Pending
                            </div>
                            <div
                                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800/20 dark:text-slate-500"
                            >
                                <span class="h-2 w-2 rounded-full bg-slate-300 ring-1 ring-slate-400"></span>
                                Cancelled
                            </div>
                            <div
                                class="inline-flex items-center gap-1.5 rounded-full border border-red-100 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 dark:border-red-800/40 dark:bg-red-900/20 dark:text-red-300"
                            >
                                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                Rejected
                            </div>
                            <button
                                @click="toggleTheme"
                                class="rounded-xl border border-slate-200 bg-white p-2 text-slate-600 transition-all hover:bg-slate-50 dark:border-slate-700 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                                :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                            >
                                <Sun v-if="isDark" class="h-4 w-4" />
                                <Moon v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Operational Hours Button -->
                        <button
                            @click="showHoursModal = true"
                            class="inline-flex items-center gap-1 sm:gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 sm:px-3 sm:py-2 text-[10px] sm:text-xs font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50 transition-all dark:border-slate-800 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                        >
                            <Clock class="h-3.5 w-3.5 text-slate-400 group-hover:text-slate-600" />
                            <span class="hidden sm:inline">Hours</span>
                        </button>
                        <div class="flex space-x-1.5">
                            <button
                                @click="prevMonth"
                                class="rounded-xl bg-blue-600 p-1.5 font-medium text-white transition-all duration-200 hover:bg-blue-500 active:scale-95 dark:bg-green-600 dark:hover:bg-green-500 sm:p-2"
                            >
                                <ChevronLeft class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                            </button>
                            <button
                                @click="nextMonth"
                                class="rounded-xl bg-blue-600 p-1.5 font-medium text-white transition-all duration-200 hover:bg-blue-500 active:scale-95 dark:bg-green-600 dark:hover:bg-green-500 sm:p-2"
                            >
                                <ChevronRight class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="custom-scrollbar p-2.5 sm:p-3.5" :style="{ 'color-scheme': isDark ? 'dark' : 'light' }">
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
                        <div v-for="n in daysInMonth[0].getDay()" :key="'empty-' + n" class="min-h-[86px] sm:min-h-[96px]"></div>

                        <!-- Day Cells -->
                        <div
                            v-for="date in daysInMonth"
                            :key="date.toISOString()"
                            @click="isPastDate(date) || resolveAvailabilityForDate(formatDateToISO(date)).is_closed ? null : openDayDetail(date)"
                            class="group relative min-h-[86px] cursor-pointer rounded-xl border p-2 transition-all active:scale-[0.98] sm:min-h-[96px] sm:p-2.5"
                            :class="[
                                isToday(date) && !isDayFullyBooked(date)
                                    ? 'border-blue-300 border-slate-200 bg-white ring-2 ring-blue-400/70 hover:border-blue-400 hover:bg-blue-50/60 dark:border-green-500 dark:border-slate-700 dark:bg-[#0a0a0a] dark:ring-green-500/70 dark:hover:border-green-400 dark:hover:bg-green-900/20'
                                    : 'border-slate-200 bg-white hover:border-blue-300 hover:bg-blue-50/40 dark:border-slate-700 dark:bg-[#0a0a0a] dark:hover:border-green-500/70 dark:hover:bg-green-900/15',
                                resolveAvailabilityForDate(formatDateToISO(date)).is_closed ? 'border-rose-200 bg-rose-50/70 dark:border-rose-950 dark:bg-rose-950/20 cursor-not-allowed' : '',
                                isDayFullyBooked(date) && !resolveAvailabilityForDate(formatDateToISO(date)).is_closed ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' : '',
                                isPastDate(date) ? 'cursor-not-allowed opacity-60 grayscale' : '',
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
                            <div v-if="resolveAvailabilityForDate(formatDateToISO(date)).is_closed" class="mt-4 flex flex-col items-center justify-center text-center px-1 overflow-hidden w-full">
                                <span class="rounded bg-rose-100 dark:bg-rose-950 px-2 py-1 text-[11px] font-black uppercase tracking-wider text-rose-600 dark:text-rose-400 max-w-full overflow-hidden whitespace-nowrap block w-full relative">
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
                                <div v-if="getWeatherForDate(date)" class="mt-1 flex items-center gap-0.5 sm:mt-1.5">
                                    <span class="text-[10px] leading-none sm:text-[11px]">{{ weatherInfo(getWeatherForDate(date)!.code).emoji }}</span>
                                    <span
                                        class="text-[9px] font-black leading-none sm:text-[9px]"
                                        :class="weatherInfo(getWeatherForDate(date)!.code).color"
                                        >{{ getWeatherForDate(date)!.temp_max }}°</span
                                    >
                                </div>

                                <!-- Booking status dots -->
                                <div v-if="getBookingsForDate(date).length > 0" class="mt-auto flex flex-col gap-0.5 pt-1">
                                    <!-- Desktop: count + per-booking dots -->
                                    <div class="hidden items-center gap-1.5 sm:flex">
                                        <span class="text-[10px] font-black text-slate-500 dark:text-slate-400">{{
                                            getBookingsForDate(date).length
                                        }}</span>
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
                                                          : booking.status === 'cancelled'
                                                            ? 'bg-slate-300 ring-1 ring-slate-400'
                                                            : booking.status === 'rejected'
                                                              ? 'bg-rose-500'
                                                              : 'bg-slate-400',
                                                ]"
                                            ></div>
                                        </div>
                                    </div>
                                    <!-- Mobile: one dot per status present -->
                                    <div class="flex items-center gap-1.5 sm:hidden">
                                        <div
                                            v-if="getBookingsForDate(date).some((b) => b.status === 'approved')"
                                            class="h-2.5 w-2.5 rounded-full bg-emerald-500"
                                        ></div>
                                        <div
                                            v-if="getBookingsForDate(date).some((b) => b.status === 'pending')"
                                            class="h-2.5 w-2.5 rounded-full bg-amber-400"
                                        ></div>
                                        <div
                                            v-if="getBookingsForDate(date).some((b) => b.status === 'cancelled')"
                                            class="h-2.5 w-2.5 rounded-full bg-slate-300 ring-1 ring-slate-400"
                                        ></div>
                                        <div
                                            v-if="getBookingsForDate(date).some((b) => b.status === 'rejected')"
                                            class="h-2.5 w-2.5 rounded-full bg-rose-500"
                                        ></div>
                                        <span class="text-[9px] font-black text-slate-500">{{ getBookingsForDate(date).length }}</span>
                                    </div>
                                </div>
                            </template>

                            <!-- Hover Plus Button (desktop only) -->
                            <button
                                v-if="!isPastDate(date) && !isDayFullyBooked(date) && !resolveAvailabilityForDate(formatDateToISO(date)).is_closed"
                                @click.stop="openStepper(date)"
                                class="absolute bottom-3 right-3 hidden scale-90 transform rounded-xl bg-blue-600 p-2 text-white opacity-0 shadow-md shadow-blue-500/20 transition-all hover:bg-blue-700 group-hover:scale-100 group-hover:opacity-100 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500 sm:block"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- ─── DAY DETAIL MODAL ─── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showDayDetail"
                    :class="['fixed inset-0 z-50 flex items-center justify-center bg-black/55 px-4', { dark: isDark }]"
                    @click.self="closeDayDetail"
                >
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 translate-y-8 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-8 sm:scale-95"
                    >
                        <div
                            v-if="showDayDetail"
                            class="flex max-h-[85vh] w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-[#0f0f0f] sm:max-w-2xl md:max-w-3xl"
                        >
                            <div
                                class="relative flex shrink-0 items-center justify-between overflow-hidden rounded-t-2xl border-b border-slate-100 bg-gradient-to-br from-blue-600 to-indigo-700 p-5 text-white dark:border-slate-800 dark:from-green-600 dark:to-emerald-700 sm:p-6"
                            >
                                <div>
                                    <h2 class="text-lg font-black">
                                        {{ dayDetailDate ? dayDetailDate.getDate() : '' }} {{ monthName.split(' ')[0] }}
                                    </h2>
                                    <p class="text-[10px] font-bold uppercase tracking-widest opacity-80">Bookings for this day</p>
                                </div>
                                <button @click="closeDayDetail" class="rounded-xl bg-white/10 p-2 transition-colors hover:bg-white/20">
                                    <X class="h-5 w-5" />
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-5 sm:p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                                    <!-- LEFT COLUMN: TIME & SCHEDULE -->
                                    <div v-if="dayDetailSlots.length > 0" class="space-y-3">
                                        <div class="flex items-center gap-2">
                                            <Clock class="h-4 w-4 text-blue-500 dark:text-green-500" />
                                            <span class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Time & Schedule</span>
                                        </div>
                                        <div class="grid grid-cols-4 gap-2 max-h-[224px] overflow-y-auto pr-1 custom-scrollbar">
                                            <div
                                                v-for="slot in dayDetailSlots"
                                                :key="slot.start"
                                                class="flex flex-col items-center justify-center py-2 px-1 rounded-xl border text-center relative h-auto min-h-[48px]"
                                                :class="[
                                                    slot.isBooked
                                                      ? 'border-rose-100 bg-rose-50/30 text-rose-500 dark:border-rose-950/20 dark:bg-rose-950/5 dark:text-rose-400/70'
                                                      : slot.isPast
                                                        ? 'border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-800/40 dark:bg-slate-900/10 dark:text-slate-500'
                                                        : 'border-emerald-200 bg-emerald-50/50 text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-950/10 dark:text-emerald-400',
                                                ]"
                                            >
                                                <span class="text-[10px] font-black tracking-tight leading-none">{{ formatTime12h(slot.start).replace(':00', '') }}</span>
                                                <span class="text-[8px] font-bold text-slate-400 dark:text-slate-500 mt-0.5">to {{ formatTime12h(slot.end).replace(':00', '') }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- RIGHT COLUMN: BOOKINGS LIST -->
                                    <div class="space-y-3 md:border-l md:border-slate-100 md:dark:border-slate-800 md:pl-6">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Bookings List</span>
                                        </div>
                                        <div v-if="dayDetailBookings.length === 0" class="py-12 text-center border border-dashed border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50/30">
                                            <CalendarIcon class="mx-auto mb-3 h-10 w-10 text-slate-350 dark:text-slate-650" />
                                            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">No bookings yet.</p>
                                        </div>
                                        <div
                                            v-for="booking in dayDetailBookings"
                                            :key="booking.id"
                                            class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-[#0a0a0a]"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-black text-slate-900 dark:text-white">{{ booking.lead_name }}</span>
                                                    <span
                                                        class="rounded-lg px-2 py-0.5 text-[9px] font-black uppercase tracking-wide"
                                                        :class="
                                                            booking.type === 'walk-in'
                                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                                                : booking.type === 'reclub'
                                                                  ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'
                                                                  : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                                        "
                                                    >
                                                        {{ booking.type || 'booking' }}
                                                    </span>
                                                </div>
                                                <span
                                                    v-if="booking.status === 'pending'"
                                                    class="rounded-lg bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                                    >Pending</span
                                                >
                                                <span
                                                    v-else-if="booking.status === 'approved'"
                                                    class="rounded-lg bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                                                    >Approved</span
                                                >
                                                <span
                                                    v-else-if="booking.status === 'cancelled'"
                                                    class="rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-400 line-through dark:bg-slate-800 dark:text-slate-500"
                                                    >Cancelled</span
                                                >
                                                <span
                                                    v-else-if="booking.status === 'rejected'"
                                                    class="rounded-lg bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-700 dark:bg-rose-900/30 dark:text-rose-400"
                                                    >Rejected</span
                                                >
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400 sm:gap-3">
                                                <span class="flex items-center gap-1 text-sm font-bold text-slate-800 dark:text-slate-200"
                                                    ><Clock class="h-4 w-4 text-blue-500 dark:text-green-400" /> {{ formatTime12h(booking.start_time) }} –
                                                    {{ formatTime12h(booking.end_time) }}</span
                                                >
                                                <span class="flex items-center gap-1"
                                                    ><MapPin class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> C{{ booking.court_number }}</span
                                                >
                                                <span class="flex items-center gap-1"
                                                    ><Users class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> {{ booking.player_count }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action button at the bottom -->
                                <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                                    <button
                                        v-if="dayDetailDate && !isPastDate(dayDetailDate) && !isDayFullyBooked(dayDetailDate)"
                                        @click="
                                            openStepper(dayDetailDate);
                                            closeDayDetail();
                                        "
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-xs font-black uppercase tracking-widest text-white shadow-md shadow-blue-500/20 transition-all hover:bg-blue-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500"
                                    >
                                        <Plus class="h-4 w-4" /> BOOK THIS DAY
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ─── STEPPER MODAL ─── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showStepper"
                    :class="['fixed inset-0 z-50 flex items-center justify-center bg-black/55 px-4', { dark: isDark }]"
                    @click.self="closeStepper"
                >
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 translate-y-8 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-8 sm:scale-95"
                    >
                        <div
                            v-if="showStepper"
                            class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-[#0f0f0f]"
                            :class="step === 2 || step === 3 ? 'sm:max-w-[900px] w-full' : 'sm:max-w-[520px]'"
                        >
                            <!-- Success State -->
                            <div v-if="submitted" class="flex flex-1 flex-col justify-center p-8 text-center">
                                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50">
                                    <CheckCircle class="h-10 w-10 text-emerald-500" />
                                </div>
                                <h3 class="mb-2 text-xl font-black text-slate-900 dark:text-white">Booking Submitted!</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Your request is pending review. We'll contact you shortly.</p>
                            </div>

                            <template v-else>
                                <!-- Modal Header -->
                                <div
                                    class="shrink-0 bg-gradient-to-br from-blue-600 to-indigo-700 p-4 text-white dark:from-green-600 dark:to-emerald-700 sm:p-5"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h2 class="text-xl font-black">Reserve Court</h2>
                                            <p class="mt-0.5 text-xs font-bold opacity-80">{{ selectedDate }}</p>
                                        </div>
                                        <button @click="closeStepper" class="rounded-2xl bg-white/15 p-2.5 transition-colors hover:bg-white/25">
                                            <X class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Stepper -->
                                <div class="shrink-0 px-5 pt-4 sm:px-6">
                                    <div class="flex items-center">
                                        <template v-for="s in 3" :key="s">
                                            <div
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-black transition-all"
                                                :class="
                                                    step >= s
                                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25 dark:bg-green-600 dark:shadow-green-500/25'
                                                        : 'bg-slate-100 text-slate-400 dark:bg-[#1a1a1a]'
                                                "
                                            >
                                                {{ s }}
                                            </div>
                                            <div
                                                v-if="s < 3"
                                                class="mx-2 h-[3px] flex-1 rounded-full transition-all"
                                                :class="step > s ? 'bg-blue-600 dark:bg-green-600' : 'bg-slate-100 dark:bg-[#1a1a1a]'"
                                            ></div>
                                        </template>
                                    </div>
                                    <p class="pt-3 text-center text-xs font-black uppercase tracking-widest text-slate-400">
                                        {{ step === 1 ? 'Guest Details' : step === 2 ? 'Time & Court' : 'Payment Proof' }}
                                    </p>
                                </div>

                                <!-- General Server Error Banner -->
                                <div
                                    v-if="Object.keys(form.errors).length > 0"
                                    class="mx-5 mt-3 shrink-0 rounded-xl bg-red-50 p-3 text-xs font-black text-red-700 dark:bg-red-950/20 dark:text-red-400"
                                >
                                    <div class="flex items-start gap-1.5">
                                        <X class="h-4 w-4 shrink-0 mt-0.5" />
                                        <div class="space-y-0.5">
                                            <p class="font-bold text-[11px]">Submission Error:</p>
                                            <ul class="list-inside list-disc font-medium opacity-90">
                                                <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- ─── STEP 1: Guest Details ─── -->
                                <div v-if="step === 1" class="flex-1 space-y-3 overflow-y-auto p-4 sm:p-5">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div class="sm:col-span-2">
                                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <CheckCircle class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> Client Type
                                                </label>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <button
                                                        type="button"
                                                        disabled
                                                        class="rounded-xl border px-3 py-2.5 text-sm font-black transition-all cursor-default"
                                                        :class="[
                                                            isLoggedInPlayer
                                                                ? 'border-blue-600 bg-blue-600 text-white dark:border-green-600 dark:bg-green-600'
                                                                : 'border-slate-200 bg-white text-slate-400 dark:border-slate-800 dark:bg-[#0a0a0a]/50 dark:text-slate-600 opacity-60',
                                                        ]"
                                                    >
                                                        Player
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled
                                                        class="rounded-xl border px-3 py-2.5 text-sm font-black transition-all cursor-default"
                                                        :class="[
                                                            !isLoggedInPlayer
                                                                ? 'border-blue-600 bg-blue-600 text-white dark:border-green-600 dark:bg-green-600'
                                                                : 'border-slate-200 bg-white text-slate-400 dark:border-slate-800 dark:bg-[#0a0a0a]/50 dark:text-slate-600 opacity-60',
                                                        ]"
                                                    >
                                                        Client
                                                    </button>
                                                </div>
                                                <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                                    Client type is based on whether you are logged in with a player account.
                                                </p>
                                            </div>

                                        <!-- Left column -->
                                        <div class="space-y-3">
                                            <div>
                                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <User class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> Lead Guest Name
                                                </label>
                                                <input
                                                    v-model="form.lead_name"
                                                    type="text"
                                                    placeholder="Juan Dela Cruz"
                                                    autocomplete="name"
                                                    :disabled="isLoggedInPlayer"
                                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-80 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/10"
                                                />
                                                <p v-if="form.errors.lead_name" class="mt-1 text-[11px] font-medium text-red-500">
                                                    {{ form.errors.lead_name }}
                                                </p>
                                            </div>
                                            <div>
                                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <MapPin class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> Address / Contact
                                                </label>
                                                <input
                                                    v-model="form.lead_address"
                                                    type="text"
                                                    placeholder="City, Country"
                                                    autocomplete="street-address"
                                                    :disabled="isLoggedInPlayer"
                                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-80 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/10"
                                                />
                                            </div>
                                        </div>
                                        <!-- Right column -->
                                        <div class="space-y-3">
                                            <div>
                                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <Users class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> Players
                                                </label>
                                                <input
                                                    v-model.number="form.player_count"
                                                    type="number"
                                                    min="1"
                                                    max="20"
                                                    :disabled="isLoggedInPlayer"
                                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-80 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/10"
                                                />
                                            </div>
                                            <div>
                                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <Phone class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> Phone
                                                </label>
                                                <input
                                                    v-model="form.guest_phone"
                                                    type="tel"
                                                    inputmode="numeric"
                                                    pattern="[0-9]*"
                                                    maxlength="11"
                                                    placeholder="0917 123 4567"
                                                    autocomplete="tel"
                                                    @input="form.guest_phone = (form.guest_phone as string).replace(/\D/g, '').slice(0, 11)"
                                                    :disabled="isLoggedInPlayer"
                                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-80 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/10"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="isLoggedInPlayer" class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                        Step 1 is locked for player bookings and uses your saved player profile information.
                                    </p>
                                </div>

                                <!-- ─── STEP 2: Schedule & Court ─── -->
                                <div v-if="step === 2" class="flex-1 overflow-y-auto p-4 sm:p-5">
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                                        <!-- COLUMN 1: TIME SLOT GRID (5 COLS ON DESKTOP) -->
                                        <div class="space-y-3 md:col-span-6 lg:col-span-5">
                                            <div class="flex items-center gap-2">
                                                <Clock class="h-3.5 w-3.5 text-blue-500 dark:text-green-500" />
                                                <span class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Time & Schedule</span>
                                            </div>
                                            <div class="grid grid-cols-4 gap-2 max-h-[224px] overflow-y-auto pr-1 custom-scrollbar">
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
                                                </button>
                                            </div>
                                        </div>

                                        <!-- COLUMN 2: COURT SELECT & SUMMARY (7 COLS ON DESKTOP) -->
                                        <div class="space-y-4 md:col-span-6 lg:col-span-7">
                                            <!-- Court selection -->
                                            <div>
                                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <MapPin class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> Court
                                                </label>
                                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                    <button
                                                        v-for="c in courtCount"
                                                        :key="c"
                                                        type="button"
                                                        @click="toggleCourtSelection(c)"
                                                        :disabled="!availableCourts.includes(c) || courtIsWalkinOnly(c)"
                                                        class="relative rounded-xl border py-2.5 text-sm font-black transition-all"
                                                        :class="
                                                            form.courts.includes(c)
                                                                ? 'border-blue-600 bg-blue-600 text-white shadow-md dark:border-green-600 dark:bg-green-600'
                                                                : availableCourts.includes(c)
                                                                  ? 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 dark:border-slate-700 dark:bg-[#0a0a0a] dark:text-slate-300 dark:hover:border-green-400'
                                                                  : courtIsWalkinOnly(c)
                                                                    ? 'cursor-not-allowed border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400'
                                                                    : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-slate-700 dark:bg-[#1a1a1a] dark:text-slate-500'
                                                        "
                                                    >
                                                        C{{ c }}
                                                        <span
                                                            v-if="courtIsWalkinOnly(c)"
                                                            class="absolute -right-1.5 -top-1.5 rounded-full bg-amber-500 px-1 py-0 text-[7px] font-black text-white"
                                                            >WALK-IN</span
                                                        >
                                                    </button>
                                                </div>
                                                <p v-if="!form.start_time || !form.end_time" class="mt-1.5 text-[11px] font-medium text-amber-600">
                                                    Please select a time slot to proceed.
                                                </p>
                                                <p v-else-if="availableCourts.length === 0" class="mt-1.5 text-[11px] font-medium text-red-500">
                                                    <span v-if="walkinCourts.length > 0">All courts are fully booked or reserved for walk-in.</span>
                                                    <span v-else>All courts are fully booked for this day.</span>
                                                </p>
                                                <p v-if="isSelectedCourtWalkin" class="mt-1.5 text-[11px] font-medium text-amber-600">
                                                    Some selected courts are walk-in only — not available to book.
                                                </p>
                                            </div>

                                            <!-- Validation chips -->
                                            <div class="space-y-1.5">
                                                <p
                                                    v-if="!isStartTimeValid"
                                                    class="flex items-center rounded-lg border border-red-100 bg-red-50 p-2 text-[10px] font-black uppercase tracking-widest text-red-500 dark:border-red-900/40 dark:bg-red-950/20"
                                                >
                                                    <X class="mr-2 h-3 w-3 shrink-0" /> Before opening ({{ formatTime12h(props.settings.opening_time) }})
                                                </p>
                                                <p
                                                    v-if="!isEndTimeValid"
                                                    class="flex items-center rounded-lg border border-red-100 bg-red-50 p-2 text-[10px] font-black uppercase tracking-widest text-red-500 dark:border-red-900/40 dark:bg-red-950/20"
                                                >
                                                    <X class="mr-2 h-3 w-3 shrink-0" /> Exceeds closing ({{ formatTime12h(props.settings.closing_time) }})
                                                </p>
                                                <p
                                                    v-if="!isEndAfterStart"
                                                    class="flex items-center rounded-lg border border-red-100 bg-red-50 p-2 text-[10px] font-black uppercase tracking-widest text-red-500 dark:border-red-900/40 dark:bg-red-950/20"
                                                >
                                                    <X class="mr-2 h-3 w-3 shrink-0" /> End time must be after start time
                                                </p>
                                                <p
                                                    v-if="isTimeOverlapping"
                                                    class="flex items-center rounded-lg border border-red-100 bg-red-50 p-2 text-[10px] font-black uppercase tracking-widest text-red-500 dark:border-red-900/40 dark:bg-red-950/20"
                                                >
                                                    <X class="mr-2 h-3 w-3 shrink-0" /> Time slot taken on selected court(s)
                                                </p>
                                                <p
                                                    v-if="isSelectedTimeInPast"
                                                    class="flex items-center rounded-lg border border-red-100 bg-red-50 p-2 text-[10px] font-black uppercase tracking-widest text-red-500 dark:border-red-900/40 dark:bg-red-950/20"
                                                >
                                                    <X class="mr-2 h-3 w-3 shrink-0" /> Error: Selected time slot has already passed for today.
                                                </p>
                                                <p
                                                    v-if="(form.errors as any).time_slot"
                                                    class="flex items-center rounded-lg border border-red-100 bg-red-50 p-2 text-[10px] font-black uppercase tracking-widest text-red-500 dark:border-red-900/40 dark:bg-red-950/20"
                                                >
                                                    <X class="mr-2 h-3 w-3 shrink-0" /> {{ (form.errors as any).time_slot }}
                                                </p>
                                            </div>

                                            <!-- Live Summary -->
                                            <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-[#0a0a0a]">
                                                <p class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                    <DollarSign class="h-3.5 w-3.5" /> Booking Summary
                                                </p>
                                                <div class="flex justify-between text-sm">
                                                    <span class="font-medium text-slate-500 dark:text-slate-400">Rate</span>
                                                    <span class="font-bold text-slate-900 dark:text-white"
                                                        >₱{{ form.client_type === 'member' ? props.pricing.member_booking_rate : props.pricing.non_member_booking_rate }}/hr</span
                                                    >
                                                </div>
                                                <div class="flex justify-between text-sm">
                                                    <span class="font-medium text-slate-500 dark:text-slate-400">Duration</span>
                                                    <span class="font-bold text-slate-900 dark:text-white"
                                                        >{{ durationHours }} hr{{ durationHours !== 1 ? 's' : '' }}</span
                                                    >
                                                </div>
                                                <div class="flex justify-between text-sm">
                                                    <span class="font-medium text-slate-500 dark:text-slate-400">Court</span>
                                                    <span class="font-bold text-slate-900 dark:text-white">
                                                        {{ form.courts.map(c => 'C' + c).join(', ') }}
                                                    </span>
                                                </div>
                                                <div class="flex justify-between text-sm">
                                                    <span class="font-medium text-slate-500 dark:text-slate-400">Client Type</span>
                                                    <span class="font-bold text-slate-900 dark:text-white">{{ isLoggedInPlayer ? 'Player' : 'Client' }}</span>
                                                </div>
                                                <div class="flex justify-between text-sm">
                                                    <span class="font-medium text-slate-500 dark:text-slate-400">Membership</span>
                                                    <span class="font-bold text-slate-900 dark:text-white">{{ form.client_type === 'member' ? 'Member' : 'Non-member' }}</span>
                                                </div>
                                                <div class="h-px bg-slate-200 dark:bg-slate-800"></div>
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">Total</span>
                                                    <span class="text-lg font-black text-blue-600 dark:text-green-400">₱{{ totalCost.toFixed(2) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Navigation (Step 1 & 2) -->
                                <div v-if="step !== 3" class="flex shrink-0 gap-3 border-t border-slate-100 p-4 dark:border-slate-800 sm:p-5">
                                    <button
                                        v-if="step > 1"
                                        @click="prevStep"
                                        class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                                    >
                                        <ArrowLeft class="h-4 w-4" /> Back
                                    </button>
                                    <button
                                        v-if="step < 3"
                                        @click="nextStep"
                                        :disabled="
                                            (step === 1 && !canProceedStep1) || (step === 2 && (!canProceedStep2 || !!(form.errors as any).time_slot))
                                        "
                                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-500/20 transition-all hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500 dark:disabled:bg-[#1a1a1a] dark:disabled:text-slate-500"
                                    >
                                        {{ step === 1 ? 'Continue' : 'Review Booking' }} <ArrowRight class="h-4 w-4" />
                                    </button>
                                </div>

                                <!-- ─── STEP 3: Receipt Upload ─── -->
                                <div v-if="step === 3" class="flex flex-1 overflow-y-auto sm:overflow-hidden">
                                    <div class="grid w-full grid-cols-1 gap-3 p-4 sm:grid-cols-[1.2fr_2fr] sm:gap-4 sm:p-5">
                                        <p
                                            v-if="(form.errors as any).time_slot"
                                            class="col-span-2 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-[11px] font-black text-red-500 dark:border-red-900/40 dark:bg-red-950/20"
                                        >
                                            <X class="h-4 w-4 shrink-0" /> {{ (form.errors as any).time_slot }}
                                        </p>

                                        <!-- Left: Payment Reference -->
                                        <div class="flex flex-col sm:h-full">
                                            <div
                                                v-if="props.settings.payment_account_name || props.settings.payment_qr_photo"
                                                class="flex flex-col items-center space-y-1.5 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-[#0a0a0a] sm:flex-1 sm:space-y-4 sm:p-4"
                                            >
                                                <p
                                                    class="flex shrink-0 items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-slate-400"
                                                >
                                                    <CreditCard class="h-3.5 w-3.5" /> Payment Reference
                                                </p>
                                                <img
                                                    v-if="props.settings.payment_qr_photo"
                                                    :src="props.settings.payment_qr_photo"
                                                    @click="showEnlargedImage = true"
                                                    class="w-full h-full max-h-72 sm:max-h-none cursor-pointer rounded-xl object-contain sm:flex-1"
                                                />
                                                <p
                                                    v-if="props.settings.payment_account_name"
                                                    class="shrink-0 text-center text-sm font-black text-slate-900 dark:text-white sm:text-lg"
                                                >
                                                    {{ props.settings.payment_account_name }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Right: Upload + Notification + Summary + Buttons -->
                                        <div class="flex flex-col gap-3">
                                            <!-- Upload Proof of Payment -->
                                            <div>
                                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <Upload class="h-3.5 w-3.5 text-blue-500 dark:text-green-400" /> Upload Proof of Payment
                                                </label>
                                                <div class="relative">
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        @change="handleReceiptChange"
                                                        class="sr-only"
                                                        id="receipt-upload"
                                                    />
                                                    <label
                                                        for="receipt-upload"
                                                        class="flex h-20 w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 transition-all hover:border-blue-400 dark:border-slate-700 dark:bg-[#0a0a0a] dark:hover:border-green-400"
                                                    >
                                                        <template v-if="!receiptPreview">
                                                            <Upload class="mb-0.5 h-5 w-5 text-slate-400" />
                                                            <span class="text-xs font-semibold text-slate-600">Click to upload receipt</span>
                                                            <span class="mt-0.5 text-[9px] text-slate-400">PNG, JPG up to 5MB</span>
                                                        </template>
                                                        <img v-else :src="receiptPreview" class="h-full w-full rounded-xl object-contain" />
                                                    </label>
                                                </div>
                                                <p v-if="receiptError" class="mt-1 text-[11px] font-medium text-red-500">{{ receiptError }}</p>
                                            </div>

                                            <!-- Notification -->
                                            <div
                                                class="rounded-xl border border-blue-100 bg-blue-50 px-3 py-2.5 text-[11px] font-medium text-blue-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300"
                                            >
                                                Your reservation is submitted for review first. We will confirm your slot once approved.
                                            </div>

                                            <!-- Final Summary -->
                                            <div
                                                class="space-y-1.5 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-[#0a0a0a]"
                                            >
                                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Final Summary</p>
                                                <div class="flex justify-between text-xs">
                                                    <span class="text-slate-500 dark:text-slate-400">Name</span>
                                                    <span class="max-w-[140px] truncate font-bold text-slate-900 dark:text-white">{{
                                                        form.lead_name
                                                    }}</span>
                                                </div>
                                                <div class="flex justify-between text-xs">
                                                    <span class="text-slate-500 dark:text-slate-400">Date</span>
                                                    <span class="font-bold text-slate-900 dark:text-white">{{ form.booking_date }}</span>
                                                </div>
                                                <div class="flex justify-between text-xs">
                                                    <span class="text-slate-500 dark:text-slate-400">Time</span>
                                                    <span class="font-bold text-slate-900 dark:text-white"
                                                        >{{ formatTime12h(form.start_time) }} – {{ formatTime12h(form.end_time) }}</span
                                                    >
                                                </div>
                                                <div class="flex justify-between text-xs">
                                                    <span class="text-slate-500 dark:text-slate-400">Court</span>
                                                    <span class="font-bold text-slate-900 dark:text-white">Court {{ form.court_number }}</span>
                                                </div>
                                                <div class="flex justify-between text-xs">
                                                    <span class="text-slate-500 dark:text-slate-400">Client Type</span>
                                                    <span class="font-bold text-slate-900 dark:text-white">{{ form.client_type === 'member' ? 'Member' : 'Non-member' }}</span>
                                                </div>
                                                <div class="flex justify-between text-xs">
                                                    <span class="text-slate-500 dark:text-slate-400">Address</span>
                                                    <span class="max-w-[140px] truncate font-bold text-slate-900 dark:text-white">{{
                                                        form.lead_address
                                                    }}</span>
                                                </div>
                                                <div class="flex items-center justify-between border-t border-slate-200 pt-1.5 dark:border-slate-800">
                                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Total</span>
                                                    <span class="text-base font-black text-blue-600 dark:text-green-400"
                                                        >₱{{ totalCost.toFixed(2) }}</span
                                                    >
                                                </div>
                                            </div>

                                            <!-- Buttons -->
                                            <div class="flex flex-row gap-1.5 sm:gap-2">
                                                <button
                                                    v-if="step > 1"
                                                    @click="prevStep"
                                                    class="flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2.5 text-sm font-bold text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:gap-2 sm:px-5 sm:py-3"
                                                >
                                                    <ArrowLeft class="h-4 w-4" /> Back
                                                </button>
                                                <button
                                                    @click="submitBooking"
                                                    :disabled="form.processing || !canSubmitStep3"
                                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/20 transition-all hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500 dark:disabled:bg-[#1a1a1a] dark:disabled:text-slate-500 sm:gap-2 sm:px-5 sm:py-3"
                                                >
                                                    <CheckCircle class="h-4 w-4" /> Submit Request
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Enlarged Image Modal -->
        <Teleport to="body">
            <div
                v-if="showEnlargedImage"
                @click="showEnlargedImage = false"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black/70 p-4"
            >
                <div class="relative max-h-[90vh] max-w-[90vw]">
                    <button
                        @click.stop="showEnlargedImage = false"
                        class="absolute -right-3 -top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white shadow-lg dark:bg-white dark:text-slate-900"
                    >
                        <X class="h-4 w-4" />
                    </button>
                    <img :src="props.settings.payment_qr_photo" class="h-full w-full rounded-2xl object-contain" @click.stop />
                </div>
            </div>
        </Teleport>

        <!-- Operational Hours Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showHoursModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="showHoursModal = false"></div>
                    <div class="relative z-10 w-full max-w-md overflow-hidden bg-white dark:bg-[#0f0f0f] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl transition-all">
                        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-[#0a0a0a]">
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4 text-blue-600 dark:text-green-500" />
                                <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 dark:text-white">Operational Hours</h3>
                            </div>
                            <button @click="showHoursModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-[#1a1a1a]">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        
                        <div class="p-5 space-y-4">
                            <div class="space-y-3">
                                <div v-for="day in formattedWeeklyHours" :key="day.day_name" class="flex items-center justify-between border-b border-slate-100 last:border-b-0 py-2.5 dark:border-slate-800/50">
                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ day.day_name }}</span>
                                    <span class="text-xs font-semibold" :class="day.is_closed ? 'text-rose-500' : 'text-slate-600 dark:text-slate-400'">
                                        {{ day.hours }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- All-Time Stats Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showStatsModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="showStatsModal = false"></div>
                    <div class="relative z-10 w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden bg-white dark:bg-[#0f0f0f] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl transition-all">
                        <!-- Modal Header -->
                        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-[#0a0a0a]">
                            <div class="flex items-center gap-2">
                                <Trophy class="h-5 w-5 text-amber-500" />
                                <h3 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-white">All-Time Stats</h3>
                            </div>
                            <button @click="showStatsModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-[#1a1a1a]">
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Tab Selection & Search Row -->
                        <div class="flex shrink-0 flex-col gap-3 border-b border-slate-100 bg-slate-50/20 p-4 dark:border-slate-800/60 dark:bg-[#0a0a0a]/30 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex gap-2">
                                <button
                                    @click="statsTab = 'leaderboard'"
                                    class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
                                    :class="
                                        statsTab === 'leaderboard'
                                            ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900'
                                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                                    "
                                >
                                    <Trophy class="h-4 w-4" />
                                    Leaderboard
                                </button>
                                <button
                                    @click="statsTab = 'history'"
                                    class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
                                    :class="
                                        statsTab === 'history'
                                            ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900'
                                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                                    "
                                >
                                    <History class="h-4 w-4" />
                                    Match History
                                </button>
                            </div>

                            <!-- Search Input -->
                            <div v-if="statsTab === 'leaderboard'" class="relative w-full sm:w-64">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="statsSearchQuery"
                                    type="text"
                                    placeholder="Search players…"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-105 py-1.5 pl-9 pr-4 text-xs font-semibold text-slate-900 placeholder-slate-400 outline-none dark:border-slate-800 dark:bg-[#1a1a1a] dark:text-white"
                                />
                            </div>
                            <div v-else class="flex w-full items-center gap-2 sm:w-auto">
                                <div class="relative flex-1 sm:w-44" ref="statsMonthDropdownRef">
                                    <button
                                        @click.stop="statsShowMonthDropdown = !statsShowMonthDropdown"
                                        class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-105 px-3 py-1.5 text-xs font-semibold text-slate-900 dark:border-slate-800 dark:bg-[#1a1a1a] dark:text-white"
                                    >
                                        <span>{{ statsHistoryMonthFilter ? statsMonthLabel(statsHistoryMonthFilter) : 'All Months' }}</span>
                                        <ChevronDown class="h-3 w-3 text-slate-400" :class="statsShowMonthDropdown ? 'rotate-180' : ''" />
                                    </button>
                                    <div
                                        v-if="statsShowMonthDropdown"
                                        class="absolute right-0 z-50 mt-1 w-full min-w-[180px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-800 dark:bg-[#0f0f0f]"
                                    >
                                        <button
                                            @click.stop="statsHistoryMonthFilter = ''; statsShowMonthDropdown = false;"
                                            class="w-full px-4 py-2 text-left text-xs font-bold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]"
                                        >
                                            All Months
                                        </button>
                                        <div class="border-t border-slate-100 dark:border-slate-800"></div>
                                        <button
                                            v-for="month in statsAvailableMonths"
                                            :key="month"
                                            @click.stop="statsHistoryMonthFilter = month; statsShowMonthDropdown = false;"
                                            class="w-full px-4 py-2 text-left text-xs font-bold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]"
                                        >
                                            {{ statsMonthLabel(month) }}
                                        </button>
                                    </div>
                                </div>
                                <div class="relative flex-1 sm:w-48">
                                    <Search class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        v-model="statsHistorySearch"
                                        type="text"
                                        placeholder="Search player..."
                                        class="w-full rounded-xl border border-slate-200 bg-slate-105 py-1.5 pl-9 pr-4 text-xs font-semibold text-slate-900 placeholder-slate-400 outline-none dark:border-slate-800 dark:bg-[#1a1a1a] dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable Modal Content -->
                        <div class="custom-scrollbar flex-1 overflow-y-auto p-4 sm:p-5">
                            <!-- TAB: LEADERBOARD -->
                            <div v-if="statsTab === 'leaderboard'" class="space-y-4">
                                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-850">
                                    <table class="w-full border-collapse text-left text-xs">
                                        <thead>
                                            <tr class="bg-slate-50 dark:bg-[#0a0a0a]/60">
                                                <th class="w-16 px-4 py-3 text-center font-black uppercase text-slate-400">Rank</th>
                                                <th class="px-4 py-3 font-black uppercase text-slate-400">Player</th>
                                                <th class="px-4 py-3 text-center font-black uppercase text-slate-400">Matches</th>
                                                <th class="px-4 py-3 text-center font-black uppercase text-slate-400">Wins</th>
                                                <th class="px-4 py-3 text-center font-black uppercase text-slate-400">Losses</th>
                                                <th class="px-4 py-3 text-center font-black uppercase text-slate-400">Points</th>
                                                <th class="px-4 py-3 text-center font-black uppercase text-slate-400">Win Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="player in statsSortedPlayers"
                                                :key="player.id"
                                                class="border-t border-slate-100 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-[#1a1a1a]/40"
                                            >
                                                <td class="px-4 py-3.5 text-center font-black">
                                                    <span
                                                        v-if="statsRankMap.get(player.id) === 0"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded bg-amber-400 text-[10px] font-black text-slate-900"
                                                    >1</span>
                                                    <span
                                                        v-else-if="statsRankMap.get(player.id) === 1"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded bg-slate-300 text-[10px] font-black text-slate-900"
                                                    >2</span>
                                                    <span
                                                        v-else-if="statsRankMap.get(player.id) === 2"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded bg-orange-400 text-[10px] font-black text-white"
                                                    >3</span>
                                                    <span v-else class="text-slate-400">#{{ (statsRankMap.get(player.id) ?? 0) + 1 }}</span>
                                                </td>
                                                <td class="px-4 py-3.5 font-bold capitalize text-slate-900 dark:text-white">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 font-black text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                            {{ player.name.charAt(0).toUpperCase() }}
                                                        </div>
                                                        <span>{{ player.name }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3.5 text-center font-bold text-slate-700 dark:text-slate-300">{{ player.total_matches }}</td>
                                                <td class="px-4 py-3.5 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ player.wins }}</td>
                                                <td class="px-4 py-3.5 text-center font-bold text-rose-500">{{ player.losses }}</td>
                                                <td class="px-4 py-3.5 text-center font-bold text-indigo-600 dark:text-green-400">{{ player.points }}</td>
                                                <td class="px-4 py-3.5 text-center font-bold text-slate-700 dark:text-slate-300">{{ player.win_rate }}%</td>
                                            </tr>
                                            <tr v-if="statsSortedPlayers.length === 0">
                                                <td colspan="7" class="py-10 text-center text-slate-400">No players found</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- TAB: MATCH HISTORY -->
                            <div v-if="statsTab === 'history'" class="space-y-3">
                                <div v-if="statsGroupedMatches.length === 0" class="py-10 text-center text-slate-400">No matches found</div>
                                <div
                                    v-for="group in statsGroupedMatches"
                                    :key="group.date"
                                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-850 dark:bg-[#0a0a0a]/20"
                                >
                                    <!-- Date Header -->
                                    <div
                                        @click="toggleStatsDay(group.date)"
                                        class="flex cursor-pointer items-center justify-between bg-slate-50/50 px-4 py-3 hover:bg-slate-50 dark:bg-[#0a0a0a] dark:hover:bg-[#1a1a1a]/60"
                                    >
                                        <div class="flex items-center gap-2">
                                            <ChevronDown class="h-4 w-4 text-slate-400 transition-transform" :class="statsExpandedDays[group.date] ? '' : '-rotate-90'" />
                                            <span class="text-xs font-black text-slate-900 dark:text-white">
                                                {{ new Date(group.date).toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' }) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Expanded Sessions -->
                                    <div v-if="statsExpandedDays[group.date]" class="border-t border-slate-100 dark:border-slate-800">
                                        <div
                                            v-for="session in getDaySessions(group.matches)"
                                            :key="session.key"
                                            class="border-b border-slate-100 last:border-b-0 dark:border-slate-800"
                                        >
                                            <div class="flex items-center gap-2 bg-slate-50/20 px-4 py-2 dark:bg-[#1a1a1a]/20">
                                                <Clock
                                                    class="h-3.5 w-3.5"
                                                    :class="
                                                        session.matches[0]?.booking_type === 'reclub'
                                                            ? 'text-purple-400'
                                                            : session.matches[0]?.booking_type === 'booking'
                                                            ? 'text-emerald-400'
                                                            : 'text-amber-400'
                                                    "
                                                />
                                                <span
                                                    class="text-[10px] font-bold"
                                                    :class="
                                                        session.matches[0]?.booking_type === 'reclub'
                                                            ? 'text-purple-700 dark:text-purple-300'
                                                            : session.matches[0]?.booking_type === 'booking'
                                                            ? 'text-emerald-700 dark:text-emerald-300'
                                                            : 'text-amber-700 dark:text-amber-300'
                                                    "
                                                >
                                                    {{ session.label }}
                                                </span>
                                                <span class="ml-auto text-[10px] font-semibold text-slate-400">
                                                    {{ session.matches.length }} match{{ session.matches.length !== 1 ? 'es' : '' }}
                                                </span>
                                            </div>
                                            <div class="px-4 py-2">
                                                <div class="grid grid-cols-[1fr_60px_50px] gap-2 border-b border-slate-100 pb-1 text-[9px] font-black uppercase text-slate-400 dark:border-slate-800">
                                                    <span>Player</span>
                                                    <span class="text-center">W/L</span>
                                                    <span class="text-right">Points</span>
                                                </div>
                                                <div
                                                    v-for="(s, si) in getSessionLeaderboard(session.matches)"
                                                    :key="s.name"
                                                    class="grid grid-cols-[1fr_60px_50px] gap-2 border-b border-slate-50 py-1.5 last:border-b-0 dark:border-slate-800/50"
                                                >
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="w-3.5 text-center font-mono text-[9px] font-bold text-slate-400">{{ si + 1 }}.</span>
                                                        <span class="min-w-0 flex-1 truncate text-xs font-bold text-slate-900 dark:text-white">{{ s.name }}</span>
                                                    </div>
                                                    <span class="text-center text-xs font-semibold">
                                                        <span class="text-emerald-600 dark:text-emerald-400">{{ s.wins }}W</span>
                                                        <span class="text-slate-400">/</span>
                                                        <span class="text-rose-500">{{ s.losses }}L</span>
                                                    </span>
                                                    <span class="text-right text-xs font-bold text-indigo-600 dark:text-green-400">{{ s.points }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Photo Lightbox Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="isLightboxOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 p-4 backdrop-blur-md">
                    <!-- Close Button -->
                    <button 
                        type="button" 
                        @click="closeLightbox" 
                        class="absolute right-6 top-6 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 hover:scale-105 active:scale-95"
                    >
                        <X class="h-6 w-6" />
                    </button>

                    <!-- Prev Arrow -->
                    <button 
                        v-if="lightboxImages.length > 1"
                        type="button" 
                        @click="prevLightboxImage" 
                        class="absolute left-6 top-1/2 -translate-y-1/2 flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 hover:scale-105 active:scale-95 z-10"
                    >
                        <ChevronLeft class="h-8 w-8" />
                    </button>

                    <!-- Image Container -->
                    <div class="flex flex-col items-center select-none max-h-[85vh] max-w-[90vw]">
                        <img 
                            :src="lightboxImages[lightboxIndex]" 
                            alt="Venue photo large view" 
                            class="max-h-[80vh] max-w-full rounded-2xl object-contain shadow-2xl border border-white/10" 
                        />
                        <span class="mt-4 text-sm font-bold text-white/70 tracking-wider">
                            {{ lightboxIndex + 1 }} / {{ lightboxImages.length }}
                        </span>
                    </div>

                    <!-- Next Arrow -->
                    <button 
                        v-if="lightboxImages.length > 1"
                        type="button" 
                        @click="nextLightboxImage" 
                        class="absolute right-6 top-1/2 -translate-y-1/2 flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 hover:scale-105 active:scale-95 z-10"
                    >
                        <ChevronRight class="h-8 w-8" />
                    </button>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent !important;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
    border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.6);
}

.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
