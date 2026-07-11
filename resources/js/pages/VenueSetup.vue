<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, Camera, Clock3, ImagePlus, Mail, MapPin, Phone, Save, Tag, Trophy, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    venue: null | {
        id: number;
        name: string;
        address: string | null;
        tagline?: string | null;
        description?: string | null;
        court_count: number;
        covered_court_count?: number | null;
        is_active: boolean;
        logo_url?: string | null;
        cover_photo_url?: string | null;
        gallery_urls?: string[];
        contact_email?: string | null;
        contact_phone?: string | null;
        facebook_url?: string | null;
        amenities?: string[];
        default_hourly_rate?: number;
        opening_time?: string | null;
        closing_time?: string | null;
    };
    default_court_count: number;
}>();

const logoPreview = ref(props.venue?.logo_url ?? '');
const coverPreview = ref(props.venue?.cover_photo_url ?? '');

interface GalleryItem {
    type: 'existing' | 'new';
    url: string;
    file?: File;
}

const galleryItems = ref<GalleryItem[]>(
    (props.venue?.gallery_urls ?? []).map((url) => ({ type: 'existing', url }))
);

const galleryPreview = computed(() => galleryItems.value.map((item) => item.url));

const form = useForm({
    name: props.venue?.name ?? '',
    address: props.venue?.address ?? '',
    tagline: props.venue?.tagline ?? '',
    description: props.venue?.description ?? '',
    contact_email: props.venue?.contact_email ?? '',
    contact_phone: props.venue?.contact_phone ?? '',
    facebook_url: props.venue?.facebook_url ?? '',
    amenities: (props.venue?.amenities ?? []).join(', '),
    covered_court_count: props.venue?.covered_court_count ?? null,
    logo_photo: null as File | null,
    cover_photo: null as File | null,
    gallery_photos: [] as File[],
    existing_gallery_paths: '' as string,
});

const title = computed(() => (props.venue ? 'Venue Setup' : 'Create your venue profile'));
const heroTitle = computed(() => form.name || 'Your venue preview');
const amenitiesPreview = computed(() =>
    form.amenities
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
        .slice(0, 6),
);

const formatTime12h = (timeStr?: string | null) => {
    if (!timeStr) return '';
    const [rawHours, rawMinutes = '00'] = timeStr.split(':');
    const hours = Number(rawHours);
    const minutes = Number(rawMinutes);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) return timeStr;

    const suffix = hours >= 12 ? 'PM' : 'AM';
    const normalizedHour = hours % 12 || 12;
    const normalizedMinutes = String(minutes).padStart(2, '0');

    return `${normalizedHour}:${normalizedMinutes} ${suffix}`;
};

const operationalHoursPreview = computed(() => {
    const open = props.venue?.opening_time;
    const close = props.venue?.closing_time;

    if (!open || !close) {
        return 'Set your hours in Operational Hours settings';
    }

    return `${formatTime12h(open)} - ${formatTime12h(close)}`;
});

const displayCourtCount = computed(() => props.venue?.court_count ?? props.default_court_count ?? 1);

const updateSinglePreview = (event: Event, type: 'logo' | 'cover') => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (!file) {
        return;
    }

    if (type === 'logo') {
        form.logo_photo = file;
        logoPreview.value = URL.createObjectURL(file);
        return;
    }

    form.cover_photo = file;
    coverPreview.value = URL.createObjectURL(file);
};

const updateGalleryPreview = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files ?? []);
    files.forEach((file) => {
        galleryItems.value.push({
            type: 'new',
            file,
            url: URL.createObjectURL(file),
        });
    });
};

const removePhoto = (index: number) => {
    galleryItems.value.splice(index, 1);
};

const submit = () => {
    form.gallery_photos = galleryItems.value
        .filter((item) => item.type === 'new')
        .map((item) => item.file as File);

    // Serialize as JSON string so FormData transmits it intact
    form.existing_gallery_paths = JSON.stringify(
        galleryItems.value
            .filter((item) => item.type === 'existing')
            .map((item) => item.url)
    );

    form.post(route('venue-setup.store'));
};
</script>

<template>
    <Head title="Venue Setup" />

    <AppLayout :breadcrumbs="[{ title: 'Venue Setup', href: '/venue-setup' }]">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:p-8">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-blue-100 text-blue-700 dark:bg-green-900/30 dark:text-green-300">
                            <Building2 class="h-7 w-7" />
                        </div>
                        <div class="flex-1">
                            <h1 class="text-2xl font-black text-slate-900 dark:text-white">{{ title }}</h1>
                            <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                                Build the public face of your venue. Add branding, court details, venue photos, and contact information players can trust when they book.
                            </p>
                        </div>
                    </div>

                    <form class="mt-8 space-y-8" @submit.prevent="submit">
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="name">Venue name</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="Example: Puerto Pickle Bay"
                                    required
                                />
                                <p v-if="form.errors.name" class="text-xs font-medium text-rose-600">{{ form.errors.name }}</p>
                            </div>

                            <div class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="tagline">Short tagline</label>
                                <input
                                    id="tagline"
                                    v-model="form.tagline"
                                    type="text"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="Built for the community, ready for every rally."
                                />
                            </div>

                            <div class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="description">Venue description</label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="Tell players what makes your venue special."
                                />
                            </div>

                            <div class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="address">Address</label>
                                <textarea
                                    id="address"
                                    v-model="form.address"
                                    rows="3"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="Sayre Highway, Cagayan de Oro"
                                />
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="court_count_display">Total courts</label>
                                <input
                                    id="court_count_display"
                                    :value="displayCourtCount"
                                    type="text"
                                    readonly
                                    class="cursor-not-allowed rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 outline-none dark:border-[#1a1a1a] dark:bg-[#111] dark:text-slate-200"
                                />
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Managed from your Operational Hours and scheduler settings.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="covered_court_count">Covered courts</label>
                                <input
                                    id="covered_court_count"
                                    v-model="form.covered_court_count"
                                    type="number"
                                    min="0"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="Optional"
                                />
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="contact_email">Contact email</label>
                                <input
                                    id="contact_email"
                                    v-model="form.contact_email"
                                    type="email"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="venue@example.com"
                                />
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="contact_phone">Contact phone</label>
                                <input
                                    id="contact_phone"
                                    v-model="form.contact_phone"
                                    type="text"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="+63..."
                                />
                            </div>

                            <div class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="facebook_url">Facebook page</label>
                                <input
                                    id="facebook_url"
                                    v-model="form.facebook_url"
                                    type="url"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="https://facebook.com/yourvenue"
                                />
                            </div>

                            <div class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="amenities">Amenities</label>
                                <textarea
                                    id="amenities"
                                    v-model="form.amenities"
                                    rows="3"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#090909] dark:text-white"
                                    placeholder="Parking, Restrooms, Waiting Area, Cafeteria, WiFi"
                                />
                                <p class="text-xs text-slate-500 dark:text-slate-400">Separate each amenity with a comma.</p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="group grid gap-3 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-5 transition hover:border-blue-400 hover:bg-blue-50/40 dark:border-[#262626] dark:bg-[#0a0a0a] dark:hover:border-green-500 dark:hover:bg-green-950/10">
                                <span class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-white">
                                    <Tag class="h-4 w-4" />
                                    Venue logo
                                </span>
                                <div class="flex h-28 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#1a1a1a] dark:bg-[#050505]">
                                    <img v-if="logoPreview" :src="logoPreview" alt="Venue logo preview" class="h-full w-full object-cover" />
                                    <span v-else class="text-xs font-semibold text-slate-400">Upload your venue logo</span>
                                </div>
                                <input type="file" accept="image/*" class="hidden" @change="(event) => updateSinglePreview(event, 'logo')" />
                            </label>

                            <label class="group grid gap-3 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-5 transition hover:border-blue-400 hover:bg-blue-50/40 dark:border-[#262626] dark:bg-[#0a0a0a] dark:hover:border-green-500 dark:hover:bg-green-950/10">
                                <span class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-white">
                                    <Camera class="h-4 w-4" />
                                    Cover photo
                                </span>
                                <div class="flex h-28 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#1a1a1a] dark:bg-[#050505]">
                                    <img v-if="coverPreview" :src="coverPreview" alt="Venue cover preview" class="h-full w-full object-cover" />
                                    <span v-else class="text-xs font-semibold text-slate-400">Upload the main venue banner</span>
                                </div>
                                <input type="file" accept="image/*" class="hidden" @change="(event) => updateSinglePreview(event, 'cover')" />
                            </label>
                        </div>

                        <div class="grid gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                            <span class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-white">
                                <ImagePlus class="h-4 w-4" />
                                Venue gallery
                            </span>
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <!-- Existing / newly selected photo thumbnails with remove button -->
                                <div
                                    v-for="(image, index) in galleryPreview"
                                    :key="`${image}-${index}`"
                                    class="group/item relative h-36 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#1a1a1a] dark:bg-[#050505]"
                                >
                                    <img :src="image" :alt="`Gallery preview ${index + 1}`" class="h-full w-full object-cover" />
                                    <button
                                        type="button"
                                        @click="removePhoto(index)"
                                        class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-black/50 text-white opacity-0 transition-opacity hover:bg-black/75 group-hover/item:opacity-100"
                                        title="Remove photo"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <!-- Add photo card — the ONLY thing that opens the file picker -->
                                <label
                                    class="group/add flex h-36 cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white text-xs font-semibold text-slate-400 transition-colors hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-600 dark:border-[#262626] dark:bg-[#050505] dark:hover:border-green-500 dark:hover:bg-green-950/10 dark:hover:text-green-400"
                                >
                                    <Plus class="mb-2 h-6 w-6" />
                                    <span>Add photo</span>
                                    <input type="file" accept="image/*" multiple class="hidden" @change="updateGalleryPreview" />
                                </label>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Click a photo to remove it. Click <strong>Add photo</strong> to upload more.</p>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-5 dark:border-[#1a1a1a]">
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                These details will be used in the player venue cards and booking display.
                            </p>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-green-600 dark:text-white dark:hover:bg-green-500"
                            >
                                <Save class="h-4 w-4" />
                                Save venue display
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="space-y-5">
                    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                        <div class="h-48 bg-slate-100 dark:bg-[#111]">
                            <img v-if="coverPreview" :src="coverPreview" alt="Venue cover preview" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full items-center justify-center text-sm font-semibold text-slate-400">Cover photo preview</div>
                        </div>

                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-3xl border border-slate-200 bg-slate-100 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                                    <img v-if="logoPreview" :src="logoPreview" alt="Venue logo preview" class="h-full w-full object-cover" />
                                    <Building2 v-else class="h-8 w-8 text-slate-400" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-2xl font-black text-slate-900 dark:text-white">{{ heroTitle }}</h2>
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Available</span>
                                    </div>
                                    <p v-if="form.tagline" class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300">{{ form.tagline }}</p>
                                    <p class="mt-2 flex items-start gap-2 text-sm text-slate-500 dark:text-slate-400">
                                        <MapPin class="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>{{ form.address || 'Your venue address will appear here.' }}</span>
                                    </p>
                                </div>
                            </div>

                            <p class="mt-5 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                {{ form.description || 'Add a short story about your venue so players know the atmosphere, experience, and why they should book here.' }}
                            </p>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-[#0a0a0a]">
                                    <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Courts</p>
                                    <p class="mt-2 text-lg font-black text-slate-900 dark:text-white">{{ displayCourtCount }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ form.covered_court_count || 0 }} covered
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-[#0a0a0a]">
                                    <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Rate</p>
                                    <p class="mt-2 text-lg font-black text-slate-900 dark:text-white">
                                        PHP {{ Number(props.venue?.default_hourly_rate ?? 0).toFixed(0) || '0' }}/hr
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Uses your scheduler pricing settings</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-[#0a0a0a] sm:col-span-2">
                                    <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-slate-400">
                                        <Clock3 class="h-4 w-4" />
                                        Operational Hours
                                    </p>
                                    <p class="mt-2 text-lg font-black text-slate-900 dark:text-white">
                                        {{ operationalHoursPreview }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Synced from your scheduler Operational Hours settings.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <span
                                    v-for="amenity in amenitiesPreview"
                                    :key="amenity"
                                    class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                >
                                    {{ amenity }}
                                </span>
                                <span v-if="amenitiesPreview.length === 0" class="text-xs text-slate-400">Amenity tags will appear here.</span>
                            </div>

                            <div class="mt-6 grid gap-3 border-t border-slate-200 pt-5 text-sm dark:border-[#1a1a1a]">
                                <p class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                    <Mail class="h-4 w-4" />
                                    <span>{{ form.contact_email || 'No public email yet' }}</span>
                                </p>
                                <p class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                    <Phone class="h-4 w-4" />
                                    <span>{{ form.contact_phone || 'No contact number yet' }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                        <div class="flex items-center gap-3">
                            <Trophy class="h-5 w-5 text-blue-600 dark:text-green-400" />
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">What players will see</h3>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">
                            Your venue card will show the main photo, logo, rate, court count, and amenity highlights. Players can book directly from that card and request a tournament from the same venue entry.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
