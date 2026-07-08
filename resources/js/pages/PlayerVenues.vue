<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Calendar, MapPin, Phone, Send, ShieldCheck, Swords, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
        amenities?: string[];
        default_hourly_rate?: number;
        contact_phone?: string | null;
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

const form = useForm({
    venue_id: null as number | null,
    name: '',
    preferred_date: '',
    preferred_start_time: '',
    notes: '',
});

const openTournamentRequest = (venueId: number) => {
    selectedVenueId.value = venueId;
    form.venue_id = venueId;
    form.clearErrors();
};

const cancelTournamentRequest = () => {
    selectedVenueId.value = null;
    form.reset('name', 'preferred_date', 'preferred_start_time', 'notes');
    form.venue_id = null;
    form.clearErrors();
};

const submit = () => {
    form.venue_id = selectedVenueId.value;
    form.post(route('tournament-requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cancelTournamentRequest();
        },
    });
};

const currency = (value?: number | null) => {
    if (!value) {
        return 'Ask venue';
    }

    return `PHP ${Number(value).toFixed(0)}/hr`;
};

const bookingStatusClass = (status: string) => {
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

                            <div class="absolute inset-x-0 top-0 flex items-start justify-between p-4">
                                <span class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-bold text-white shadow-md">Available</span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-600 px-3 py-1 text-xs font-bold text-white shadow-md dark:bg-green-600">
                                    <ShieldCheck class="h-3.5 w-3.5" />
                                    Venue
                                </span>
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

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="amenity in (venue.amenities || []).slice(0, 5)"
                                    :key="`${venue.id}-${amenity}`"
                                    class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                >
                                    {{ amenity }}
                                </span>
                                <span v-if="(venue.amenities || []).length > 5" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-[#1a1a1a] dark:text-slate-300">
                                    +{{ (venue.amenities || []).length - 5 }} more
                                </span>
                            </div>

                            <div v-if="venue.contact_phone" class="mt-4 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                <Phone class="h-4 w-4" />
                                <span>{{ venue.contact_phone }}</span>
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
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">My Tournament Requests</h2>
                        <div class="mt-4 space-y-3">
                            <div
                                v-for="requestItem in requests"
                                :key="requestItem.id"
                                class="rounded-2xl border border-slate-200 p-4 dark:border-[#1a1a1a]"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ requestItem.name }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ requestItem.venue?.name }}</p>
                                    </div>
                                    <span
                                        class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-wider"
                                        :class="
                                            requestItem.status === 'approved'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                : requestItem.status === 'rejected'
                                                  ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'
                                                  : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'
                                        "
                                    >
                                        {{ requestItem.status }}
                                    </span>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                    <span v-if="requestItem.preferred_date" class="inline-flex items-center gap-1">
                                        <Calendar class="h-3.5 w-3.5" />
                                        {{ requestItem.preferred_date }}
                                    </span>
                                    <span v-if="requestItem.preferred_start_time">{{ requestItem.preferred_start_time }}</span>
                                </div>
                                <p v-if="requestItem.notes" class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ requestItem.notes }}</p>
                                <p v-if="requestItem.rejection_reason" class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ requestItem.rejection_reason }}</p>
                                <p v-if="requestItem.tournamentDay && !requestItem.tournament" class="mt-3 text-sm text-emerald-600 dark:text-emerald-300">
                                    Approved main folder ready: {{ requestItem.tournamentDay.name }} ({{ requestItem.tournamentDay.status }})
                                </p>
                                <p v-if="requestItem.tournament" class="mt-3 text-sm text-emerald-600 dark:text-emerald-300">
                                    Tournament access: {{ requestItem.tournament.name }} ({{ requestItem.tournament.status }})
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
                            <div
                                v-for="booking in bookings"
                                :key="booking.id"
                                class="rounded-2xl border border-slate-200 p-4 dark:border-[#1a1a1a]"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ booking.venue_name || 'Venue booking' }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ booking.player_username }}</p>
                                    </div>
                                    <span
                                        class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-wider"
                                        :class="bookingStatusClass(booking.status)"
                                    >
                                        {{ booking.status }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                    <p class="inline-flex items-center gap-2">
                                        <Calendar class="h-4 w-4 text-blue-600 dark:text-green-400" />
                                        <span>{{ booking.booking_date }} · {{ booking.start_time }} - {{ booking.end_time }}</span>
                                    </p>
                                    <p>Court {{ booking.court_number }} · {{ booking.player_count }} players · {{ booking.client_type || 'booking' }}</p>
                                    <p>Total: PHP {{ Number(booking.total_cost).toFixed(2) }}</p>
                                    <p v-if="booking.payment_status" class="text-xs uppercase tracking-wider text-slate-400">
                                        Payment: {{ booking.payment_status }}
                                    </p>
                                </div>
                            </div>
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
            <div class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl dark:bg-[#0f0f0f] sm:rounded-[2rem]">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-5 dark:border-[#1a1a1a] sm:px-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <Swords class="h-5 w-5 text-blue-600 dark:text-green-400" />
                            <h3 class="text-xl font-black text-slate-900 dark:text-white">Request a tournament</h3>
                        </div>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Send your preferred tournament details to <span class="font-bold text-slate-700 dark:text-slate-200">{{ selectedVenue.name }}</span>.
                            Once approved, your main folder will be ready. You can choose the tournament category later when you create the tournament card.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="cancelTournamentRequest"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:border-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#171717] dark:hover:text-white"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <form class="flex-1 overflow-y-auto px-5 py-5 sm:px-6" @submit.prevent="submit">
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Tournament name</label>
                            <input v-model="form.name" type="text" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-rose-500">{{ form.errors.name }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Preferred date</label>
                                <input v-model="form.preferred_date" type="date" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white" />
                                <p v-if="form.errors.preferred_date" class="mt-1 text-xs text-rose-500">{{ form.errors.preferred_date }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Preferred start time</label>
                                <input v-model="form.preferred_start_time" type="time" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white" />
                                <p v-if="form.errors.preferred_start_time" class="mt-1 text-xs text-rose-500">{{ form.errors.preferred_start_time }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Notes</label>
                            <textarea v-model="form.notes" rows="5" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"></textarea>
                            <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-500">{{ form.errors.notes }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 dark:border-[#1a1a1a] sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            @click="cancelTournamentRequest"
                            class="inline-flex min-h-[48px] items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:text-slate-200 dark:hover:bg-[#171717]"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || !selectedVenueId || !form.name"
                            class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            <Send class="h-4 w-4" />
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
