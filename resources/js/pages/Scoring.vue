<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Bell, CheckCircle, ChevronDown, Clock, DollarSign, GripVertical, History, ListOrdered, Pencil, Plus, Shuffle, Swords, Trash2, Trophy, User, UserPlus, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    matches: any[];
    players: any[];
    allPlayers: any[];
    bookingRoster?: Array<{ id: number; name: string; status: 'pending' | 'accepted' | 'declined'; user_id?: number | null; responded_at?: string | null }>;
    settings?: {
        court_count: string;
        booking_expiration_grace_minutes?: string;
        player_scoring_mode?: boolean;
        player_warning_minutes?: number;
        player_scoring_blocked?: boolean;
        player_scoring_view_only?: boolean;
    };
    assignedCourts?: number[];
    activeBookings?: Record<number, { type: string; start_time: string; end_time: string; lead_name: string } | string>;
    playerBooking?: { id: number; court_number: number; venue_name?: string | null; start_time: string; end_time: string; lead_name: string; access_mode?: 'edit' | 'view' | null } | null;
    scoringState?: {
        activePlayerIds?: number[];
        localRegisteredPlayerIds?: number[];
        tempSessionPlayers?: SessionPlayer[];
        queue?: QueuedMatch[];
        currentMatch?: StoredCurrentMatch | null;
        lateJoinerOffsets?: Record<number, number>;
        playerPairs?: Record<number, number>;
        playerGroups?: Array<{ type: 'none' | 'pair' | 'quad'; playerIds: (number | null)[] }>;
    } | null;
    playerScoringNotice?: string | null;
    upcomingNotice?: string | null;
}>();

type SessionPlayer = {
    id: number;
    name: string;
    is_member?: boolean;
    total_matches?: number;
    wins?: number;
    losses?: number;
    win_rate?: number;
    isTemporary?: boolean;
};

type QueuedMatch = {
    player1Id: number;
    player2Id: number;
    player3Id?: number | null;
    player4Id?: number | null;
};

type StoredCurrentMatch = {
    player1Id: number;
    player2Id: number;
    player3Id?: number | null;
    player4Id?: number | null;
};

type SharedScoringState = {
    activePlayerIds?: number[];
    localRegisteredPlayerIds?: number[];
    tempSessionPlayers?: SessionPlayer[];
    queue?: QueuedMatch[];
    currentMatch?: StoredCurrentMatch | null;
    lateJoinerOffsets?: Record<number, number>;
    playerPairs?: Record<number, number>;
    playerGroups?: Array<{ type: 'none' | 'pair' | 'quad'; playerIds: (number | null)[] }>;
};
const getCourtActiveType = (court: number) => {
    const b = props.activeBookings?.[court];
    if (!b) return null;
    return typeof b === 'object' ? b.type : b;
};

// Court selection
const courtCount = computed(() => parseInt(props.settings?.court_count || '1') || 1);

const courtModeLabel = (court: number) => {
    const activeType = getCourtActiveType(court);
    if (activeType) {
        if (activeType === 'walk-in') return 'Walk-in';
        if (activeType === 'reclub') return 'Reclub';
        return 'Booking';
    }
    return 'Booking';
};
const courtModeColor = (court: number) => {
    const activeType = getCourtActiveType(court);
    if (activeType === 'walk-in') return 'bg-amber-400';
    if (activeType === 'reclub') return 'bg-violet-400';
    return 'bg-emerald-400';
};
const page = usePage();
const isScorer = computed(() => {
    const role = (page.props as any).auth?.user?.role;
    return role === 'scorer' || role === 'scheduler_scorer';
});
const isPlayerScoringMode = computed(() => Boolean(props.settings?.player_scoring_mode));
const isPlayerScoringBlocked = computed(() => Boolean(props.settings?.player_scoring_blocked));
const isPlayerScoringViewOnly = computed(() => Boolean(props.settings?.player_scoring_view_only));
const isPlayerBookingOwner = computed(() => isPlayerScoringMode.value && !isPlayerScoringViewOnly.value);
const canEditScoringBoard = computed(() => !isPlayerScoringViewOnly.value);
const canManageVenueRoster = computed(() => !isPlayerScoringMode.value);
const canAddRosterPlayers = computed(() => canManageVenueRoster.value || isPlayerBookingOwner.value);
const bookingRoster = computed(() => props.bookingRoster ?? []);
const pendingBookingRoster = computed(() => bookingRoster.value.filter((player) => player.status === 'pending'));

const allCourts = computed<{ court: number; mode: 'booking' | 'walkin' | 'reclub' }[]>(() => {
    if (isPlayerScoringMode.value && props.playerBooking) {
        return [{ court: props.playerBooking.court_number, mode: 'booking' }];
    }
    const courts = Array.from({ length: courtCount.value }, (_, i) => i + 1);
    const list: { court: number; mode: 'booking' | 'walkin' | 'reclub' }[] = [];
    for (const c of courts) {
        if (isScorer.value && !props.assignedCourts?.includes(c)) continue;
        
        const activeType = getCourtActiveType(c);
        if (activeType) {
            const mappedMode = activeType === 'walk-in' ? 'walkin' : (activeType === 'reclub' ? 'reclub' : 'booking');
            list.push({ court: c, mode: mappedMode });
        } else {
            list.push({ court: c, mode: 'walkin' });
        }
    }
    return list;
});
const selectedCourt = ref<number | null>(allCourts.value[0]?.court ?? null);
const selectedCourtMode = ref<'booking' | 'walkin' | 'reclub'>(allCourts.value[0]?.mode ?? 'booking');

const activeBookingForSelectedCourt = computed(() => {
    if (selectedCourt.value === null || !props.activeBookings) return null;
    const b = props.activeBookings[selectedCourt.value];
    return typeof b === 'object' ? b : null;
});

const minutesRemaining = computed(() => {
    const booking = activeBookingForSelectedCourt.value;
    if (!booking || !booking.end_time || !booking.start_time) return null;

    const [sh, sm] = booking.start_time.split(':').map(Number);
    const [hours, minutes] = booking.end_time.split(':').map(Number);
    const bookingEnd = new Date();
    bookingEnd.setHours(hours, minutes, 0, 0);

    // If the booking crosses midnight, the end time belongs to the next day
    if (hours < sh || (hours === sh && minutes <= sm)) {
        bookingEnd.setDate(bookingEnd.getDate() + 1);
    }

    const diffMs = bookingEnd.getTime() - new Date().getTime();
    return Math.max(0, Math.floor(diffMs / 60000));
});

const graceMinutes = computed(() => {
    if (isPlayerScoringMode.value) {
        return props.settings?.player_warning_minutes || 5;
    }
    return parseInt(props.settings?.booking_expiration_grace_minutes || '10') || 10;
});

const isTimeAlmostExpired = computed(() => {
    return minutesRemaining.value !== null && minutesRemaining.value <= graceMinutes.value && minutesRemaining.value > 0;
});

const isTimeFullyExpired = computed(() => {
    return minutesRemaining.value !== null && minutesRemaining.value <= 0;
});

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

const isQueueLocked = computed(() => {
    const booking = activeBookingForSelectedCourt.value;
    if (!booking) return false;
    if (booking.type === 'walk-in') return false;
    return isTimeFullyExpired.value;
});

// Keep selectedCourtMode in sync when active bookings change in the background
watch(
    () => props.activeBookings,
    () => {
        if (selectedCourt.value !== null) {
            const courtObj = allCourts.value.find((c) => c.court === selectedCourt.value);
            if (courtObj) {
                selectedCourtMode.value = courtObj.mode;
            }
        }
    },
    { deep: true }
);

const isSelectedCourtWalkin = computed(() => {
    return selectedCourtMode.value === 'walkin';
});
const selectCourt = (court: number, mode: 'booking' | 'walkin' | 'reclub') => {
    selectedCourt.value = court;
    selectedCourtMode.value = mode;
    showCourtDropdown.value = false;
    showCourtDropdownMobile.value = false;
};
const courtOptionColor = (mode: 'booking' | 'walkin' | 'reclub') => {
    if (mode === 'walkin') return 'bg-amber-400';
    if (mode === 'reclub') return 'bg-violet-400';
    return 'bg-emerald-400';
};

const selectedCourtButtonClass = computed(() => {
    if (selectedCourtMode.value === 'walkin') {
        return 'border-amber-200 bg-amber-50 text-amber-800 ring-amber-400 focus:ring-2 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200';
    }
    if (selectedCourtMode.value === 'reclub') {
        return 'border-violet-200 bg-violet-50 text-violet-800 ring-violet-400 focus:ring-2 dark:border-violet-700 dark:bg-violet-900/20 dark:text-violet-200';
    }
    return 'border-emerald-200 bg-emerald-50 text-emerald-800 ring-emerald-400 focus:ring-2 dark:border-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-200';
});

const showTotalFee = computed(() => isSelectedCourtWalkin.value);
const localRegisteredSessionPlayers = ref<SessionPlayer[]>([]);
const tempSessionPlayers = ref<SessionPlayer[]>([]);
const derivedBookingRosterPlayers = computed<SessionPlayer[]>(() => {
    return bookingRoster.value
        .filter((player) => player.status === 'accepted')
        .map((player) => findRegisteredPlayerById(player.id))
        .filter((player): player is NonNullable<typeof player> => Boolean(player))
        .map(mapRegisteredPlayerToSessionPlayer);
});

const derivedTemporaryMatchPlayers = computed<SessionPlayer[]>(() => {
    const temporaryNames = new Set<string>();

    const existingNames = new Set<string>();
    [
        ...props.players,
        ...derivedBookingRosterPlayers.value,
        ...localRegisteredSessionPlayers.value,
        ...tempSessionPlayers.value
    ].forEach((p) => {
        if (p && p.name) {
            existingNames.add(normalizePlayerName(p.name));
        }
    });

    for (const match of localMatches.value) {
        [match.player_1_name, match.player_2_name, match.player_3_name, match.player_4_name]
            .map((name) => String(name ?? '').trim())
            .filter((name) => name !== '')
            .forEach((name) => {
                const normalized = normalizePlayerName(name);
                if (!existingNames.has(normalized)) {
                    temporaryNames.add(name);
                }
            });
    }

    return Array.from(temporaryNames).map((name, index) => ({
        id: -1000 - index,
        name,
        is_member: false,
        total_matches: 0,
        wins: 0,
        losses: 0,
        win_rate: 0,
        isTemporary: true,
    }));
});

const sessionPlayers = computed<SessionPlayer[]>(() => {
    const merged = [
        ...(isPlayerScoringMode.value ? [] : props.players),
        ...derivedBookingRosterPlayers.value,
        ...localRegisteredSessionPlayers.value,
        ...derivedTemporaryMatchPlayers.value,
        ...tempSessionPlayers.value,
    ];
    const uniquePlayers = new Map<number, SessionPlayer>();
    for (const player of merged) {
        if (!uniquePlayers.has(player.id)) {
            uniquePlayers.set(player.id, player);
        }
    }
    return Array.from(uniquePlayers.values());
});
const sessionPlayerMap = computed(() => new Map(sessionPlayers.value.map((player) => [player.id, player])));
const defaultActiveBookingPlayerIds = computed(() => new Set(derivedBookingRosterPlayers.value.map((player) => player.id)));
const nextTemporaryPlayerId = ref(-1);
const courtLocked = computed(() => sessionPlayers.value.length > 0);
const showCourtDropdown = ref(false);
const showCourtDropdownMobile = ref(false);
const courtDropdownRef = ref<HTMLElement | null>(null);
const courtDropdownRefMobile = ref<HTMLElement | null>(null);

// Close dropdowns on outside click
const closeCourtDropdown = (e: MouseEvent) => {
    if (courtDropdownRef.value && !courtDropdownRef.value.contains(e.target as Node)) showCourtDropdown.value = false;
    if (courtDropdownRefMobile.value && !courtDropdownRefMobile.value.contains(e.target as Node)) showCourtDropdownMobile.value = false;
};
if (typeof window !== 'undefined') {
    window.addEventListener('click', closeCourtDropdown);
}

const localMatches = ref<any[]>([...props.matches]);
watch(
    () => props.matches,
    (val) => {
        localMatches.value = [...val];
    },
);

const normalizePlayerName = (value: string | null | undefined) => String(value ?? '').trim().toLowerCase();

const rosterSessionPlayers = computed<SessionPlayer[]>(() => {
    const winPoints = Number(props.settings?.scoring_win_points || 10);
    const lossPenalty = Number(props.settings?.scoring_loss_penalty || 5);

    return sessionPlayers.value.map((player) => {
        let wins = 0;
        let losses = 0;

        for (const match of localMatches.value) {
            const teamOneWon = Number(match.player_1_score) > Number(match.player_2_score);
            const teamTwoWon = Number(match.player_2_score) > Number(match.player_1_score);

            const registeredIds = {
                p1: match.player_1_id != null ? Number(match.player_1_id) : null,
                p2: match.player_2_id != null ? Number(match.player_2_id) : null,
                p3: match.player_3_id != null ? Number(match.player_3_id) : null,
                p4: match.player_4_id != null ? Number(match.player_4_id) : null,
            };

            const temporaryNames = {
                p1: normalizePlayerName(match.player_1_name),
                p2: normalizePlayerName(match.player_2_name),
                p3: normalizePlayerName(match.player_3_name),
                p4: normalizePlayerName(match.player_4_name),
            };

            const isTemporaryPlayer = Boolean(player.isTemporary || player.id < 0);
            const playerName = normalizePlayerName(player.name);

            const isOnTeamOne = isTemporaryPlayer
                ? temporaryNames.p1 === playerName || temporaryNames.p3 === playerName
                : registeredIds.p1 === Number(player.id) || registeredIds.p3 === Number(player.id);

            const isOnTeamTwo = isTemporaryPlayer
                ? temporaryNames.p2 === playerName || temporaryNames.p4 === playerName
                : registeredIds.p2 === Number(player.id) || registeredIds.p4 === Number(player.id);

            if (isOnTeamOne) {
                if (teamOneWon) wins++;
                else if (teamTwoWon) losses++;
            } else if (isOnTeamTwo) {
                if (teamTwoWon) wins++;
                else if (teamOneWon) losses++;
            }
        }

        const total = wins + losses;
        const winRate = total > 0 ? Math.round((wins / total) * 1000) / 10 : 0;

        return {
            ...player,
            wins,
            losses,
            total_matches: total,
            win_rate: winRate,
            points: wins * winPoints - losses * lossPenalty,
        };
    });
});

const SCORING_QUEUE_STORAGE_KEY = 'pickle_scoring_round_robin_queue_v1';
const sharedScoringStateSyncEnabled = computed(() => Boolean(isPlayerScoringMode.value && props.playerBooking?.id));
const canWriteSharedScoringState = computed(() => sharedScoringStateSyncEnabled.value && !isPlayerScoringViewOnly.value);
const scoringQueueStorageKey = computed(() => (props.playerBooking ? `${SCORING_QUEUE_STORAGE_KEY}_${props.playerBooking.id}` : SCORING_QUEUE_STORAGE_KEY));
let scoringStateSaveTimer: ReturnType<typeof setTimeout> | null = null;
const appAlert = ref<{ message: string; tone: 'info' | 'error' } | null>(null);
const showConfirmModal = ref(false);
const confirmMessage = ref('');
const confirmAction = ref<null | (() => void)>(null);
const showReplacePlayerModal = ref(false);
const replacingSlot = ref<'p1' | 'p2' | 'p3' | 'p4' | null>(null);
const replacementPlayerId = ref<number | null>(null);
const showCustomMatchModal = ref(false);

let alertTimeout: any = null;
const showSystemAlert = (message: string, tone: 'info' | 'error' = 'info') => {
    appAlert.value = { message, tone };
    if (alertTimeout) {
        window.clearTimeout(alertTimeout);
    }
    alertTimeout = window.setTimeout(() => {
        appAlert.value = null;
        alertTimeout = null;
    }, 5000);
};

const openSystemConfirm = (message: string, action: () => void) => {
    confirmMessage.value = message;
    confirmAction.value = action;
    showConfirmModal.value = true;
};

const cancelSystemConfirm = () => {
    showConfirmModal.value = false;
    confirmMessage.value = '';
    confirmAction.value = null;
};

const runSystemConfirm = () => {
    if (confirmAction.value) {
        confirmAction.value();
    }
    cancelSystemConfirm();
};

// --- Active Player Management (Center Panel) ---
const activePlayerIds = ref<Set<number>>(new Set());
const lateJoinerOffsets = ref<Record<number, number>>({});
const playerPairs = ref<Record<number, number>>({});

const findPlayerByName = (name: string) => {
    const normalized = name.trim().toLowerCase();
    return sessionPlayers.value.find((player) => player.name.trim().toLowerCase() === normalized) ?? null;
};

const findRegisteredPlayerById = (id: number | string | null | undefined) => {
    if (id == null || id === '') return null;
    return props.allPlayers.find((player: any) => Number(player.id) === Number(id)) ?? null;
};

const createTemporaryPlayer = (name: string) => {
    const trimmedName = name.trim();
    if (!trimmedName) return null;

    const existing = findPlayerByName(trimmedName);
    if (existing) {
        return existing;
    }

    const temporaryPlayer: SessionPlayer = {
        id: nextTemporaryPlayerId.value--,
        name: trimmedName,
        is_member: false,
        total_matches: 0,
        wins: 0,
        losses: 0,
        win_rate: 0,
        isTemporary: true,
    };

    tempSessionPlayers.value = [...tempSessionPlayers.value, temporaryPlayer];
    return temporaryPlayer;
};

const mapRegisteredPlayerToSessionPlayer = (player: any): SessionPlayer => ({
    id: Number(player.id),
    name: String(player.name ?? '').trim(),
    is_member: Boolean(player.is_member),
    total_matches: 0,
    wins: 0,
    losses: 0,
    win_rate: 0,
});

const addLocalRegisteredPlayersToSession = (players: any[]) => {
    if (players.length === 0) return;

    const existingIds = new Set(sessionPlayers.value.map((player) => player.id));
    const additions = players
        .filter((player) => !existingIds.has(Number(player.id)))
        .map(mapRegisteredPlayerToSessionPlayer);

    if (additions.length > 0) {
        localRegisteredSessionPlayers.value = [...localRegisteredSessionPlayers.value, ...additions];
    }

    const newSet = new Set(activePlayerIds.value);
    for (const player of players) {
        const playerId = Number(player.id);
        newSet.add(playerId);
        registerLateJoinerOffset(playerId);
    }
    activePlayerIds.value = newSet;
};

const removeTemporaryPlayerState = (playerId: number) => {
    tempSessionPlayers.value = tempSessionPlayers.value.filter((player) => player.id !== playerId);

    const nextActive = new Set(activePlayerIds.value);
    nextActive.delete(playerId);
    activePlayerIds.value = nextActive;

    delete lateJoinerOffsets.value[playerId];

    queuedMatches.value = queuedMatches.value.filter((match) => {
        const ids = [match.player1Id, match.player2Id, match.player3Id, match.player4Id].filter((id): id is number => id != null);
        return !ids.includes(playerId);
    });

    if (currentMatch.value) {
        const currentIds = [currentMatch.value.p1?.id, currentMatch.value.p2?.id, currentMatch.value.p3?.id, currentMatch.value.p4?.id].filter(
            (id): id is number => id != null,
        );

        if (currentIds.includes(playerId)) {
            currentMatch.value = null;
            matchStarted.value = false;
        }
    }

    playerGroups.value = playerGroups.value.map((group) => ({
        ...group,
        playerIds: group.playerIds.map((id) => (id === playerId ? null : id)),
    }));

    Object.entries(playerPairs.value).forEach(([key, value]) => {
        if (Number(key) === playerId || value === playerId) {
            delete playerPairs.value[Number(key)];
        }
    });

    saveQueueState();
};

const registerLateJoinerOffset = (playerId: number) => {
    if (lateJoinerOffsets.value[playerId] !== undefined) return;
    const sessionActive = props.matches.length > 0 || localMatches.value.length > 0 || currentMatch.value !== null;
    if (!sessionActive) return;

    // Find the minimum matches played by already active players
    const activePlayers = sessionPlayers.value.filter(p => activePlayerIds.value.has(p.id) && p.id !== playerId);
    if (activePlayers.length > 0) {
        const matchesPlayed = activePlayers.map(p => {
            const offset = lateJoinerOffsets.value[p.id] || 0;
            return (p.total_matches || 0) + offset;
        });
        const minMatches = Math.min(...matchesPlayed);
        lateJoinerOffsets.value[playerId] = minMatches;
    }
};

watch(defaultActiveBookingPlayerIds, (nextIds, previousIds) => {
    if (!isPlayerBookingOwner.value) return;

    const newlyAcceptedIds = Array.from(nextIds).filter((id) => !(previousIds?.has(id) ?? false));
    if (newlyAcceptedIds.length === 0) return;

    const nextActive = new Set(activePlayerIds.value);
    let changed = false;

    for (const id of newlyAcceptedIds) {
        if (nextActive.has(id)) continue;
        nextActive.add(id);
        registerLateJoinerOffset(id);
        changed = true;
    }

    if (!changed) return;

    activePlayerIds.value = nextActive;
    tryAutoGenerateFirstMatch();
    saveQueueState();
}, { flush: 'post' });

const getFairSelectedPlayers = (availablePlayers: any[], numRequired: number, extraQueueMatches: any[] = []): any[] => {
    const loads = new Map<number, number>();
    for (const p of availablePlayers) {
        const offset = lateJoinerOffsets.value[p.id] || 0;
        loads.set(p.id, (p.total_matches || 0) + offset);
    }

    if (currentMatch.value) {
        const slots = ['p1', 'p2', 'p3', 'p4'] as const;
        for (const slot of slots) {
            const playerObj = currentMatch.value[slot];
            if (playerObj && loads.has(playerObj.id)) {
                loads.set(playerObj.id, (loads.get(playerObj.id) || 0) + 1);
            }
        }
    }

    for (const qm of queuedMatches.value) {
        const ids = [qm.player1Id, qm.player2Id, qm.player3Id, qm.player4Id].filter((id): id is number => id != null);
        for (const id of ids) {
            if (loads.has(id)) {
                loads.set(id, (loads.get(id) || 0) + 1);
            }
        }
    }

    for (const eqm of extraQueueMatches) {
        const ids = [eqm.player1Id, eqm.player2Id, eqm.player3Id, eqm.player4Id].filter((id): id is number => id != null);
        for (const id of ids) {
            if (loads.has(id)) {
                loads.set(id, (loads.get(id) || 0) + 1);
            }
        }
    }

    const sorted = [...availablePlayers].sort((a, b) => {
        const loadA = loads.get(a.id) || 0;
        const loadB = loads.get(b.id) || 0;
        const valA = loadA + Math.random() * 0.1;
        const valB = loadB + Math.random() * 0.1;
        return valA - valB;
    });

    if (numRequired !== 4) {
        return sorted.slice(0, numRequired);
    }

    // Doubles selection (4 players required)
    const selected: any[] = [];
    const selectedIds = new Set<number>();

    const getPlayerGroup = (playerId: number) => {
        return playerGroups.value.find(g => g.type !== 'none' && g.playerIds.includes(playerId));
    };

    for (const p of sorted) {
        if (selectedIds.has(p.id)) continue;

        const group = getPlayerGroup(p.id);

        if (group && group.type === 'quad') {
            const memberIds = group.playerIds.filter((id): id is number => id !== null);
            const activeMembers = sorted.filter(sp => memberIds.includes(sp.id));
            if (activeMembers.length === 4 && selected.length === 0) {
                selected.push(...activeMembers);
                activeMembers.forEach(sp => selectedIds.add(sp.id));
                break;
            }
        } else if (group && group.type === 'pair') {
            const partnerId = group.playerIds.find(id => id !== null && id !== p.id);
            const partner = partnerId ? sorted.find(sp => sp.id === partnerId) : null;
            if (partner && !selectedIds.has(partner.id)) {
                if (selected.length + 2 <= 4) {
                    selected.push(p, partner);
                    selectedIds.add(p.id);
                    selectedIds.add(partner.id);
                }
            }
        } else {
            const partnerId = playerPairs.value[p.id];
            const partner = partnerId ? sorted.find(sp => sp.id === partnerId) : null;
            if (partner && !selectedIds.has(partner.id)) {
                if (selected.length + 2 <= 4) {
                    selected.push(p, partner);
                    selectedIds.add(p.id);
                    selectedIds.add(partner.id);
                }
            } else if (!partnerId) {
                if (selected.length + 1 <= 4) {
                    selected.push(p);
                    selectedIds.add(p.id);
                }
            }
        }

        if (selected.length === 4) break;
    }

    if (selected.length < 4) {
        for (const p of sorted) {
            if (!selectedIds.has(p.id)) {
                selected.push(p);
                selectedIds.add(p.id);
            }
            if (selected.length === 4) break;
        }
    }

    // Check if the selected 4 are exactly a quad group
    const firstGroup = getPlayerGroup(selected[0].id);
    if (firstGroup && firstGroup.type === 'quad' && firstGroup.playerIds.every(id => id !== null && selected.some(sp => sp.id === id))) {
        const arranged = [];
        const memberIds = firstGroup.playerIds;
        arranged[0] = selected.find(sp => sp.id === memberIds[0]);
        arranged[2] = selected.find(sp => sp.id === memberIds[1]);
        arranged[1] = selected.find(sp => sp.id === memberIds[2]);
        arranged[3] = selected.find(sp => sp.id === memberIds[3]);
        return arranged;
    }

    const getPartnerId = (pid: number) => {
        const group = getPlayerGroup(pid);
        if (group && group.type === 'pair') {
            return group.playerIds.find(id => id !== null && id !== pid) ?? null;
        }
        return playerPairs.value[pid] ?? null;
    };

    const pairIndices: [number, number][] = [];
    for (let i = 0; i < 4; i++) {
        for (let j = i + 1; j < 4; j++) {
            if (getPartnerId(selected[i].id) === selected[j].id) {
                pairIndices.push([i, j]);
            }
        }
    }

    const arranged = [...selected];
    if (pairIndices.length === 1) {
        const [i, j] = pairIndices[0];
        const pA = selected[i];
        const pB = selected[j];
        const others = selected.filter((_, idx) => idx !== i && idx !== j);
        arranged[0] = pA;
        arranged[2] = pB;
        arranged[1] = others[0];
        arranged[3] = others[1];
    } else if (pairIndices.length === 2) {
        const [i1, j1] = pairIndices[0];
        const p1A = selected[i1];
        const p1B = selected[j1];
        
        const p2A = selected.find((_, idx) => idx !== i1 && idx !== j1);
        const p2B = selected.find((_, idx) => idx !== i1 && idx !== j1 && _ !== p2A);
        
        arranged[0] = p1A;
        arranged[2] = p1B;
        arranged[1] = p2A;
        arranged[3] = p2B;
    }

    return arranged;
};

const tryAutoGenerateFirstMatch = () => {
    if (currentMatch.value || queuedMatches.value.length > 0) return;
    const availablePlayers = sessionPlayers.value.filter((p) => activePlayerIds.value.has(p.id));
    if (availablePlayers.length < 2) return;
    const isDoubles = availablePlayers.length >= 4;
    const numRequired = isDoubles ? 4 : 2;
    const selected = getFairSelectedPlayers(availablePlayers, numRequired);
    queuedMatches.value = [
        {
            player1Id: selected[0].id,
            player2Id: selected[1].id,
            player3Id: isDoubles ? selected[2].id : null,
            player4Id: isDoubles ? selected[3].id : null,
        },
    ];
    saveQueueState();
};

const togglePlayerActive = (id: number) => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    if (isQueueLocked.value) {
        showSystemAlert('Queue is locked. Booking has expired.', 'error');
        return;
    }
    const wasAdded = !activePlayerIds.value.has(id);
    const newSet = new Set(activePlayerIds.value);
    if (newSet.has(id)) {
        newSet.delete(id);
    } else {
        newSet.add(id);
        registerLateJoinerOffset(id);
    }
    activePlayerIds.value = newSet;

    // Auto-generate first match when 2+ players active and queue empty
    if (wasAdded) {
        tryAutoGenerateFirstMatch();
    }

    // Persist active toggle state even before queue generation.
    saveQueueState();
};

const removeFromSession = (playerId: number) => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    if (playerId < 0) {
        removeTemporaryPlayerState(playerId);
        return;
    }

    const isLocalRegisteredPlayer = localRegisteredSessionPlayers.value.some((player) => player.id === playerId);
    if (isLocalRegisteredPlayer) {
        localRegisteredSessionPlayers.value = localRegisteredSessionPlayers.value.filter((player) => player.id !== playerId);
        const newSet = new Set(activePlayerIds.value);
        newSet.delete(playerId);
        activePlayerIds.value = newSet;
        delete lateJoinerOffsets.value[playerId];
        queuedMatches.value = queuedMatches.value.filter((match) => {
            const ids = [match.player1Id, match.player2Id, match.player3Id, match.player4Id].filter((id): id is number => id != null);
            return !ids.includes(playerId);
        });
        if (currentMatch.value) {
            const currentIds = [
                currentMatch.value.p1?.id,
                currentMatch.value.p2?.id,
                currentMatch.value.p3?.id,
                currentMatch.value.p4?.id,
            ].filter((id): id is number => id != null);
            if (currentIds.includes(playerId)) {
                currentMatch.value = null;
                matchStarted.value = false;
            }
        }
        saveQueueState();
        showSystemAlert('Player removed from your booking roster.', 'info');
        return;
    }

    const isLeadPlayer = isPlayerScoringMode.value && !isPlayerScoringViewOnly.value;
    if (isLeadPlayer) {
        if (sessionStarted.value) {
            showSystemAlert('Cannot remove players once the session has started.', 'error');
            return;
        }
        router.post(route('players.remove-from-session', playerId), {}, {
            preserveScroll: true,
            onSuccess: () => {
                const newSet = new Set(activePlayerIds.value);
                newSet.delete(playerId);
                activePlayerIds.value = newSet;
                saveQueueState();
            }
        });
        return;
    }

    if (!canManageVenueRoster.value) {
        showSystemAlert('You can remove only players added inside your booking session.', 'error');
        return;
    }

    router.post(route('players.remove-from-session', playerId), {}, {
        preserveScroll: true,
    });
};

const playerForm = useForm({ name: '', email: '' });
const invitePlayersForm = useForm({ player_ids: [] as number[] });
const showAddPlayer = ref(false);
const showAddPlayerModal = ref(false);
const addPlayerSearch = ref('');
const selectedModalPlayerIds = ref<Set<number>>(new Set());
const applyingSelectedPlayers = ref(false);

const filteredPlayers = computed(() => {
    if (!playerForm.name.trim()) return [];
    const query = playerForm.name.toLowerCase();
    return props.allPlayers.filter((p) => p.name.toLowerCase().includes(query)).slice(0, 5);
});

const modalFilteredPlayers = computed(() => {
    const query = addPlayerSearch.value.trim().toLowerCase();
    const temps = tempSessionPlayers.value.filter((p) => {
        if (!query) return true;
        return p.name.toLowerCase().includes(query);
    });
    const list = query ? props.allPlayers.filter((p) => p.name.toLowerCase().includes(query)) : props.allPlayers;
    return [...temps, ...list].slice(0, 30);
});

const rosterPlayerIds = computed(() => new Set(sessionPlayers.value.map((p) => p.id)));
const hasExactRegisteredMatch = computed(() =>
    props.allPlayers.some((p) => p.name.trim().toLowerCase() === addPlayerSearch.value.trim().toLowerCase()),
);

const selectPlayerFromDropdown = (name: string) => {
    playerForm.name = name;
    quickAddPlayer();
};

const quickAddPlayer = () => {
    if (!canAddRosterPlayers.value) {
        showSystemAlert('You cannot add players right now.', 'error');
        return;
    }
    if (isQueueLocked.value) {
        showSystemAlert('Queue is locked. Booking has expired.', 'error');
        return;
    }
    if (selectedCourt.value === null) {
        showSystemAlert('You must be assigned/select a court first.', 'error');
        return;
    }
    if (!playerForm.name.trim()) return;

    const enteredName = playerForm.name.trim().toLowerCase();
    const existingPlayer = sessionPlayers.value.find((p) => p.name.trim().toLowerCase() === enteredName);

    if (existingPlayer) {
        const newSet = new Set(activePlayerIds.value);
        newSet.add(existingPlayer.id);
        activePlayerIds.value = newSet;
        saveQueueState();
        showSystemAlert(`${existingPlayer.name} is already added to this session.`, 'info');
        playerForm.reset();
        showAddPlayer.value = false;
        return;
    }

    const registeredPlayer = props.allPlayers.find((p) => p.name.trim().toLowerCase() === enteredName);
    if (!registeredPlayer) {
        const temporaryPlayer = createTemporaryPlayer(playerForm.name);
        if (!temporaryPlayer) return;

        const newSet = new Set(activePlayerIds.value);
        newSet.add(temporaryPlayer.id);
        activePlayerIds.value = newSet;
        registerLateJoinerOffset(temporaryPlayer.id);
        playerForm.reset();
        showAddPlayer.value = false;
        tryAutoGenerateFirstMatch();
        saveQueueState();
        showSystemAlert(`${temporaryPlayer.name} added as a temporary player. Stats will not be saved.`, 'info');
        return;
    }

    if (isPlayerBookingOwner.value) {
        invitePlayersForm.player_ids = [registeredPlayer.id];
        invitePlayersForm.post(route('scoring.invitations.store'), {
            preserveScroll: true,
            preserveState: true,
            only: ['players', 'allPlayers', 'bookingRoster', 'bookingInvitations'],
            onSuccess: () => {
                playerForm.reset();
                showAddPlayer.value = false;
                showSystemAlert(`${registeredPlayer.name} invited to your current schedule.`, 'info');
                invitePlayersForm.reset();
            },
            onError: () => {
                showSystemAlert('Failed to invite player to your booking.', 'error');
            },
        });
        return;
    }

    router.post(
        route('players.bulk-session'),
        { names: [registeredPlayer.name] },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['players', 'allPlayers'],
            onSuccess: () => {
                const newSet = new Set(activePlayerIds.value);
                newSet.add(registeredPlayer.id);
                activePlayerIds.value = newSet;
                registerLateJoinerOffset(registeredPlayer.id);
                playerForm.reset();
                showAddPlayer.value = false;
                saveQueueState();
            },
            onError: () => {
                showSystemAlert('Failed to add player to scoring.', 'error');
            },
        },
    );
};

const openAddPlayerModal = () => {
    if (!canAddRosterPlayers.value) {
        showSystemAlert('You cannot add players right now.', 'error');
        return;
    }
    if (isQueueLocked.value) {
        showSystemAlert('Queue is locked. Booking has expired.', 'error');
        return;
    }
    if (selectedCourt.value === null) {
        showSystemAlert('You must be assigned/select a court first.', 'error');
        return;
    }
    showAddPlayerModal.value = true;
    addPlayerSearch.value = '';
    selectedModalPlayerIds.value = new Set();
};

const closeAddPlayerModal = () => {
    showAddPlayerModal.value = false;
    addPlayerSearch.value = '';
    selectedModalPlayerIds.value = new Set();
};

// --- Custom Groups Setup Modal ---
const showGroupSetupModal = ref(false);
const playerGroups = ref<Array<{ type: 'none' | 'pair' | 'quad'; playerIds: (number | null)[] }>>([
    { type: 'none', playerIds: [] },
]);

const handleGroupTypeChange = (idx: number) => {
    const type = playerGroups.value[idx].type;
    if (type === 'none') {
        playerGroups.value[idx].playerIds = [];
    } else if (type === 'pair') {
        playerGroups.value[idx].playerIds = [null, null];
    } else if (type === 'quad') {
        playerGroups.value[idx].playerIds = [null, null, null, null];
    }
};

const addGroup = () => {
    if (playerGroups.value.length < 4) {
        playerGroups.value.push({ type: 'none', playerIds: [] });
    }
};

const removeGroup = (idx: number) => {
    playerGroups.value.splice(idx, 1);
    if (playerGroups.value.length === 0) {
        playerGroups.value.push({ type: 'none', playerIds: [] });
    }
};

const openGroupSetupModal = () => {
    if (isQueueLocked.value) {
        showSystemAlert('Queue is locked. Booking has expired.', 'error');
        return;
    }
    if (selectedCourt.value === null) {
        showSystemAlert('You must be assigned/select a court first.', 'error');
        return;
    }

    const raw = sharedScoringStateSyncEnabled.value ? JSON.stringify(props.scoringState ?? null) : localStorage.getItem(scoringQueueStorageKey.value);
    if (raw) {
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed.playerGroups)) {
                playerGroups.value = parsed.playerGroups
                    .filter((g: any) => g.type !== 'none')
                    .map((g: any) => ({
                        type: g.type || 'none',
                        playerIds: Array.isArray(g.playerIds) ? [...g.playerIds] : []
                    }));
                
                if (playerGroups.value.length === 0) {
                    playerGroups.value.push({ type: 'none', playerIds: [] });
                }
                showGroupSetupModal.value = true;
                return;
            }
        } catch (e) {
            console.error(e);
        }
    }

    const visited = new Set<number>();
    const currentPairs: Array<{ p1: number; p2: number }> = [];
    const activeRosterIds = new Set(sessionPlayers.value.filter(p => activePlayerIds.value.has(p.id)).map(p => p.id));

    for (const [idStr, partnerId] of Object.entries(playerPairs.value)) {
        const id = Number(idStr);
        if (visited.has(id) || visited.has(partnerId)) continue;
        if (activeRosterIds.has(id) && activeRosterIds.has(partnerId)) {
            currentPairs.push({ p1: id, p2: partnerId });
            visited.add(id);
            visited.add(partnerId);
        }
    }

    playerGroups.value = [];
    for (let i = 0; i < Math.min(currentPairs.length, 4); i++) {
        playerGroups.value.push({
            type: 'pair',
            playerIds: [currentPairs[i].p1, currentPairs[i].p2]
        });
    }

    if (playerGroups.value.length === 0) {
        playerGroups.value.push({ type: 'none', playerIds: [] });
    }

    showGroupSetupModal.value = true;
};

const closeGroupSetupModal = () => {
    showGroupSetupModal.value = false;
};

const clearGroupSetup = () => {
    playerGroups.value = [
        { type: 'none', playerIds: [] }
    ];
};

const saveGroupSetup = () => {
    let totalPlayersCount = 0;
    playerGroups.value.forEach(g => {
        if (g.type !== 'none') {
            totalPlayersCount += g.playerIds.filter(id => id !== null).length;
        }
    });

    if (totalPlayersCount > 8) {
        showSystemAlert('Maximum 8 players can be grouped/paired at a time.', 'error');
        return;
    }

    // Filter out groups with type 'none' before saving
    playerGroups.value = playerGroups.value.filter(g => g.type !== 'none');

    // Automatically clear all legacy simple paired players
    playerPairs.value = {};

    saveQueueState();
    closeGroupSetupModal();
    showSystemAlert('Groups and pairings updated successfully.', 'info');
};

const getAvailableGroupPlayers = (groupIndex: number, playerSlotIndex: number) => {
    const selectedIds = new Set<number>();
    playerGroups.value.forEach((g, gIdx) => {
        g.playerIds.forEach((pid, pIdx) => {
            if (pid !== null) {
                if (gIdx !== groupIndex || pIdx !== playerSlotIndex) {
                    selectedIds.add(pid);
                }
            }
        });
    });

    return sessionPlayers.value.filter(p => activePlayerIds.value.has(p.id) && !selectedIds.has(p.id));
};

const getPlayerGroupInfo = (playerId: number) => {
    const group = playerGroups.value.find(g => g.type !== 'none' && g.playerIds.includes(playerId));
    if (!group) {
        const partnerId = playerPairs.value[playerId];
        if (partnerId) {
            const partner = sessionPlayers.value.find(p => p.id === partnerId);
            return {
                type: 'pair',
                label: '2-Pair',
                membersText: partner ? partner.name : 'Partner',
                otherIds: [partnerId]
            };
        }
        return null;
    }

    const otherIds = group.playerIds.filter((id): id is number => id !== null && id !== playerId);
    const otherPlayers = sessionPlayers.value.filter(p => otherIds.includes(p.id));
    const names = otherPlayers.map(p => p.name).join(', ');

    if (group.type === 'pair') {
        return {
            type: 'pair',
            label: '2-Pair',
            membersText: names || 'Partner',
            otherIds
        };
    } else {
        return {
            type: 'quad',
            label: '4-Pair',
            membersText: names || 'Group',
            otherIds
        };
    }
};

const getPlayerGroupCode = (playerId: number) => {
    const groupIdx = playerGroups.value.findIndex(g => g.type !== 'none' && g.playerIds.includes(playerId));
    if (groupIdx !== -1) {
        return `G${groupIdx + 1}`;
    }
    if (playerPairs.value[playerId]) {
        const partnerId = playerPairs.value[playerId];
        const partnerGroupIdx = playerGroups.value.findIndex(g => g.type !== 'none' && g.playerIds.includes(partnerId));
        if (partnerGroupIdx !== -1) {
            return `G${partnerGroupIdx + 1}`;
        }
        return 'Paired';
    }
    return null;
};

const removePlayerFromGroup = (playerId: number) => {
    const groupIndex = playerGroups.value.findIndex(g => g.type !== 'none' && g.playerIds.includes(playerId));
    if (groupIndex !== -1) {
        const group = playerGroups.value[groupIndex];
        group.playerIds = group.playerIds.map(id => id === playerId ? null : id);
        const activeCount = group.playerIds.filter(id => id !== null).length;
        if (activeCount < 2) {
            playerGroups.value[groupIndex] = { type: 'none', playerIds: [] };
        }
    }

    const partnerId = playerPairs.value[playerId];
    delete playerPairs.value[playerId];
    if (partnerId !== undefined) {
        delete playerPairs.value[partnerId];
    }

    saveQueueState();
    showSystemAlert('Player removed from pair/group.', 'info');
};

const canRemoveSessionPlayer = (player: SessionPlayer) => {
    const isLeadPlayer = isPlayerScoringMode.value && !isPlayerScoringViewOnly.value;
    if (isLeadPlayer) {
        const currentUserId = (page.props as any).auth?.user?.id;
        const isSelf = player.user_id === currentUserId || player.user?.id === currentUserId;
        return !isSelf;
    }
    return player.id < 0 || localRegisteredSessionPlayers.value.some((entry) => entry.id === player.id) || canManageVenueRoster.value;
};

const toggleModalPlayerSelection = (id: number) => {
    if (rosterPlayerIds.value.has(id)) return;

    if (selectedModalPlayerIds.value.has(id)) {
        selectedModalPlayerIds.value.delete(id);
        return;
    }
    selectedModalPlayerIds.value.add(id);
};

const addTemporaryPlayerFromModal = () => {
    const temporaryPlayer = createTemporaryPlayer(addPlayerSearch.value);
    if (!temporaryPlayer) return;

    const newSet = new Set(activePlayerIds.value);
    newSet.add(temporaryPlayer.id);
    activePlayerIds.value = newSet;
    registerLateJoinerOffset(temporaryPlayer.id);

    tryAutoGenerateFirstMatch();
    saveQueueState();
    addPlayerSearch.value = '';
    showSystemAlert(`${temporaryPlayer.name} added as a temporary player. Stats will not be saved.`, 'info');
};

const applySelectedRegisteredPlayers = () => {
    if (selectedModalPlayerIds.value.size === 0) return;

    const selectedPlayers = props.allPlayers.filter((p) => selectedModalPlayerIds.value.has(p.id));
    const allNames = selectedPlayers.map((p) => p.name);

    if (allNames.length === 0) return;

    if (isPlayerBookingOwner.value) {
        applyingSelectedPlayers.value = true;
        invitePlayersForm.player_ids = selectedPlayers.map((player) => player.id);
        invitePlayersForm.post(route('scoring.invitations.store'), {
            preserveScroll: true,
            preserveState: true,
            only: ['players', 'allPlayers', 'bookingRoster', 'bookingInvitations'],
            onSuccess: () => {
                applyingSelectedPlayers.value = false;
                selectedModalPlayerIds.value.clear();
                addPlayerSearch.value = '';
                showSystemAlert(`${allNames.length} player invitation(s) sent.`, 'info');
                invitePlayersForm.reset();
            },
            onError: () => {
                applyingSelectedPlayers.value = false;
                showSystemAlert('Failed to send player invitations.', 'error');
            },
        });
        return;
    }

    applyingSelectedPlayers.value = true;

    // Single bulk request: sets in_session=true for all selected players
    router.post(
        route('players.bulk-session'),
        { names: allNames },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['players', 'allPlayers'],
            onSuccess: () => {
                const lowerNames = allNames.map((n) => n.trim().toLowerCase());
                const newSet = new Set(activePlayerIds.value);
                for (const player of selectedPlayers) {
                    newSet.add(player.id);
                    registerLateJoinerOffset(player.id);
                }
                activePlayerIds.value = newSet;

                tryAutoGenerateFirstMatch();
                saveQueueState();
                selectedModalPlayerIds.value.clear();
                addPlayerSearch.value = '';
                applyingSelectedPlayers.value = false;
                showSystemAlert(`${allNames.length} players added to session.`, 'info');
            },
            onError: () => {
                applyingSelectedPlayers.value = false;
                showSystemAlert('Failed to add players.', 'error');
            },
        },
    );
};

// --- Edit/Delete Player Logic ---
const editingPlayer = ref<any>(null);
const editPlayerForm = useForm({ name: '', full_name: '', email: '' });

const submitEditPlayer = () => {
    if (!canManageVenueRoster.value) {
        showSystemAlert('Player accounts cannot edit venue player records.', 'error');
        return;
    }
    if (!editingPlayer.value) return;
    editPlayerForm.put(route('players.update', editingPlayer.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingPlayer.value = null;
            editPlayerForm.reset();
        },
    });
};

// --- Session Workflow ---
const resetSessionForm = useForm({});
const saveSessionForm = useForm({});

const resetSession = () => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    if (!canManageVenueRoster.value) {
        showSystemAlert('Only your booking scores can be reset during player scoring.', 'info');
    }
    openSystemConfirm('Clear current queue and permanently delete all matches recorded today?', () => {
        resetSessionForm.post(route('scoring.reset'), {
            preserveScroll: true,
            onSuccess: () => {
                activePlayerIds.value = new Set();
                localRegisteredSessionPlayers.value = [];
                tempSessionPlayers.value = [];
                queuedMatches.value = [];
                currentMatch.value = null;
                matchStarted.value = false;
                sessionStarted.value = false;
                lateJoinerOffsets.value = {};
                playerPairs.value = {};
                playerGroups.value = [
                    { type: 'none', playerIds: [] }
                ];
if (sharedScoringStateSyncEnabled.value) {
                    void persistSharedScoringState(null);
                } else {
                    localStorage.removeItem(scoringQueueStorageKey.value);
                }
            },
        });
    });
};

const canSaveSession = computed(() => sessionPlayers.value.length > 0 || localMatches.value.length > 0);

const saveSession = () => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    if (!canSaveSession.value) return;
    openSystemConfirm('Save this session and finalize all recorded match results?', () => {
        // Clear local state immediately on confirm — no waiting for server
        localMatches.value = [];
        activePlayerIds.value = new Set();
        localRegisteredSessionPlayers.value = [];
        tempSessionPlayers.value = [];
        queuedMatches.value = [];
        currentMatch.value = null;
        matchStarted.value = false;
        sessionStarted.value = false;
        lateJoinerOffsets.value = {};
        playerPairs.value = {};
        playerGroups.value = [
            { type: 'none', playerIds: [] }
        ];
        if (sharedScoringStateSyncEnabled.value) {
            void persistSharedScoringState(null);
        } else {
            localStorage.removeItem(scoringQueueStorageKey.value);
        }

        saveSessionForm.post(route('scoring.save'), {
            preserveScroll: true,
            preserveState: false,
        });
    });
};

// --- Match Panel State (Left Panel) ---
const MAX_QUEUE = 10;
const AUTO_FILL_COUNT = 2;

const currentMatch = ref<{
    p1: any;
    p2: any;
    p3: any | null;
    p4: any | null;
} | null>(null);

const matchStarted = ref(false);
const sessionStarted = ref(false);

const queuedMatches = ref<QueuedMatch[]>([]);

const buildScoringStatePayload = (): SharedScoringState => ({
    activePlayerIds: Array.from(activePlayerIds.value),
    localRegisteredPlayerIds: localRegisteredSessionPlayers.value.map((player) => player.id),
    tempSessionPlayers: tempSessionPlayers.value,
    queue: queuedMatches.value,
    currentMatch: currentMatch.value
        ? {
              player1Id: currentMatch.value.p1.id,
              player2Id: currentMatch.value.p2.id,
              player3Id: currentMatch.value.p3?.id ?? null,
              player4Id: currentMatch.value.p4?.id ?? null,
          }
        : null,
    lateJoinerOffsets: lateJoinerOffsets.value,
    playerPairs: playerPairs.value,
    playerGroups: playerGroups.value.map((group) => ({
        type: group.type,
        playerIds: group.playerIds,
    })),
});

const persistSharedScoringState = async (state: SharedScoringState | null) => {
    if (!sharedScoringStateSyncEnabled.value || !canWriteSharedScoringState.value) return;

    await fetch(route('scoring.state.store'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ state }),
    });
};

const saveQueueState = () => {
    const payload = buildScoringStatePayload();

    localStorage.setItem(scoringQueueStorageKey.value, JSON.stringify(payload));

    if (sharedScoringStateSyncEnabled.value) {
        if (!canWriteSharedScoringState.value) return;
        if (scoringStateSaveTimer) {
            clearTimeout(scoringStateSaveTimer);
        }
        scoringStateSaveTimer = setTimeout(() => {
            void persistSharedScoringState(payload);
        }, 200);
        return;
    }

    localStorage.setItem(scoringQueueStorageKey.value, JSON.stringify(payload));
};

const buildCurrentMatchFromPair = (player1Id: number, player2Id: number, randomizePartners = true) => {
    const playerMap = sessionPlayerMap.value;
    const p1 = playerMap.get(player1Id);
    const p2 = playerMap.get(player2Id);

    if (!p1 || !p2) return null;

    const partnerPool = sessionPlayers.value.filter((p) => activePlayerIds.value.has(p.id) && p.id !== player1Id && p.id !== player2Id);
    const selectedPartners = randomizePartners ? [...partnerPool].sort(() => Math.random() - 0.5) : [...partnerPool].sort((a, b) => a.id - b.id);

    return {
        p1,
        p2,
        p3: selectedPartners[0] ?? null,
        p4: selectedPartners[1] ?? null,
    };
};

const nextMatchPreview = computed(() => {
    if (queuedMatches.value.length === 0) return null;
    const nextPair = queuedMatches.value[0];
    if (nextPair.player3Id != null || nextPair.player4Id != null) {
        const playerMap = sessionPlayerMap.value;
        const p1 = playerMap.get(nextPair.player1Id);
        const p2 = playerMap.get(nextPair.player2Id);
        if (!p1 || !p2) return null;
        return {
            p1,
            p2,
            p3: nextPair.player3Id ? (playerMap.get(nextPair.player3Id) ?? null) : null,
            p4: nextPair.player4Id ? (playerMap.get(nextPair.player4Id) ?? null) : null,
        };
    }
    return buildCurrentMatchFromPair(nextPair.player1Id, nextPair.player2Id, false);
});

const advanceToNextMatch = () => {
    while (queuedMatches.value.length > 0) {
        const next = queuedMatches.value.shift()!;
        let nextMatch;
        if (next.player3Id != null || next.player4Id != null) {
            const playerMap = sessionPlayerMap.value;
            const p1 = playerMap.get(next.player1Id);
            const p2 = playerMap.get(next.player2Id);
            if (!p1 || !p2) continue;
            nextMatch = {
                p1,
                p2,
                p3: next.player3Id ? (playerMap.get(next.player3Id) ?? null) : null,
                p4: next.player4Id ? (playerMap.get(next.player4Id) ?? null) : null,
            };
        } else {
            nextMatch = buildCurrentMatchFromPair(next.player1Id, next.player2Id);
        }
        if (!nextMatch) continue;

        currentMatch.value = nextMatch;
        matchStarted.value = true;
        sessionStarted.value = true;
        saveQueueState();
        autoFillQueue();
        return;
    }

    currentMatch.value = null;
    matchStarted.value = false;
    saveQueueState();
};

const handleStartMatchClick = () => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    if (isQueueLocked.value) {
        showSystemAlert('Queue is locked. Booking has expired.', 'error');
        return;
    }
    if (matchStarted.value || currentMatch.value) {
        showSystemAlert('A match is already active.', 'info');
        return;
    }
    if (queuedMatches.value.length === 0) {
        tryAutoGenerateFirstMatch();
    }
    advanceToNextMatch();
};

const autoFillQueue = () => {
    const availablePlayers = sessionPlayers.value.filter((p) => activePlayerIds.value.has(p.id));
    if (availablePlayers.length < 2) return;
    const slotsToFill = AUTO_FILL_COUNT - queuedMatches.value.length;
    if (slotsToFill <= 0) return;

    const tempAddedMatches: QueuedMatch[] = [];
    for (let i = 0; i < slotsToFill; i++) {
        const isDoubles = availablePlayers.length >= 4;
        const numRequired = isDoubles ? 4 : 2;
        const selected = getFairSelectedPlayers(availablePlayers, numRequired, tempAddedMatches);
        tempAddedMatches.push({
            player1Id: selected[0].id,
            player2Id: selected[1].id,
            player3Id: isDoubles ? selected[2].id : null,
            player4Id: isDoubles ? selected[3].id : null,
        });
    }

    queuedMatches.value = [...queuedMatches.value, ...tempAddedMatches];
    saveQueueState();
};

const resetQueue = () => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    openSystemConfirm('Are you sure you want to reset the current match and waiting queue? This will cancel the active match.', () => {
        currentMatch.value = null;
        queuedMatches.value = [];
        matchStarted.value = false;
        saveQueueState();
        showSystemAlert('Queuing reset successfully. Click "Start Match" to begin.', 'info');
    });
};

const hydrateQueueState = (sharedState?: SharedScoringState | null) => {
    const raw = sharedState !== undefined
        ? JSON.stringify(sharedState)
        : sharedScoringStateSyncEnabled.value
          ? JSON.stringify(props.scoringState ?? null)
          : localStorage.getItem(scoringQueueStorageKey.value);

    tempSessionPlayers.value = [];
    localRegisteredSessionPlayers.value = [];
    activePlayerIds.value = new Set();
    queuedMatches.value = [];
    currentMatch.value = null;
    lateJoinerOffsets.value = {};
    playerPairs.value = {};
    playerGroups.value = [{ type: 'none', playerIds: [] }];
    nextTemporaryPlayerId.value = -1;

    if (!raw || raw === 'null') {
        if (isPlayerScoringMode.value && defaultActiveBookingPlayerIds.value.size > 0) {
            activePlayerIds.value = new Set(defaultActiveBookingPlayerIds.value);
            sessionStarted.value = true;
        } else {
            sessionStarted.value = false;
        }
        matchStarted.value = false;
        return;
    }

    try {
        const parsed = JSON.parse(raw) as SharedScoringState;

        if (Array.isArray(parsed.tempSessionPlayers)) {
            tempSessionPlayers.value = parsed.tempSessionPlayers.map((player) => ({
                ...player,
                isTemporary: true,
                is_member: false,
                total_matches: player.total_matches ?? 0,
                wins: player.wins ?? 0,
                losses: player.losses ?? 0,
                win_rate: player.win_rate ?? 0,
            }));
            const minTempId = tempSessionPlayers.value.reduce((min, player) => Math.min(min, player.id), -1);
            nextTemporaryPlayerId.value = minTempId - 1;
        }

        if (Array.isArray(parsed.localRegisteredPlayerIds)) {
            const existingIds = isPlayerScoringMode.value ? new Set<number>() : new Set(props.players.map((player: SessionPlayer) => player.id));
            localRegisteredSessionPlayers.value = props.allPlayers
                .filter((player: any) => parsed.localRegisteredPlayerIds?.includes(Number(player.id)) && !existingIds.has(Number(player.id)))
                .map(mapRegisteredPlayerToSessionPlayer);
        }

        const allHydratedPlayers = [
            ...(isPlayerScoringMode.value ? [] : props.players),
            ...derivedBookingRosterPlayers.value,
            ...localRegisteredSessionPlayers.value,
            ...derivedTemporaryMatchPlayers.value,
            ...tempSessionPlayers.value,
        ];
        const playerMap = new Map(allHydratedPlayers.map((player) => [player.id, player]));

        if (Array.isArray(parsed.activePlayerIds)) {
            activePlayerIds.value = new Set(parsed.activePlayerIds.filter((id) => playerMap.has(id)));
        }

        if (Array.isArray(parsed.queue)) {
            queuedMatches.value = parsed.queue.filter(
                (m) =>
                    playerMap.has(m.player1Id) &&
                    playerMap.has(m.player2Id) &&
                    (m.player3Id == null || playerMap.has(m.player3Id)) &&
                    (m.player4Id == null || playerMap.has(m.player4Id)),
            );
        }

        if (parsed.lateJoinerOffsets && typeof parsed.lateJoinerOffsets === 'object') {
            lateJoinerOffsets.value = parsed.lateJoinerOffsets;
        }

        if (parsed.playerPairs && typeof parsed.playerPairs === 'object') {
            playerPairs.value = parsed.playerPairs;
        }

        if (Array.isArray(parsed.playerGroups)) {
            playerGroups.value = parsed.playerGroups.map((group: any) => ({
                type: group.type || 'none',
                playerIds: Array.isArray(group.playerIds) ? [...group.playerIds] : [],
            }));
        } else {
            const visited = new Set<number>();
            const currentPairs: Array<{ p1: number; p2: number }> = [];
            const activeRosterIds = new Set(sessionPlayers.value.filter((p) => activePlayerIds.value.has(p.id)).map((p) => p.id));

            for (const [idStr, partnerId] of Object.entries(playerPairs.value)) {
                const id = Number(idStr);
                if (visited.has(id) || visited.has(partnerId)) continue;
                if (activeRosterIds.has(id) && activeRosterIds.has(partnerId)) {
                    currentPairs.push({ p1: id, p2: partnerId });
                    visited.add(id);
                    visited.add(partnerId);
                }
            }

            playerGroups.value = [];
            for (let i = 0; i < Math.min(currentPairs.length, 4); i++) {
                playerGroups.value.push({
                    type: 'pair',
                    playerIds: [currentPairs[i].p1, currentPairs[i].p2],
                });
            }
            if (playerGroups.value.length === 0) {
                playerGroups.value.push({ type: 'none', playerIds: [] });
            }
        }

        if (parsed.currentMatch && playerMap.has(parsed.currentMatch.player1Id) && playerMap.has(parsed.currentMatch.player2Id)) {
            currentMatch.value = {
                p1: playerMap.get(parsed.currentMatch.player1Id),
                p2: playerMap.get(parsed.currentMatch.player2Id),
                p3: parsed.currentMatch.player3Id ? (playerMap.get(parsed.currentMatch.player3Id) ?? null) : null,
                p4: parsed.currentMatch.player4Id ? (playerMap.get(parsed.currentMatch.player4Id) ?? null) : null,
            };
            matchStarted.value = true;
            sessionStarted.value = true;
        } else {
            matchStarted.value = false;
            sessionStarted.value = queuedMatches.value.length > 0 || activePlayerIds.value.size > 0;
        }
    } catch {
        if (!sharedScoringStateSyncEnabled.value) {
            localStorage.removeItem(scoringQueueStorageKey.value);
        }
    }
};

// --- Waiting Queue Modal ---
const showQueueModal = ref(false);

const queueModalEntries = computed(() => {
    const playerMap = sessionPlayerMap.value;
    return queuedMatches.value.map((m, idx) => ({
        index: idx,
        p1: playerMap.get(m.player1Id),
        p2: playerMap.get(m.player2Id),
        p3: m.player3Id ? playerMap.get(m.player3Id) : null,
        p4: m.player4Id ? playerMap.get(m.player4Id) : null,
        isDoubles: m.player3Id != null || m.player4Id != null,
    }));
});

const openQueueModal = () => {
    if (!canEditScoringBoard.value) {
        return;
    }
    if (isQueueLocked.value) {
        showSystemAlert('Queue is locked. Booking has expired.', 'error');
        return;
    }

    const availablePlayers = sessionPlayers.value.filter((p) => activePlayerIds.value.has(p.id));
    if (availablePlayers.length >= 2 && queuedMatches.value.length < AUTO_FILL_COUNT) {
        const slotsToFill = AUTO_FILL_COUNT - queuedMatches.value.length;
        const tempAddedMatches: QueuedMatch[] = [];
        for (let i = 0; i < slotsToFill; i++) {
            const isDoubles = availablePlayers.length >= 4;
            const numRequired = isDoubles ? 4 : 2;
            const selected = getFairSelectedPlayers(availablePlayers, numRequired, tempAddedMatches);
            tempAddedMatches.push({
                player1Id: selected[0].id,
                player2Id: selected[1].id,
                player3Id: isDoubles ? selected[2].id : null,
                player4Id: isDoubles ? selected[3].id : null,
            });
        }
        queuedMatches.value = [...queuedMatches.value, ...tempAddedMatches];
        saveQueueState();
    }

    showQueueModal.value = true;
};

const addRandomToQueue = () => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    if (isQueueLocked.value) {
        showSystemAlert('Queue is locked. Booking has expired.', 'error');
        return;
    }
    if (queuedMatches.value.length >= MAX_QUEUE) {
        showSystemAlert('Queue is full (max ' + MAX_QUEUE + ' matches).', 'info');
        return;
    }

    const availablePlayers = sessionPlayers.value.filter((p) => activePlayerIds.value.has(p.id));
    if (availablePlayers.length < 2) {
        showSystemAlert('Please select at least 2 active players from the table.', 'error');
        return;
    }

    const isDoubles = availablePlayers.length >= 4;
    const numRequired = isDoubles ? 4 : 2;
    const selected = getFairSelectedPlayers(availablePlayers, numRequired);
    queuedMatches.value = [
        ...queuedMatches.value,
        {
            player1Id: selected[0].id,
            player2Id: selected[1].id,
            player3Id: isDoubles ? selected[2].id : null,
            player4Id: isDoubles ? selected[3].id : null,
        },
    ];
    saveQueueState();
};

const showReplaceNextModal = ref(false);
const replacingNextSlot = ref<'np1' | 'np2' | 'np3' | 'np4' | null>(null);
const replacementNextPlayerId = ref<number | null>(null);

const openReplaceNextPlayerModal = (slot: 'np1' | 'np2' | 'np3' | 'np4') => {
    if (!canEditScoringBoard.value) return;
    if (isQueueLocked.value || !nextMatchPreview.value) return;
    replacingNextSlot.value = slot;
    replacementNextPlayerId.value = null;
    showReplaceNextModal.value = true;
};

const closeReplaceNextModal = () => {
    showReplaceNextModal.value = false;
    replacingNextSlot.value = null;
    replacementNextPlayerId.value = null;
};

const replacementNextOptions = computed(() => {
    if (!nextMatchPreview.value || !replacingNextSlot.value) return [];

    const usedIds = new Set<number>();
    const slotMap = {
        np1: 'p1',
        np2: 'p2',
        np3: 'p3',
        np4: 'p4',
    } as const;

    (['np1', 'np2', 'np3', 'np4'] as const).forEach((slot) => {
        if (slot !== replacingNextSlot.value) {
            const key = slotMap[slot];
            const player = nextMatchPreview.value?.[key];
            if (player) usedIds.add(player.id);
        }
    });

    return sessionPlayers.value.filter((p) => activePlayerIds.value.has(p.id) && !usedIds.has(p.id));
});

const confirmReplaceNextPlayer = () => {
    if (!canEditScoringBoard.value) return;
    if (!queuedMatches.value.length || !replacingNextSlot.value || !replacementNextPlayerId.value) return;
    const preview = nextMatchPreview.value;
    if (!preview) return;

    const entry = { ...queuedMatches.value[0] };
    const slot = replacingNextSlot.value;
    if (slot === 'np1') {
        entry.player1Id = replacementNextPlayerId.value;
    } else if (slot === 'np2') {
        entry.player2Id = replacementNextPlayerId.value;
    } else if (slot === 'np3') {
        entry.player3Id = replacementNextPlayerId.value;
        if (entry.player4Id == null) entry.player4Id = preview.p4?.id ?? null;
    } else if (slot === 'np4') {
        entry.player4Id = replacementNextPlayerId.value;
        if (entry.player3Id == null) entry.player3Id = preview.p3?.id ?? null;
    }

    queuedMatches.value[0] = entry;
    saveQueueState();
    closeReplaceNextModal();
};

// --- Scoring Modal State & Logic ---
const showScoringModal = ref(false);
const editingMatchId = ref<number | null>(null);
const scoringMatchDetails = ref<{ p1: any; p2: any; p3: any | null; p4: any | null } | null>(null);

const matchForm = useForm({
    player_1_id: '',
    player_1_name: '',
    player_2_id: '',
    player_2_name: '',
    player_3_id: '',
    player_3_name: '',
    player_4_id: '',
    player_4_name: '',
    player_1_score: 0,
    player_2_score: 0,
    match_date: new Date().toISOString().split('T')[0],
    is_walkin: false as boolean,
    walkin_fee_type: 'with_ball' as 'with_ball' | 'without_ball',
    booking_id: null as number | null,
});

const buildMatchParticipant = (player: any, fallbackName: string | null | undefined) => {
    if (player) {
        return {
            ...player,
            isTemporary: false,
        };
    }

    if (!fallbackName) return null;

    return {
        id: null,
        name: fallbackName,
        is_member: false,
        isTemporary: true,
    };
};

const fillMatchFormSlot = (slot: 1 | 2 | 3 | 4, participant: any | null) => {
    const idKey = `player_${slot}_id` as const;
    const nameKey = `player_${slot}_name` as const;

    matchForm[idKey] = participant?.id && participant.id > 0 ? participant.id : '';
    matchForm[nameKey] = participant?.id && participant.id > 0 ? '' : participant?.name ?? '';
};

const openScoringModal = () => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    if (!currentMatch.value) return;

    if (!currentMatch.value.p3 || !currentMatch.value.p4) {
        showSystemAlert('Please fill all 4 player slots before entering score.', 'error');
        return;
    }

    fillMatchFormSlot(1, currentMatch.value.p1);
    fillMatchFormSlot(2, currentMatch.value.p2);
    fillMatchFormSlot(3, currentMatch.value.p3);
    fillMatchFormSlot(4, currentMatch.value.p4);
    matchForm.player_1_score = 0;
    matchForm.player_2_score = 0;
    matchForm.is_walkin = isPlayerScoringMode.value ? false : (isSelectedCourtWalkin.value as false | true);
    matchForm.walkin_fee_type = 'with_ball';
    matchForm.booking_id = activeBookingForSelectedCourt.value?.id ?? null;

    scoringMatchDetails.value = {
        p1: currentMatch.value.p1,
        p2: currentMatch.value.p2,
        p3: currentMatch.value.p3,
        p4: currentMatch.value.p4,
    };
    editingMatchId.value = null;
    showScoringModal.value = true;
};

const openEditScoringModal = (match: any) => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'error');
        return;
    }
    const p1 = buildMatchParticipant(match.player1, match.player_1_name);
    const p2 = buildMatchParticipant(match.player2, match.player_2_name);
    const p3 = buildMatchParticipant(match.player3, match.player_3_name);
    const p4 = buildMatchParticipant(match.player4, match.player_4_name);

    fillMatchFormSlot(1, p1);
    fillMatchFormSlot(2, p2);
    fillMatchFormSlot(3, p3);
    fillMatchFormSlot(4, p4);
    matchForm.player_1_score = match.player_1_score;
    matchForm.player_2_score = match.player_2_score;
    matchForm.is_walkin = isPlayerScoringMode.value ? false : (match.is_walkin ? true : false);
    matchForm.walkin_fee_type = match.walkin_fee_type || 'with_ball';
    matchForm.booking_id = match.booking_id ?? null;

    scoringMatchDetails.value = {
        p1,
        p2,
        p3,
        p4,
    };
    editingMatchId.value = match.id;
    showScoringModal.value = true;
};

const submitScore = () => {
    if (!canEditScoringBoard.value) {
        showSystemAlert('This scoring board is view only for invited players.', 'info');
        return;
    }
    if (matchForm.player_1_score === matchForm.player_2_score) {
        showSystemAlert('Matches cannot end in a draw. Please enter a winning score.', 'error');
        return;
    }

    if (!scoringMatchDetails.value?.p3 || !scoringMatchDetails.value?.p4) {
        showSystemAlert('Cannot submit score. Player 3 and Player 4 are required.', 'error');
        return;
    }

    if (editingMatchId.value) {
        matchForm.put(route('matches.update', editingMatchId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showScoringModal.value = false;
                editingMatchId.value = null;
                scoringMatchDetails.value = null;
                router.reload({ only: ['matches', 'players'] });
            },
        });
    } else {
        matchForm.post(route('matches.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showScoringModal.value = false;
                scoringMatchDetails.value = null;
                advanceToNextMatch();
                router.reload({ only: ['matches', 'players', 'bookingRoster', 'scoringState'] });
            },
        });
    }
};

const closeScoringModal = () => {
    showScoringModal.value = false;
    editingMatchId.value = null;
    scoringMatchDetails.value = null;
    matchForm.reset('player_1_score', 'player_2_score');
};

const setScore = (team: 1 | 2, delta: number) => {
    const field = team === 1 ? 'player_1_score' : 'player_2_score';
    const currentRaw = matchForm[field];
    const current = Number(currentRaw) || 0;
    const next = Math.min(99, Math.max(0, current + delta));
    matchForm[field] = next;
};

const clampScore = (team: 1 | 2) => {
    const field = team === 1 ? 'player_1_score' : 'player_2_score';
    const val = Number(matchForm[field]) || 0;
    matchForm[field] = Math.min(99, Math.max(0, val));
};

// --- Helpers ---
const getTeamNames = (match: any, teamNum: number) => {
    const nameOf = (playerId: number | null | undefined, player: any, fallback: string | null | undefined) => {
        const registered = playerId != null ? findRegisteredPlayerById(playerId) : null;
        return registered?.name ?? player?.name ?? fallback ?? 'Temporary Player';
    };

    if (teamNum === 1) {
        const playerOne = nameOf(match.player_1_id, match.player1, match.player_1_name);
        const playerThree = match.player3 || match.player_3_name ? nameOf(match.player_3_id, match.player3, match.player_3_name) : null;
        return playerThree ? `${playerOne} & ${playerThree}` : playerOne;
    } else {
        const playerTwo = nameOf(match.player_2_id, match.player2, match.player_2_name);
        const playerFour = match.player4 || match.player_4_name ? nameOf(match.player_4_id, match.player4, match.player_4_name) : null;
        return playerFour ? `${playerTwo} & ${playerFour}` : playerTwo;
    }
};

const walkinFeePreview = computed(() => {
    if (!matchForm.is_walkin) return null;
    const memberFee = props.settings?.walkin_member_fee || 15;
    const nonMemberFee = props.settings?.walkin_non_member_fee || 20;
    const ballSurcharge = props.settings?.walkin_ball_surcharge || 5;
    const hasBall = matchForm.walkin_fee_type !== 'without_ball';

    const breakdown: { name: string; fee: number; isMember: boolean }[] = [];
    let total = 0;

    for (const slot of [1, 2, 3, 4] as const) {
        const idKey = `player_${slot}_id` as const;
        const nameKey = `player_${slot}_name` as const;
        const registered = findRegisteredPlayerById(matchForm[idKey] as any);
        const fallbackName = String(matchForm[nameKey] ?? '').trim();

        if (!registered && !fallbackName) continue;

        const isMember = registered?.is_member ?? false;
        const base = isMember ? memberFee : nonMemberFee;
        const fee = base + (hasBall ? 0 : ballSurcharge);
        breakdown.push({ name: registered?.name ?? fallbackName, fee, isMember });
        total += fee;
    }

    return { breakdown, total };
});

const getPlayerTotalFee = (player: any) => {
    const memberFee = props.settings?.walkin_member_fee || 15;
    const nonMemberFee = props.settings?.walkin_non_member_fee || 20;
    const ballSurcharge = props.settings?.walkin_ball_surcharge || 5;

    const playerData = findRegisteredPlayerById(player.id);
    const isMember = playerData?.is_member || false;
    const baseFee = isMember ? memberFee : nonMemberFee;

    const totalFee = localMatches.value.reduce((sum, match) => {
        if (!match.is_walkin) return sum;
        const registeredIds = [match.player_1_id, match.player_2_id, match.player_3_id, match.player_4_id].filter(Boolean).map(Number);
        const temporaryNames = [match.player_1_name, match.player_2_name, match.player_3_name, match.player_4_name]
            .filter(Boolean)
            .map((name: string) => name.trim().toLowerCase());
        const isIncluded = player.id < 0
            ? temporaryNames.includes(player.name.trim().toLowerCase())
            : registeredIds.includes(Number(player.id));
        if (!isIncluded) return sum;

        const hasBall = match.walkin_fee_type !== 'without_ball';
        return sum + baseFee + (hasBall ? 0 : ballSurcharge);
    }, 0);

    return totalFee;
};

const isHydrated = ref(false);

onMounted(() => {
    hydrateQueueState();
    if (isPlayerScoringViewOnly.value) {
        router.reload({ only: POLL_RELOAD });
    }
    setTimeout(() => {
        isHydrated.value = true;
    }, 1000);
});

watch(
    () => props.scoringState,
    (state) => {
        if (sharedScoringStateSyncEnabled.value) {
            hydrateQueueState(state ?? null);
        }
    },
    { deep: true },
);

watch(isQueueLocked, (locked) => {
    if (!isHydrated.value) return;
    if (locked && canSaveSession.value && canEditScoringBoard.value) {
        localMatches.value = [];
        if (isPlayerScoringMode.value) {
            const owner = props.allPlayers.find(p => p.user_id && props.playerBooking?.user_id && Number(p.user_id) === Number(props.playerBooking.user_id));
            const ownerId = owner ? owner.id : null;
            activePlayerIds.value = ownerId ? new Set([ownerId]) : new Set();
        } else {
            activePlayerIds.value = new Set();
        }
        localRegisteredSessionPlayers.value = [];
        tempSessionPlayers.value = [];
        queuedMatches.value = [];
        currentMatch.value = null;
        matchStarted.value = false;
        sessionStarted.value = false;
        lateJoinerOffsets.value = {};
        playerPairs.value = {};
        playerGroups.value = [
            { type: 'none', playerIds: [] }
        ];
        if (sharedScoringStateSyncEnabled.value) {
            void persistSharedScoringState(null);
        } else {
            localStorage.removeItem(scoringQueueStorageKey.value);
        }

        saveSessionForm.post(route('scoring.save'), {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                showSystemAlert('Session has expired and matches have been automatically saved.', 'info');
            }
        });
    }
});
let pollInterval: ReturnType<typeof setInterval> | null = null;
const POLL_RELOAD = ['matches', 'players', 'allPlayers', 'assignedCourts', 'activeBookings', 'bookingRoster', 'scoringState', 'playerScoringNotice', 'upcomingNotice', 'settings'];

const startPoll = () => {
    if (pollInterval) return;
    pollInterval = setInterval(() => {
        router.reload({ only: POLL_RELOAD });
    }, 5000);
};

const stopPoll = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const handlePollVisibility = () => {
    if (document.visibilityState === 'visible') {
        router.reload({ only: POLL_RELOAD });
        startPoll();
    } else {
        stopPoll();
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handlePollVisibility);
    handlePollVisibility();
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handlePollVisibility);
    stopPoll();
    if (scoringStateSaveTimer) {
        clearTimeout(scoringStateSaveTimer);
        scoringStateSaveTimer = null;
    }
});
</script>

<template>
    <Head title="Scoring & Stats" />

    <AppLayout>
        <div
            v-if="appAlert"
            class="fixed right-5 top-5 z-[120] rounded-xl border px-4 py-3 text-sm font-bold tracking-wide shadow-xl"
            :class="appAlert.tone === 'error' ? 'border-rose-700 bg-rose-950/95 text-rose-100' : 'border-indigo-700 bg-indigo-950/95 text-indigo-100'"
        >
            {{ appAlert.message }}
        </div>

        <div
            class="relative mx-auto flex w-full max-w-none flex-col p-3 transition-all duration-200 sm:p-4 lg:p-6 xl:h-[calc(100vh-64px)] xl:overflow-hidden"
        >
            <div class="mb-3 px-1 sm:mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white sm:text-3xl">Scoring & Stats</h1>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        Manage live matches, player rotation, and session results.
                    </p>
                </div>

                <!-- Active Booking Expiration Alert -->
                <div
                    v-if="isTimeAlmostExpired || isTimeFullyExpired"
                    class="rounded-2xl border p-3.5 shadow-sm transition-all duration-300 flex items-center gap-3 md:max-w-2xl shrink-0"
                    :class="
                        isTimeFullyExpired
                            ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-200'
                            : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-200'
                    "
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                        :class="isTimeFullyExpired ? 'bg-red-100 dark:bg-red-900/30' : 'bg-amber-100 dark:bg-amber-900/30'"
                    >
                        <svg class="h-4.5 w-4.5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                        <h3 class="text-sm font-black uppercase tracking-wider shrink-0">
                            {{ isTimeFullyExpired ? 'Schedule Expired' : 'Schedule Expiring Soon' }}
                        </h3>
                        <span class="hidden sm:inline opacity-30">|</span>
                        <p class="text-xs opacity-90 font-bold">
                            {{ isTimeFullyExpired
                                ? `The booking for ${activeBookingForSelectedCourt?.lead_name || 'Client'} has expired. Queue controls are not locked and the data of the match is automatically saved.`
                                : `The booking for ${activeBookingForSelectedCourt?.lead_name || 'Client'} ends in ${minutesRemaining} minutes (${formatTime12h(activeBookingForSelectedCourt?.end_time)}).`
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="isPlayerScoringBlocked"
                class="flex flex-1 items-center justify-center"
            >
                <div class="glass-card w-full max-w-3xl rounded-3xl border border-blue-200/60 bg-white/80 p-8 text-center shadow-sm dark:border-blue-900/30 dark:bg-[#0f0f0f]/85 sm:p-10">
                    <!-- Upcoming Session Notice -->
                    <div v-if="props.upcomingNotice" class="mb-8 rounded-2xl bg-amber-500/15 border border-amber-500/25 p-4 text-amber-700 dark:text-amber-300 flex items-center gap-3 text-left">
                        <div class="rounded-full bg-amber-500/20 p-2 text-amber-600 dark:text-amber-400 shrink-0">
                            <Bell class="h-5 w-5 animate-bounce" />
                        </div>
                        <div>
                            <p class="text-sm font-black uppercase tracking-wider text-amber-800 dark:text-amber-400">Upcoming Session Notice</p>
                            <p class="text-xs font-semibold mt-0.5 opacity-90">{{ props.upcomingNotice }}</p>
                        </div>
                    </div>

                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950/30 dark:text-blue-300">
                        <Clock class="h-8 w-8" />
                    </div>
                    <h2 class="mt-5 text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                        Scoring Needs an Active Booking
                    </h2>
                    <p class="mx-auto mt-3 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300 sm:text-base">
                        {{ props.playerScoringNotice }}
                    </p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Book a venue first, then open scoring during your scheduled time.
                    </p>
                    <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a
                            href="/book"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-black uppercase tracking-widest text-white transition hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Book a Venue
                        </a>
                        <a
                            href="/venues"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black uppercase tracking-widest text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-200 dark:hover:bg-[#151515]"
                        >
                            View Venues
                        </a>
                    </div>
                </div>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:gap-6 xl:min-h-0 xl:flex-1 xl:grid-cols-12">
                <!-- LEFT PANEL: MATCH (3 Cols) -->
                <div
                    class="glass-card flex h-full min-h-0 flex-col overflow-hidden rounded-3xl md:group-data-[state=collapsed]/sidebar-wrapper:xl:col-span-2 xl:col-span-3"
                >
                    <div class="shrink-0 border-b border-slate-200 bg-white/70 p-5 text-center dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/80">
                        <h2 class="text-2xl font-black uppercase tracking-widest text-slate-900 dark:text-white">MATCH</h2>
                    </div>

                    <div
                        class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 overflow-y-auto bg-slate-50/40 p-2 dark:bg-[#0a0a0a]/50 xl:justify-evenly"
                    >
                        <!-- EMPTY STATE: no match queued yet -->
                        <template v-if="!currentMatch">
                            <div class="flex w-full flex-1 flex-col items-center justify-center gap-3">
                                <div class="flex flex-col items-center gap-2 opacity-40">
                                    <Swords class="h-10 w-10 text-slate-400 dark:text-slate-600" />
                                    <p class="text-center text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-600">
                                        No match queued
                                    </p>
                                    <p class="text-center text-[10px] text-slate-400 dark:text-slate-600">Add players &amp; press Random Queue</p>
                                </div>
                                <button
                                    v-if="queuedMatches.length > 0 || activePlayerIds.size >= 2"
                                    type="button"
                                    @click="handleStartMatchClick"
                                    :disabled="isQueueLocked || isPlayerScoringViewOnly || matchStarted"
                                    class="mt-2 rounded-2xl bg-emerald-500 px-8 py-3 text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-emerald-500/30 transition-all hover:bg-emerald-600 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-emerald-500"
                                >
                                    Start Match
                                </button>
                            </div>
                        </template>

                        <!-- MATCH VIEW: match is active, reveal teams -->
                        <template v-else>
                            <div
                                class="w-full max-w-[420px] space-y-1 rounded-lg border border-blue-200 bg-blue-50/80 p-1 dark:border-green-500/30 dark:bg-green-900/20"
                            >
                                <p class="text-center text-[9px] font-black uppercase tracking-[0.18em] text-blue-600 dark:text-green-400">Team A</p>
                                <button type="button" class="match-slot match-slot-a" :disabled="isPlayerScoringViewOnly" @click="openReplacePlayerModal('p1')">
                                    {{ currentMatch.p1.name }}
                                </button>
                                <button type="button" class="match-slot match-slot-a" :disabled="isPlayerScoringViewOnly" @click="openReplacePlayerModal('p3')">
                                    {{ currentMatch.p3?.name ?? 'TBD' }}
                                </button>
                            </div>

                            <div class="flex w-full max-w-[420px] items-center gap-2 px-1 py-0">
                                <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]"></div>
                                <div
                                    class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white/80 text-xs font-black text-slate-300 shadow-inner dark:border-[#2a2a2a] dark:bg-[#0a0a0a]/80 dark:text-slate-600"
                                >
                                    VS
                                </div>
                                <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]"></div>
                            </div>

                            <div
                                class="w-full max-w-[420px] space-y-1 rounded-lg border border-rose-200 bg-rose-50/80 p-1 dark:border-rose-500/30 dark:bg-rose-950/30"
                            >
                                <p class="text-center text-[9px] font-black uppercase tracking-[0.18em] text-rose-600 dark:text-rose-400">Team B</p>
                                <button type="button" class="match-slot match-slot-b" :disabled="isPlayerScoringViewOnly" @click="openReplacePlayerModal('p2')">
                                    {{ currentMatch.p2.name }}
                                </button>
                                <button type="button" class="match-slot match-slot-b" :disabled="isPlayerScoringViewOnly" @click="openReplacePlayerModal('p4')">
                                    {{ currentMatch.p4?.name ?? 'TBD' }}
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- NEXT GAME CONTAINER (always visible) -->
                    <div
                        v-if="currentMatch || nextMatchPreview"
                        @click.self="isPlayerScoringViewOnly ? null : openQueueModal"
                        class="mx-2 mb-2 shrink-0 rounded-lg border border-slate-300/60 bg-white/45 p-1.5 transition-colors dark:border-[#2a2a2a]/70 dark:bg-[#0a0a0a]/40"
                        :class="isPlayerScoringViewOnly ? '' : 'cursor-pointer hover:border-indigo-400 dark:hover:border-green-500'"
                    >
                        <div class="pointer-events-none mb-1 flex items-center justify-center gap-1.5">
                            <p class="text-center text-[9px] font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Next Game</p>
                            <span
                                v-if="queuedMatches.length > 0"
                                class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[9px] font-black text-indigo-600 dark:bg-green-900/30 dark:text-green-300"
                                >+{{ queuedMatches.length }} queued</span
                            >
                        </div>
                        <div v-if="nextMatchPreview" @click.self="isPlayerScoringViewOnly ? null : openQueueModal" class="grid grid-cols-2 gap-1.5 sm:gap-1">
                            <button
                                type="button"
                                class="next-game-pill truncate transition-colors"
                                :class="(isQueueLocked || isPlayerScoringViewOnly) ? 'cursor-not-allowed opacity-50 text-slate-400' : 'cursor-pointer hover:border-indigo-400 hover:text-indigo-600 dark:hover:border-green-500 dark:hover:text-green-400'"
                                :disabled="isQueueLocked || isPlayerScoringViewOnly"
                                @click.stop="openReplaceNextPlayerModal('np1')"
                            >
                                {{ nextMatchPreview.p1.name }}
                            </button>
                            <button
                                type="button"
                                class="next-game-pill truncate transition-colors"
                                :class="(isQueueLocked || isPlayerScoringViewOnly) ? 'cursor-not-allowed opacity-50 text-slate-400' : 'cursor-pointer hover:border-indigo-400 hover:text-indigo-600 dark:hover:border-green-500 dark:hover:text-green-400'"
                                :disabled="isQueueLocked || isPlayerScoringViewOnly"
                                @click.stop="openReplaceNextPlayerModal('np2')"
                            >
                                {{ nextMatchPreview.p2.name }}
                            </button>
                            <button
                                type="button"
                                class="next-game-pill truncate transition-colors"
                                :class="(isQueueLocked || isPlayerScoringViewOnly) ? 'cursor-not-allowed opacity-50 text-slate-400' : 'cursor-pointer hover:border-indigo-400 hover:text-indigo-600 dark:hover:border-green-500 dark:hover:text-green-400'"
                                :disabled="isQueueLocked || isPlayerScoringViewOnly"
                                @click.stop="openReplaceNextPlayerModal('np3')"
                            >
                                {{ nextMatchPreview.p3 ? nextMatchPreview.p3.name : 'TBD' }}
                            </button>
                            <button
                                type="button"
                                class="next-game-pill truncate transition-colors"
                                :class="(isQueueLocked || isPlayerScoringViewOnly) ? 'cursor-not-allowed opacity-50 text-slate-400' : 'cursor-pointer hover:border-indigo-400 hover:text-indigo-600 dark:hover:border-green-500 dark:hover:text-green-400'"
                                :disabled="isQueueLocked || isPlayerScoringViewOnly"
                                @click.stop="openReplaceNextPlayerModal('np4')"
                            >
                                {{ nextMatchPreview.p4 ? nextMatchPreview.p4.name : 'TBD' }}
                            </button>
                        </div>
                        <p v-else class="pointer-events-none py-2 text-center text-xs font-semibold text-slate-400 dark:text-slate-500">
                            Click to manage queue.
                        </p>
                    </div>

                    <div class="shrink-0 border-t border-slate-200 bg-white/80 p-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/80">
                        <div class="mx-auto w-full max-w-[280px] flex gap-2">
                            <button
                                @click="openScoringModal"
                                :disabled="!currentMatch || isPlayerScoringViewOnly"
                                class="h-10 flex-1 whitespace-nowrap rounded-xl text-[11px] font-black uppercase tracking-widest text-white shadow-sm transition-all active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                :class="(!currentMatch || isPlayerScoringViewOnly) ? 'bg-blue-600/50 dark:bg-green-600/50' : 'bg-blue-600 shadow-blue-500/20 hover:bg-blue-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500'"
                            >
                                Enter Score
                            </button>
                            <button
                                @click="resetQueue"
                                :disabled="isQueueLocked || !currentMatch || isPlayerScoringViewOnly"
                                class="h-10 flex-1 whitespace-nowrap rounded-xl border border-slate-200 bg-white text-[11px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-all active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-800 dark:bg-[#0a0a0a] dark:text-slate-300 dark:hover:bg-[#151515]"
                            >
                                Reset Queue
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CENTER PANEL: PLAYER TABLE (5 Cols) -->
                <div
                    class="glass-card flex flex-col overflow-hidden rounded-2xl sm:rounded-3xl md:group-data-[state=collapsed]/sidebar-wrapper:xl:col-span-8 xl:col-span-6 xl:h-full"
                >
                    <div
                        class="flex items-center justify-between gap-2 border-b border-slate-200 bg-white/70 p-3 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/80 sm:p-5"
                    >
                        <div class="min-w-0">
                            <h2 class="text-heading truncate text-lg font-black uppercase tracking-widest text-slate-900 dark:text-white sm:text-2xl">
                                Player Roster
                            </h2>
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                {{ isPlayerScoringMode ? 'Use your active booking to record scores for this session' : 'Select active players for random queue' }}
                            </p>
                        </div>

                        <!-- Court Selector -->
                        <div class="hidden items-center gap-2 sm:flex">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Court</span>
                            <div class="relative" ref="courtDropdownRef">
                                <button
                                    v-if="!courtLocked"
                                    @click="showCourtDropdown = !showCourtDropdown"
                                    class="btn-heading flex min-h-[32px] min-w-[150px] items-center gap-2 rounded-xl border py-1.5 pl-3 pr-3 text-xs shadow-sm transition-all focus:outline-none"
                                    :class="selectedCourtButtonClass"
                                >
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :class="selectedCourtMode === 'walkin' ? 'bg-amber-400' : (selectedCourtMode === 'reclub' ? 'bg-violet-400' : 'bg-emerald-400')"
                                    ></span>
                                    {{
                                        selectedCourt !== null
                                            ? `C${selectedCourt} — ${selectedCourtMode === 'walkin' ? 'Walk-in' : (selectedCourtMode === 'reclub' ? 'Reclub' : 'Booking')}`
                                            : 'Not Assigned'
                                    }}
                                    <svg
                                        class="ml-auto h-3.5 w-3.5 transition-transform"
                                        :class="showCourtDropdown ? 'rotate-180' : ''"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div
                                    v-else
                                    class="flex cursor-not-allowed items-center gap-2 text-xs font-bold text-slate-500 opacity-80 dark:text-slate-400"
                                    title="Court locked"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                        />
                                    </svg>
                                    {{
                                        selectedCourt !== null
                                            ? `C${selectedCourt} — ${selectedCourtMode === 'walkin' ? 'Walk-in' : (selectedCourtMode === 'reclub' ? 'Reclub' : 'Booking')}`
                                            : 'Not Assigned'
                                    }}
                                </div>
                                <div
                                    v-if="showCourtDropdown && !courtLocked"
                                    class="absolute left-0 top-full z-50 mt-1.5 w-full overflow-hidden rounded-xl border shadow-lg"
                                    :class="
                                        selectedCourtMode === 'walkin'
                                            ? 'border-amber-200 bg-white dark:border-amber-800 dark:bg-amber-950'
                                            : selectedCourtMode === 'reclub'
                                              ? 'border-violet-200 bg-white dark:border-violet-800 dark:bg-violet-950'
                                              : 'border-emerald-200 bg-white dark:border-emerald-800 dark:bg-emerald-950'
                                    "
                                >
                                    <button
                                        v-for="opt in allCourts"
                                        :key="`${opt.court}-${opt.mode}`"
                                        @click="selectCourt(opt.court, opt.mode)"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-xs font-bold transition-colors"
                                        :class="
                                            selectedCourt === opt.court && selectedCourtMode === opt.mode
                                                ? opt.mode === 'walkin'
                                                    ? 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200'
                                                    : opt.mode === 'reclub'
                                                      ? 'bg-violet-50 text-violet-800 dark:bg-violet-900/30 dark:text-violet-200'
                                                      : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200'
                                                : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                        "
                                    >
                                        <span
                                            class="h-2 w-2 shrink-0 rounded-full"
                                            :class="courtOptionColor(opt.mode)"
                                        ></span>
                                        C{{ opt.court }} — {{ opt.mode === 'walkin' ? 'Walk-in' : (opt.mode === 'reclub' ? 'Reclub' : 'Booking') }}
                                    </button>
                                    <div
                                        v-if="allCourts.length === 0"
                                        class="px-3 py-3 text-center text-[11px] font-medium text-slate-400"
                                    >
                                        <span v-if="isScorer">No courts assigned to you today. Ask the scheduler to assign a court.</span>
                                        <span v-else>No courts available.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Court Selector -->
                        <div class="flex items-center gap-1.5 sm:hidden">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Court</span>
                            <div class="relative" ref="courtDropdownRefMobile">
                                <button
                                    v-if="!courtLocked"
                                    @click="showCourtDropdownMobile = !showCourtDropdownMobile"
                                    class="flex min-h-[32px] items-center gap-1.5 rounded-xl border py-1.5 pl-2.5 pr-2.5 text-xs font-bold shadow-sm transition-all focus:outline-none"
                                    :class="selectedCourtButtonClass"
                                >
                                    <span
                                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                                        :class="selectedCourtMode === 'walkin' ? 'bg-amber-400' : (selectedCourtMode === 'reclub' ? 'bg-violet-400' : 'bg-emerald-400')"
                                    ></span>
                                    {{ selectedCourt !== null ? `C${selectedCourt}` : 'Not Assigned' }}
                                    <svg
                                        class="h-3 w-3 transition-transform"
                                        :class="showCourtDropdownMobile ? 'rotate-180' : ''"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div
                                    v-else
                                    class="flex cursor-not-allowed items-center gap-1.5 text-xs font-bold text-slate-500 opacity-80 dark:text-slate-400"
                                    title="Court locked"
                                >
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                        />
                                    </svg>
                                    {{ selectedCourt !== null ? `C${selectedCourt}` : 'Not Assigned' }}
                                </div>
                                <div
                                    v-if="showCourtDropdownMobile && !courtLocked"
                                    class="absolute right-0 top-full z-50 mt-1.5 w-max min-w-[160px] overflow-hidden rounded-xl border shadow-lg"
                                    :class="
                                        selectedCourtMode === 'walkin'
                                            ? 'border-amber-200 bg-white dark:border-amber-800 dark:bg-amber-950'
                                            : selectedCourtMode === 'reclub'
                                              ? 'border-violet-200 bg-white dark:border-violet-800 dark:bg-violet-950'
                                              : 'border-emerald-200 bg-white dark:border-emerald-800 dark:bg-emerald-950'
                                    "
                                >
                                    <button
                                        v-for="opt in allCourts"
                                        :key="`${opt.court}-${opt.mode}`"
                                        @click="selectCourt(opt.court, opt.mode)"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-xs font-bold transition-colors"
                                        :class="
                                            selectedCourt === opt.court && selectedCourtMode === opt.mode
                                                ? opt.mode === 'walkin'
                                                    ? 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200'
                                                    : opt.mode === 'reclub'
                                                      ? 'bg-violet-50 text-violet-800 dark:bg-violet-900/30 dark:text-violet-200'
                                                      : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200'
                                                : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                        "
                                    >
                                        <span
                                            class="h-2 w-2 shrink-0 rounded-full"
                                            :class="courtOptionColor(opt.mode)"
                                        ></span>
                                        C{{ opt.court }} — {{ opt.mode === 'walkin' ? 'Walk-in' : (opt.mode === 'reclub' ? 'Reclub' : 'Booking') }}
                                    </button>
                                    <div
                                        v-if="allCourts.length === 0"
                                        class="px-3 py-3 text-center text-[11px] font-medium text-slate-400"
                                    >
                                        <span v-if="isScorer">No courts assigned to you today. Ask the scheduler to assign a court.</span>
                                        <span v-else>No courts available.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Player Button/Form -->
                        <div v-if="canAddRosterPlayers" class="relative flex items-center gap-2">
                            <button
                                v-if="!showAddPlayer"
                                :disabled="selectedCourt === null || isQueueLocked"
                                @click="openAddPlayerModal"
                                title="Add Player"
                                class="flex items-center justify-center rounded-lg p-1.5 transition-colors hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-55 dark:text-green-400 dark:hover:bg-green-900/20 text-indigo-600"
                            >
                                <UserPlus class="h-5 w-5" />
                            </button>
                            <button
                                v-if="!showAddPlayer"
                                :disabled="selectedCourt === null || isQueueLocked || sessionPlayers.length === 0"
                                @click="openGroupSetupModal"
                                title="Setup Groups"
                                class="flex items-center justify-center rounded-lg p-1.5 transition-colors hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-55 dark:text-green-400 dark:hover:bg-green-900/20 text-indigo-600"
                            >
                                <Plus class="h-5 w-5" />
                            </button>
                            <div v-else class="relative hidden animate-in fade-in slide-in-from-right-4">
                                <form @submit.prevent="quickAddPlayer" class="relative z-20 flex items-center gap-2">
                                    <input
                                        v-model="playerForm.name"
                                        type="text"
                                        placeholder="Name..."
                                        class="w-40 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm ring-indigo-500 focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500"
                                        autofocus
                                        autocomplete="off"
                                    />
                                    <button
                                        type="submit"
                                        :disabled="!playerForm.name"
                                        class="rounded-lg bg-indigo-600 p-1.5 text-white transition-colors hover:bg-indigo-700 dark:bg-green-600 dark:hover:bg-green-500"
                                    >
                                        <CheckCircle class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            showAddPlayer = false;
                                            playerForm.reset();
                                        "
                                        class="rounded-lg bg-slate-200 p-1.5 text-slate-500 transition-colors hover:bg-slate-300 dark:bg-[#1a1a1a] dark:hover:bg-[#2a2a2a]"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </form>

                                <!-- Search Dropdown -->
                                <div
                                    v-if="playerForm.name.trim() && filteredPlayers.length > 0"
                                    class="absolute left-0 top-full z-50 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                >
                                    <ul class="py-1">
                                        <li v-for="fp in filteredPlayers" :key="fp.id">
                                            <button
                                                @click="selectPlayerFromDropdown(fp.name)"
                                                class="w-full px-3 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-slate-300 dark:hover:bg-green-900/20 dark:hover:text-green-400"
                                            >
                                                {{ fp.name }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="custom-scrollbar table-surface max-h-[340px] flex-1 overflow-auto bg-slate-50/40 dark:bg-[#0a0a0a]/50 sm:max-h-[380px] md:max-h-[440px] xl:max-h-none"
                    >
                        <div
                            v-if="isPlayerScoringMode && props.playerBooking"
                            class="space-y-2 border-b border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-700 dark:border-blue-900/40 dark:bg-blue-950/20 dark:text-blue-300"
                        >
                            <p>
                                Scoring access is tied to your booking at <strong>{{ props.playerBooking.venue_name }}</strong>, court {{ props.playerBooking.court_number }}.
                                <span v-if="isPlayerScoringViewOnly">You are viewing this board as an invited player, so scores are read only.</span>
                                <span v-else>Use this page to record scores for your session only.</span>
                            </p>
                            <div v-if="pendingBookingRoster.length" class="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-amber-800 dark:border-amber-700/40 dark:bg-amber-900/10 dark:text-amber-300">
                                Waiting for: {{ pendingBookingRoster.map((player) => player.name).join(', ') }}
                            </div>
                        </div>
                        <!-- Mobile Card List -->
                        <div class="space-y-2 p-2.5 sm:hidden">
                            <div
                                v-for="(player, index) in rosterSessionPlayers"
                                :key="`m-${player.id}`"
                                class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                            >
                                <!-- Rank -->
                                <div class="w-6 flex-shrink-0 text-center">
                                    <span class="text-xs font-black text-slate-400">{{ index + 1 }}</span>
                                </div>
                                <!-- Avatar + Name -->
                                <div class="flex min-w-0 flex-1 items-center gap-2.5">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-600 dark:bg-green-900/20 dark:text-green-400"
                                    >
                                        {{ player.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ player.name }}</p>
                                            <template v-if="activePlayerIds.has(player.id)">
                                                <span v-if="getPlayerGroupInfo(player.id)" 
                                                    class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.25 text-[9px] font-black"
                                                    :class="getPlayerGroupInfo(player.id).type === 'pair'
                                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400'
                                                        : 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400'"
                                                    :title="`${getPlayerGroupInfo(player.id).label}: ${getPlayerGroupInfo(player.id).membersText}`"
                                                >
                                                    🔗 {{ getPlayerGroupCode(player.id) }}
                                                </span>
                                            </template>
                                        </div>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                            <span
                                                class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-[#1a1a1a] dark:text-slate-300"
                                                >{{ player.wins }}W/{{ player.losses }}L</span
                                            >
                                            <span
                                                class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-black text-indigo-600 dark:bg-green-900/20 dark:text-green-400"
                                                >{{ (player.wins * (settings.scoring_win_points || 10)) - (player.losses * (settings.scoring_loss_penalty || 5)) }} pts</span
                                            >
                                            <span
                                                class="text-[10px] font-black"
                                                :class="
                                                    player.win_rate > 50
                                                        ? 'text-emerald-500'
                                                        : player.win_rate > 0
                                                          ? 'text-amber-500'
                                                          : 'text-slate-400'
                                                "
                                                >{{ player.win_rate }}%</span
                                            >

                                        </div>
                                    </div>
                                </div>
                                <!-- Right side: Fee + Toggle + Remove -->
                                <div class="flex flex-shrink-0 flex-col items-end gap-1.5">
                                    <span
                                        v-if="showTotalFee"
                                        class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700 dark:bg-emerald-900/25 dark:text-emerald-300"
                                    >
                                        ₱{{ getPlayerTotalFee(player) }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="!isPlayerScoringViewOnly && player.wins === 0 && player.losses === 0 && canRemoveSessionPlayer(player)"
                                            @click="removeFromSession(player.id)"
                                            class="text-slate-400 transition hover:text-red-500 dark:hover:text-red-400"
                                            title="Remove player"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                        <label class="relative inline-flex items-center" :class="(isQueueLocked || isPlayerScoringViewOnly) ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
                                            <input
                                                type="checkbox"
                                                :checked="activePlayerIds.has(player.id)"
                                                @change="togglePlayerActive(player.id)"
                                                :disabled="isQueueLocked || isPlayerScoringViewOnly"
                                                class="peer sr-only"
                                            />
                                            <div
                                                class="peer h-5 w-9 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:peer-checked:bg-green-500"
                                            ></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Table -->
                        <table class="hidden w-full border-collapse text-left sm:table">
                            <thead class="sticky top-0 z-10 bg-slate-100 shadow-sm dark:bg-[#0f0f0f]">
                                <tr>
                                    <th
                                        class="w-16 border-b border-r border-slate-200 px-6 py-4 text-center text-[11px] font-black uppercase tracking-widest text-slate-500 dark:border-[#1a1a1a]"
                                    >
                                        No.
                                    </th>
                                    <th
                                        class="border-b border-r border-slate-200 px-6 py-4 text-[11px] font-black uppercase tracking-widest text-slate-500 dark:border-[#1a1a1a]"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="w-24 border-b border-r border-slate-200 px-6 py-4 text-center text-[11px] font-black uppercase tracking-widest text-slate-500 dark:border-[#1a1a1a]"
                                    >
                                        W/L
                                    </th>
                                    <th
                                        class="w-28 border-b border-r border-slate-200 px-6 py-4 text-center text-[11px] font-black uppercase tracking-widest text-slate-500 dark:border-[#1a1a1a]"
                                    >
                                        Win Rate
                                    </th>
                                    <th
                                        class="w-20 border-b border-r border-slate-200 px-6 py-4 text-center text-[11px] font-black uppercase tracking-widest text-indigo-500 dark:border-[#1a1a1a] dark:text-green-400"
                                    >
                                        PTS
                                    </th>
                                    <th
                                        class="w-20 border-b border-slate-200 px-4 py-4 text-center text-[10px] font-black uppercase tracking-widest text-indigo-500 dark:border-[#1a1a1a] dark:text-green-400"
                                        :class="{ 'border-r border-slate-200 dark:border-[#1a1a1a]': showTotalFee }"
                                    >
                                        Active
                                    </th>
                                    <th
                                        v-if="showTotalFee"
                                        class="w-32 border-b border-slate-200 px-4 py-4 text-center text-[11px] font-black uppercase tracking-widest text-emerald-500 dark:border-[#1a1a1a]"
                                    >
                                        Total Fee
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(player, index) in rosterSessionPlayers"
                                    :key="player.id"
                                    class="border-b border-slate-200 transition-colors hover:bg-white dark:border-[#1a1a1a]/50 dark:hover:bg-[#0f0f0f]/50"
                                >
                                    <td
                                        class="border-r border-slate-200 px-6 py-3 text-center text-sm font-bold text-slate-500 dark:border-[#1a1a1a]/50"
                                    >
                                        {{ index + 1 }}
                                    </td>
                                    <td class="border-r border-slate-200 px-6 py-3 font-bold text-slate-900 dark:border-[#1a1a1a]/50 dark:text-white">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-black text-indigo-600 dark:bg-green-900/20 dark:text-green-400"
                                            >
                                                {{ player.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span>{{ player.name }}</span>
                                                <span v-if="activePlayerIds.has(player.id) && getPlayerGroupInfo(player.id)" 
                                                    class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-black"
                                                    :class="getPlayerGroupInfo(player.id).type === 'pair'
                                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400'
                                                        : 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400'"
                                                    :title="`${getPlayerGroupInfo(player.id).label}: ${getPlayerGroupInfo(player.id).membersText}`"
                                                >
                                                    🔗 {{ getPlayerGroupCode(player.id) }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border-r border-slate-200 px-6 py-3 text-center dark:border-[#1a1a1a]/50">
                                        <span
                                            class="rounded-md bg-slate-100 px-2 py-1 text-sm font-bold text-slate-600 dark:bg-[#1a1a1a] dark:text-slate-300"
                                        >
                                            {{ player.wins }}/{{ player.losses }}
                                        </span>
                                    </td>
                                    <td class="border-r border-slate-200 px-6 py-3 text-center dark:border-[#1a1a1a]/50">
                                        <span
                                            class="text-sm font-black"
                                            :class="
                                                player.win_rate > 50 ? 'text-emerald-500' : player.win_rate > 0 ? 'text-amber-500' : 'text-slate-400'
                                            "
                                        >
                                            {{ player.win_rate }}%
                                        </span>
                                    </td>
                                    <td class="border-r border-slate-200 px-6 py-3 text-center dark:border-[#1a1a1a]/50">
                                        <span
                                            class="rounded-md bg-indigo-50 px-2 py-1 text-sm font-black text-indigo-600 dark:bg-green-900/20 dark:text-green-400"
                                        >
                                            {{ (player.wins * (settings.scoring_win_points || 10)) - (player.losses * (settings.scoring_loss_penalty || 5)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center dark:border-[#1a1a1a]/50" :class="{ 'border-r border-slate-200 dark:border-[#1a1a1a]/50': showTotalFee }">
                                        <label class="relative inline-flex items-center" :class="(isQueueLocked || isPlayerScoringViewOnly) ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
                                            <input
                                                type="checkbox"
                                                :checked="activePlayerIds.has(player.id)"
                                                @change="togglePlayerActive(player.id)"
                                                :disabled="isQueueLocked || isPlayerScoringViewOnly"
                                                class="peer sr-only"
                                            />
                                            <div
                                                class="peer h-5 w-9 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:peer-checked:bg-green-500"
                                            ></div>
                                        </label>
                                    </td>
                                    <td v-if="showTotalFee" class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex min-w-[92px] items-center justify-center rounded-md bg-emerald-50 px-3 py-1 text-sm font-black text-emerald-700 dark:bg-emerald-900/25 dark:text-emerald-300"
                                        >
                                            ₱{{ getPlayerTotalFee(player) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Empty State (shared) -->
                        <div v-if="sessionPlayers.length === 0" class="p-8">
                            <div
                                class="rounded-3xl border-2 border-dashed border-slate-200 bg-white/60 p-10 text-center dark:border-[#1a1a1a] dark:bg-[#0a0a0a]/30"
                            >
                                <User class="mx-auto mb-4 h-14 w-14 text-slate-300 dark:text-slate-700" />
                                <h3 class="text-xl font-black tracking-tight text-slate-700 dark:text-slate-200">No Players Yet</h3>
                                <p class="mb-6 mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ canAddRosterPlayers ? 'Add a player from the venue list or type a temporary name to start generating matches.' : 'No registered booking players were loaded for this session yet.' }}
                                </p>
                                <button
                                    v-if="canAddRosterPlayers"
                                    :disabled="selectedCourt === null"
                                    @click="openAddPlayerModal"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-white transition-all hover:bg-blue-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-55 dark:bg-green-600 dark:hover:bg-green-500"
                                >
                                    <UserPlus class="h-3.5 w-3.5" />
                                    Add Player
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: HISTORY MATCH (3 Cols) -->
                <div
                    class="glass-card flex flex-col overflow-hidden rounded-2xl sm:rounded-3xl md:group-data-[state=collapsed]/sidebar-wrapper:xl:col-span-2 xl:col-span-3 xl:h-full"
                >
                    <div class="border-b border-slate-200 bg-white/70 p-3 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/80 sm:p-6">
                        <h2 class="flex items-center text-lg font-black uppercase tracking-widest text-slate-900 dark:text-white sm:text-2xl">
                            History Match
                        </h2>
                    </div>

                    <div
                        class="custom-scrollbar max-h-[180px] flex-1 overflow-y-auto bg-slate-50/40 p-3 dark:bg-[#0a0a0a]/50 sm:max-h-[260px] sm:p-5 md:max-h-[380px] xl:max-h-none"
                    >
                        <div
                            v-if="localMatches.length === 0"
                            class="flex h-full flex-col items-center justify-center py-6 text-center opacity-50 sm:py-12"
                        >
                            <History class="mb-2 h-10 w-10 text-slate-600 sm:mb-4 sm:h-12 sm:w-12" />
                            <h3 class="text-sm font-bold text-slate-400 sm:text-base">No History Today</h3>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="match in localMatches"
                                :key="match.id"
                                @click="openEditScoringModal(match)"
                                class="group relative cursor-pointer rounded-2xl border border-l-4 border-slate-200 bg-white p-3 shadow-sm transition-all duration-150 hover:border-slate-300 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:hover:border-[#2a2a2a]"
                                :class="match.player_1_score > match.player_2_score ? 'border-l-blue-500 dark:border-l-green-500' : 'border-l-rose-500'"
                            >
                                <div class="space-y-1.5">
                                    <div class="absolute right-2 top-2 opacity-0 transition-opacity group-hover:opacity-60">
                                        <Pencil class="h-3 w-3 text-slate-500 dark:text-slate-400" />
                                    </div>
                                    <div class="mb-1 flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-slate-400">
                                        <span>
                                            <template v-if="match.booking">
                                                {{ match.booking_time ?? 'Session' }}
                                                <span v-if="match.booking_lead"> - {{ match.booking_lead }}</span>
                                            </template>
                                            <template v-else>Walk-in</template>
                                        </span>
                                        <span v-if="match.is_walkin" class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                            {{ match.walkin_fee_type === 'without_ball' ? 'Without ball' : 'With ball' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-[11px] font-bold" :class="match.player_1_score > match.player_2_score ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">
                                            {{ getTeamNames(match, 1) }}
                                        </p>
                                        <span class="text-sm font-black" :class="match.player_1_score > match.player_2_score ? 'text-blue-500 dark:text-green-500' : 'text-slate-400'">
                                            {{ match.player_1_score }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-[11px] font-bold" :class="match.player_2_score > match.player_1_score ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">
                                            {{ getTeamNames(match, 2) }}
                                        </p>
                                        <span class="text-sm font-black" :class="match.player_2_score > match.player_1_score ? 'text-rose-500' : 'text-slate-400'">
                                            {{ match.player_2_score }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Session Buttons -->
                    <div
                        class="mt-auto flex gap-2 border-t border-slate-200 bg-white/70 p-3 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/80 sm:gap-3 sm:p-6"
                    >
                        <button
                            v-if="!isPlayerScoringViewOnly"
                            @click="resetSession"
                            class="h-10 flex-1 whitespace-nowrap rounded-xl bg-slate-200 text-[11px] font-black uppercase tracking-widest text-slate-700 shadow-sm transition-all hover:bg-slate-300 active:scale-95 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                        >
                            Reset
                        </button>
                        <button
                            v-if="!isPlayerScoringViewOnly"
                            @click="saveSession"
                            :disabled="!canSaveSession || (isPlayerScoringMode && !isTimeAlmostExpired && !isTimeFullyExpired)"
                            class="h-10 flex-1 whitespace-nowrap rounded-xl text-[11px] font-black uppercase tracking-widest transition-all"
                            :class="
                                (canSaveSession && (!isPlayerScoringMode || isTimeAlmostExpired || isTimeFullyExpired))
                                    ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20 hover:bg-blue-700 active:scale-95 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500'
                                    : 'cursor-not-allowed bg-emerald-200 text-emerald-400 opacity-60 dark:bg-emerald-900/30 dark:text-emerald-700'
                            "
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- EDIT PLAYER MODAL -->
        <div v-if="editingPlayer" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/80 p-4 transition-opacity sm:p-6">
            <div
                class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl duration-200 animate-in zoom-in-95 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
            >
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 p-5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                    <h2 class="flex items-center text-lg font-black uppercase tracking-widest text-slate-900 dark:text-white">
                        <Pencil class="mr-2 h-4 w-4 text-blue-500" />
                        Edit Player
                    </h2>
                    <button
                        @click="editingPlayer = null"
                        class="rounded-lg bg-slate-200 p-1.5 text-slate-500 transition-colors hover:bg-slate-300 hover:text-slate-900 dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a] dark:hover:text-white"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <form @submit.prevent="submitEditPlayer" class="space-y-4 p-6">
                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase tracking-widest text-slate-500">Username</label>
                        <input
                            v-model="editPlayerForm.name"
                            type="text"
                            required
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-900 outline-none ring-blue-500 focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:focus:ring-green-500"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase tracking-widest text-slate-500"
                            >Full Name <span class="font-normal text-slate-400">(Optional)</span></label
                        >
                        <input
                            v-model="editPlayerForm.full_name"
                            type="text"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-900 outline-none ring-blue-500 focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:focus:ring-green-500"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase tracking-widest text-slate-500"
                            >Email <span class="font-normal text-slate-400">(Optional)</span></label
                        >
                        <input
                            v-model="editPlayerForm.email"
                            type="email"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-900 outline-none ring-blue-500 focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:focus:ring-green-500"
                        />
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            @click="editingPlayer = null"
                            class="w-1/2 rounded-xl bg-slate-100 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editPlayerForm.processing"
                            class="w-1/2 rounded-xl bg-blue-600 py-2.5 text-[10px] font-black uppercase tracking-widest text-white shadow-md shadow-blue-500/20 transition-all hover:bg-blue-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SCORING MODAL -->
        <div
            v-if="showScoringModal && scoringMatchDetails"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-3 transition-opacity sm:p-6"
        >
            <div
                class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 shadow-2xl duration-200 animate-in zoom-in-95 dark:border-[#1a1a1a]"
            >
                <!-- Header -->
                <div
                    class="relative flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 pb-4 pt-4 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] sm:px-6 sm:pb-5 sm:pt-6"
                >
                    <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-200 ring-1 ring-slate-300 dark:bg-green-900/50 dark:ring-green-800/50 sm:h-10 sm:w-10 sm:rounded-2xl"
                        >
                            <CheckCircle class="h-4 w-4 text-emerald-500 dark:text-green-400 sm:h-5 sm:w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 sm:text-[10px]">Scoring</p>
                            <h2 class="text-base font-black leading-none tracking-tight text-slate-900 dark:text-[#EDEDEC] sm:text-lg">
                                {{ editingMatchId ? 'Edit Score' : 'Record Score' }}
                            </h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div
                            v-if="matchForm.is_walkin"
                            class="flex items-center overflow-hidden rounded-full border border-slate-300 bg-slate-100 p-0.5 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]"
                        >
                            <button
                                type="button"
                                @click="matchForm.walkin_fee_type = 'with_ball'"
                                class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest transition-all"
                                :class="matchForm.walkin_fee_type === 'with_ball' ? 'bg-emerald-600 text-white' : 'text-slate-500 dark:text-slate-400'"
                            >
                                With ball
                            </button>
                            <button
                                type="button"
                                @click="matchForm.walkin_fee_type = 'without_ball'"
                                class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest transition-all"
                                :class="matchForm.walkin_fee_type === 'without_ball' ? 'bg-rose-600 text-white' : 'text-slate-500 dark:text-slate-400'"
                            >
                                Without ball
                            </button>
                        </div>
                        <div
                            v-if="walkinFeePreview"
                            class="flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black text-amber-700 dark:bg-amber-900/20 dark:text-amber-300"
                        >
                            <DollarSign class="h-3 w-3" />
                            ₱{{ walkinFeePreview.total }}
                        </div>
                        <button
                            @click="closeScoringModal"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-slate-200 text-slate-500 transition-all hover:bg-slate-300 hover:text-slate-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a] dark:hover:text-[#EDEDEC]"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Score Body -->
                <form @submit.prevent="submitScore" class="bg-white dark:bg-[#161615]">
                    <!-- Teams Section -->
                    <div class="grid grid-cols-2 divide-x divide-slate-200 dark:divide-[#1a1a1a]">
                        <!-- Team A -->
                        <div class="flex flex-col items-center gap-3 bg-blue-50 px-2 py-5 dark:bg-[#0f0f0f] sm:gap-4 sm:px-6 sm:py-7">
                            <div class="w-full space-y-1 px-1 text-center">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-blue-600 dark:border-green-600/20 dark:bg-green-900/20 dark:text-green-400 sm:gap-1.5 sm:px-3 sm:py-1 sm:text-[10px]"
                                >
                                    <span class="h-1 w-1 shrink-0 rounded-full bg-blue-500 dark:bg-green-400 sm:h-1.5 sm:w-1.5"></span>
                                    Team A
                                </span>
                                <p class="truncate pt-0.5 text-xs font-bold leading-snug text-slate-800 dark:text-[#EDEDEC] sm:text-sm">
                                    {{ scoringMatchDetails.p3 ? `${scoringMatchDetails.p1.name} & ${scoringMatchDetails.p3.name}` : scoringMatchDetails.p1.name }}
                                </p>
                            </div>

                            <!-- Score Counter A -->
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <button
                                    type="button"
                                    @click="setScore(1, -1)"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-slate-200 text-lg font-black text-slate-600 transition-all hover:bg-blue-100 hover:text-blue-600 active:scale-90 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a] dark:hover:text-green-400 sm:h-10 sm:w-10 sm:rounded-xl sm:text-xl"
                                >
                                    −
                                </button>
                                <div class="relative">
                                    <input
                                        v-model.number="matchForm.player_1_score"
                                        type="number"
                                        min="0"
                                        max="99"
                                        required
                                        @input="clampScore(1)"
                                        class="w-14 border-0 bg-transparent py-1 text-center text-4xl font-black tabular-nums text-slate-900 outline-none [appearance:textfield] focus:ring-0 dark:text-[#EDEDEC] sm:w-24 sm:text-6xl [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                        placeholder="00"
                                    />
                                    <div
                                        class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-gradient-to-r from-transparent via-blue-400/40 to-transparent dark:via-green-500/40"
                                    ></div>
                                </div>
                                <button
                                    type="button"
                                    @click="setScore(1, 1)"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-slate-200 text-lg font-black text-slate-600 transition-all hover:bg-blue-100 hover:text-blue-600 active:scale-90 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a] dark:hover:text-green-400 sm:h-10 sm:w-10 sm:rounded-xl sm:text-xl"
                                >
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- Team B -->
                        <div class="flex flex-col items-center gap-3 bg-rose-50 px-2 py-5 dark:bg-[#0f0f0f] sm:gap-4 sm:px-6 sm:py-7">
                            <div class="w-full space-y-1 px-1 text-center">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-rose-600 dark:border-rose-600/20 dark:bg-rose-900/20 dark:text-rose-400 sm:gap-1.5 sm:px-3 sm:py-1 sm:text-[10px]"
                                >
                                    <span class="h-1 w-1 shrink-0 rounded-full bg-rose-500 dark:bg-rose-400 sm:h-1.5 sm:w-1.5"></span>
                                    Team B
                                </span>
                                <p class="truncate pt-0.5 text-xs font-bold leading-snug text-slate-800 dark:text-[#EDEDEC] sm:text-sm">
                                    {{ scoringMatchDetails.p4 ? `${scoringMatchDetails.p2.name} & ${scoringMatchDetails.p4.name}` : scoringMatchDetails.p2.name }}
                                </p>
                            </div>

                            <!-- Score Counter B -->
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <button
                                    type="button"
                                    @click="setScore(2, -1)"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-slate-200 text-lg font-black text-slate-600 transition-all hover:bg-rose-100 hover:text-rose-600 active:scale-90 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a] dark:hover:text-rose-400 sm:h-10 sm:w-10 sm:rounded-xl sm:text-xl"
                                >
                                    −
                                </button>
                                <div class="relative">
                                    <input
                                        v-model.number="matchForm.player_2_score"
                                        type="number"
                                        min="0"
                                        max="99"
                                        required
                                        @input="clampScore(2)"
                                        class="w-14 border-0 bg-transparent py-1 text-center text-4xl font-black tabular-nums text-slate-900 outline-none [appearance:textfield] focus:ring-0 dark:text-[#EDEDEC] sm:w-24 sm:text-6xl [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                        placeholder="00"
                                    />
                                    <div
                                        class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-gradient-to-r from-transparent via-rose-400/40 to-transparent"
                                    ></div>
                                </div>
                                <button
                                    type="button"
                                    @click="setScore(2, 1)"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-slate-200 text-lg font-black text-slate-600 transition-all hover:bg-rose-100 hover:text-rose-600 active:scale-90 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a] dark:hover:text-rose-400 sm:h-10 sm:w-10 sm:rounded-xl sm:text-xl"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- VS divider pill -->
                    <div
                        class="relative flex items-center justify-center border-y border-slate-200 bg-white py-2 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                    >
                        <span
                            class="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[9px] font-black tracking-widest text-slate-400 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400"
                            >VS</span
                        >
                    </div>

                    <!-- Actions -->
                    <div
                        class="flex gap-2 border-t border-slate-200 bg-slate-50 px-3 py-4 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] sm:gap-3 sm:px-6 sm:py-5"
                    >
                        <button
                            type="button"
                            @click="closeScoringModal"
                            class="flex-none rounded-xl border border-slate-300 bg-slate-200 px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-slate-600 transition-all hover:bg-slate-300 hover:text-slate-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a] dark:hover:text-[#EDEDEC] sm:px-6 sm:py-3 sm:text-[11px]"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="matchForm.processing"
                            class="flex-1 rounded-xl bg-blue-600 py-2.5 text-[10px] font-black uppercase tracking-widest text-white shadow-sm shadow-blue-500/20 transition-all hover:bg-blue-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500 sm:py-3 sm:text-[11px]"
                        >
                            <span v-if="!matchForm.processing">Submit Result</span>
                            <span v-else class="flex items-center justify-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                </svg>
                                Saving...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SYSTEM CONFIRM MODAL -->
        <div
            v-if="showConfirmModal"
            class="fixed inset-0 z-[110] flex items-center justify-center bg-white/20 p-4 transition-opacity dark:bg-[#0a0a0a]/40 sm:p-6"
        >
            <div
                class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white/95 shadow-lg dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/90"
            >
                <div class="border-b border-slate-200 p-6 dark:border-[#1a1a1a]">
                    <h3 class="text-base font-black uppercase tracking-widest text-slate-900 dark:text-white">Confirm Action</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ confirmMessage }}</p>
                </div>
                <div class="flex gap-3 p-5">
                    <button
                        @click="cancelSystemConfirm"
                        class="flex-1 rounded-xl bg-slate-100 py-2.5 text-xs font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                    >
                        Cancel
                    </button>
                    <button
                        @click="runSystemConfirm"
                        class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase tracking-widest text-white transition-all hover:bg-indigo-700 dark:bg-green-600 dark:hover:bg-green-500"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>

        <!-- REPLACE PLAYER MODAL -->
        <div
            v-if="showReplacePlayerModal"
            class="fixed inset-0 z-[115] flex items-center justify-center bg-white/20 p-4 transition-opacity dark:bg-[#0a0a0a]/40 sm:p-6"
        >
            <div
                class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white/95 shadow-lg dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/90"
            >
                <div class="border-b border-slate-200 p-6 dark:border-[#1a1a1a]">
                    <h3 class="text-base font-black uppercase tracking-widest text-slate-900 dark:text-white">Replace Player</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Swap only this slot. Round-robin queue order stays unchanged.</p>
                </div>
                <div class="space-y-4 p-5">
                    <select
                        v-model="replacementPlayerId"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                    >
                        <option v-for="player in replacementOptions" :key="player.id" :value="player.id">
                            {{ player.name }}
                        </option>
                    </select>
                    <div class="flex gap-3">
                        <button
                            @click="closeReplacePlayerModal"
                            class="flex-1 rounded-xl bg-slate-100 py-2.5 text-xs font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmReplacePlayer"
                            :disabled="!replacementPlayerId"
                            class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase tracking-widest text-white transition-all hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Replace
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- WAITING QUEUE MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showQueueModal"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/40 p-4"
                    @click.self="showQueueModal = false"
                >
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-4"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 translate-y-4"
                    >
                        <div
                            v-if="showQueueModal"
                            class="mx-4 w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                        >
                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-[#1a1a1a]">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 dark:bg-green-900/20">
                                        <ListOrdered class="h-4 w-4 text-indigo-500 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 dark:text-white">Waiting Queue</h3>
                                        <p class="text-[11px] text-slate-500">{{ queueModalEntries.length }} / {{ MAX_QUEUE }} matches queued</p>
                                    </div>
                                </div>
                                <button
                                    @click="showQueueModal = false"
                                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-[#1a1a1a]"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <!-- Queue List -->
                            <div class="custom-scrollbar max-h-[360px] space-y-2 overflow-y-auto p-4">
                                <div v-if="queueModalEntries.length === 0" class="py-10 text-center">
                                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-[#1a1a1a]">
                                        <Shuffle class="h-5 w-5 text-slate-400" />
                                    </div>
                                    <p class="text-xs font-bold text-slate-500">No matches queued yet</p>
                                    <p class="mt-1 text-[10px] text-slate-400">Add a random match to start filling the queue.</p>
                                </div>
                                <div
                                    v-for="entry in queueModalEntries"
                                    :key="entry.index"
                                    draggable="true"
                                    @dragstart="handleDragStart(entry.index)"
                                    @dragover="handleDragOver"
                                    @drop="handleDrop(entry.index)"
                                    class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3 transition-all hover:border-indigo-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]/30 dark:hover:border-green-700 cursor-move"
                                >
                                    <!-- Drag grip handle -->
                                    <div class="text-slate-300 dark:text-slate-700 cursor-move group-hover:text-slate-400">
                                        <GripVertical class="h-4 w-4" />
                                    </div>
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[11px] font-black"
                                        :class="
                                            entry.index === 0
                                                ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : 'bg-slate-200 text-slate-600 dark:bg-[#1a1a1a] dark:text-slate-300'
                                        "
                                    >
                                        {{ entry.index === 0 ? 'NX' : entry.index + 1 }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            v-if="entry.index === 0"
                                            class="mb-0.5 text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400"
                                        >
                                            Next Game
                                        </p>
                                        <div class="flex flex-wrap items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-100">
                                            <span class="text-blue-600 dark:text-green-400">{{ entry.p1?.name ?? '?' }}</span>
                                            <span v-if="entry.isDoubles" class="text-[10px] text-slate-400">&amp;</span>
                                            <span v-if="entry.isDoubles" class="text-blue-600 dark:text-green-400">{{ entry.p3?.name ?? '?' }}</span>
                                            <span class="text-[10px] text-slate-400">vs</span>
                                            <span class="text-rose-600 dark:text-rose-400">{{ entry.p2?.name ?? '?' }}</span>
                                            <span v-if="entry.isDoubles" class="text-[10px] text-slate-400">&amp;</span>
                                            <span v-if="entry.isDoubles" class="text-rose-600 dark:text-rose-400">{{ entry.p4?.name ?? '?' }}</span>
                                        </div>
                                    </div>
                                    <!-- Reorder controls (arrows) -->
                                    <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            v-if="entry.index > 0"
                                            @click.stop="moveQueueItem(entry.index, 'up')"
                                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600 dark:hover:bg-[#1a1a1a]"
                                            title="Move Up"
                                        >
                                            <ArrowUp class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            v-if="entry.index < queueModalEntries.length - 1"
                                            @click.stop="moveQueueItem(entry.index, 'down')"
                                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600 dark:hover:bg-[#1a1a1a]"
                                            title="Move Down"
                                        >
                                            <ArrowDown class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <button
                                        @click="removeFromQueue(entry.index)"
                                        class="shrink-0 rounded-lg p-1.5 text-slate-400 opacity-0 transition-colors hover:bg-rose-50 hover:text-rose-500 group-hover:opacity-100 dark:hover:bg-rose-900/20"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="flex gap-2 border-t border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]/40">
                                <button
                                    @click="openCustomMatchModal"
                                    :disabled="queueModalEntries.length >= MAX_QUEUE || isQueueLocked"
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-2.5 text-[11px] font-black uppercase tracking-widest text-white shadow-sm shadow-indigo-500/20 transition-all hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500"
                                >
                                    <Plus class="h-3.5 w-3.5" /> Add Match
                                </button>
                                <button
                                    @click="fillQueueToMax"
                                    :disabled="queueModalEntries.length >= MAX_QUEUE || isQueueLocked"
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2.5 text-[11px] font-black uppercase tracking-widest text-white transition-all hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                                >
                                    <Shuffle class="h-3.5 w-3.5" /> +5 More
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- CUSTOM MATCH BUILDER MODAL -->
        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="showCustomMatchModal"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/30 p-4"
                    @click.self="showCustomMatchModal = false"
                >
                    <Transition name="scale-fade">
                        <div
                            v-if="showCustomMatchModal"
                            class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                        >
                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-[#1a1a1a]">
                                <h3 class="text-sm font-black uppercase tracking-widest text-slate-900 dark:text-white">Custom Match</h3>
                                <button
                                    @click="showCustomMatchModal = false"
                                    class="rounded-lg p-1.5 transition-colors hover:bg-slate-100 dark:hover:bg-[#1a1a1a]"
                                >
                                    <X class="h-4 w-4 text-slate-500" />
                                </button>
                            </div>

                            <!-- Team A -->
                            <div class="space-y-2 p-4">
                                <p class="text-[10px] font-black uppercase tracking-widest text-blue-600 dark:text-green-400">Team A</p>
                                <select
                                    v-model="customSlots.teamA1"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-white"
                                >
                                    <option :value="null">— Select Player 1 —</option>
                                    <option
                                        v-for="p in customAvailablePlayers.concat(
                                            customSlots.teamA1 ? [sessionPlayers.find((x) => x.id === customSlots.teamA1)].filter(Boolean) : [],
                                        )"
                                        :key="'a1-' + p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                                <select
                                    v-model="customSlots.teamA2"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-white"
                                >
                                    <option :value="null">— Select Player 2 —</option>
                                    <option
                                        v-for="p in customAvailablePlayers.concat(
                                            customSlots.teamA2 ? [sessionPlayers.find((x) => x.id === customSlots.teamA2)].filter(Boolean) : [],
                                        )"
                                        :key="'a2-' + p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- VS Divider -->
                            <div class="flex items-center justify-center py-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">vs</span>
                            </div>

                            <!-- Team B -->
                            <div class="space-y-2 p-4 pt-0">
                                <p class="text-[10px] font-black uppercase tracking-widest text-rose-600 dark:text-rose-400">Team B</p>
                                <select
                                    v-model="customSlots.teamB1"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-white"
                                >
                                    <option :value="null">— Select Player 1 —</option>
                                    <option
                                        v-for="p in customAvailablePlayers.concat(
                                            customSlots.teamB1 ? [sessionPlayers.find((x) => x.id === customSlots.teamB1)].filter(Boolean) : [],
                                        )"
                                        :key="'b1-' + p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                                <select
                                    v-model="customSlots.teamB2"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-white"
                                >
                                    <option :value="null">— Select Player 2 —</option>
                                    <option
                                        v-for="p in customAvailablePlayers.concat(
                                            customSlots.teamB2 ? [sessionPlayers.find((x) => x.id === customSlots.teamB2)].filter(Boolean) : [],
                                        )"
                                        :key="'b2-' + p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Footer -->
                            <div class="flex gap-2 border-t border-slate-200 p-4 dark:border-[#1a1a1a]">
                                <button
                                    @click="showCustomMatchModal = false"
                                    class="flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-[11px] font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-50 dark:border-[#2a2a2a] dark:text-slate-300 dark:hover:bg-[#1a1a1a]"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="confirmCustomMatch"
                                    :disabled="!customCanConfirm"
                                    class="flex-1 rounded-xl bg-emerald-600 px-3 py-2.5 text-[11px] font-black uppercase tracking-widest text-white shadow-sm shadow-emerald-500/20 transition-all hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Add to Queue
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- REPLACE NEXT GAME PLAYER MODAL -->
        <div
            v-if="showReplaceNextModal"
            class="fixed inset-0 z-[115] flex items-center justify-center bg-white/20 p-4 transition-opacity dark:bg-[#0a0a0a]/40 sm:p-6"
        >
            <div
                class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white/95 shadow-lg dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/90"
            >
                <div class="border-b border-slate-200 p-6 dark:border-[#1a1a1a]">
                    <h3 class="text-base font-black uppercase tracking-widest text-slate-900 dark:text-white">Replace Next Game Player</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Choose a replacement for the upcoming match.</p>
                </div>
                <div class="space-y-4 p-5">
                    <select
                        v-model="replacementNextPlayerId"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                    >
                        <option v-for="player in replacementNextOptions" :key="player.id" :value="player.id">
                            {{ player.name }}
                        </option>
                    </select>
                    <div class="flex gap-3">
                        <button
                            @click="closeReplaceNextModal"
                            class="flex-1 rounded-xl bg-slate-100 py-2.5 text-xs font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmReplaceNextPlayer"
                            :disabled="!replacementNextPlayerId"
                            class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase tracking-widest text-white transition-all hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Replace
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD PLAYER PICKER MODAL -->
        <div
            v-if="showAddPlayerModal"
            class="fixed inset-0 z-[116] flex items-center justify-center bg-white/20 p-4 transition-opacity dark:bg-[#0a0a0a]/40 sm:p-6"
        >
            <div
                class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white/95 shadow-lg dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/90"
            >
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-5 dark:border-[#1a1a1a]">
                    <h3 class="text-base font-black uppercase tracking-widest text-slate-900 dark:text-white">Select Player</h3>
                    <button
                        @click="closeAddPlayerModal"
                        class="rounded-lg bg-slate-200 p-1.5 text-slate-500 transition-colors hover:bg-slate-300 dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a]"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="border-b border-slate-200 p-4 dark:border-[#1a1a1a]">
                    <input
                        v-model="addPlayerSearch"
                        type="text"
                        placeholder="Search players or type a temporary name..."
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500"
                    />
                </div>
                <div class="custom-scrollbar max-h-[380px] space-y-2 overflow-y-auto p-3">
                    <div
                        v-if="addPlayerSearch.trim() && !hasExactRegisteredMatch"
                        class="flex items-start gap-3 rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-3 text-left dark:bg-amber-900/20"
                    >
                        <button
                            @click="addTemporaryPlayerFromModal"
                            class="shrink-0 rounded-lg bg-amber-500 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-amber-600"
                        >
                            Add Temporary Player
                        </button>
                        <div class="min-w-0">
                            <span class="block font-bold text-slate-900 dark:text-white">{{ addPlayerSearch.trim() }}</span>
                            <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                This player is not registered yet. Please register them soon so their stats and activity can be fully tracked in the system.
                            </p>
                        </div>
                    </div>
                    <button
                        v-for="player in modalFilteredPlayers"
                        :key="player.id"
                        @click="toggleModalPlayerSelection(player.id)"
                        class="flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left transition-colors"
                        :class="
                            rosterPlayerIds.has(player.id)
                                ? 'cursor-not-allowed border-amber-300 bg-amber-50/80 opacity-90 dark:bg-amber-900/20'
                                : selectedModalPlayerIds.has(player.id)
                                  ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/25'
                                  : 'border-slate-200 bg-white hover:bg-indigo-50 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:hover:bg-green-900/15'
                        "
                    >
                        <span class="font-bold text-slate-900 dark:text-white">{{ player.name }}</span>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="rosterPlayerIds.has(player.id) && canRemoveSessionPlayer(player)"
                                @click.stop="removeFromSession(player.id)"
                                class="text-amber-400 transition hover:text-red-500 dark:hover:text-red-400"
                                title="Remove from session"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                            <button
                                v-else-if="selectedModalPlayerIds.has(player.id)"
                                @click.stop="toggleModalPlayerSelection(player.id)"
                                class="text-indigo-400 transition hover:text-red-500 dark:text-green-300 dark:hover:text-red-400"
                                title="Deselect"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                            <span
                                class="text-[10px] font-black uppercase tracking-widest"
                                :class="
                                    rosterPlayerIds.has(player.id)
                                        ? 'text-amber-600 dark:text-amber-300'
                                        : selectedModalPlayerIds.has(player.id)
                                          ? 'text-indigo-600 dark:text-green-300'
                                          : 'text-slate-400'
                                "
                            >
                                {{ rosterPlayerIds.has(player.id) ? 'In Roster' : selectedModalPlayerIds.has(player.id) ? 'Selected' : 'Select' }}
                            </span>
                        </div>
                    </button>
                    <p v-if="modalFilteredPlayers.length === 0" class="py-8 text-center text-sm text-slate-500">No registered players found yet.</p>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 p-4 dark:border-[#1a1a1a]">
                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400">
                        {{ selectedModalPlayerIds.size }} selected
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            @click="closeAddPlayerModal"
                            class="rounded-lg bg-slate-100 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                        >
                            Cancel
                        </button>
                        <button
                            @click="applySelectedRegisteredPlayers"
                            :disabled="selectedModalPlayerIds.size === 0 || applyingSelectedPlayers"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            {{ applyingSelectedPlayers ? 'Adding...' : 'Add Selected' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SETUP FIXED PLAYING GROUPS MODAL -->
        <div
            v-if="showGroupSetupModal"
            class="fixed inset-0 z-[116] flex items-center justify-center bg-white/20 p-4 transition-opacity dark:bg-[#0a0a0a]/40 sm:p-6"
        >
            <div
                class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white/95 shadow-lg dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/90"
            >
                <!-- Header -->
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-5 dark:border-[#1a1a1a]">
                    <div>
                        <h3 class="text-base font-black uppercase tracking-widest text-slate-900 dark:text-white">Setup Fixed Groups</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Link active players into Teammate Pairs (size 2) or Match Quads (size 4)</p>
                    </div>
                    <button
                        @click="closeGroupSetupModal"
                        class="rounded-lg bg-slate-200 p-1.5 text-slate-500 transition-colors hover:bg-slate-300 dark:bg-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#2a2a2a]"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Content / Group Cards -->
                <div class="custom-scrollbar max-h-[380px] space-y-4 overflow-y-auto p-5">
                    <div
                        v-for="(group, idx) in playerGroups"
                        :key="`player-group-${idx}`"
                        class="relative rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]/30"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                            <span class="text-xs font-black uppercase tracking-widest text-indigo-500 dark:text-green-400">
                                Group {{ idx + 1 }}
                            </span>
                            <div class="flex items-center gap-3">
                                <select
                                    v-model="group.type"
                                    @change="handleGroupTypeChange(idx)"
                                    class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-bold text-slate-700 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                                >
                                    <option value="none">Disabled</option>
                                    <option value="pair">Teammate Pair (2 Players)</option>
                                    <option value="quad">Match Quad (4 Players)</option>
                                </select>
                                <button
                                    @click="removeGroup(idx)"
                                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-red-500 transition-colors dark:hover:bg-slate-900 dark:hover:text-red-400"
                                    title="Delete Group"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Dropdowns depending on group type -->
                        <div v-if="group.type !== 'none'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div v-for="pIdx in (group.type === 'pair' ? 2 : 4)" :key="`slot-${idx}-${pIdx}`">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">
                                    Player {{ pIdx }}
                                </label>
                                <select
                                    v-model="group.playerIds[pIdx - 1]"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                                >
                                    <option :value="null">— Select —</option>
                                    <option
                                        v-for="player in getAvailableGroupPlayers(idx, pIdx - 1)"
                                        :key="`group-${idx}-p-${player.id}`"
                                        :value="player.id"
                                    >
                                        {{ player.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Plus Button to Add Group -->
                    <button
                        v-if="playerGroups.length < 4"
                        @click="addGroup"
                        type="button"
                        class="flex w-full items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-slate-200 p-3 text-xs font-bold text-slate-500 transition-colors hover:border-slate-300 hover:text-slate-700 dark:border-slate-800 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:text-slate-300"
                    >
                        <Plus class="h-4 w-4" /> Add Group
                    </button>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 p-4 dark:border-[#1a1a1a]">
                    <button
                        @click="clearGroupSetup"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-50 dark:border-[#2a2a2a] dark:text-slate-300 dark:hover:bg-[#1a1a1a]"
                    >
                        Clear All
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            @click="closeGroupSetupModal"
                            class="rounded-lg bg-slate-100 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveGroupSetup"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:bg-indigo-700 dark:bg-green-600 dark:hover:bg-green-500"
                        >
                            Confirm Groups
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.glass-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(24px);
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 1.75rem;
    box-shadow: 0 20px 35px -25px rgba(15, 23, 42, 0.35);
    position: relative;
}

.glass-card::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: inherit;
    background: linear-gradient(to bottom right, rgba(34, 197, 94, 0.03), rgba(16, 185, 129, 0.02));
}

.dark .glass-card {
    background: rgba(15, 15, 15, 0.85);
    border-color: rgba(42, 42, 42, 0.5);
    box-shadow: 0 20px 35px -25px rgba(0, 0, 0, 0.6);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(34, 197, 94, 0.25);
    border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(34, 197, 94, 0.45);
}

.table-surface {
    background-image: linear-gradient(to bottom, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
    background-size: 100% 56px;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.match-slot {
    width: 100%;
    border: 1px solid transparent;
    border-radius: 0.75rem;
    padding: 0.5rem 0.6rem;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.7rem;
    font-weight: 900;
    color: #1e293b;
    background: linear-gradient(90deg, #f1f5f9, #e2e8f0);
    transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        border-color 180ms ease;
    line-height: 1.2;
}

@media (prefers-color-scheme: dark) {
    .match-slot {
        color: rgba(241, 245, 249, 0.95);
        background: linear-gradient(90deg, rgba(10, 10, 10, 0.98), rgba(26, 26, 26, 0.98));
    }
}

.dark .match-slot {
    color: rgba(241, 245, 249, 0.95);
    background: linear-gradient(90deg, rgba(10, 10, 10, 0.98), rgba(26, 26, 26, 0.98));
}

@media (min-width: 1280px) {
    .match-slot {
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        border-radius: 1rem;
    }
}

.match-slot:hover {
    transform: translateY(-1px);
}

.match-slot-a {
    border-color: rgba(59, 130, 246, 0.5);
    box-shadow: 0 8px 14px -12px rgba(59, 130, 246, 0.4);
}

.dark .match-slot-a {
    border-color: rgba(34, 197, 94, 0.5);
    box-shadow: 0 8px 14px -12px rgba(34, 197, 94, 0.5);
}

.match-slot-b {
    border-color: rgba(244, 63, 94, 0.5);
    box-shadow: 0 8px 14px -12px rgba(244, 63, 94, 0.4);
}

.dark .match-slot-b {
    border-color: rgba(251, 113, 133, 0.5);
    box-shadow: 0 8px 14px -12px rgba(244, 63, 94, 0.75);
}

.score-card {
    width: 100%;
    max-width: 172px;
    background: linear-gradient(180deg, rgba(15, 15, 15, 0.9), rgba(10, 10, 10, 0.98));
    border: 1px solid rgba(42, 42, 42, 0.4);
    border-radius: 1.1rem;
    display: grid;
    grid-template-columns: 42px 1fr 42px;
    align-items: center;
    overflow: hidden;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.05),
        0 12px 24px -18px rgba(0, 0, 0, 0.6);
}

.score-card-a {
    border-color: rgba(59, 130, 246, 0.45);
}

.score-card-b {
    border-color: rgba(244, 63, 94, 0.45);
}

.score-step-btn {
    height: 100%;
    border: 0;
    background: rgba(26, 26, 26, 0.75);
    color: rgba(226, 232, 240, 0.9);
    font-size: 1.2rem;
    font-weight: 900;
    cursor: pointer;
    transition:
        background-color 160ms ease,
        color 160ms ease;
}

.score-step-btn:hover {
    background: rgba(42, 42, 42, 0.75);
    color: #ffffff;
}

.score-input {
    width: 100%;
    background: transparent;
    border: 0;
    outline: none;
    color: #ffffff;
    text-align: center;
    font-size: 3rem;
    font-weight: 900;
    line-height: 1;
    padding: 0.75rem 0;
    letter-spacing: -0.02em;
}

.score-input::-webkit-outer-spin-button,
.score-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.score-input[type='number'] {
    -moz-appearance: textfield;
    appearance: textfield;
}

.next-game-pill {
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: rgba(248, 250, 252, 0.7);
    color: rgb(51, 65, 85);
    border-radius: 0.5rem;
    padding: 0.35rem 0.4rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
    text-transform: uppercase;
}

.dark .next-game-pill {
    border-color: rgba(100, 116, 139, 0.4);
    background: rgba(15, 15, 15, 0.55);
    color: rgb(203, 213, 225);
}
</style>























