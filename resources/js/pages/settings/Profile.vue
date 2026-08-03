<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, BadgeCheck, Camera, ChartColumn, ChevronDown, ChevronLeft, ChevronRight, ChevronUp, Eye, EyeOff, Globe, ImagePlus, Link2, Mail, MapPin, Percent, Plus, Trash2, Trophy, UserRound, X } from 'lucide-vue-next';
import { TransitionRoot } from '@headlessui/vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface PlayerProfile {
    id: number;
    phone?: string | null;
    birthday?: string | null;
    address?: string | null;
    is_member?: boolean;
    membership_expires_at?: string | null;
    wins?: number;
    losses?: number;
    total_matches?: number;
    points?: number;
    win_rate?: number;
    venue_id?: number | null;
    venue_name?: string | null;
}

interface ProfileStats {
    wins: number;
    losses: number;
    total_matches: number;
    points: number;
    win_rate: number;
}

interface PlayedVenue {
    id: number;
    name: string;
    wins?: number;
    losses?: number;
    matches_count?: number;
    points?: number;
    win_rate?: number;
    is_primary?: boolean;
    is_member?: boolean;
    membership_expires_at?: string | null;
}

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    playerProfile?: PlayerProfile | null;
    profileStats?: ProfileStats;
    playedVenues?: PlayedVenue[];
    allTimeStatsVisibleFields?: string[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);
const isPlayer = computed(() => user.value?.role === 'player');
const playedVenues = computed(() => props.playedVenues ?? []);
const statsVenueName = computed(() => props.playerProfile?.venue_name ?? 'Not assigned');

const playerStats = computed(() => ({
    wins: props.profileStats?.wins ?? props.playerProfile?.wins ?? 0,
    losses: props.profileStats?.losses ?? props.playerProfile?.losses ?? 0,
    total_matches: props.profileStats?.total_matches ?? props.playerProfile?.total_matches ?? 0,
    points: props.profileStats?.points ?? props.playerProfile?.points ?? 0,
    win_rate: props.profileStats?.win_rate ?? props.playerProfile?.win_rate ?? 0,
}));

interface SocialLinkItem {
    platform: string;
    url: string;
}

const getInitialSocialLinks = (): SocialLinkItem[] => {
    if (user.value.social_links && Array.isArray(user.value.social_links) && user.value.social_links.length > 0) {
        return user.value.social_links.slice(0, 3).map((item) => ({ platform: item.platform || 'website', url: item.url || '' }));
    }
    const legacy: SocialLinkItem[] = [];
    if (user.value.facebook_url) legacy.push({ platform: 'facebook', url: user.value.facebook_url });
    if (user.value.instagram_url) legacy.push({ platform: 'instagram', url: user.value.instagram_url });
    if (user.value.website_url) legacy.push({ platform: 'website', url: user.value.website_url });
    return legacy.slice(0, 3);
};

const form = useForm({
    first_name: user.value.first_name || '',
    middle_name: user.value.middle_name || '',
    last_name: user.value.last_name || '',
    suffix: user.value.suffix || '',
    gender: user.value.gender || '',
    gender_other: user.value.gender_other || '',
    username: user.value.username || '',
    email: user.value.email,
    facebook_url: user.value.facebook_url || '',
    instagram_url: user.value.instagram_url || '',
    website_url: user.value.website_url || '',
    social_links: getInitialSocialLinks(),
    phone: props.playerProfile?.phone || '',
    birthday: props.playerProfile?.birthday || '',
    address: props.playerProfile?.address || '',
    avatar: null as File | null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const avatarInput = ref<HTMLInputElement | null>(null);
const avatarPreview = ref(user.value.avatar || '');
const isEditing = ref(false);
const isAvatarExpanded = ref(false);
const showProfileAlertModal = ref(false);

const startEditingProfile = () => {
    isEditing.value = true;
    showProfileAlertModal.value = false;
    setTimeout(() => {
        document.getElementById('phone')?.focus();
    }, 100);
};

watch(() => props.playerProfile, (profile) => {
    if (isPlayer.value && (!profile?.phone || !profile?.address)) {
        showProfileAlertModal.value = true;
    }
}, { immediate: true });
const currentPasswordInput = ref<HTMLInputElement | null>(null);
const passwordInput = ref<HTMLInputElement | null>(null);
const showProfileSavedBanner = ref(props.status === 'profile-updated');
let profileSavedBannerTimer: ReturnType<typeof setTimeout> | null = null;

const displayName = computed(() => {
    const parts = [user.value.first_name, user.value.middle_name, user.value.last_name, user.value.suffix]
        .filter((part): part is string => Boolean(part && part.trim()));
    return parts.join(' ') || user.value.name || user.value.username || 'Player';
});

const displayHandle = computed(() => user.value.username || '@player');
const avatarInitial = computed(() => (displayName.value || 'P').trim().charAt(0).toUpperCase());
const playedVenuesList = computed(() => props.playedVenues ?? []);
const activeVenueIndex = ref(0);
const isVenueHistoryExpanded = ref(true);

const currentPlayedVenue = computed(() => {
    const list = playedVenuesList.value;
    if (list.length === 0) return null;
    return list[activeVenueIndex.value] ?? list[0];
});

const nextVenue = () => {
    const list = playedVenuesList.value;
    if (list.length <= 1) return;
    activeVenueIndex.value = (activeVenueIndex.value + 1) % list.length;
};

const prevVenue = () => {
    const list = playedVenuesList.value;
    if (list.length <= 1) return;
    activeVenueIndex.value = (activeVenueIndex.value - 1 + list.length) % list.length;
};

watch(playedVenuesList, (list) => {
    if (list.length > 0) {
        const primaryIdx = list.findIndex((v) => v.is_primary || v.name === statsVenueName.value);
        activeVenueIndex.value = primaryIdx !== -1 ? primaryIdx : 0;
    }
}, { immediate: true });

const emailVerified = computed(() => Boolean(user.value.email_verified_at));
const canResendVerification = computed(() => !emailVerified.value);
const allTimeStatsVisibleFields = ref<string[]>(props.allTimeStatsVisibleFields ?? []);
const visibilitySavingField = ref<string | null>(null);
const allTimeStatsVisibilityOptions = [
    'first_name',
    'middle_name',
    'last_name',
    'suffix',
    'gender',
    'username',
    'birthday',
    'address',
    'facebook_url',
    'instagram_url',
    'website_url',
] as const;
const genderOptions = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'other', label: 'Other' },
] as const;
const displayValue = (value?: string | null, fallback = 'Not provided') => {
    const normalized = typeof value === 'string' ? value.trim() : '';
    return normalized || fallback;
};
const displayBirthday = computed(() => {
    if (!form.birthday) return 'Not provided';

    const birthday = new Date(form.birthday);
    if (Number.isNaN(birthday.getTime())) {
        return form.birthday;
    }

    return birthday.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
});
const displayGender = computed(() => {
    if (form.gender === 'other') {
        return displayValue(form.gender_other);
    }
    const selected = genderOptions.find((option) => option.value === form.gender);
    return selected?.label ?? 'Not provided';
});

const socialPlatforms = [
    { value: 'facebook', label: 'Facebook', icon: Globe, description: 'Public page link', placeholder: 'https://facebook.com/yourname', field: 'facebook_url' as const },
    { value: 'instagram', label: 'Instagram', icon: ImagePlus, description: 'Photo sharing profile', placeholder: 'https://instagram.com/yourname', field: 'instagram_url' as const },
    { value: 'twitter', label: 'X / Twitter', icon: Link2, description: 'Social feed link', placeholder: 'https://x.com/yourname', field: 'website_url' as const },
    { value: 'youtube', label: 'YouTube', icon: Camera, description: 'Video highlights link', placeholder: 'https://youtube.com/@channel', field: 'website_url' as const },
    { value: 'tiktok', label: 'TikTok', icon: Camera, description: 'Short videos link', placeholder: 'https://tiktok.com/@yourname', field: 'website_url' as const },
    { value: 'linkedin', label: 'LinkedIn', icon: UserRound, description: 'Professional profile link', placeholder: 'https://linkedin.com/in/yourname', field: 'website_url' as const },
    { value: 'discord', label: 'Discord', icon: Mail, description: 'Community server link', placeholder: 'https://discord.gg/yourserver', field: 'website_url' as const },
    { value: 'website', label: 'Personal Website', icon: Link2, description: 'Personal or club page', placeholder: 'https://yourdomain.com', field: 'website_url' as const },
] as const;

const getPlatformMeta = (platformValue: string) => {
    return socialPlatforms.find((p) => p.value === platformValue) ?? {
        value: platformValue,
        label: platformValue ? platformValue.charAt(0).toUpperCase() + platformValue.slice(1) : 'Website',
        icon: Link2,
        description: 'Profile link',
        placeholder: 'https://...',
        field: 'website_url' as const,
    };
};

const addSocialLink = () => {
    if (form.social_links.length >= 3) return;
    const used = new Set(form.social_links.map((s) => s.platform));
    const available = socialPlatforms.find((p) => !used.has(p.value));
    form.social_links.push({
        platform: available ? available.value : 'website',
        url: '',
    });
};

const removeSocialLink = (index: number) => {
    form.social_links.splice(index, 1);
};

const isAllTimeStatsFieldVisible = (field: string) => allTimeStatsVisibleFields.value.includes(field);

const toggleAllTimeStatsFieldVisibility = async (field: (typeof allTimeStatsVisibilityOptions)[number]) => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const nextVisible = !isAllTimeStatsFieldVisible(field);
    visibilitySavingField.value = field;

    try {
        const response = await fetch(route('profile.visibility.update'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({
                field,
                visible: nextVisible,
            }),
        });

        if (!response.ok) {
            throw new Error('Failed to update visibility setting.');
        }

        const payload = await response.json();
        allTimeStatsVisibleFields.value = payload.visible_fields ?? [];
    } catch (error) {
        console.error(error);
    } finally {
        visibilitySavingField.value = null;
    }
};

const syncFormFromProps = () => {
    form.first_name = user.value.first_name || '';
    form.middle_name = user.value.middle_name || '';
    form.last_name = user.value.last_name || '';
    form.suffix = user.value.suffix || '';
    form.gender = user.value.gender || '';
    form.gender_other = user.value.gender_other || '';
    form.username = user.value.username || '';
    form.email = user.value.email;
    form.facebook_url = user.value.facebook_url || '';
    form.instagram_url = user.value.instagram_url || '';
    form.website_url = user.value.website_url || '';
    form.social_links = getInitialSocialLinks();
    form.phone = props.playerProfile?.phone || '';
    form.birthday = props.playerProfile?.birthday || '';
    form.address = props.playerProfile?.address || '';
    form.avatar = null;
    avatarPreview.value = user.value.avatar || '';
};

const openAvatarPicker = () => {
    if (!isEditing.value) {
        return;
    }

    avatarInput.value?.click();
};

const openAvatarExpanded = () => {
    if (!avatarPreview.value) {
        return;
    }

    isAvatarExpanded.value = true;
};

const closeAvatarExpanded = () => {
    isAvatarExpanded.value = false;
};

const enableEditing = () => {
    isEditing.value = true;
};

const cancelEditing = () => {
    syncFormFromProps();
    isEditing.value = false;
    passwordForm.reset();
};

const handleAvatarChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    form.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const submit = () => {
    form.post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showProfileSavedBanner.value = true;
            startProfileSavedBannerTimer();
            syncFormFromProps();
            isEditing.value = false;
            form.clearErrors();
        },
    });
};

const startProfileSavedBannerTimer = () => {
    if (profileSavedBannerTimer) {
        clearTimeout(profileSavedBannerTimer);
    }

    profileSavedBannerTimer = setTimeout(() => {
        showProfileSavedBanner.value = false;
        profileSavedBannerTimer = null;
    }, 5000);
};

watch(
    () => form.gender,
    (gender) => {
        if (gender !== 'other') {
            form.gender_other = '';
        }
    },
);

watch(
    () => props.status,
    (status) => {
        if (status === 'profile-updated') {
            showProfileSavedBanner.value = true;
            startProfileSavedBannerTimer();
            return;
        }

        showProfileSavedBanner.value = false;
    },
    { immediate: true },
);

watch(
    () => props.allTimeStatsVisibleFields,
    (fields) => {
        allTimeStatsVisibleFields.value = fields ?? [];
    },
    { immediate: true },
);

onUnmounted(() => {
    if (profileSavedBannerTimer) {
        clearTimeout(profileSavedBannerTimer);
    }
});

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: (errors: Record<string, string>) => {
            if (errors.password) {
                passwordForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }

            if (errors.current_password) {
                passwordForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="space-y-8">
                <section class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl shadow-slate-200/60 dark:border-white/10 dark:bg-card/80 dark:text-slate-100 dark:shadow-black/10">
                        <div class="mt-6 flex flex-col items-center gap-4 text-center">
                            <div class="relative">
                                <button
                                    type="button"
                                    class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 shadow-inner shadow-slate-200/50 transition hover:scale-[1.02] dark:border-white/10 dark:bg-slate-950/80"
                                    :class="avatarPreview ? 'cursor-zoom-in' : 'cursor-default'"
                                    :disabled="!avatarPreview"
                                    @click="openAvatarExpanded"
                                >
                                    <img v-if="avatarPreview" :src="avatarPreview" :alt="displayName" class="h-full w-full object-cover" />
                                    <span v-else class="text-4xl font-bold text-slate-500 dark:text-slate-400">{{ avatarInitial }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="absolute -bottom-2 -right-2 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-primary text-primary-foreground shadow-lg shadow-primary/25 transition hover:scale-105 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="!isEditing"
                                    @click="openAvatarPicker"
                                >
                                    <Camera class="h-4 w-4" />
                                </button>
                            </div>

                            <div>
                                <p class="text-xl font-semibold">{{ displayName }}</p>
                                <p class="text-sm text-slate-500 dark:text-muted-foreground">{{ displayHandle }}</p>
                            </div>

                            <div class="flex flex-wrap items-center justify-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="isMember ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'"
                                >
                                    <BadgeCheck class="h-3.5 w-3.5" />
                                    {{ isMember ? 'Member' : 'Non-member' }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="emailVerified ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-400'"
                                >
                                    <Mail class="h-3.5 w-3.5" />
                                    {{ emailVerified ? 'Email verified' : 'Email unverified' }}
                                </span>
                            </div>

                            <div class="w-full rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left shadow-sm shadow-slate-200/60 dark:border-white/10 dark:bg-slate-950/60 dark:shadow-none">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-transparent dark:bg-white/5">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-slate-500 dark:text-muted-foreground">Wins</p>
                                        <p class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ playerStats.wins }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-transparent dark:bg-white/5">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-slate-500 dark:text-muted-foreground">Losses</p>
                                        <p class="mt-1 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ playerStats.losses }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-transparent dark:bg-white/5">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-slate-500 dark:text-muted-foreground">Total</p>
                                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ playerStats.total_matches }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-transparent dark:bg-white/5">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-slate-500 dark:text-muted-foreground">Points</p>
                                        <p class="mt-1 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ playerStats.points }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-transparent dark:bg-white/5">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="inline-flex items-center gap-2 text-slate-500 dark:text-muted-foreground">
                                            <Percent class="h-4 w-4" />
                                            Win rate
                                        </span>
                                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ playerStats.win_rate }}%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="w-full rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-left dark:border-white/10 dark:bg-white/5 transition-all">
                                <div
                                    @click="isVenueHistoryExpanded = !isVenueHistoryExpanded"
                                    class="-m-1.5 p-1.5 rounded-xl flex items-center justify-between cursor-pointer select-none group transition-all hover:bg-slate-200/50 dark:hover:bg-white/10"
                                >
                                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">
                                        <Trophy class="h-4 w-4 text-amber-500 dark:text-amber-400 group-hover:scale-110 transition-transform duration-200" />
                                        <span class="group-hover:translate-x-0.5 transition-transform duration-200">Venue history</span>
                                        <component
                                            :is="isVenueHistoryExpanded ? ChevronUp : ChevronDown"
                                            class="h-4 w-4 text-slate-400 transition-all duration-200 group-hover:text-slate-700 dark:group-hover:text-slate-200 group-hover:scale-125"
                                        />
                                    </div>

                                    <!-- 1-by-1 Arrow Toggle Pager -->
                                    <div v-if="isVenueHistoryExpanded && playedVenuesList.length > 0" class="flex items-center gap-1.5" @click.stop>
                                        <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">
                                            {{ activeVenueIndex + 1 }} of {{ playedVenuesList.length }}
                                        </span>
                                        <button
                                            type="button"
                                            :disabled="playedVenuesList.length <= 1"
                                            @click="prevVenue"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed dark:border-white/10 dark:bg-white/10 dark:text-slate-200 dark:hover:bg-white/15 cursor-pointer"
                                            title="Previous venue"
                                        >
                                            <ChevronLeft class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="playedVenuesList.length <= 1"
                                            @click="nextVenue"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed dark:border-white/10 dark:bg-white/10 dark:text-slate-200 dark:hover:bg-white/15 cursor-pointer"
                                            title="Next venue"
                                        >
                                            <ChevronRight class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="isVenueHistoryExpanded">
                                    <!-- Empty State -->
                                    <div v-if="!currentPlayedVenue" class="mt-3 py-2 text-center text-xs italic text-slate-400">
                                        No venue history recorded yet.
                                    </div>

                                    <!-- Direct 1-by-1 Display with Individual Venue Stats -->
                                    <div v-else class="mt-3 space-y-3 text-sm">
                                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-2 dark:border-white/10">
                                            <span class="text-slate-500 dark:text-muted-foreground">Stats venue</span>
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="font-bold text-slate-900 dark:text-slate-100 truncate">{{ currentPlayedVenue.name }}</span>
                                                <span
                                                    class="shrink-0 rounded-md px-2 py-0.5 text-[9px] font-black uppercase tracking-wider"
                                                    :class="currentPlayedVenue.name === statsVenueName || currentPlayedVenue.is_primary ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-400'"
                                                >
                                                    {{ currentPlayedVenue.name === statsVenueName || currentPlayedVenue.is_primary ? 'Primary' : 'Visited' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="rounded-xl border border-slate-200/80 bg-white p-2 text-center shadow-sm dark:border-white/10 dark:bg-white/5">
                                                <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-muted-foreground">Wins</p>
                                                <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ currentPlayedVenue.wins ?? 0 }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200/80 bg-white p-2 text-center shadow-sm dark:border-white/10 dark:bg-white/5">
                                                <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-muted-foreground">Losses</p>
                                                <p class="text-base font-bold text-rose-600 dark:text-rose-400">{{ currentPlayedVenue.losses ?? 0 }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200/80 bg-white p-2 text-center shadow-sm dark:border-white/10 dark:bg-white/5">
                                                <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-muted-foreground">Total</p>
                                                <p class="text-base font-bold text-slate-900 dark:text-slate-100">{{ currentPlayedVenue.matches_count ?? 0 }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200/80 bg-white p-2 text-center shadow-sm dark:border-white/10 dark:bg-white/5">
                                                <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-muted-foreground">Points</p>
                                                <p class="text-base font-bold text-amber-600 dark:text-amber-400">{{ currentPlayedVenue.points ?? 0 }}</p>
                                            </div>
                                        </div>

                                        <div class="space-y-2 pt-1">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-slate-500 dark:text-muted-foreground">Venue Win Rate</span>
                                                <span class="font-bold text-slate-900 dark:text-slate-100">{{ currentPlayedVenue.win_rate ?? 0 }}%</span>
                                            </div>
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-slate-500 dark:text-muted-foreground">Membership</span>
                                                <span
                                                    class="inline-flex items-center gap-1 font-bold"
                                                    :class="currentPlayedVenue.is_member ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                                                >
                                                    {{ currentPlayedVenue.is_member ? '👑 Active Member' : '👤 Non-member' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="rounded-3xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl shadow-slate-200/60 dark:border-white/10 dark:bg-card/80 dark:text-slate-100 dark:shadow-black/10">
                                <div class="flex flex-col gap-2 border-b border-slate-200 pb-5 dark:border-white/10">
                                    <div class="flex items-center gap-3">
                                        <div class="rounded-2xl bg-primary/10 p-3 text-primary dark:bg-primary/15">
                                            <ChartColumn class="h-5 w-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="text-xl font-semibold">Personal information</h3>
                                            <p class="text-sm text-slate-500 dark:text-muted-foreground">Keep your booking identity complete and up to date.</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3 pt-2">
                                        <Button
                                            v-if="!isEditing"
                                            type="button"
                                            variant="outline"
                                            class="ml-auto rounded-full border border-slate-200 bg-slate-100 px-5 font-semibold text-slate-700 shadow-sm transition hover:bg-slate-200 dark:border-white/10 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:bg-slate-800/90"
                                            @click="enableEditing"
                                        >
                                            Edit information
                                        </Button>
                                        <template v-else>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="ml-auto rounded-full border border-slate-200 bg-white px-5 text-slate-700 shadow-sm hover:bg-slate-100 dark:border-white/10 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:bg-slate-800/90"
                                                @click="cancelEditing"
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                :disabled="form.processing"
                                                class="rounded-full bg-slate-900 px-5 text-white shadow-sm hover:bg-slate-800 dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90"
                                            >
                                                Save changes
                                            </Button>
                                        </template>
                                    </div>

                                    <div v-if="showProfileSavedBanner" class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                        Profile saved successfully.
                                    </div>
                                </div>

                                <div v-if="!isEditing" class="mt-6">
                                    <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                                        Click the eye beside each field to choose what other users are allowed to see in All-Time Stats.
                                    </div>
                                    <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>First name</Label>
                                            <button type="button" :disabled="visibilitySavingField === 'first_name'" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="toggleAllTimeStatsFieldVisibility('first_name')">
                                                <Eye v-if="isAllTimeStatsFieldVisible('first_name')" class="h-4 w-4" />
                                                <EyeOff v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100">{{ displayValue(form.first_name) }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Last name</Label>
                                            <button type="button" :disabled="visibilitySavingField === 'last_name'" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="toggleAllTimeStatsFieldVisibility('last_name')">
                                                <Eye v-if="isAllTimeStatsFieldVisible('last_name')" class="h-4 w-4" />
                                                <EyeOff v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100">{{ displayValue(form.last_name) }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Middle name <span class="text-xs text-slate-500 dark:text-muted-foreground">(Optional)</span></Label>
                                            <button type="button" :disabled="visibilitySavingField === 'middle_name'" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="toggleAllTimeStatsFieldVisibility('middle_name')">
                                                <Eye v-if="isAllTimeStatsFieldVisible('middle_name')" class="h-4 w-4" />
                                                <EyeOff v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100">{{ displayValue(form.middle_name) }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Suffix <span class="text-xs text-slate-500 dark:text-muted-foreground">(Optional)</span></Label>
                                            <button type="button" :disabled="visibilitySavingField === 'suffix'" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="toggleAllTimeStatsFieldVisibility('suffix')">
                                                <Eye v-if="isAllTimeStatsFieldVisible('suffix')" class="h-4 w-4" />
                                                <EyeOff v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100">{{ displayValue(form.suffix) }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Gender</Label>
                                            <button type="button" :disabled="visibilitySavingField === 'gender'" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="toggleAllTimeStatsFieldVisibility('gender')">
                                                <Eye v-if="isAllTimeStatsFieldVisible('gender')" class="h-4 w-4" />
                                                <EyeOff v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100">{{ displayGender }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Username</Label>
                                            <button type="button" :disabled="visibilitySavingField === 'username'" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="toggleAllTimeStatsFieldVisibility('username')">
                                                <Eye v-if="isAllTimeStatsFieldVisible('username')" class="h-4 w-4" />
                                                <EyeOff v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100">{{ displayValue(form.username) }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Email address</Label>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100 break-all">{{ displayValue(form.email) }}</p>
                                        <div v-if="canResendVerification" class="flex flex-wrap items-center gap-3 pt-1">
                                            <span class="text-xs text-slate-500 dark:text-muted-foreground">
                                                This email is not verified yet.
                                            </span>
                                            <Link
                                                :href="route('verification.send')"
                                                method="post"
                                                as="button"
                                                class="inline-flex items-center justify-center rounded-full border border-amber-300 bg-amber-100 px-4 py-2 text-xs font-semibold text-amber-900 transition hover:bg-amber-200 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20"
                                            >
                                                Resend verification email
                                            </Link>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Phone number</Label>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100">{{ displayValue(form.phone) }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Birthday</Label>
                                            <button type="button" :disabled="visibilitySavingField === 'birthday'" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="toggleAllTimeStatsFieldVisibility('birthday')">
                                                <Eye v-if="isAllTimeStatsFieldVisible('birthday')" class="h-4 w-4" />
                                                <EyeOff v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100">{{ displayBirthday }}</p>
                                    </div>

                                    <div class="sm:col-span-2 space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Address</Label>
                                            <button type="button" :disabled="visibilitySavingField === 'address'" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="toggleAllTimeStatsFieldVisibility('address')">
                                                <Eye v-if="isAllTimeStatsFieldVisible('address')" class="h-4 w-4" />
                                                <EyeOff v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-slate-100 whitespace-pre-line">{{ displayValue(form.address) }}</p>
                                    </div>
                                    </div>
                                </div>

                                <div v-else class="mt-6 grid gap-5 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="first_name">First name</Label>
                                        <Input id="first_name" v-model="form.first_name" autocomplete="given-name" placeholder="First name" :disabled="!isEditing" />
                                        <InputError :message="form.errors.first_name" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="last_name">Last name</Label>
                                        <Input id="last_name" v-model="form.last_name" autocomplete="family-name" placeholder="Last name" :disabled="!isEditing" />
                                        <InputError :message="form.errors.last_name" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="middle_name">Middle name <span class="text-xs text-slate-500 dark:text-muted-foreground">(Optional)</span></Label>
                                        <Input id="middle_name" v-model="form.middle_name" autocomplete="additional-name" placeholder="Middle name" :disabled="!isEditing" />
                                        <InputError :message="form.errors.middle_name" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="suffix">Suffix <span class="text-xs text-slate-500 dark:text-muted-foreground">(Optional)</span></Label>
                                        <Input id="suffix" v-model="form.suffix" autocomplete="honorific-suffix" placeholder="Jr., Sr., III" :disabled="!isEditing" />
                                        <InputError :message="form.errors.suffix" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="gender">Gender</Label>
                                        <select
                                            id="gender"
                                            v-model="form.gender"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                        >
                                            <option value="">Select gender</option>
                                            <option v-for="option in genderOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.gender" />
                                    </div>

                                    <div v-if="form.gender === 'other'" class="space-y-2">
                                        <Label for="gender_other">Please specify</Label>
                                        <Input
                                            id="gender_other"
                                            v-model="form.gender_other"
                                            placeholder="Enter your gender identity"
                                        />
                                        <InputError :message="form.errors.gender_other" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="username">
                                            Username
                                            <span v-if="isPlayer" class="text-red-500">*</span>
                                        </Label>
                                        <Input id="username" v-model="form.username" autocomplete="username" placeholder="username" :disabled="!isEditing" />
                                        <InputError :message="form.errors.username" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="email">Email address</Label>
                                        <Input id="email" v-model="form.email" type="email" autocomplete="email" placeholder="name@example.com" :disabled="!isEditing" />
                                        <InputError :message="form.errors.email" />
                                        <div v-if="canResendVerification" class="flex flex-wrap items-center gap-3 pt-1">
                                            <span class="text-xs text-slate-500 dark:text-muted-foreground">
                                                This email is not verified yet.
                                            </span>
                                            <Link
                                                :href="route('verification.send')"
                                                method="post"
                                                as="button"
                                                class="inline-flex items-center justify-center rounded-full border border-amber-300 bg-amber-100 px-4 py-2 text-xs font-semibold text-amber-900 transition hover:bg-amber-200 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20"
                                            >
                                                Resend verification email
                                            </Link>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="phone">
                                            Phone number
                                            <span v-if="isPlayer" class="text-red-500">*</span>
                                        </Label>
                                        <Input id="phone" v-model="form.phone" type="tel" autocomplete="tel" placeholder="09xx xxx xxxx" :disabled="!isEditing" />
                                        <InputError :message="form.errors.phone" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="birthday">Birthday</Label>
                                        <Input id="birthday" v-model="form.birthday" type="date" autocomplete="bday" :disabled="!isEditing" />
                                        <InputError :message="form.errors.birthday" />
                                    </div>

                                    <div class="sm:col-span-2 space-y-2">
                                        <Label for="address">
                                            Address
                                            <span v-if="isPlayer" class="text-red-500">*</span>
                                        </Label>
                                        <textarea
                                            id="address"
                                            v-model="form.address"
                                            rows="3"
                                            class="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            placeholder="City, country or full address"
                                            :disabled="!isEditing"
                                        />
                                        <InputError :message="form.errors.address" />
                                    </div>

                                    <div v-if="isEditing" class="sm:col-span-2 space-y-2">
                                        <Label for="avatar">Profile photo</Label>
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                            <input
                                                ref="avatarInput"
                                                id="avatar"
                                                type="file"
                                                accept="image/*"
                                                class="hidden"
                                                @change="handleAvatarChange"
                                            />
                                            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="openAvatarPicker">
                                                <ImagePlus class="mr-2 h-4 w-4" />
                                                Select image
                                            </Button>
                                            <p class="text-sm text-slate-500 dark:text-muted-foreground">
                                                Use a clear square photo so your player profile stays recognizable.
                                            </p>
                                        </div>
                                        <InputError :message="form.errors.avatar" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="isEditing" class="rounded-3xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl shadow-slate-200/60 dark:border-white/10 dark:bg-card/80 dark:text-slate-100 dark:shadow-black/10">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-2xl bg-violet-50 p-3 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                                        <ChartColumn class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-semibold">Password</h3>
                                        <p class="text-sm text-slate-500 dark:text-muted-foreground">Update your login password using your current password.</p>
                                    </div>
                                </div>

                                <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
                                    <div class="space-y-2">
                                        <Label for="current_password">Old password</Label>
                                        <PasswordInput
                                            id="current_password"
                                            ref="currentPasswordInput"
                                            v-model="passwordForm.current_password"
                                            autocomplete="current-password"
                                            placeholder="Old password"
                                        />
                                        <InputError :message="passwordForm.errors.current_password" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="password">New password</Label>
                                        <PasswordInput
                                            id="password"
                                            ref="passwordInput"
                                            v-model="passwordForm.password"
                                            autocomplete="new-password"
                                            placeholder="New password"
                                        />
                                        <InputError :message="passwordForm.errors.password" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="password_confirmation">Confirm new password</Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            v-model="passwordForm.password_confirmation"
                                            autocomplete="new-password"
                                            placeholder="Confirm new password"
                                        />
                                        <InputError :message="passwordForm.errors.password_confirmation" />
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <Button :disabled="passwordForm.processing" class="rounded-full px-5">Save password</Button>

                                        <TransitionRoot
                                            :show="passwordForm.recentlySuccessful"
                                            enter="transition ease-in-out"
                                            enter-from="opacity-0"
                                            leave="transition ease-in-out"
                                            leave-to="opacity-0"
                                        >
                                            <p class="text-sm text-emerald-700 dark:text-emerald-400">Password updated.</p>
                                        </TransitionRoot>
                                    </div>
                                </form>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl shadow-slate-200/60 dark:border-white/10 dark:bg-card/80 dark:text-slate-100 dark:shadow-black/10">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-2xl bg-sky-50 p-3 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400">
                                        <Globe class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-semibold">Social links</h3>
                                        <p class="text-sm text-slate-500 dark:text-muted-foreground">A compact area for pages or profiles you want players to recognize.</p>
                                    </div>
                                </div>

                                <!-- View Mode: No links -->
                                <div v-if="!isEditing && form.social_links.filter(l => l.url).length === 0" class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-4 text-center text-sm text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-muted-foreground">
                                    No social links added yet. Click <span class="font-semibold text-slate-600 dark:text-slate-300">Edit information</span> above to add your profile links.
                                </div>

                                <!-- View Mode: Has links -->
                                <div v-else-if="!isEditing" class="mt-5 grid gap-3 sm:grid-cols-3">
                                    <template v-for="(item, idx) in form.social_links.filter(l => l.url)" :key="idx">
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                                    <div class="rounded-xl bg-white p-2 text-slate-700 shadow-sm dark:bg-white/5 dark:text-slate-200">
                                                        <component :is="getPlatformMeta(item.platform).icon" class="h-4 w-4" />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ getPlatformMeta(item.platform).label }}</p>
                                                        <p class="text-xs text-slate-500 dark:text-muted-foreground">{{ getPlatformMeta(item.platform).description }}</p>
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    :disabled="visibilitySavingField === getPlatformMeta(item.platform).field"
                                                    class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-slate-100 shrink-0"
                                                    :title="isAllTimeStatsFieldVisible(getPlatformMeta(item.platform).field) ? 'Visible in All-Time Stats' : 'Hidden from All-Time Stats'"
                                                    @click="toggleAllTimeStatsFieldVisibility(getPlatformMeta(item.platform).field)"
                                                >
                                                    <Eye v-if="isAllTimeStatsFieldVisible(getPlatformMeta(item.platform).field)" class="h-4 w-4 text-emerald-500" />
                                                    <EyeOff v-else class="h-4 w-4 text-slate-400" />
                                                </button>
                                            </div>

                                            <div class="mt-4">
                                                <a
                                                    :href="item.url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="break-all text-sm font-medium text-sky-600 underline decoration-sky-300 underline-offset-4 transition hover:text-sky-500 dark:text-sky-400 dark:decoration-sky-700 dark:hover:text-sky-300"
                                                >
                                                    {{ item.url }}
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Edit Mode: Dynamic 3-link editor -->
                                <div v-else class="mt-5 space-y-4">
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div
                                            v-for="(item, index) in form.social_links"
                                            :key="index"
                                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60 space-y-3"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <div class="rounded-lg bg-white p-1.5 text-slate-700 shadow-sm dark:bg-white/5 dark:text-slate-200">
                                                        <component :is="getPlatformMeta(item.platform).icon" class="h-4 w-4" />
                                                    </div>
                                                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Link #{{ index + 1 }}</span>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="rounded-full p-1 text-red-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"
                                                    title="Remove link"
                                                    @click="removeSocialLink(index)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <div class="space-y-1">
                                                <Label class="text-xs text-slate-500">Platform</Label>
                                                <select
                                                    v-model="item.platform"
                                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                                >
                                                    <option v-for="plat in socialPlatforms" :key="plat.value" :value="plat.value">
                                                        {{ plat.label }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="space-y-1">
                                                <Label class="text-xs text-slate-500">URL</Label>
                                                <Input
                                                    v-model="item.url"
                                                    type="url"
                                                    :placeholder="getPlatformMeta(item.platform).placeholder"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between pt-1">
                                        <p class="text-xs text-slate-500 dark:text-muted-foreground">
                                            You can add up to <span class="font-bold text-slate-700 dark:text-slate-300">3 social profile links</span> max.
                                        </p>
                                        <Button
                                            v-if="form.social_links.length < 3"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-full border-dashed border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/20 dark:text-slate-200 dark:hover:bg-white/10"
                                            @click="addSocialLink"
                                        >
                                            <Plus class="mr-1 h-3.5 w-3.5" /> Add social link ({{ form.social_links.length }}/3)
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="space-y-1">
                                        <p class="font-semibold text-amber-800 dark:text-amber-300">Verify your email address</p>
                                        <p class="text-sm text-amber-800/80 dark:text-amber-100/80">
                                            Your account is safer when the email on file is verified. You can resend the verification email anytime.
                                        </p>
                                    </div>

                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="inline-flex items-center justify-center rounded-md border border-amber-300 bg-amber-100 px-4 py-2 text-sm font-medium text-amber-900 transition hover:bg-amber-200 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20"
                                    >
                                        Resend verification email
                                    </Link>
                                </div>

                                <div v-if="props.status === 'verification-link-sent'" class="mt-4 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                    A new verification link has been sent to your email address.
                                </div>
                            </div>
                        </form>

                        <DeleteUser v-if="isEditing" />
                    </div>
                </section>
            </div>
        </SettingsLayout>

        <TransitionRoot
            :show="isAvatarExpanded"
            as="template"
            enter="transition ease-out duration-200"
            enter-from="opacity-0"
            enter-to="opacity-100"
            leave="transition ease-in duration-150"
            leave-from="opacity-100"
            leave-to="opacity-0"
        >
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-sm" @click="closeAvatarExpanded">
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                    @click.stop="closeAvatarExpanded"
                >
                    <X class="h-5 w-5" />
                </button>

                <img
                    v-if="avatarPreview"
                    :src="avatarPreview"
                    :alt="displayName"
                    class="max-h-[85vh] max-w-[90vw] rounded-3xl object-contain shadow-2xl"
                    @click.stop
                />
            </div>
        </TransitionRoot>

        <Dialog :open="showProfileAlertModal" @update:open="showProfileAlertModal = $event">
            <DialogContent class="sm:max-w-md [&>button]:hidden">
                <DialogHeader class="flex flex-col items-center justify-center text-center space-y-4">
                    <div class="rounded-full bg-amber-50 p-3 text-amber-500 dark:bg-amber-950/30 dark:text-amber-400">
                        <AlertCircle class="h-8 w-8" />
                    </div>
                    <DialogTitle class="text-xl font-bold text-slate-900 dark:text-white">Profile Details Required</DialogTitle>
                    <DialogDescription class="text-sm text-slate-500 dark:text-slate-400">
                        You must complete your profile by providing your <strong>Phone Number</strong> and <strong>Address</strong> before you can proceed.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="sm:justify-center">
                    <Button 
                        type="button" 
                        class="w-full rounded-full bg-blue-600 hover:bg-blue-700 text-white dark:bg-green-600 dark:hover:bg-green-700 font-semibold"
                        @click="startEditingProfile"
                    >
                        Okay, Fill in Details
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
