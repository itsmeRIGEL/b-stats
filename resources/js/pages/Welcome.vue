<script setup lang="ts">
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import InputError from '@/components/InputError.vue';
import type { SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Eye, EyeOff, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const modal = ref<'login' | 'register' | null>(null);
const loginRole = ref<'scheduler' | 'scorer' | 'scheduler_scorer' | 'player'>('scheduler');
const openLogin = (role: 'scheduler' | 'scorer' | 'scheduler_scorer' | 'player' = 'scheduler') => {
    modal.value = 'login';
    loginRole.value = role;
};
const registerRole = ref<'player' | 'scheduler'>('player');
const openRegister = (role: 'player' | 'scheduler' = 'player') => {
    modal.value = 'register';
    registerRole.value = role;
};
const closeModal = () => {
    modal.value = null;
    loginForm.reset();
    registerForm.reset();
};

const page = usePage<SharedData>();
const userRole = computed(() => page.props.auth.user?.role);
const isScheduler = computed(() => userRole.value === 'scheduler' || userRole.value === 'scheduler_scorer');
const isScorer = computed(() => userRole.value === 'scorer' || userRole.value === 'scheduler_scorer');
const isPlayer = computed(() => userRole.value === 'player');

const loginForm = useForm({ email: '', password: '', remember: false, role: 'scheduler' });
const isLoggingIn = ref(false);
const showLoginPassword = ref(false);
const submitLogin = () => {
    isLoggingIn.value = true;
    loginForm.role = loginRole.value;
    loginForm.post(route('login'), {
        onFinish: () => {
            isLoggingIn.value = false;
            loginForm.reset('password');
        },
        onError: () => {
            isLoggingIn.value = false;
        },
    });
};

const registerForm = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'player',
});
const isRegistering = ref(false);
const showRegisterPassword = ref(false);
const showRegisterConfirmPassword = ref(false);
const submitRegister = () => {
    isRegistering.value = true;
    registerForm.role = registerRole.value;
    registerForm.post(route('register'), {
        onFinish: () => {
            isRegistering.value = false;
            registerForm.reset('password', 'password_confirmation');
        },
        onError: () => {
            isRegistering.value = false;
        },
    });
};

const logoutForm = useForm({});
const logout = () => {
    logoutForm.post(route('logout'));
};

/* ─── Mini Calendar ─── */
const calMonth = ref(new Date());
const calMonthName = computed(() => calMonth.value.toLocaleString('default', { month: 'long', year: 'numeric' }));

const formatDateISO = (date: Date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const calDays = computed(() => {
    const year = calMonth.value.getFullYear();
    const month = calMonth.value.getMonth();
    const date = new Date(year, month, 1);
    const days = [];
    while (date.getMonth() === month) {
        days.push(new Date(date));
        date.setDate(date.getDate() + 1);
    }
    return days;
});

const calPrev = () => {
    calMonth.value = new Date(calMonth.value.getFullYear(), calMonth.value.getMonth() - 1, 1);
};
const calNext = () => {
    calMonth.value = new Date(calMonth.value.getFullYear(), calMonth.value.getMonth() + 1, 1);
};
const calIsToday = (date: Date) => formatDateISO(new Date()) === formatDateISO(date);
// Cache bust comment to trigger new build hash for InfinityFree
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div class="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a]">
        <!-- Navigation -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/80 backdrop-blur-sm dark:border-slate-800 dark:bg-[#0a0a0a]/80">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link :href="route('home')" class="flex items-center gap-2">
                    <img :src="page.props.appLogo || '/logo.svg'" :alt="page.props.name" class="h-8 w-auto rounded-lg" />
                    <span class="text-xl font-bold tracking-tight text-[#1b1b18] dark:text-[#EDEDEC]">{{ page.props.name }}</span>
                </Link>
                <nav class="flex items-center gap-3">
                    <AppearanceToggle />
                    <template v-if="$page.props.auth.user">
                        <Link
                            v-if="isScheduler"
                            :href="route('bookings')"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Schedule
                        </Link>
                        <Link
                            v-else-if="isScorer"
                            :href="route('scoring')"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Score
                        </Link>
                        <Link
                            v-else-if="isPlayer"
                            :href="route('all-time-stats')"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            My Stats
                        </Link>
                        <Link
                            v-else
                            :href="route('dashboard')"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Dashboard
                        </Link>
                        <button
                            @click="logout"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-[#1b1b18] transition-colors hover:bg-slate-100 dark:border-slate-700 dark:text-[#EDEDEC] dark:hover:bg-slate-800"
                        >
                            Log out
                        </button>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('tournaments.live.index')"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Live
                        </Link>
                        <button
                            @click="() => openRegister('player')"
                            class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition-colors hover:bg-blue-100 dark:border-green-800 dark:bg-green-950/30 dark:text-green-300 dark:hover:bg-green-900/40"
                        >
                            Register
                        </button>
                        <button
                            @click="() => openLogin('scheduler')"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Log in
                        </button>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="flex-1">
            <section class="relative overflow-hidden px-6 py-20 lg:py-32">
                <div class="mx-auto max-w-6xl">
                    <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-8">
                        <div class="flex flex-col gap-6">
                            <div
                                class="inline-flex w-fit items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-green-900/20 dark:text-green-400 dark:ring-green-600/20"
                            >
                                <span class="relative flex h-2 w-2">
                                    <span
                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75 dark:bg-green-400"
                                    ></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-blue-500 dark:bg-green-500"></span>
                                </span>
                                Now open for bookings
                            </div>
                            <h1 class="text-4xl font-extrabold tracking-tight text-[#1b1b18] dark:text-[#EDEDEC] sm:text-5xl lg:text-6xl">
                                Book courts.<br />
                                <span class="text-blue-600 dark:text-green-400">Play pickleball.</span>
                            </h1>
                            <p class="max-w-lg text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                                The all-in-one platform for managing pickleball court bookings, match scheduling, and live score tracking. Built for
                                clubs, communities, and competitive leagues.
                            </p>
                            <div class="flex flex-wrap items-center gap-3">
                                <Link
                                    v-if="!$page.props.auth.user"
                                    :href="route('book')"
                                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-blue-600/20 transition-all hover:scale-[1.02] hover:bg-blue-700 active:scale-[0.98] dark:bg-green-600 dark:shadow-green-600/20 dark:hover:bg-green-500"
                                >
                                    Start Booking
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </Link>
                                <button
                                    v-if="!$page.props.auth.user"
                                    @click="() => openLogin('scheduler')"
                                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-6 py-3 text-base font-semibold text-[#1b1b18] transition-all hover:bg-slate-50 dark:border-slate-700 dark:text-[#EDEDEC] dark:hover:bg-slate-800"
                                >
                                    Scheduler Login
                                </button>
                                <button
                                    v-if="!$page.props.auth.user"
                                    @click="() => openRegister('player')"
                                    class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-6 py-3 text-base font-semibold text-blue-700 transition-all hover:bg-blue-100 dark:border-green-800 dark:bg-green-950/30 dark:text-green-300 dark:hover:bg-green-900/40"
                                >
                                    Register
                                </button>
                                <Link
                                    v-if="$page.props.auth.user"
                                    :href="route('dashboard')"
                                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-blue-600/20 transition-all hover:bg-blue-700 dark:bg-green-600 dark:shadow-green-600/20 dark:hover:bg-green-500"
                                >
                                    Go to Dashboard
                                </Link>
                            </div>
                        </div>
                        <div class="relative">
                            <div
                                class="relative rounded-3xl bg-gradient-to-br from-blue-50 to-yellow-50 p-6 ring-1 ring-inset ring-slate-900/5 dark:from-green-950/30 dark:to-yellow-950/20 dark:ring-white/10"
                            >
                                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-yellow-400/20 blur-2xl"></div>
                                <div class="absolute -bottom-4 -left-4 h-24 w-24 rounded-full bg-blue-400/20 blur-2xl dark:bg-green-400/20"></div>
                                <!-- Mini Calendar -->
                                <div
                                    class="relative overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10"
                                >
                                    <!-- Calendar Header -->
                                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                                        <div class="flex items-center gap-2">
                                            <CalendarIcon class="h-4 w-4 text-blue-600 dark:text-green-400" />
                                            <h3 class="text-sm font-bold text-[#1b1b18] dark:text-[#EDEDEC]">{{ calMonthName }}</h3>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                @click="calPrev"
                                                class="cursor-pointer rounded-lg p-1.5 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                            >
                                                <ChevronLeft class="h-4 w-4 text-slate-600 dark:text-slate-300" />
                                            </button>
                                            <button
                                                @click="calNext"
                                                class="cursor-pointer rounded-lg p-1.5 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                            >
                                                <ChevronRight class="h-4 w-4 text-slate-600 dark:text-slate-300" />
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Day Headers -->
                                    <div class="grid grid-cols-7 gap-1 px-4 pb-1 pt-3">
                                        <div
                                            v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']"
                                            :key="day"
                                            class="py-1 text-center text-[10px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            {{ day }}
                                        </div>
                                    </div>
                                    <!-- Day Cells -->
                                    <div class="grid grid-cols-7 gap-1 px-4 pb-4">
                                        <div v-for="n in calDays[0]?.getDay() ?? 0" :key="'e' + n" class="min-h-[36px]"></div>
                                        <Link
                                            v-for="date in calDays"
                                            :key="date.toISOString()"
                                            :href="route('book')"
                                            class="flex min-h-[36px] items-center justify-center rounded-lg text-sm font-semibold transition-all"
                                            :class="[
                                                calIsToday(date)
                                                    ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 hover:bg-blue-700 dark:bg-green-600 dark:shadow-green-600/30 dark:hover:bg-green-500'
                                                    : 'text-[#1b1b18] hover:bg-blue-50 hover:text-blue-600 dark:text-[#EDEDEC] dark:hover:bg-green-900/30 dark:hover:text-green-400',
                                            ]"
                                        >
                                            {{ date.getDate() }}
                                        </Link>
                                    </div>
                                    <!-- Footer CTA -->
                                    <div class="border-t border-slate-100 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-950">
                                        <Link
                                            :href="route('book')"
                                            class="flex items-center justify-center gap-2 text-sm font-semibold text-blue-600 transition-colors hover:text-blue-700 dark:text-green-400 dark:hover:text-green-300"
                                        >
                                            <CalendarIcon class="h-4 w-4" />
                                            View full calendar & book
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="border-t border-slate-200 bg-white px-6 py-20 dark:border-slate-800 dark:bg-[#0f0f0f]">
                <div class="mx-auto max-w-6xl">
                    <div class="mb-16 text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-[#1b1b18] dark:text-[#EDEDEC] sm:text-4xl">
                            Everything you need to run your courts
                        </h2>
                        <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">
                            From booking to scoring, manage your pickleball operations in one place.
                        </p>
                    </div>
                    <div class="grid justify-center gap-8 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="w-full max-w-sm rounded-2xl bg-slate-50 p-6 ring-1 ring-inset ring-slate-900/5 dark:bg-slate-900/50 dark:ring-white/5">
                            <div
                                class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-green-900/40 dark:text-green-400"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                            <h3 class="font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">Court Booking</h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                Reserve courts by time slot with real-time availability and instant confirmation.
                            </p>
                        </div>
                        <div class="w-full max-w-sm rounded-2xl bg-slate-50 p-6 ring-1 ring-inset ring-slate-900/5 dark:bg-slate-900/50 dark:ring-white/5">
                            <div
                                class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <h3 class="font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">Match Scheduling</h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                Organize tournaments, leagues, and casual games with automated scheduling.
                            </p>
                        </div>
                        <div class="w-full max-w-sm rounded-2xl bg-slate-50 p-6 ring-1 ring-inset ring-slate-900/5 dark:bg-slate-900/50 dark:ring-white/5">
                            <div
                                class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                    />
                                </svg>
                            </div>
                            <h3 class="font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">Live Scoring</h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                Track match scores in real-time with support for rallies, side-outs, and sets.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Modal backdrop + panels -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 sm:overflow-hidden sm:p-6">
                <div class="absolute inset-0 bg-black/50" @click="closeModal" />

                <!-- Login -->
                <div
                    v-if="modal === 'login'"
                    class="relative z-10 my-auto max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl duration-200 animate-in zoom-in-95 dark:bg-[#161615] sm:max-h-none sm:overflow-visible sm:p-8"
                >
                    <button
                        @click="closeModal"
                        class="absolute right-5 top-4 text-2xl leading-none text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                    >
                        &times;
                    </button>
                    <div class="mb-5 text-center">
                        <h2 class="text-heading text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]">Log in to your account</h2>
                        <p class="text-caption mt-1 text-slate-500 dark:text-slate-400">Select your role and enter your credentials</p>
                    </div>
                    <!-- Role tabs -->
                    <div class="mb-5 flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <button
                            type="button"
                            @click="
                                () => {
                                    loginRole = 'scheduler';
                                }
                            "
                            :class="
                                loginRole === 'scheduler'
                                    ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-700 dark:text-green-400'
                                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            "
                            class="btn-heading flex-1 rounded-lg py-2 text-sm transition-all"
                        >
                            Scheduler
                        </button>
                        <button
                            type="button"
                            @click="
                                () => {
                                    loginRole = 'scorer';
                                }
                            "
                            :class="
                                loginRole === 'scorer'
                                    ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-700 dark:text-green-400'
                                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            "
                            class="btn-heading flex-1 rounded-lg py-2 text-sm transition-all"
                        >
                            Scorer
                        </button>
                        <button
                            type="button"
                            @click="
                                () => {
                                    loginRole = 'player';
                                }
                            "
                            :class="
                                loginRole === 'player'
                                    ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-700 dark:text-green-400'
                                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            "
                            class="btn-heading flex-1 rounded-lg py-2 text-sm transition-all"
                        >
                            Player
                        </button>
                    </div>
                    <form @submit.prevent="submitLogin" class="flex flex-col gap-4">
                        <div class="grid gap-1.5">
                            <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Email address</label>
                            <input
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                v-model="loginForm.email"
                                placeholder="email@example.com"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                            />
                            <InputError :message="loginForm.errors.email" />
                        </div>
                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Password</label>
                                <Link :href="route('password.request')" class="text-xs text-blue-600 hover:underline dark:text-green-400"
                                    >Forgot password?</Link
                                >
                            </div>
                            <div class="relative">
                                <input
                                    :type="showLoginPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    v-model="loginForm.password"
                                    placeholder="Password"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 pr-10 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                                />
                                <button
                                    type="button"
                                    @click="showLoginPassword = !showLoginPassword"
                                    :aria-label="showLoginPassword ? 'Hide password' : 'Show password'"
                                    :title="showLoginPassword ? 'Hide password' : 'Show password'"
                                    tabindex="-1"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition-colors hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-500 dark:hover:text-slate-300 dark:focus:ring-green-500"
                                >
                                    <EyeOff v-if="showLoginPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <InputError :message="loginForm.errors.password" />
                        </div>
                        <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-[#1b1b18] dark:text-[#EDEDEC]">
                            <input type="checkbox" v-model="loginForm.remember" class="rounded border-slate-300" />
                            Remember me
                        </label>
                        <button
                            type="submit"
                            :disabled="loginForm.processing || isLoggingIn"
                            class="btn-heading mt-2 flex w-full transform items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm text-white transition-all duration-200 hover:scale-[1.02] hover:bg-blue-700 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            <LoaderCircle v-if="loginForm.processing || isLoggingIn" class="h-4 w-4 animate-spin" />
                            <span v-else>Log in</span>
                            <span v-if="isLoggingIn" class="text-xs opacity-75">Authenticating...</span>
                        </button>
                    </form>
                    <div class="mt-5 border-t border-slate-200 pt-4 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        New here?
                        <button
                            type="button"
                            @click="openRegister('player')"
                            class="ml-1 font-semibold text-blue-600 hover:underline dark:text-green-400"
                        >
                            Register as Player or Scheduler
                        </button>
                    </div>
                </div>

                <!-- Register -->
                <div
                    v-if="modal === 'register'"
                    class="relative z-10 my-auto max-h-[calc(100vh-2rem)] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl duration-200 animate-in zoom-in-95 dark:bg-[#161615] sm:max-h-none sm:max-w-4xl sm:overflow-visible sm:p-7"
                >
                    <button
                        @click="closeModal"
                        class="absolute right-5 top-4 text-2xl leading-none text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                    >
                        &times;
                    </button>
                    <div class="mb-4 text-center">
                        <h2 class="text-heading text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]">Create a new account</h2>
                        <p class="text-caption mt-1 text-slate-500 dark:text-slate-400">Register as a new player or scheduler</p>
                    </div>
                    <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-900/40 dark:bg-blue-950/30 dark:text-blue-300">
                        Player accounts can view their stats and score during active bookings. Scorer accounts are still created by schedulers.
                    </div>
                    <div class="mb-4 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="registerRole = 'player'"
                            class="rounded-xl border px-4 py-3 text-left text-sm font-semibold transition-all"
                            :class="
                                registerRole === 'player'
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-400 dark:bg-green-950/30 dark:text-green-300'
                                    : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'
                            "
                        >
                            Player
                        </button>
                        <button
                            type="button"
                            @click="registerRole = 'scheduler'"
                            class="rounded-xl border px-4 py-3 text-left text-sm font-semibold transition-all"
                            :class="
                                registerRole === 'scheduler'
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-400 dark:bg-green-950/30 dark:text-green-300'
                                    : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'
                            "
                        >
                            Scheduler
                        </button>
                    </div>
                    <form @submit.prevent="submitRegister" class="flex flex-col gap-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="grid gap-1.5">
                                <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">First name</label>
                                <input
                                    type="text"
                                    required
                                    autocomplete="given-name"
                                    v-model="registerForm.first_name"
                                    placeholder="First name"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                                />
                                <InputError :message="registerForm.errors.first_name" />
                            </div>
                            <div class="grid gap-1.5">
                                <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Last name</label>
                                <input
                                    type="text"
                                    required
                                    autocomplete="family-name"
                                    v-model="registerForm.last_name"
                                    placeholder="Last name"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                                />
                                <InputError :message="registerForm.errors.last_name" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="grid gap-1.5">
                                <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Middle name <span class="text-xs text-slate-500">(Optional)</span></label>
                                <input
                                    type="text"
                                    autocomplete="additional-name"
                                    v-model="registerForm.middle_name"
                                    placeholder="Middle name"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                                />
                                <InputError :message="registerForm.errors.middle_name" />
                            </div>
                            <div class="grid gap-1.5">
                                <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Username</label>
                                <input
                                    type="text"
                                    required
                                    autocomplete="username"
                                    v-model="registerForm.username"
                                    placeholder="username"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                                />
                                <InputError :message="registerForm.errors.username" />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Email address</label>
                            <input
                                type="email"
                                required
                                autocomplete="email"
                                v-model="registerForm.email"
                                placeholder="email@example.com"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                            />
                            <InputError :message="registerForm.errors.email" />
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="grid gap-1.5">
                                <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Password</label>
                                <div class="relative">
                                    <input
                                        :type="showRegisterPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="new-password"
                                        v-model="registerForm.password"
                                        placeholder="Password"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 pr-10 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                                    />
                                    <button
                                        type="button"
                                        @click="showRegisterPassword = !showRegisterPassword"
                                        :aria-label="showRegisterPassword ? 'Hide password' : 'Show password'"
                                        :title="showRegisterPassword ? 'Hide password' : 'Show password'"
                                        tabindex="-1"
                                        class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition-colors hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-500 dark:hover:text-slate-300 dark:focus:ring-green-500"
                                    >
                                        <EyeOff v-if="showRegisterPassword" class="h-4 w-4" />
                                        <Eye v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <InputError :message="registerForm.errors.password" />
                            </div>
                            <div class="grid gap-1.5">
                                <label class="text-small text-ui font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Confirm password</label>
                                <div class="relative">
                                    <input
                                        :type="showRegisterConfirmPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="new-password"
                                        v-model="registerForm.password_confirmation"
                                        placeholder="Confirm password"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 pr-10 text-sm text-[#1b1b18] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-[#EDEDEC] dark:focus:ring-green-500"
                                    />
                                    <button
                                        type="button"
                                        @click="showRegisterConfirmPassword = !showRegisterConfirmPassword"
                                        :aria-label="showRegisterConfirmPassword ? 'Hide password confirmation' : 'Show password confirmation'"
                                        :title="showRegisterConfirmPassword ? 'Hide password confirmation' : 'Show password confirmation'"
                                        tabindex="-1"
                                        class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition-colors hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-500 dark:hover:text-slate-300 dark:focus:ring-green-500"
                                    >
                                        <EyeOff v-if="showRegisterConfirmPassword" class="h-4 w-4" />
                                        <Eye v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <InputError :message="registerForm.errors.password_confirmation" />
                                <InputError :message="registerForm.errors.role" />
                            </div>
                        </div>
                        <button
                            type="submit"
                            :disabled="registerForm.processing || isRegistering"
                            class="btn-heading mt-1 flex w-full transform items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm text-white transition-all duration-200 hover:scale-[1.02] hover:bg-blue-700 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            <LoaderCircle v-if="registerForm.processing || isRegistering" class="h-4 w-4 animate-spin" />
                            <span v-else>Create account</span>
                            <span v-if="isRegistering" class="text-xs opacity-75">Creating account...</span>
                        </button>
                    </form>
                    <div class="mt-4 border-t border-slate-200 pt-4 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        Already have an account?
                        <button
                            type="button"
                            @click="openLogin(registerRole === 'scheduler' ? 'scheduler' : 'player')"
                            class="ml-1 font-semibold text-blue-600 hover:underline dark:text-green-400"
                        >
                            Log in here
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
