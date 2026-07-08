<script setup lang="ts">
import { ArrowLeftRight, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';

interface Team {
    id: number;
    player1_name?: string;
    player2_name?: string;
}

interface Match {
    id: number;
    team1?: Team | null;
    team2?: Team | null;
    team1_id?: number | null;
    team2_id?: number | null;
    team1_score?: number | null;
    team2_score?: number | null;
    winner_id?: number | null;
    scheduled_time?: string | null;
    court_number?: number | null;
    bypass_count?: number;
    is_forfeited?: boolean;
}

const props = withDefaults(
    defineProps<{
        match: Match;
        variant?: 'winners' | 'losers' | 'grand_final' | 'round_robin';
        clickable?: boolean;
        showScores?: boolean;
        compact?: boolean;
        editable?: boolean;
        swappable?: boolean;
        bestOf?: 1 | 3 | 5;
        hasSubFolder?: boolean;
    }>(),
    {
        variant: 'winners',
        clickable: false,
        showScores: true,
        compact: false,
        editable: false,
        swappable: false,
        bestOf: 1,
        hasSubFolder: false,
    },
);

const emit = defineEmits<{
    (e: 'click'): void;
    (e: 'edit'): void;
    (e: 'swap'): void;
}>();

const handleEdit = (event: Event) => {
    event.stopPropagation();
    emit('edit');
};

const handleSwap = (event: Event) => {
    event.stopPropagation();
    emit('swap');
};

const teamName = (team?: Team | null): string => {
    if (!team) return 'TBD';
    return `${team.player1_name ?? ''} & ${team.player2_name ?? ''}`;
};

const formatTime = (timeStr?: string | null) => {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    const h = parseInt(parts[0]);
    const m = parts[1] || '00';
    const ampm = h >= 12 ? 'PM' : 'AM';
    const displayHour = h % 12 === 0 ? 12 : h % 12;
    return `${displayHour}:${m} ${ampm}`;
};

const accentColors = {
    winners: {
        winnerBorder: 'border-emerald-300 dark:border-emerald-500/40',
        winnerRow: 'bg-emerald-50 dark:bg-emerald-600/25 text-emerald-800 dark:text-emerald-100 font-bold',
    },
    losers: {
        winnerBorder: 'border-rose-300 dark:border-rose-500/40',
        winnerRow: 'bg-rose-50 dark:bg-rose-600/25 text-rose-800 dark:text-rose-100 font-bold',
    },
    grand_final: {
        winnerBorder: 'border-yellow-300 dark:border-yellow-500/40 shadow-yellow-200 dark:shadow-yellow-500/20',
        winnerRow: 'bg-yellow-50 dark:bg-yellow-600/30 text-yellow-800 dark:text-yellow-100 font-bold',
    },
    round_robin: {
        winnerBorder: 'border-emerald-300 dark:border-emerald-500/40',
        winnerRow: 'bg-emerald-50 dark:bg-emerald-600/25 text-emerald-800 dark:text-emerald-100 font-bold',
    },
};

const accent = computed(() => accentColors[props.variant]);

const borderClass = computed(() => {
    const base = 'rounded-xl overflow-hidden border shadow-sm transition w-full';
    if (props.match.winner_id) return `${base} ${accent.value.winnerBorder}`;
    if (props.variant === 'grand_final') return `${base} border-yellow-300 dark:border-yellow-600/40`;
    return `${base} border-slate-200 dark:border-[#1a1a1a]`;
});

const hoverClass = computed(() => {
    if (!props.clickable) return '';
    if (props.variant === 'grand_final') return 'cursor-pointer hover:border-yellow-500 dark:hover:border-yellow-400';
    return 'cursor-pointer group-hover:border-green-400 dark:group-hover:border-green-500/40 group-hover:shadow-green-500/20 dark:group-hover:shadow-green-500/20';
});

const rowClass = (isTeam1: boolean) => {
    const teamId = isTeam1 ? props.match.team1_id : props.match.team2_id;
    const heightClass = props.compact ? 'h-7' : 'h-8';
    const base = `flex items-center justify-between px-3 ${heightClass}`;
    const border = !isTeam1 ? 'border-t border-slate-100 dark:border-[#1a1a1a]/60' : '';
    if (props.match.winner_id === teamId && teamId) {
        return `${base} ${border} ${accent.value.winnerRow}`;
    }
    if (props.match.winner_id) {
        return `${base} ${border} bg-slate-50 dark:bg-[#1a1a1a]/50 text-slate-400 dark:text-slate-500`;
    }
    return `${base} ${border} bg-white dark:bg-[#1a1a1a] text-slate-700 dark:text-slate-200`;
};

const score = (s: number | null | undefined) => (s === null || s === undefined ? '' : s);
</script>

<template>
    <div :class="['group relative transition', clickable ? 'cursor-pointer' : '']" @click="clickable ? $emit('click') : null">
        <div
            v-if="bestOf > 1"
            class="absolute left-1 top-1 z-10 inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-violet-700 shadow-sm dark:bg-violet-500/20 dark:text-violet-300"
        >
            Bo{{ bestOf }}
        </div>
        <div class="absolute right-1 top-1 z-10 flex gap-1">
            <button
                v-if="swappable && !match.winner_id"
                @click="handleSwap"
                class="rounded bg-white/80 p-1 text-slate-400 opacity-0 shadow-sm transition hover:text-amber-500 group-hover:opacity-100 dark:bg-[#0f0f0f]/80 dark:hover:text-amber-400"
            >
                <ArrowLeftRight class="h-3 w-3" />
            </button>
            <button
                v-if="editable && !match.winner_id"
                @click="handleEdit"
                class="rounded bg-white/80 p-1 text-slate-400 opacity-0 shadow-sm transition hover:text-blue-500 group-hover:opacity-100 dark:bg-[#0f0f0f]/80 dark:hover:text-green-400"
            >
                <Pencil class="h-3 w-3" />
            </button>
        </div>
        <div :class="[borderClass, hoverClass]">
            <!-- Scheduled Time Header -->
            <div v-if="match.scheduled_time || match.court_number || match.is_forfeited || (match.bypass_count && match.bypass_count > 0)" class="flex items-center justify-between px-3 py-1 bg-slate-50/50 dark:bg-[#0a0a0a]/30 border-b border-slate-100 dark:border-[#1a1a1a]/40 text-[9px] font-black uppercase tracking-wider text-slate-400">
                <span>
                    <span v-if="match.is_forfeited" class="font-bold text-red-600 dark:text-red-400 mr-2">Forfeited</span>
                    <span v-else-if="match.court_number" class="font-bold text-violet-600 dark:text-violet-400 mr-2">Court {{ match.court_number }}</span>
                    <span v-else-if="match.team1_id && match.team2_id && !match.winner_id" class="font-bold text-amber-600 dark:text-amber-400 mr-2">
                        Waiting for Court
                        <span v-if="match.bypass_count && match.bypass_count > 0" class="text-[8px] text-slate-500 font-normal lowercase">(bypassed x{{ match.bypass_count }})</span>
                    </span>
                    <span v-else>Time Slot</span>
                </span>
                <span v-if="match.scheduled_time" class="text-indigo-600 dark:text-green-400 font-bold">{{ formatTime(match.scheduled_time) }}</span>
            </div>
            <!-- Team 1 row -->
            <div :class="rowClass(true)">
                <span class="truncate text-xs flex-1 min-w-0 pr-2">{{ teamName(match.team1) }}</span>
                <span v-if="showScores" class="ml-2 font-mono text-xs tabular-nums flex-shrink-0">{{ score(match.team1_score) }}</span>
            </div>
            <!-- Team 2 row -->
            <div :class="rowClass(false)">
                <span class="truncate text-xs flex-1 min-w-0 pr-2">{{ teamName(match.team2) }}</span>
                <span v-if="showScores" class="ml-2 font-mono text-xs tabular-nums flex-shrink-0">{{ score(match.team2_score) }}</span>
            </div>
        </div>
    </div>
</template>
