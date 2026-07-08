<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import MatchCard from '@/components/MatchCard.vue';
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Activity, ArrowLeft, Award, Calendar, CheckCircle, Clock, ChevronLeft, ChevronRight, Medal, Swords, Trophy, User, Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Team {
    id: number;
    player1_name: string;
    player2_name: string;
    seed?: number;
}

interface Match {
    id: number;
    round: number;
    bracket: 'winners' | 'losers' | 'grand_final' | 'round_robin';
    team1_id: number | null;
    team2_id: number | null;
    team1_score: number | null;
    team2_score: number | null;
    winner_id: number | null;
    scheduled_time?: string | null;
    team1?: Team | null;
    team2?: Team | null;
    winner?: Team | null;
}

interface TournamentListItem {
    id: number;
    name: string;
    type: 'single_elimination' | 'double_elimination' | 'round_robin';
    category?: 'mens' | 'female' | 'mix' | null;
    status: 'setup' | 'in_progress' | 'completed';
    min_players: number;
    max_players: number;
    best_of?: 1 | 3 | 5;
    start_time?: string | null;
    created_at: string;
    teams_count?: number;
    teams?: Team[];
    tournament_sub_folder_id?: number | null;
    sub_folder?: { id: number; name: string } | null;
}

const props = defineProps<{
    tournaments: TournamentListItem[];
    activeTournament?: TournamentListItem & { matches: Match[]; teams: Team[] };
}>();

let intervalId: ReturnType<typeof setInterval> | null = null;
const POLL_ONLY = ['tournaments', 'activeTournament'];

const activeSlideIndex = ref(0);
let slideIntervalId = null;

const startPolling = () => {
    if (intervalId) return;
    intervalId = setInterval(() => {
        router.reload({ only: POLL_ONLY });
    }, 5000);
};

const stopPolling = () => {
    if (intervalId !== null) {
        clearInterval(intervalId);
        intervalId = null;
    }
};

const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        router.reload({ only: POLL_ONLY });
        startPolling();
        startSlideShow();
    } else {
        stopPolling();
        stopSlideShow();
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    startPolling();
    startSlideShow();
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    stopPolling();
    stopSlideShow();
});

const lastUpdatedText = 'just now';

const liveTournaments = computed(() => (props.tournaments ?? []).filter((t) => t.status === 'in_progress'));
const completedTournaments = computed(() => (props.tournaments ?? []).filter((t) => t.status === 'completed'));

const isInProgress = computed(() => props.activeTournament?.status === 'in_progress');
const isCompleted = computed(() => props.activeTournament?.status === 'completed');

const winnersMatches = computed<Record<number, Match[]>>(() => {
    if (!props.activeTournament?.matches) return {};
    const matches = props.activeTournament.matches.filter((m) => m.bracket === 'winners');
    const grouped: Record<number, Match[]> = {};
    for (const m of matches) {
        if (!grouped[m.round]) grouped[m.round] = [];
        grouped[m.round].push(m);
    }
    return grouped;
});

const losersMatches = computed<Record<number, Match[]>>(() => {
    if (!props.activeTournament?.matches) return {};
    const matches = props.activeTournament.matches.filter((m) => m.bracket === 'losers');
    const grouped: Record<number, Match[]> = {};
    for (const m of matches) {
        if (!grouped[m.round]) grouped[m.round] = [];
        grouped[m.round].push(m);
    }
    return grouped;
});

const grandFinalMatch = computed<Match | null>(() => {
    if (!props.activeTournament?.matches) return null;
    return props.activeTournament.matches.find((m) => m.bracket === 'grand_final') || null;
});

const roundRobinMatches = computed<Record<number, Match[]>>(() => {
    if (!props.activeTournament?.matches) return {};
    const matches = props.activeTournament.matches.filter((m) => m.bracket === 'round_robin');
    const grouped: Record<number, Match[]> = {};
    for (const m of matches) {
        if (!grouped[m.round]) grouped[m.round] = [];
        grouped[m.round].push(m);
    }
    return grouped;
});

const winnersRounds = computed(() => Object.keys(winnersMatches.value).map(Number).sort((a, b) => a - b));
const losersRounds = computed(() => Object.keys(losersMatches.value).map(Number).sort((a, b) => a - b));
const rrRounds = computed(() => Object.keys(roundRobinMatches.value).map(Number).sort((a, b) => a - b));

const roundRobinStandings = computed(() => {
    if (!props.activeTournament?.matches || !props.activeTournament?.teams) return [];
    const teams = props.activeTournament.teams;
    const matches = props.activeTournament.matches;
    const stats: Record<number, { team: Team; wins: number; losses: number; pf: number; pa: number }> = {};

    for (const t of teams) {
        stats[t.id] = { team: t, wins: 0, losses: 0, pf: 0, pa: 0 };
    }

    for (const m of matches) {
        if (m.winner_id === null) continue;
        if (m.team1_id && stats[m.team1_id]) {
            stats[m.team1_id].pf += m.team1_score || 0;
            stats[m.team1_id].pa += m.team2_score || 0;
            if (m.winner_id === m.team1_id) stats[m.team1_id].wins++;
            else stats[m.team1_id].losses++;
        }
        if (m.team2_id && stats[m.team2_id]) {
            stats[m.team2_id].pf += m.team2_score || 0;
            stats[m.team2_id].pa += m.team1_score || 0;
            if (m.winner_id === m.team2_id) stats[m.team2_id].wins++;
            else stats[m.team2_id].losses++;
        }
    }

    return Object.values(stats).sort((a, b) => b.wins - a.wins || b.pf - b.pa - (a.pf - a.pa));
});

const tournamentChampion = computed<Team | null>(() => {
    if (!isCompleted.value || !props.activeTournament?.matches) return null;
    const type = props.activeTournament.type;
    if (type === 'round_robin') {
        return roundRobinStandings.value[0]?.team || null;
    }
    if (type === 'double_elimination' && grandFinalMatch.value?.winner) {
        return grandFinalMatch.value.winner;
    }
    const rounds = winnersRounds.value;
    if (rounds.length === 0) return null;
    const maxRound = Math.max(...rounds);
    const finalMatches = winnersMatches.value[maxRound];
    if (finalMatches && finalMatches.length > 0 && finalMatches[0].winner) {
        return finalMatches[0].winner;
    }
    return null;
});

const getTopTwoWinners = (t: any): { first: any; second: any } => {
    if (t.status !== 'completed' || !t.matches?.length) return { first: null, second: null };
    if (t.type === 'round_robin') {
        const teams = t.teams || [];
        const stats: Record<number, { team: any; wins: number; losses: number; pf: number; pa: number }> = {};
        for (const team of teams) stats[team.id] = { team, wins: 0, losses: 0, pf: 0, pa: 0 };
        for (const m of t.matches) {
            if (m.winner_id === null || !m.team1_id || !m.team2_id) continue;
            const t1 = stats[m.team1_id], t2 = stats[m.team2_id];
            if (!t1 || !t2) continue;
            t1.pf += m.team1_score; t1.pa += m.team2_score;
            t2.pf += m.team2_score; t2.pa += m.team1_score;
            if (m.winner_id === m.team1_id) { t1.wins++; t2.losses++; }
            else { t2.wins++; t1.losses++; }
        }
        const sorted = Object.values(stats).sort((a, b) => b.wins - a.wins || (b.pf - b.pa) - (a.pf - a.pa));
        return { first: sorted[0]?.team || null, second: sorted[1]?.team || null };
    }
    if (t.type === 'double_elimination') {
        const gf = t.matches.find((m: any) => m.bracket === 'grand_final');
        if (gf?.winner) {
            const loserId = gf.winner_id === gf.team1_id ? gf.team2_id : gf.team1_id;
            const loser = t.teams?.find((tm: any) => tm.id === loserId);
            return { first: gf.winner, second: loser || null };
        }
        return { first: null, second: null };
    }
    const winnersBracket = t.matches.filter((m: any) => m.bracket === 'winners');
    const maxRound = Math.max(...winnersBracket.map((m: any) => m.round));
    const finalMatch = winnersBracket.find((m: any) => m.round === maxRound);
    if (finalMatch?.winner) {
        const loserId = finalMatch.winner_id === finalMatch.team1_id ? finalMatch.team2_id : finalMatch.team1_id;
        const loser = t.teams?.find((tm: any) => tm.id === loserId);
        return { first: finalMatch.winner, second: loser || null };
    }
    return { first: null, second: null };
};

const teamName = (team: Team | null | undefined): string => {
    if (!team) return 'TBD';
    return `${team.player1_name} & ${team.player2_name}`;
};

const typeLabel = (type: string) => {
    const labels: Record<string, string> = {
        single_elimination: 'Single Elimination',
        double_elimination: 'Double Elimination',
        round_robin: 'Round Robin',
    };
    return labels[type] || type;
};

const categoryLabel = (category: string | null | undefined) => {
    const labels: Record<string, string> = {
        mens: "Men's",
        female: "Women's",
        mix: 'Mixed',
    };
    return (category && labels[category]) || 'Open';
};

const formatTime = (time: string | null | undefined): string => {
    if (!time) return '';
    const parts = time.split(':');
    if (parts.length < 2) return time;
    const h = parseInt(parts[0] ?? '0', 10);
    const m = parseInt(parts[1] ?? '0', 10);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const displayH = h % 12 === 0 ? 12 : h % 12;
    return `${displayH}:${m.toString().padStart(2, '0')} ${ampm}`;
};

const formatDate = (iso: string | null | undefined): string => {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return iso;
    }
};

const bracketMatchHeight = 84;
const bracketConnectorWidth = 44;

const deWinnersHeight = computed(() => {
    const rounds = winnersRounds.value;
    if (rounds.length === 0) return 200;
    const maxMatches = Math.max(...rounds.map((r) => (winnersMatches.value[r] || []).length));
    return Math.max(maxMatches * (bracketMatchHeight + 28), 200);
});

const deLosersHeight = computed(() => {
    const rounds = losersRounds.value;
    if (rounds.length === 0) return 200;
    const maxMatches = Math.max(...rounds.map((r) => (losersMatches.value[r] || []).length));
    return Math.max(maxMatches * (bracketMatchHeight + 28), 200);
});

const deWinnersRoundY = (round: number, matchIndex: number): number => {
    const matches = winnersMatches.value[round] || [];
    const count = matches.length;
    if (count === 0) return 0;
    const height = deWinnersHeight.value;
    return ((matchIndex + 0.5) * height) / count;
};

const deLosersRoundY = (round: number, matchIndex: number): number => {
    const matches = losersMatches.value[round] || [];
    const count = matches.length;
    if (count === 0) return 0;
    const height = deLosersHeight.value;
    return ((matchIndex + 0.5) * height) / count;
};

const roundLabel = (round: number, totalRounds: number, bracket: 'winners' | 'losers' | 'rr' = 'winners'): string => {
    if (bracket === 'rr') return `Round ${round}`;
    if (bracket === 'losers') return `Losers R${round}`;
    const remaining = totalRounds - round;
    if (remaining === 0) return 'Final';
    if (remaining === 1) return 'Semi Final';
    if (remaining === 2) return 'Quarter Final';
    return `Round ${round}`;
};

const openTournament = (id: number) => {
    router.visit(`/tournaments/live/${id}`);
};

interface GroupedSubfolderMatches {
    subFolderId: number | string;
    subFolderName: string;
    currentMatches: any[];
    nextMatches: any[];
}

const subFolderMatchFlows = computed(() => {
    const active = (props.tournaments ?? []).filter((t) => t.status === 'in_progress');
    const groups = new Map<number | string, { name: string; matches: any[] }>();

    for (const t of active) {
        if (!t.sub_folder) continue;
        const subId = t.sub_folder.id;
        const name = t.sub_folder.name;
        if (!groups.has(subId)) {
            groups.set(subId, { name: name, matches: [] });
        }
        const matches = t.matches ?? [];
        for (const m of matches) {
            groups.get(subId)!.matches.push({
                ...m,
                tournament_name: t.name,
            });
        }
    }

    const flows: GroupedSubfolderMatches[] = [];
    for (const [subId, data] of groups.entries()) {
        const current = data.matches
            .filter((m: any) => m.court_number !== null && m.winner_id === null && m.team1 && m.team2)
            .sort((a: any, b: any) => a.court_number - b.court_number);

        const next = data.matches
            .filter((m: any) => m.court_number === null && m.winner_id === null && m.team1 && m.team2)
            .sort((a: any, b: any) => {
                const timeA = a.scheduled_time ?? '99:99';
                const timeB = b.scheduled_time ?? '99:99';
                if (timeA !== timeB) return timeA.localeCompare(timeB);
                return a.id - b.id;
            });

        if (current.length > 0 || next.length > 0) {
            flows.push({
                subFolderId: subId,
                subFolderName: data.name,
                currentMatches: current,
                nextMatches: next,
            });
        }
    }

    return flows;
});

const startSlideShow = () => {
    if (slideIntervalId) return;
    slideIntervalId = setInterval(() => {
        if (subFolderMatchFlows.value.length > 1) {
            activeSlideIndex.value = (activeSlideIndex.value + 1) % subFolderMatchFlows.value.length;
        }
    }, 10000) as any;
};

const stopSlideShow = () => {
    if (slideIntervalId !== null) {
        clearInterval(slideIntervalId);
        slideIntervalId = null;
    }
};

watch(() => subFolderMatchFlows.value.length, (newLength) => {
    if (activeSlideIndex.value >= newLength) {
        activeSlideIndex.value = Math.max(0, newLength - 1);
    }
});

const prevSlide = () => {
    stopSlideShow();
    if (subFolderMatchFlows.value.length > 0) {
        activeSlideIndex.value = (activeSlideIndex.value - 1 + subFolderMatchFlows.value.length) % subFolderMatchFlows.value.length;
    }
    startSlideShow();
};

const nextSlide = () => {
    stopSlideShow();
    if (subFolderMatchFlows.value.length > 0) {
        activeSlideIndex.value = (activeSlideIndex.value + 1) % subFolderMatchFlows.value.length;
    }
    startSlideShow();
};

const goToSlide = (idx: number) => {
    stopSlideShow();
    activeSlideIndex.value = idx;
    startSlideShow();
};
</script>

<template>
    <Head title="Tournament Brackets · Live" />

    <div class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <!-- Public header -->
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/80 backdrop-blur-md dark:border-slate-800 dark:bg-[#0f0f0f]/80">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
                <Link :href="route('home')" class="flex items-center gap-2">
                    <AppLogo />
                </Link>
                <div class="flex items-center gap-3">
                    <span
                        v-if="activeTournament"
                        class="hidden items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-700 sm:inline-flex dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        Live
                    </span>
                    <span
                        v-if="activeTournament"
                        class="hidden text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:inline-flex dark:text-slate-500"
                    >
                        Updated {{ lastUpdatedText }}
                    </span>
                    <AppearanceToggle />
                </div>
            </div>
        </header>

        <!-- LIST VIEW (no activeTournament) -->
        <main v-if="!activeTournament" class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <div class="mb-8 flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                <!-- Title & Description (Left Column) -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <Activity class="h-5 w-5 text-emerald-500" />
                        <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Spectator View</span>
                    </div>
                    <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Tournament Brackets</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400 max-w-xl">
                        Pick a tournament to follow its bracket in real time. Brackets refresh automatically every 5 seconds.
                    </p>
                </div>

                <!-- Subfolder Match Flow Widget (Right Column) -->
                <div v-if="subFolderMatchFlows.length > 0" class="w-full md:w-[480px] shrink-0 space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]">
                        <Transition name="fade" mode="out-in">
                            <div :key="activeSlideIndex" v-if="subFolderMatchFlows[activeSlideIndex]">
                                <!-- Subfolder Header -->
                                <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2 dark:border-[#1a1a1a]/40">
                                    <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                        {{ subFolderMatchFlows[activeSlideIndex].subFolderName }} Flow
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                        <span class="h-1 animate-pulse w-1 rounded-full bg-emerald-500"></span>
                                        Match Flow
                                    </span>
                                </div>

                                <!-- Match Flow Columns -->
                                <div class="grid grid-cols-2 gap-3">
                                    <!-- On Court -->
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                            On Court
                                        </div>
                                        <div v-if="subFolderMatchFlows[activeSlideIndex].currentMatches.length === 0" class="text-[10px] text-slate-400 dark:text-slate-500 italic">
                                            No matches active
                                        </div>
                                        <div
                                            v-for="m in subFolderMatchFlows[activeSlideIndex].currentMatches.slice(0, 1)"
                                            :key="m.id"
                                            class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-2.5 dark:border-emerald-800/20 dark:bg-emerald-950/10"
                                        >
                                            <div class="flex items-center justify-between text-[8px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mb-1">
                                                <span>Court {{ m.court_number }}</span>
                                                <span class="truncate max-w-[80px]" :title="m.tournament_name">{{ m.tournament_name }}</span>
                                            </div>
                                            <p class="truncate text-xs font-extrabold text-slate-900 dark:text-white capitalize">
                                                {{ teamName(m.team1) }}
                                            </p>
                                            <p class="my-0.5 text-[8px] font-bold text-slate-400 text-center uppercase">vs</p>
                                            <p class="truncate text-xs font-extrabold text-slate-900 dark:text-white capitalize">
                                                {{ teamName(m.team2) }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- On Deck -->
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-wider text-blue-500 dark:text-green-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500 dark:bg-green-400"></span>
                                            On Deck
                                        </div>
                                        <div v-if="subFolderMatchFlows[activeSlideIndex].nextMatches.length === 0" class="text-[10px] text-slate-400 dark:text-slate-500 italic">
                                            No upcoming matches
                                        </div>
                                        <div
                                            v-for="m in subFolderMatchFlows[activeSlideIndex].nextMatches.slice(0, 1)"
                                            :key="m.id"
                                            class="rounded-xl border border-slate-150 bg-slate-50/50 p-2.5 dark:border-[#2a2a2a]/40 dark:bg-[#1a1a1a]/30"
                                        >
                                            <div class="flex items-center justify-between text-[8px] font-black uppercase tracking-wider text-slate-400 mb-1">
                                                <span>{{ formatTime(m.scheduled_time) }}</span>
                                                <span class="truncate max-w-[80px]" :title="m.tournament_name">{{ m.tournament_name }}</span>
                                            </div>
                                            <p class="truncate text-[11px] font-bold text-slate-700 dark:text-slate-300 capitalize">
                                                {{ teamName(m.team1) }}
                                            </p>
                                            <p class="my-0.5 text-[7px] font-semibold text-slate-400 text-center uppercase">vs</p>
                                            <p class="truncate text-[11px] font-bold text-slate-700 dark:text-slate-300 capitalize">
                                                {{ teamName(m.team2) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>

                        <!-- Bullet Pagination Dots & Navigation Buttons -->
                        <div v-if="subFolderMatchFlows.length > 1" class="mt-4 flex items-center justify-center gap-3">
                            <button
                                @click="prevSlide"
                                class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-[#1a1a1a] dark:hover:text-slate-300 transition-colors focus:outline-none"
                                aria-label="Previous slide"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>

                            <div class="flex justify-center gap-1.5">
                                <button
                                    v-for="(flow, idx) in subFolderMatchFlows"
                                    :key="flow.subFolderId"
                                    @click="goToSlide(idx)"
                                    class="h-1.5 rounded-full transition-all duration-300 focus:outline-none"
                                    :class="activeSlideIndex === idx ? 'bg-emerald-500 w-4' : 'bg-slate-200 dark:bg-slate-800 w-1.5 hover:bg-slate-300 dark:hover:bg-slate-700'"
                                    :aria-label="'Go to slide ' + (idx + 1)"
                                />
                            </div>

                            <button
                                @click="nextSlide"
                                class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-[#1a1a1a] dark:hover:text-slate-300 transition-colors focus:outline-none"
                                aria-label="Next slide"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="tournaments.length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-20 text-center dark:border-slate-700 dark:bg-[#0f0f0f]">
                <Trophy class="mb-3 h-12 w-12 text-slate-300 dark:text-slate-700" />
                <p class="text-lg font-bold">No live tournaments right now</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Check back later when a tournament is in progress or has just completed.</p>
            </div>

            <div v-else>
                <!-- Live Section -->
                <div v-if="liveTournaments.length > 0" class="mb-8">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                        Live
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="t in liveTournaments"
                            :key="t.id"
                            @click="openTournament(t.id)"
                            class="group flex flex-col items-stretch overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-lg dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f] dark:hover:border-green-500/40"
                        >
                            <div class="mb-4 flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 dark:border-green-800 dark:bg-green-900/20">
                                        <Trophy class="h-5 w-5 text-blue-600 dark:text-green-400" />
                                    </div>
                                    <h3 class="truncate text-base font-bold text-slate-900 transition group-hover:text-blue-600 dark:text-white dark:group-hover:text-green-400">
                                        {{ t.name }}
                                    </h3>
                                </div>
                                <span class="inline-flex flex-shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
                                    Live
                                </span>
                            </div>
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300">
                                    <Swords class="h-3 w-3 text-slate-500 dark:text-slate-400" />
                                    {{ typeLabel(t.type) }} · {{ categoryLabel(t.category) }}
                                </span>
                            </div>
                            <div class="mt-auto flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                <span class="flex items-center gap-1">
                                    <Users class="h-3.5 w-3.5" />
                                    {{ t.teams_count ?? (t.teams?.length || 0) }} teams
                                </span>
                                <span class="flex items-center gap-1">
                                    <User class="h-3.5 w-3.5" />
                                    {{ t.min_players }}-{{ t.max_players }} players
                                </span>
                                <span v-if="t.start_time" class="flex items-center gap-1">
                                    <Clock class="h-3.5 w-3.5" />
                                    {{ formatTime(t.start_time) }}
                                </span>
                                <span v-if="t.created_at" class="flex items-center gap-1">
                                    <Calendar class="h-3.5 w-3.5" />
                                    {{ formatDate(t.created_at) }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Completed Section -->
                <div v-if="completedTournaments.length > 0">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                        <CheckCircle class="h-4 w-4" />
                        Completed
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="t in completedTournaments"
                            :key="t.id"
                            @click="openTournament(t.id)"
                            class="group flex flex-col items-stretch overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-lg dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f] dark:hover:border-blue-500/40"
                        >
                            <div class="mb-4 flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20">
                                        <Trophy class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <h3 class="truncate text-base font-bold text-slate-900 transition group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                        {{ t.name }}
                                    </h3>
                                </div>
                                <span class="inline-flex flex-shrink-0 items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-400"></span>
                                    Completed
                                </span>
                            </div>
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300">
                                    <Swords class="h-3 w-3 text-slate-500 dark:text-slate-400" />
                                    {{ typeLabel(t.type) }} · {{ categoryLabel(t.category) }}
                                </span>
                            </div>
                            <div class="mt-auto flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                <span class="flex items-center gap-1">
                                    <Users class="h-3.5 w-3.5" />
                                    {{ t.teams_count ?? (t.teams?.length || 0) }} teams
                                </span>
                                <span class="flex items-center gap-1">
                                    <User class="h-3.5 w-3.5" />
                                    {{ t.min_players }}-{{ t.max_players }} players
                                </span>
                                <span v-if="t.start_time" class="flex items-center gap-1">
                                    <Clock class="h-3.5 w-3.5" />
                                    {{ formatTime(t.start_time) }}
                                </span>
                                <span v-if="t.created_at" class="flex items-center gap-1">
                                    <Calendar class="h-3.5 w-3.5" />
                                    {{ formatDate(t.created_at) }}
                                </span>
                            </div>
                            <div v-if="t.status === 'completed' && (getTopTwoWinners(t).first || getTopTwoWinners(t).second)" class="mt-2.5 flex items-center gap-3 text-xs">
                                <span v-if="getTopTwoWinners(t).first" class="flex items-center gap-1 font-semibold text-amber-600 dark:text-amber-400">
                                    <Medal class="h-3 w-3" />
                                    1st: {{ teamName(getTopTwoWinners(t).first) }}
                                </span>
                                <span v-if="getTopTwoWinners(t).second" class="flex items-center gap-1 font-medium text-slate-400 dark:text-slate-500">
                                    <Medal class="h-3 w-3" />
                                    2nd: {{ teamName(getTopTwoWinners(t).second) }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- DETAIL VIEW (with activeTournament) -->
        <main v-else class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
            <button
                @click="router.visit(route('tournaments.live.index'))"
                class="mb-4 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-[#1a1a1a] dark:hover:text-white"
            >
                <ArrowLeft class="h-3.5 w-3.5" />
                Back to live tournaments
            </button>

            <!-- Tournament header -->
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="mb-2 flex items-center gap-2">
                            <Trophy class="h-5 w-5 text-yellow-500" />
                            <span
                                v-if="isInProgress"
                                class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                </span>
                                Live
                            </span>
                            <span
                                v-else-if="isCompleted"
                                class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400"
                            >
                                Completed
                            </span>
                        </div>
                        <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ activeTournament.name }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 font-semibold text-slate-600 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300">
                                <Swords class="h-3 w-3" />
                                {{ typeLabel(activeTournament.type) }} · {{ categoryLabel(activeTournament.category) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <Users class="h-3.5 w-3.5" />
                                {{ activeTournament.teams?.length || 0 }} teams
                            </span>
                            <span v-if="activeTournament.start_time" class="flex items-center gap-1">
                                <Clock class="h-3.5 w-3.5" />
                                {{ formatTime(activeTournament.start_time) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHAMPION BANNER -->
            <div
                v-if="isCompleted && tournamentChampion"
                class="mb-6 rounded-xl border border-yellow-300 bg-gradient-to-r from-yellow-100 to-amber-100 p-6 text-center dark:border-yellow-500/30 dark:from-yellow-500/20 dark:to-amber-500/20"
            >
                <Award class="mx-auto mb-2 h-12 w-12 text-yellow-500 dark:text-yellow-400" />
                <h2 class="text-2xl font-bold text-yellow-800 dark:text-yellow-300">CHAMPION TEAM</h2>
                <div class="mt-2 flex items-center justify-center gap-2">
                    <Users class="h-5 w-5 text-yellow-600 dark:text-yellow-400/70" />
                    <p class="text-xl font-semibold text-slate-900 dark:text-white">{{ teamName(tournamentChampion) }}</p>
                </div>
                <span class="mt-2 inline-block rounded-full bg-yellow-200 px-3 py-0.5 text-xs text-yellow-800 dark:bg-yellow-600/20 dark:text-yellow-300">
                    2v2 Doubles
                </span>
            </div>

            <!-- ELIMINATION BRACKET (single + double) -->
            <div v-if="activeTournament.type !== 'round_robin' && winnersRounds.length > 0" class="mb-6">
                <!-- Desktop: Bracket Tree -->
                <div class="hidden sm:block">
                    <!-- Winners Bracket Section -->
                    <div class="mb-2 flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Winners Bracket</h3>
                        <div class="h-px flex-1 bg-gradient-to-r from-emerald-500/40 to-transparent"></div>
                    </div>

                    <!-- Winners Round Headers -->
                    <div class="mb-2 flex items-stretch">
                        <template v-for="(round, rIdx) in winnersRounds" :key="`whdr-${round}`">
                            <div class="flex flex-1 items-center justify-center">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{
                                    roundLabel(round, winnersRounds.length, 'winners')
                                }}</span>
                            </div>
                            <div v-if="rIdx < winnersRounds.length - 1" :style="{ width: `${bracketConnectorWidth}px` }"></div>
                        </template>
                    </div>

                    <!-- Winners Bracket Body -->
                    <div class="flex items-stretch" :style="{ height: `${deWinnersHeight}px` }">
                        <template v-for="(round, rIdx) in winnersRounds" :key="`wbody-${round}`">
                            <div class="relative flex-1" :style="{ height: `${deWinnersHeight}px` }">
                                <div
                                    v-for="(match, mIdx) in winnersMatches[round]"
                                    :key="match.id"
                                    class="group absolute left-0 right-0 transition"
                                    :style="{ top: `${deWinnersRoundY(round, mIdx) - bracketMatchHeight / 2}px`, height: `${bracketMatchHeight}px` }"
                                >
                                    <div
                                        :class="[
                                            'w-full overflow-hidden rounded-xl border shadow-sm transition',
                                            match.winner_id
                                                ? 'border-emerald-300 dark:border-emerald-500/30'
                                                : 'border-slate-200 dark:border-[#1a1a1a]',
                                        ]"
                                    >
                                        <div v-if="match.scheduled_time || match.court_number" class="flex h-5 items-center justify-between px-3 py-0.5 bg-slate-50/50 dark:bg-[#0a0a0a]/30 border-b border-slate-100 dark:border-[#1a1a1a]/40 text-[8px] font-black uppercase tracking-wider text-slate-400">
                                            <span>
                                                <span v-if="match.court_number" class="font-bold text-violet-600 dark:text-violet-400 mr-1">Court {{ match.court_number }}</span>
                                                <span v-else-if="match.team1_id && match.team2_id && !match.winner_id" class="font-bold text-amber-600 dark:text-amber-400 mr-1">Waiting for Court</span>
                                                <span v-else>Time Slot</span>
                                            </span>
                                            <span v-if="match.scheduled_time" class="text-indigo-600 dark:text-green-400 font-bold">{{ formatTime(match.scheduled_time) }}</span>
                                        </div>
                                        <div
                                            :class="[
                                                'flex h-8 items-center justify-between px-3',
                                                match.winner_id === match.team1_id
                                                    ? 'bg-emerald-50 font-bold text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                                    : match.winner_id
                                                      ? 'bg-slate-50 text-slate-500 dark:bg-[#1a1a1a]/50 dark:text-slate-400'
                                                      : 'bg-white text-slate-800 dark:bg-[#1a1a1a] dark:text-slate-200',
                                            ]"
                                        >
                                            <span class="truncate text-xs">{{ teamName(match.team1) }}</span>
                                            <span class="ml-2 font-mono text-xs font-semibold tabular-nums">{{ match.team1_score ?? '' }}</span>
                                        </div>
                                        <div
                                            :class="[
                                                'flex h-8 items-center justify-between border-t border-slate-100 px-3 dark:border-[#1a1a1a]/60',
                                                match.winner_id === match.team2_id
                                                    ? 'bg-emerald-50 font-bold text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                                    : match.winner_id
                                                      ? 'bg-slate-50 text-slate-500 dark:bg-[#1a1a1a]/50 dark:text-slate-400'
                                                      : 'bg-white text-slate-800 dark:bg-[#1a1a1a] dark:text-slate-200',
                                            ]"
                                        >
                                            <span class="truncate text-xs">{{ teamName(match.team2) }}</span>
                                            <span class="ml-2 font-mono text-xs font-semibold tabular-nums">{{ match.team2_score ?? '' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Winners connectors -->
                            <div
                                v-if="rIdx < winnersRounds.length - 1"
                                class="relative flex-shrink-0"
                                :style="{ width: `${bracketConnectorWidth}px` }"
                            >
                                <svg :height="deWinnersHeight" :width="bracketConnectorWidth" class="absolute inset-0">
                                    <g v-for="(parentMatch, pIdx) in winnersMatches[winnersRounds[rIdx + 1]]" :key="parentMatch.id">
                                        <template v-if="winnersMatches[round] && winnersMatches[round].length >= 2 * pIdx + 2">
                                            <line
                                                :x1="0"
                                                :y1="deWinnersRoundY(round, 2 * pIdx)"
                                                :x2="bracketConnectorWidth / 2"
                                                :y2="deWinnersRoundY(round, 2 * pIdx)"
                                                stroke="#64748b"
                                                stroke-width="2"
                                            />
                                            <line
                                                :x1="0"
                                                :y1="deWinnersRoundY(round, 2 * pIdx + 1)"
                                                :x2="bracketConnectorWidth / 2"
                                                :y2="deWinnersRoundY(round, 2 * pIdx + 1)"
                                                stroke="#64748b"
                                                stroke-width="2"
                                            />
                                            <line
                                                :x1="bracketConnectorWidth / 2"
                                                :y1="deWinnersRoundY(round, 2 * pIdx)"
                                                :x2="bracketConnectorWidth / 2"
                                                :y2="deWinnersRoundY(round, 2 * pIdx + 1)"
                                                stroke="#64748b"
                                                stroke-width="2"
                                            />
                                            <line
                                                :x1="bracketConnectorWidth / 2"
                                                :y1="deWinnersRoundY(winnersRounds[rIdx + 1], pIdx)"
                                                :x2="bracketConnectorWidth"
                                                :y2="deWinnersRoundY(winnersRounds[rIdx + 1], pIdx)"
                                                stroke="#64748b"
                                                stroke-width="2"
                                            />
                                        </template>
                                    </g>
                                </svg>
                            </div>
                        </template>

                        <!-- Winners -> Grand Final connector (double elimination only) -->
                        <div
                            v-if="grandFinalMatch"
                            class="relative flex-shrink-0"
                            :style="{ width: `${bracketConnectorWidth}px`, height: `${deWinnersHeight}px` }"
                        >
                            <svg :height="deWinnersHeight" :width="bracketConnectorWidth" class="absolute inset-0">
                                <line
                                    :x1="0"
                                    :y1="deWinnersRoundY(winnersRounds[winnersRounds.length - 1], 0)"
                                    :x2="bracketConnectorWidth"
                                    :y2="deWinnersRoundY(winnersRounds[winnersRounds.length - 1], 0)"
                                    stroke="#eab308"
                                    stroke-width="2"
                                />
                            </svg>
                        </div>

                        <!-- Grand Final column (double elimination only) -->
                        <div v-if="grandFinalMatch" class="relative flex-1" :style="{ height: `${deWinnersHeight}px` }">
                            <div
                                class="group absolute left-0 right-0"
                                :style="{
                                    top: `${deWinnersRoundY(winnersRounds[winnersRounds.length - 1] || 1, 0) - bracketMatchHeight / 2}px`,
                                    height: `${bracketMatchHeight}px`,
                                }"
                            >
                                <div class="mb-1 flex items-center justify-center">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-yellow-200 bg-yellow-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-yellow-700 dark:border-yellow-500/30 dark:bg-yellow-500/20 dark:text-yellow-300"
                                    >
                                        <Award class="h-3 w-3" /> Grand Final
                                    </span>
                                </div>
                                <div
                                    :class="[
                                        'w-full overflow-hidden rounded-xl border shadow-sm transition',
                                        grandFinalMatch.winner_id
                                            ? 'border-yellow-500 shadow-yellow-300 dark:border-yellow-400/60 dark:shadow-yellow-500/20'
                                            : 'border-yellow-400 dark:border-yellow-500/50',
                                    ]"
                                >
                                    <div v-if="grandFinalMatch.scheduled_time || grandFinalMatch.court_number" class="flex h-5 items-center justify-between px-3 py-0.5 bg-slate-50/50 dark:bg-[#0a0a0a]/30 border-b border-slate-100 dark:border-[#1a1a1a]/40 text-[8px] font-black uppercase tracking-wider text-slate-400">
                                        <span>
                                            <span v-if="grandFinalMatch.court_number" class="font-bold text-violet-600 dark:text-violet-400 mr-1">Court {{ grandFinalMatch.court_number }}</span>
                                            <span v-else-if="grandFinalMatch.team1_id && grandFinalMatch.team2_id && !grandFinalMatch.winner_id" class="font-bold text-amber-600 dark:text-amber-400 mr-1">Waiting for Court</span>
                                            <span v-else>Time Slot</span>
                                        </span>
                                        <span v-if="grandFinalMatch.scheduled_time" class="text-indigo-600 dark:text-green-400 font-bold">{{ formatTime(grandFinalMatch.scheduled_time) }}</span>
                                    </div>
                                    <div
                                        :class="[
                                            'flex h-8 items-center justify-between px-3',
                                            grandFinalMatch.winner_id === grandFinalMatch.team1_id
                                                ? 'bg-yellow-50 font-bold text-yellow-900 dark:bg-yellow-900/30 dark:text-yellow-300'
                                                : 'bg-white text-slate-800 dark:bg-[#1a1a1a] dark:text-slate-200',
                                        ]"
                                    >
                                        <span class="truncate text-xs">{{ teamName(grandFinalMatch.team1) }}</span>
                                        <span class="ml-2 font-mono text-xs font-semibold tabular-nums">{{
                                            grandFinalMatch.team1_score ?? ''
                                        }}</span>
                                    </div>
                                    <div
                                        :class="[
                                            'flex h-8 items-center justify-between border-t border-slate-100 px-3 dark:border-[#1a1a1a]/60',
                                            grandFinalMatch.winner_id === grandFinalMatch.team2_id
                                                ? 'bg-yellow-50 font-bold text-yellow-900 dark:bg-yellow-900/30 dark:text-yellow-300'
                                                : 'bg-white text-slate-800 dark:bg-[#1a1a1a] dark:text-slate-200',
                                        ]"
                                    >
                                        <span class="truncate text-xs">{{ teamName(grandFinalMatch.team2) }}</span>
                                        <span class="ml-2 font-mono text-xs font-semibold tabular-nums">{{
                                            grandFinalMatch.team2_score ?? ''
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Champion connector -->
                        <div
                            v-if="tournamentChampion"
                            class="relative flex-shrink-0"
                            :style="{ width: `${bracketConnectorWidth}px`, height: `${deWinnersHeight}px` }"
                        >
                            <svg :height="deWinnersHeight" :width="bracketConnectorWidth" class="absolute inset-0">
                                <line
                                    :x1="0"
                                    :y1="grandFinalMatch
                                        ? deWinnersRoundY(winnersRounds[winnersRounds.length - 1] || 1, 0)
                                        : deWinnersRoundY(winnersRounds[winnersRounds.length - 1], 0)"
                                    :x2="bracketConnectorWidth"
                                    :y2="deWinnersHeight / 2"
                                    stroke="#eab308"
                                    stroke-width="2"
                                />
                            </svg>
                        </div>

                        <!-- Champion column -->
                        <div
                            v-if="tournamentChampion"
                            class="relative flex-shrink-0"
                            :style="{ width: '160px', height: `${deWinnersHeight}px` }"
                        >
                            <div
                                class="absolute left-0 right-0"
                                :style="{
                                    top: `${deWinnersHeight / 2 - bracketMatchHeight / 2}px`,
                                    height: `${bracketMatchHeight}px`,
                                }"
                            >
                                <div class="mb-1 flex items-center justify-center">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-yellow-200 bg-yellow-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-yellow-700 dark:border-yellow-500/30 dark:bg-yellow-500/20 dark:text-yellow-300"
                                    >
                                        <Trophy class="h-3 w-3" /> Champion
                                    </span>
                                </div>
                                <div
                                    class="w-full overflow-hidden rounded-xl border-2 border-yellow-400 shadow-md shadow-yellow-200 transition dark:border-yellow-500/60 dark:shadow-yellow-500/20"
                                >
                                    <div class="flex h-8 items-center justify-center px-3 bg-gradient-to-r from-yellow-50 to-amber-50 dark:from-yellow-900/30 dark:to-amber-900/30">
                                        <Trophy class="mr-1 h-3 w-3 text-yellow-500 dark:text-yellow-400" />
                                        <span class="truncate text-xs font-bold text-yellow-800 dark:text-yellow-200">{{ teamName(tournamentChampion) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Losers Bracket Section -->
                    <template v-if="activeTournament.type === 'double_elimination' && losersRounds.length > 0">
                        <div class="mb-2 mt-8 flex items-center gap-2">
                            <div class="h-2 w-2 rounded-full bg-rose-500"></div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Losers Bracket</h3>
                            <div class="h-px flex-1 bg-gradient-to-r from-rose-500/40 to-transparent"></div>
                        </div>

                        <!-- Losers Round Headers -->
                        <div class="mb-2 flex items-stretch">
                            <template v-for="(round, rIdx) in losersRounds" :key="`lhdr-${round}`">
                                <div class="flex flex-1 items-center justify-center">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{
                                        roundLabel(round, losersRounds.length, 'losers')
                                    }}</span>
                                </div>
                                <div v-if="rIdx < losersRounds.length - 1" :style="{ width: `${bracketConnectorWidth}px` }"></div>
                            </template>
                        </div>

                        <!-- Losers Bracket Body -->
                        <div class="flex items-stretch" :style="{ height: `${deLosersHeight}px` }">
                            <template v-for="(round, rIdx) in losersRounds" :key="`lbody-${round}`">
                                <div class="relative flex-1" :style="{ height: `${deLosersHeight}px` }">
                                    <div
                                        v-for="(match, mIdx) in losersMatches[round]"
                                        :key="match.id"
                                        class="group absolute left-0 right-0 transition"
                                        :style="{ top: `${deLosersRoundY(round, mIdx) - bracketMatchHeight / 2}px`, height: `${bracketMatchHeight}px` }"
                                    >
                                        <div
                                            :class="[
                                                'w-full overflow-hidden rounded-xl border shadow-sm transition',
                                                match.winner_id
                                                    ? 'border-rose-300 dark:border-rose-500/30'
                                                    : 'border-slate-200 dark:border-[#1a1a1a]',
                                            ]"
                                        >
                                            <div v-if="match.scheduled_time || match.court_number" class="flex h-5 items-center justify-between px-3 py-0.5 bg-slate-50/50 dark:bg-[#0a0a0a]/30 border-b border-slate-100 dark:border-[#1a1a1a]/40 text-[8px] font-black uppercase tracking-wider text-slate-400">
                                                <span>
                                                    <span v-if="match.court_number" class="font-bold text-violet-600 dark:text-violet-400 mr-1">Court {{ match.court_number }}</span>
                                                    <span v-else-if="match.team1_id && match.team2_id && !match.winner_id" class="font-bold text-amber-600 dark:text-amber-400 mr-1">Waiting for Court</span>
                                                    <span v-else>Time Slot</span>
                                                </span>
                                                <span v-if="match.scheduled_time" class="text-indigo-600 dark:text-green-400 font-bold">{{ formatTime(match.scheduled_time) }}</span>
                                            </div>
                                            <div
                                                :class="[
                                                    'flex h-8 items-center justify-between px-3',
                                                    match.winner_id === match.team1_id
                                                        ? 'bg-rose-50 font-bold text-rose-800 dark:bg-rose-900/30 dark:text-rose-300'
                                                        : match.winner_id
                                                          ? 'bg-slate-50 text-slate-500 dark:bg-[#1a1a1a]/50 dark:text-slate-400'
                                                          : 'bg-white text-slate-800 dark:bg-[#1a1a1a] dark:text-slate-200',
                                                ]"
                                            >
                                                <span class="truncate text-xs">{{ teamName(match.team1) }}</span>
                                                <span class="ml-2 font-mono text-xs font-semibold tabular-nums">{{ match.team1_score ?? '' }}</span>
                                            </div>
                                            <div
                                                :class="[
                                                    'flex h-8 items-center justify-between border-t border-slate-100 px-3 dark:border-[#1a1a1a]/60',
                                                    match.winner_id === match.team2_id
                                                        ? 'bg-rose-50 font-bold text-rose-800 dark:bg-rose-900/30 dark:text-rose-300'
                                                        : match.winner_id
                                                          ? 'bg-slate-50 text-slate-500 dark:bg-[#1a1a1a]/50 dark:text-slate-400'
                                                          : 'bg-white text-slate-800 dark:bg-[#1a1a1a] dark:text-slate-200',
                                                ]"
                                            >
                                                <span class="truncate text-xs">{{ teamName(match.team2) }}</span>
                                                <span class="ml-2 font-mono text-xs font-semibold tabular-nums">{{ match.team2_score ?? '' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Losers connectors -->
                                <div v-if="rIdx < losersRounds.length - 1" class="relative flex-shrink-0" :style="{ width: `${bracketConnectorWidth}px` }">
                                    <svg :height="deLosersHeight" :width="bracketConnectorWidth" class="absolute inset-0">
                                        <template v-if="round % 2 === 0">
                                            <g v-for="(parentMatch, pIdx) in losersMatches[losersRounds[rIdx + 1]]" :key="parentMatch.id">
                                                <template v-if="losersMatches[round] && losersMatches[round].length >= 2 * pIdx + 2">
                                                    <line :x1="0" :y1="deLosersRoundY(round, 2 * pIdx)" :x2="bracketConnectorWidth / 2" :y2="deLosersRoundY(round, 2 * pIdx)" stroke="#64748b" stroke-width="2" />
                                                    <line :x1="0" :y1="deLosersRoundY(round, 2 * pIdx + 1)" :x2="bracketConnectorWidth / 2" :y2="deLosersRoundY(round, 2 * pIdx + 1)" stroke="#64748b" stroke-width="2" />
                                                    <line :x1="bracketConnectorWidth / 2" :y1="deLosersRoundY(round, 2 * pIdx)" :x2="bracketConnectorWidth / 2" :y2="deLosersRoundY(round, 2 * pIdx + 1)" stroke="#64748b" stroke-width="2" />
                                                    <line :x1="bracketConnectorWidth / 2" :y1="deLosersRoundY(losersRounds[rIdx + 1], pIdx)" :x2="bracketConnectorWidth" :y2="deLosersRoundY(losersRounds[rIdx + 1], pIdx)" stroke="#64748b" stroke-width="2" />
                                                </template>
                                            </g>
                                        </template>
                                        <template v-else>
                                            <g v-for="(match, mIdx) in losersMatches[round]" :key="match.id">
                                                <line :x1="0" :y1="deLosersRoundY(round, mIdx)" :x2="bracketConnectorWidth" :y2="deLosersRoundY(losersRounds[rIdx + 1], mIdx)" stroke="#64748b" stroke-width="2" />
                                            </g>
                                        </template>
                                    </svg>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Mobile: List -->
                <div class="sm:hidden space-y-6">
                    <!-- Winners Bracket -->
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]">
                        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-[#1a1a1a]/40 dark:bg-[#1a1a1a]/40">
                            <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Winners Bracket</h3>
                        </div>
                        <div class="space-y-5 p-3 sm:p-4">
                            <div v-for="round in winnersRounds" :key="`w-${round}`">
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                        {{ roundLabel(round, winnersRounds.length, 'winners') }}
                                    </span>
                                    <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]/40"></div>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <MatchCard
                                        v-for="match in winnersMatches[round]"
                                        :key="match.id"
                                        :match="match"
                                        variant="winners"
                                        :best-of="activeTournament.best_of ?? 1"
                                        :clickable="false"
                                        :editable="false"
                                        :swappable="false"
                                        :has-sub-folder="!!activeTournament?.tournament_sub_folder_id"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Losers Bracket (double elimination only) -->
                    <div v-if="activeTournament.type === 'double_elimination' && losersRounds.length > 0" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]">
                        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-[#1a1a1a]/40 dark:bg-[#1a1a1a]/40">
                            <div class="h-2 w-2 rounded-full bg-rose-500"></div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Losers Bracket</h3>
                        </div>
                        <div class="space-y-5 p-3 sm:p-4">
                            <div v-for="round in losersRounds" :key="`l-${round}`">
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                        {{ roundLabel(round, losersRounds.length, 'losers') }}
                                    </span>
                                    <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]/40"></div>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <MatchCard
                                        v-for="match in losersMatches[round]"
                                        :key="match.id"
                                        :match="match"
                                        variant="losers"
                                        :best-of="activeTournament.best_of ?? 1"
                                        :clickable="false"
                                        :editable="false"
                                        :swappable="false"
                                        :has-sub-folder="!!activeTournament?.tournament_sub_folder_id"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grand Final (double elimination only) -->
                    <div v-if="grandFinalMatch" class="overflow-hidden rounded-2xl border border-yellow-300 bg-white shadow-sm dark:border-yellow-500/30 dark:bg-[#0f0f0f]">
                        <div class="flex items-center gap-2 border-b border-yellow-300/60 bg-yellow-50 px-4 py-3 dark:border-yellow-500/20 dark:bg-yellow-500/10">
                            <Award class="h-4 w-4 text-yellow-500 dark:text-yellow-400" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-yellow-700 dark:text-yellow-400">Grand Final</h3>
                        </div>
                        <div class="p-3 sm:p-4">
                            <MatchCard
                                :match="grandFinalMatch"
                                variant="grand_final"
                                :best-of="activeTournament.best_of ?? 1"
                                :clickable="false"
                                :editable="false"
                                :swappable="false"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROUND ROBIN -->
            <div v-else-if="activeTournament.type === 'round_robin'">
                <!-- Leaderboard -->
                <div
                    v-if="roundRobinStandings.length > 0"
                    class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]"
                >
                    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-[#1a1a1a]/40 dark:bg-[#1a1a1a]/40">
                        <Award class="h-4 w-4 text-yellow-500 dark:text-yellow-400" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Leaderboard</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs text-slate-400 dark:border-[#1a1a1a]/40 dark:text-slate-500">
                                    <th class="w-12 px-4 py-2 text-left font-medium">Rank</th>
                                    <th class="px-4 py-2 text-left font-medium">Team</th>
                                    <th class="px-4 py-2 text-center font-medium">W</th>
                                    <th class="px-4 py-2 text-center font-medium">L</th>
                                    <th class="px-4 py-2 text-center font-medium">PF</th>
                                    <th class="px-4 py-2 text-center font-medium">PA</th>
                                    <th class="px-4 py-2 text-center font-medium">+/-</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(s, idx) in roundRobinStandings"
                                    :key="s.team.id"
                                    class="border-b border-slate-100 text-slate-800 dark:border-[#1a1a1a]/20 dark:text-slate-200"
                                >
                                    <td class="px-4 py-2.5">
                                        <span
                                            v-if="idx === 0"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-yellow-100 text-xs font-bold text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-300"
                                        >1</span>
                                        <span
                                            v-else-if="idx === 1"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-700 dark:bg-[#2a2a2a]/30 dark:text-slate-300"
                                        >2</span>
                                        <span
                                            v-else-if="idx === 2"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700 dark:bg-amber-600/20 dark:text-amber-300"
                                        >3</span>
                                        <span
                                            v-else
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-[#1a1a1a]/30 dark:text-slate-400"
                                        >{{ idx + 1 }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <Users class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" />
                                            <span class="truncate font-medium">{{ teamName(s.team) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ s.wins }}</td>
                                    <td class="px-4 py-2.5 text-center font-bold text-rose-600 dark:text-rose-400">{{ s.losses }}</td>
                                    <td class="px-4 py-2.5 text-center text-slate-500 dark:text-slate-400">{{ s.pf }}</td>
                                    <td class="px-4 py-2.5 text-center text-slate-500 dark:text-slate-400">{{ s.pa }}</td>
                                    <td
                                        class="px-4 py-2.5 text-center font-bold"
                                        :class="s.pf - s.pa >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                    >
                                        {{ s.pf - s.pa > 0 ? '+' : '' }}{{ s.pf - s.pa }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Rounds -->
                <div class="space-y-5">
                    <div v-for="round in rrRounds" :key="round">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600 dark:bg-[#1a1a1a] dark:text-slate-400">
                                Round {{ round }}
                            </span>
                            <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]/40"></div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <MatchCard
                                v-for="match in roundRobinMatches[round]"
                                :key="match.id"
                                :match="match"
                                variant="round_robin"
                                :best-of="activeTournament.best_of ?? 1"
                                :clickable="false"
                                :editable="false"
                                :swappable="false"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-400 dark:border-slate-800 dark:text-slate-600">
            <p>Spectator view · auto-refreshes every 5 seconds · read-only</p>
        </footer>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
