<script setup lang="ts">
import MatchCard from '@/components/MatchCard.vue';
import { useBreakpoint } from '@/composables/useBreakpoint';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Archive,
    ArchiveRestore,
    ArrowLeftRight,
    Award,
    Calendar,
    CheckCircle,
    CheckSquare,
    ChevronDown,
    ChevronLeft,
    Clock,
    ExternalLink,
    Filter,
    Folder,
    FolderInput,
    FolderMinus,
    FolderOpen,
    FolderPlus,
    LayoutGrid,
    List,
    Pencil,
    Play,
    Plus,
    RotateCcw,
    Square,
    Swords,
    ShieldCheck,
    Trash2,
    Medal,
    Trophy,
    User,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const { isBelow } = useBreakpoint();
const isBelowMd = isBelow('md');

// View mode toggle for brackets (tree vs list). Defaults to list on mobile, tree on desktop.
const userViewMode = ref<'auto' | 'tree' | 'list'>('auto');
const bracketViewMode = computed<'tree' | 'list'>(() => {
    if (userViewMode.value === 'tree') return 'tree';
    if (userViewMode.value === 'list') return 'list';
    return isBelowMd.value ? 'list' : 'tree';
});

const props = defineProps<{
    tournaments: any[];
    archivedTournaments?: any[];
    allPlayers: any[];
    activeTournament?: any;
    tournamentDays?: any[];
    tournamentSubFolders?: any[];
    authUser?: any;
    scorers?: any[];
    courtCount?: number;
    tournamentRequests?: any[];
}>();

const page = usePage();

// --- Role checks ---
const currentUserId = computed(() => props.authUser?.id ?? null);
const isScorerOnly = computed(() => {
    const role = props.authUser?.role;
    return role === 'scorer';
});
const isPlayerRole = computed(() => props.authUser?.role === 'player');
const canManageTournaments = computed(() => {
    const role = props.authUser?.role;
    return role === 'admin' || role === 'scheduler' || role === 'scheduler_scorer';
});
const canUseBulkActions = computed(() => canManageTournaments.value || isPlayerRole.value);
const canCreateTournamentDays = computed(() => canManageTournaments.value);
const canMoveTournamentsBetweenDays = computed(() => canManageTournaments.value);
const canBulkDeleteTournaments = computed(() => canManageTournaments.value || isPlayerRole.value);
const canCreateTournamentCards = computed(() => canManageTournaments.value || (isPlayerRole.value && activeDaysList.value.length > 0));
const tournamentRequests = computed(() => props.tournamentRequests ?? []);
const pendingTournamentRequestsCount = computed(() => tournamentRequests.value.filter((item: any) => item.status === 'pending').length);

// --- Toast ---
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

// --- State ---
const showCreateModal = ref(false);
const showAddTeamModal = ref(false);
const showScoreModal = ref(false);
const showDeleteConfirm = ref(false);
const showBulkDeleteConfirm = ref(false);
const tournamentToDelete = ref<number | null>(null);
const scoringMatch = ref<any>(null);
const appAlert = ref<{ message: string; tone: 'info' | 'error' } | null>(null);
const playerSearch = ref('');
const editingTeamId = ref<number | null>(null);
const showEditMatchModal = ref(false);
const editingMatch = ref<any>(null);
const showScheduleSettingsModal = ref(false);
const showBackToSetupConfirm = ref(false);
const showTournamentRequestsModal = ref(false);
const requestStatusFilter = ref<'all' | 'pending' | 'approved' | 'completed' | 'rejected'>('all');

const pendingRequestsCount = computed(() => tournamentRequests.value.filter((item: any) => item.status === 'pending').length);
const approvedRequestsCount = computed(() => tournamentRequests.value.filter((item: any) => item.status === 'approved' && item.tournamentDay?.status !== 'finished').length);
const completedRequestsCount = computed(() => tournamentRequests.value.filter((item: any) => item.status === 'approved' && item.tournamentDay?.status === 'finished').length);
const rejectedRequestsCount = computed(() => tournamentRequests.value.filter((item: any) => item.status === 'rejected').length);

const filteredTournamentRequests = computed(() => {
    const list = tournamentRequests.value || [];
    if (requestStatusFilter.value === 'pending') {
        return list.filter((r: any) => r.status === 'pending');
    }
    if (requestStatusFilter.value === 'approved') {
        return list.filter((r: any) => r.status === 'approved' && r.tournamentDay?.status !== 'finished');
    }
    if (requestStatusFilter.value === 'completed') {
        return list.filter((r: any) => r.status === 'approved' && r.tournamentDay?.status === 'finished');
    }
    if (requestStatusFilter.value === 'rejected') {
        return list.filter((r: any) => r.status === 'rejected');
    }
    return list;
});
const showPlayerFinishDayConfirm = ref(false);
const showEditAccessRequestModal = ref(false);
const playerFinishDayId = ref<number | null>(null);
const rejectingRequestId = ref<number | null>(null);
const requestRejectForm = useForm({
    rejection_reason: '',
});
const editAccessRequestFolderName = ref('');
const editAccessRequestForm = useForm({
    venue_id: null as number | null,
    tournament_id: null as number | null,
    request_type: 'edit_access',
    name: '',
    category: 'mens',
    preferred_date: '',
    preferred_start_time: '',
    notes: '',
});

const approveTournamentRequest = (requestId: number) => {
    router.post(route('tournament-requests.approve', requestId), {}, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Tournament request approved and the player main folder is ready.');
        },
    });
};

const openRejectTournamentRequest = (requestId: number) => {
    rejectingRequestId.value = requestId;
    requestRejectForm.rejection_reason = '';
};

const submitRejectTournamentRequest = (requestId: number) => {
    requestRejectForm.post(route('tournament-requests.reject', requestId), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Tournament request rejected.');
            rejectingRequestId.value = null;
            requestRejectForm.reset();
        },
    });
};

const resolveMainFolderName = (targetTournament?: any, targetSection?: DaySection | null) => {
    if (targetSection?.kind === 'day') {
        return targetSection.day?.name ?? '';
    }

    return targetTournament?.tournament_day?.name
        ?? tournamentDayById.value.get(targetTournament?.tournament_day_id ?? -1)?.name
        ?? '';
};

const openEditAccessRequestModal = (targetTournament?: any, targetSection?: DaySection | null) => {
    const tournament = targetTournament ?? props.activeTournament;
    if (!tournament) return;
    editAccessRequestForm.venue_id = tournament.venue_id;
    editAccessRequestForm.tournament_id = tournament.id;
    editAccessRequestForm.request_type = 'edit_access';
    editAccessRequestFolderName.value = resolveMainFolderName(tournament, targetSection);
    editAccessRequestForm.name = tournament.name ?? '';
    editAccessRequestForm.category = tournament.category ?? 'mens';
    editAccessRequestForm.preferred_date = '';
    editAccessRequestForm.preferred_start_time = '';
    editAccessRequestForm.notes = '';
    editAccessRequestForm.clearErrors();
    showEditAccessRequestModal.value = true;
};

const submitEditAccessRequest = () => {
    editAccessRequestForm.post(route('tournament-requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Main folder access request submitted to the scheduler.');
            showEditAccessRequestModal.value = false;
            editAccessRequestFolderName.value = '';
            editAccessRequestForm.reset('notes');
        },
    });
};

const openPlayerFinishDayConfirm = (dayId?: number | null) => {
    playerFinishDayId.value = dayId ?? props.activeTournament?.tournament_day_id ?? null;
    if (!playerFinishDayId.value) return;
    showPlayerFinishDayConfirm.value = true;
};

const closePlayerFinishDayConfirm = () => {
    showPlayerFinishDayConfirm.value = false;
    playerFinishDayId.value = null;
};

const confirmPlayerFinishDay = () => {
    if (props.activeTournament?.id) {
        router.post(route('tournaments.finish', props.activeTournament.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('Tournament finished and archived successfully.');
                closePlayerFinishDayConfirm();
            },
        });
    } else if (playerFinishDayId.value) {
        router.post(route('tournament-days.finish-player-access', playerFinishDayId.value), {}, {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('Tournament day finished and workspace archived.');
                closePlayerFinishDayConfirm();
            },
        });
    }
};

// --- Bracket Settings (setup only) ---
const showBracketSettingsModal = ref(false);
const bracketSettingsForm = useForm({
    type: 'single_elimination' as string,
    category: 'mens' as 'mens' | 'female' | 'mix' | null,
    max_players: 8 as number,
    min_players: 2 as number,
    tournament_day_id: null as number | null,
    tournament_sub_folder_id: null as number | null,
    best_of: 1 as 1 | 3 | 5,
});

const openBracketSettingsModal = () => {
    if (!props.activeTournament) return;
    bracketSettingsForm.type = props.activeTournament.type;
    bracketSettingsForm.category = (props.activeTournament.category as 'mens' | 'female' | 'mix' | null) ?? 'mens';
    bracketSettingsForm.max_players = props.activeTournament.max_players;
    bracketSettingsForm.min_players = props.activeTournament.min_players;
    bracketSettingsForm.tournament_day_id = props.activeTournament.tournament_day_id ?? null;
    bracketSettingsForm.tournament_sub_folder_id = props.activeTournament.tournament_sub_folder_id ?? null;
    bracketSettingsForm.best_of = ((props.activeTournament.best_of as 1 | 3 | 5 | null) ?? 1);
    bracketSettingsForm.clearErrors();
    showBracketSettingsModal.value = true;
};

const closeBracketSettingsModal = () => {
    showBracketSettingsModal.value = false;
    bracketSettingsForm.clearErrors();
};

watch(
    () => bracketSettingsForm.type,
    (newType) => {
        if (newType === 'double_elimination' || newType === 'single_elimination') {
            const nearest = validDeCounts.find((v) => v >= bracketSettingsForm.max_players) || 32;
            bracketSettingsForm.max_players = nearest;
            bracketSettingsForm.min_players = newType === 'double_elimination' ? nearest : 2;
        } else if (newType === 'round_robin') {
            const rrSlots = [3, 4, 5, 8];
            const nearest = rrSlots.find((v) => v >= bracketSettingsForm.max_players) || 8;
            bracketSettingsForm.max_players = nearest;
            bracketSettingsForm.min_players = 2;
        }
    },
);

watch(
    () => bracketSettingsForm.max_players,
    (newMax) => {
        if (bracketSettingsForm.type === 'double_elimination') {
            bracketSettingsForm.min_players = newMax;
        } else {
            bracketSettingsForm.min_players = Math.min(bracketSettingsForm.min_players, newMax);
        }
    },
);

const submitBracketSettings = () => {
    if (!props.activeTournament) return;
    bracketSettingsForm.put(route('tournaments.update', props.activeTournament.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Bracket settings updated.');
            showBracketSettingsModal.value = false;
        },
        onError: (errors: any) => {
            showSystemAlert(errors.error || errors.max_players || 'Failed to update bracket settings.', 'error');
        },
    });
};

const backToSetup = () => {
    if (!props.activeTournament) return;
    showBackToSetupConfirm.value = true;
};

const cancelBackToSetup = () => {
    showBackToSetupConfirm.value = false;
};

const confirmBackToSetup = () => {
    if (!props.activeTournament) return;
    router.post(route('tournaments.back-to-setup', props.activeTournament.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Tournament reset to setup. Edit bracket type, max players, and teams.');
            showBackToSetupConfirm.value = false;
        },
        onError: (errors: any) => {
            showSystemAlert(errors.error || 'Failed to reset tournament.', 'error');
            showBackToSetupConfirm.value = false;
        },
    });
};

// --- Inline Rename (tournament name) ---
const renameForm = useForm({ name: '' });
const renamingActive = ref(false);
const renameActiveInputRef = ref<HTMLInputElement | null>(null);

const canRenameTournament = (status: string) =>
    (status === 'setup' || status === 'in_progress' || status === 'completed') && !isDayFinishedMode.value;

const startRenameActive = () => {
    if (!props.activeTournament || !canRenameTournament(props.activeTournament.status)) return;
    renameForm.name = props.activeTournament.name ?? '';
    renameForm.clearErrors();
    renamingActive.value = true;
    nextTick(() => {
        const el = renameActiveInputRef.value as any;
        const target = Array.isArray(el) ? el[0] : el;
        target?.focus();
        target?.select();
    });
};

const cancelRenameActive = () => {
    renamingActive.value = false;
    renameForm.reset();
    renameForm.clearErrors();
};

const submitRenameActive = () => {
    if (!props.activeTournament) return;
    const trimmed = renameForm.name.trim();
    if (!trimmed) return;
    renameForm.name = trimmed;
    renameForm.put(route('tournaments.update', props.activeTournament.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Tournament renamed successfully.');
            cancelRenameActive();
        },
    });
};

// --- Archive ---
const showArchiveModal = ref(false);
const archivedTournaments = computed(() => props.archivedTournaments ?? []);
const finishedDays = computed(() =>
    (props.tournamentDays ?? []).filter((d: any) => d.status === 'finished'),
);
const archivedDays = computed(() =>
    (props.tournamentDays ?? []).filter((d: any) => d.status === 'archived'),
);
const archivedTournamentCount = computed(() =>
    archivedTournaments.value.length,
);

// --- Tournament list filter ---
const STATUS_FILTER_STORAGE_KEY = 'tournament:statusFilter';
const validStatusFilterValues = ['all', 'in_progress', 'setup', 'completed'] as const;
type StatusFilterValue = (typeof validStatusFilterValues)[number];
const readPersistedStatusFilter = (): StatusFilterValue => {
    try {
        const raw = localStorage.getItem(STATUS_FILTER_STORAGE_KEY);
        if (raw && (validStatusFilterValues as readonly string[]).includes(raw)) {
            return raw as StatusFilterValue;
        }
    } catch {}
    return 'all';
};
const statusFilter = ref<StatusFilterValue>(readPersistedStatusFilter());
const showStatusFilterMenu = ref(false);
const statusFilterMenuRef = ref<HTMLElement | null>(null);

const filteredTournaments = computed(() => {
    if (statusFilter.value === 'all') return props.tournaments ?? [];
    return (props.tournaments ?? []).filter((t: any) => t.status === statusFilter.value);
});

const statusFilterOptions = [
    { value: 'all', label: 'All' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'setup', label: 'Setup' },
    { value: 'completed', label: 'Completed' },
] as const;

const activeStatusFilterLabel = computed(() => {
    return statusFilterOptions.find((o) => o.value === statusFilter.value)?.label ?? 'All';
});

const filterCounts = computed(() => {
    const list = props.tournaments ?? [];
    return {
        all: list.length,
        in_progress: list.filter((t: any) => t.status === 'in_progress').length,
        setup: list.filter((t: any) => t.status === 'setup').length,
        completed: list.filter((t: any) => t.status === 'completed').length,
    };
});

const handleStatusFilterClickOutside = (e: MouseEvent) => {
    const target = e.target as Node;
    if (statusFilterMenuRef.value && !statusFilterMenuRef.value.contains(target)) {
        showStatusFilterMenu.value = false;
    }
    if (showDayMoveMenu.value) {
        const menuEl = (e.target as HTMLElement).closest('[data-day-move-menu]');
        const buttonEl = (e.target as HTMLElement).closest('[data-day-move-button]');
        if (!menuEl && !buttonEl) {
            showDayMoveMenu.value = false;
        }
    }
    if (showSubFolderMoveMenu.value) {
        const menuEl = (e.target as HTMLElement).closest('[data-sub-folder-move-menu]');
        const buttonEl = (e.target as HTMLElement).closest('[data-sub-folder-move-button]');
        if (!menuEl && !buttonEl) {
            showSubFolderMoveMenu.value = false;
        }
    }
    if (activeScorerAssignSubId.value !== null) {
        const container = (e.target as HTMLElement).closest('[data-scorer-assign]');
        if (!container) {
            activeScorerAssignSubId.value = null;
        }
    }
};
onMounted(() => document.addEventListener('click', handleStatusFilterClickOutside));
onUnmounted(() => document.removeEventListener('click', handleStatusFilterClickOutside));

let liveInterval: ReturnType<typeof setInterval> | null = null;
const POLL_ONLY = ['tournaments', 'tournamentDays', 'tournamentSubFolders', 'activeTournament'];

const startPolling = () => {
    if (liveInterval) return;
    liveInterval = setInterval(() => {
        router.reload({ only: POLL_ONLY });
    }, 5000);
};

const stopPolling = () => {
    if (liveInterval) {
        clearInterval(liveInterval);
        liveInterval = null;
    }
};

const handlePollVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        router.reload({ only: POLL_ONLY });
        startPolling();
    } else {
        stopPolling();
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handlePollVisibilityChange);
    startPolling();
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handlePollVisibilityChange);
    stopPolling();
});

const pickStatusFilter = (value: StatusFilterValue) => {
    statusFilter.value = value;
    showStatusFilterMenu.value = false;
};

watch(statusFilter, (val) => {
    try {
        localStorage.setItem(STATUS_FILTER_STORAGE_KEY, val);
    } catch {}
});

// --- Tournament Days + Multi-Select ---
const tournamentDaysList = computed(() => props.tournamentDays ?? []);
const activeDaysList = computed(() => tournamentDaysList.value.filter((d: any) => d.status !== 'finished' && d.status !== 'archived'));
const tournamentDayById = computed(() => {
    const map = new Map<number, any>();
    for (const d of tournamentDaysList.value) map.set(d.id, d);
    return map;
});

const dayDateLabel = (dateStr: string) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

// --- Sub-folders ---
const tournamentSubFoldersList = computed(() => {
    const all = props.tournamentSubFolders ?? [];
    if (isScorerOnly.value && currentUserId.value) {
        return all.filter((s: any) => s.assigned_scorer_id === currentUserId.value);
    }
    return all;
});

const assignedSubFolderIds = computed(() => {
    if (!isScorerOnly.value) return null;
    const ids = new Set<number>();
    for (const s of tournamentSubFoldersList.value) {
        if (s?.id != null) ids.add(s.id);
    }
    return ids;
});

const subFoldersById = computed(() => {
    const map = new Map<number, any>();
    for (const sub of tournamentSubFoldersList.value) {
        if (sub?.id != null) map.set(sub.id, sub);
    }
    return map;
});

const subFoldersByDayId = computed(() => {
    const map = new Map<number, any[]>();
    for (const sub of tournamentSubFoldersList.value) {
        const dayId = sub?.tournament_day_id;
        if (dayId == null) continue;
        if (!map.has(dayId)) map.set(dayId, []);
        map.get(dayId)!.push(sub);
    }
    for (const list of map.values()) {
        list.sort((a, b) => {
            const ao = a.order ?? 0;
            const bo = b.order ?? 0;
            if (ao !== bo) return ao - bo;
            return a.id - b.id;
        });
    }
    return map;
});

const subFoldersForDay = (dayId: number | null) => {
    if (dayId == null) return [];
    return subFoldersByDayId.value.get(dayId) ?? [];
};

const firstSelectedDayId = computed<number | null>(() => {
    if (selectedIds.value.size === 0) return null;
    for (const t of filteredTournaments.value) {
        if (selectedIds.value.has(t.id) && t.tournament_day_id != null) {
            return t.tournament_day_id;
        }
    }
    return null;
});

const firstSelectedDay = computed(() => {
    const id = firstSelectedDayId.value;
    if (id == null) return null;
    return tournamentDayById.value.get(id) ?? null;
});

const selectedIds = ref<Set<number>>(new Set());
const showDayMoveMenu = ref(false);
const showSubFolderMoveMenu = ref(false);
const isTournamentReadOnlyInList = (tournament: any) => {
    if (!tournament?.tournament_day_id) return false;
    const day = tournamentDayById.value.get(tournament.tournament_day_id);
    return day?.status === 'finished' || day?.status === 'archived';
};
const isTournamentIdSelectable = (id: number) => {
    const tournament = filteredTournaments.value.find((t: any) => t.id === id);
    if (!tournament) return false;
    return !isTournamentReadOnlyInList(tournament);
};
const showBulkBar = computed(() => selectedIds.value.size > 0);

const toggleSelect = (id: number) => {
    if (!isTournamentIdSelectable(id)) return;
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
};

const isSelected = (id: number) => selectedIds.value.has(id);

const clearSelection = () => {
    selectedIds.value = new Set();
    showDayMoveMenu.value = false;
};

const selectVisible = () => {
    const next = new Set(selectedIds.value);
    for (const t of filteredTournaments.value) {
        if (!isTournamentReadOnlyInList(t)) {
            next.add(t.id);
        }
    }
    selectedIds.value = next;
};

watch(
    () => filteredTournaments.value.map((t: any) => t.id).join(','),
    () => {
        const visibleIds = new Set(filteredTournaments.value.map((t: any) => t.id));
        const next = new Set(selectedIds.value);
        let changed = false;
        for (const id of Array.from(next)) {
            if (!visibleIds.has(id) || !isTournamentIdSelectable(id)) {
                next.delete(id);
                changed = true;
            }
        }
        if (changed) selectedIds.value = next;
    },
);

// --- Group tournaments by day ---
type SubFolderSection = {
    kind: 'subfolder';
    id: number;
    name: string;
    order: number;
    cards: any[];
    assigned_courts: number[];
};

type DaySection = {
    kind: 'unscheduled' | 'day';
    id: number | null;
    day: any | null;
    subSections: SubFolderSection[];
    unfiledCards: any[];
};

const sortTournaments = (arr: any[]) => {
    arr.sort((x: any, y: any) => {
        return x.id - y.id;
    });
};

const sectionTotal = (section: DaySection) => {
    if (section.kind === 'unscheduled') return section.unfiledCards.length;
    return section.subSections.reduce((sum, sub) => sum + sub.cards.length, 0) + section.unfiledCards.length;
};

const subFolderSectionLabel = (sub: SubFolderSection) => {
    const n = sub.cards.length;
    const noun = n === 1 ? 'BRACKET' : 'BRACKETS';
    return `${sub.name.toUpperCase()} · ${n} ${noun}`;
};

const groupedByDay = computed<DaySection[]>(() => {
    const sectionsMap = new Map<number, DaySection>();
    const unscheduled: DaySection = { kind: 'unscheduled', id: null, day: null, subSections: [], unfiledCards: [] };

    // First, register every day from the list so empty days are still rendered.
    for (const d of tournamentDaysList.value) {
        if (d?.id == null) continue;
        if (!sectionsMap.has(d.id)) {
            sectionsMap.set(d.id, { kind: 'day', id: d.id, day: d, subSections: [], unfiledCards: [] });
        }
    }

    // Then, register every sub-folder under its day so empty sub-folders are still rendered.
    for (const sub of tournamentSubFoldersList.value) {
        const dayId = sub?.tournament_day_id;
        if (dayId == null) continue;
        if (!sectionsMap.has(dayId)) {
            const day = tournamentDayById.value.get(dayId) ?? null;
            sectionsMap.set(dayId, { kind: 'day', id: dayId, day, subSections: [], unfiledCards: [] });
        }
        const section = sectionsMap.get(dayId)!;
        if (!section.subSections.some((s) => s.id === sub.id)) {
            section.subSections.push({
                kind: 'subfolder',
                id: sub.id,
                name: sub.name ?? 'Sub-folder',
                order: sub.order ?? 0,
                tournament_day_id: sub.tournament_day_id ?? null,
                assigned_scorer_id: sub.assigned_scorer_id ?? null,
                assignedScorer: sub.assignedScorer ?? null,
                assigned_courts: Array.isArray(sub.assigned_courts) ? sub.assigned_courts : [],
                cards: [],
            });
        }
    }

    // Finally, partition tournaments into the matching sub-folder or unfiled.
    for (const t of filteredTournaments.value) {
        const dayId = t.tournament_day_id ?? null;
        if (dayId === null) {
            if (!isScorerOnly.value) {
                unscheduled.unfiledCards.push(t);
            }
            continue;
        }
        if (!sectionsMap.has(dayId)) {
            const day = tournamentDayById.value.get(dayId) ?? null;
            sectionsMap.set(dayId, { kind: 'day', id: dayId, day, subSections: [], unfiledCards: [] });
        }
        const section = sectionsMap.get(dayId)!;
        const subId = t.tournament_sub_folder_id ?? null;
        if (isScorerOnly.value && assignedSubFolderIds.value && subId !== null && !assignedSubFolderIds.value.has(subId)) {
            continue;
        }
        if (isScorerOnly.value && subId === null) {
            continue;
        }
        if (subId === null) {
            section.unfiledCards.push(t);
        } else {
            const subSection = section.subSections.find((s) => s.id === subId);
            if (subSection) {
                subSection.cards.push(t);
            } else {
                // Sub-folder not registered (deleted, race, etc.) — fall back to unfiled.
                section.unfiledCards.push(t);
            }
        }
    }

    const sections: DaySection[] = [...sectionsMap.values()];
    sections.sort((a, b) => {
        const aDate = a.day?.date ?? '';
        const bDate = b.day?.date ?? '';
        return bDate.localeCompare(aDate);
    });

    for (const s of sections) {
        s.subSections.sort((a, b) => {
            if (a.order !== b.order) return a.order - b.order;
            return a.id - b.id;
        });
        for (const sub of s.subSections) {
            sortTournaments(sub.cards);
        }
        sortTournaments(s.unfiledCards);
    }

    if (unscheduled.unfiledCards.length > 0) {
        sortTournaments(unscheduled.unfiledCards);
    }

    return [...sections, unscheduled].filter((s) => {
        if (s.kind === 'unscheduled') return s.unfiledCards.length > 0;
        if (s.day?.status === 'archived') return false;
        if (isScorerOnly.value) {
            if (s.day?.status === 'finished') return false;
            const hasContent = s.subSections.length > 0 || s.unfiledCards.length > 0;
            return hasContent;
        }
        return true;
    });
});

const sectionLabel = (section: DaySection) => {
    const n = sectionTotal(section);
    const noun = n === 1 ? 'BRACKET' : 'BRACKETS';
    if (section.kind === 'unscheduled') {
        return `UNSCHEDULED · ${n} ${noun}`;
    }
    const date = section.day?.date ? dayDateLabel(section.day.date) : '—';
    const name = section.day?.name ?? '';
    return `${date.toUpperCase()} · ${name.toUpperCase()} · ${n} ${noun}`;
};

// --- Day section collapse (minimize) ---
const loadDayCollapse = (): Set<number> => {
    try {
        const raw = localStorage.getItem('tournament_collapsed_days');
        if (raw) return new Set(JSON.parse(raw));
    } catch {}
    return new Set();
};
const saveDayCollapse = (ids: Set<number>) => {
    localStorage.setItem('tournament_collapsed_days', JSON.stringify([...ids]));
};
const collapsedDayIds = ref<Set<number>>(loadDayCollapse());

const isDayCollapsed = (section: DaySection) => {
    return section.kind === 'day' && section.id !== null && collapsedDayIds.value.has(section.id);
};

const toggleDayCollapse = (section: DaySection) => {
    if (section.kind !== 'day' || section.id === null) return;
    const next = new Set(collapsedDayIds.value);
    if (next.has(section.id)) next.delete(section.id);
    else next.add(section.id);
    collapsedDayIds.value = next;
    saveDayCollapse(next);
};

// --- Day finish/reopen ---
const isDayFinished = (section: DaySection) => {
    return section.kind === 'day' && section.day?.status === 'finished';
};

const sectionOwnedTournament = (section: DaySection) => {
    if (section.kind !== 'day') return null;

    const sectionTournaments = [
        ...section.unfiledCards,
        ...section.subSections.flatMap((sub) => sub.cards),
    ];

    return sectionTournaments.find((t: any) => t.manager_user_id === currentUserId.value) ?? null;
};

const canRequestEditAccessForSection = (section: DaySection) => {
    return isPlayerRole.value && isDayFinished(section) && !!sectionOwnedTournament(section);
};

const toggleDayFinish = (section: DaySection) => {
    if (section.kind !== 'day' || section.id === null) return;
    const newStatus = isDayFinished(section) ? 'active' : 'finished';
    useForm({ status: newStatus }).put(
        route('tournament-days.update', section.id),
        { preserveScroll: true, only: ['tournamentDays', 'tournaments'] }
    );
};

const archiveDay = (dayId: number) => {
    useForm({ status: 'archived' }).put(
        route('tournament-days.update', dayId),
        { preserveScroll: true, only: ['tournamentDays', 'tournaments'] }
    );
};

// --- Day Form (create/edit) ---
const showDayModal = ref(false);
const dayModalMode = ref<'create' | 'edit'>('create');
const editingDayId = ref<number | null>(null);
const dayForm = useForm({
    name: '',
    date: new Date().toISOString().slice(0, 10),
    assigned_courts: [] as number[],
});
const pendingBulkAssignAfterCreate = ref<number[] | null>(null);

const openCreateDayModal = (opts?: { suggestedName?: string; suggestedDate?: string; assignIds?: number[] }) => {
    dayModalMode.value = 'create';
    editingDayId.value = null;
    dayForm.name = opts?.suggestedName ?? `Day — ${dayDateLabel(new Date().toISOString().slice(0, 10)).split(',')[0] ?? ''}`.trim();
    dayForm.date = opts?.suggestedDate ?? new Date().toISOString().slice(0, 10);
    dayForm.assigned_courts = [];
    dayForm.clearErrors();
    pendingBulkAssignAfterCreate.value = opts?.assignIds ?? null;
    showDayModal.value = true;
};

const openEditDayModal = (day: any) => {
    dayModalMode.value = 'edit';
    editingDayId.value = day.id;
    dayForm.name = day.name ?? '';
    dayForm.date = day.date ?? new Date().toISOString().slice(0, 10);
    dayForm.assigned_courts = day.assigned_courts ?? [];
    dayForm.clearErrors();
    pendingBulkAssignAfterCreate.value = null;
    showDayModal.value = true;
};

const closeDayModal = () => {
    showDayModal.value = false;
    dayForm.clearErrors();
    pendingBulkAssignAfterCreate.value = null;
};

const toggleDayCourtSelection = (courtNum: number) => {
    const list = [...dayForm.assigned_courts];
    const index = list.indexOf(courtNum);

    if (index >= 0) {
        list.splice(index, 1);
    } else {
        list.push(courtNum);
    }

    dayForm.assigned_courts = list.sort((a, b) => a - b);
};

const submitDayForm = () => {
    if (dayModalMode.value === 'create') {
        const pending = pendingBulkAssignAfterCreate.value;
        dayForm.post(route('tournament-days.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (pending && pending.length > 0) {
                    const newDay = tournamentDaysList.value[0];
                    if (newDay) {
                        bulkAssign(pending, newDay.id, true);
                    }
                }
                closeDayModal();
            },
        });
    } else if (editingDayId.value !== null) {
        dayForm.put(route('tournament-days.update', editingDayId.value), {
            preserveScroll: true,
            onSuccess: () => closeDayModal(),
        });
    }
};

const bulkAssign = (tournamentIds: number[], tournamentDayId: number | null, silent = false) => {
    router.post(
        route('tournaments.bulk-assign-day'),
        { tournament_ids: tournamentIds, tournament_day_id: tournamentDayId },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (!silent) {
                    const name =
                        tournamentDayId === null
                            ? 'Unscheduled'
                            : tournamentDayById.value.get(tournamentDayId)?.name ?? 'Day';
                    triggerToast(
                        `${tournamentIds.length} tournament${tournamentIds.length === 1 ? '' : 's'} moved to ${name}.`,
                    );
                }
                clearSelection();
            },
        },
    );
};

const moveSelectedToDay = (dayId: number | null) => {
    if (selectedIds.value.size === 0) return;
    bulkAssign(Array.from(selectedIds.value), dayId);
    showDayMoveMenu.value = false;
};

const createDayFromSelection = () => {
    const ids = Array.from(selectedIds.value);
    let suggestedDate: string | undefined;
    if (ids.length > 0) {
        const firstSelected = filteredTournaments.value.find((t: any) => t.id === ids[0]);
        if (firstSelected?.tournament_day?.date) {
            suggestedDate = firstSelected.tournament_day.date;
        }
    }
    openCreateDayModal({
        suggestedName: `Day — ${dayDateLabel(suggestedDate ?? new Date().toISOString().slice(0, 10))}`,
        suggestedDate: suggestedDate ?? new Date().toISOString().slice(0, 10),
        assignIds: ids,
    });
};

// --- Sub-folder collapse / modal / bulk assign ---
const loadSubFolderCollapse = (): Set<number> => {
    try {
        const raw = localStorage.getItem('tournament_collapsed_subfolders');
        if (raw) return new Set(JSON.parse(raw));
    } catch {}
    return new Set((props.tournamentSubFolders ?? []).map((s: any) => s.id));
};
const saveSubFolderCollapse = (ids: Set<number>) => {
    localStorage.setItem('tournament_collapsed_subfolders', JSON.stringify([...ids]));
};
const collapsedSubFolderIds = ref<Set<number>>(loadSubFolderCollapse());

const isSubFolderCollapsed = (sub: SubFolderSection) => collapsedSubFolderIds.value.has(sub.id);

const toggleSubFolderCollapse = (sub: SubFolderSection) => {
    const next = new Set(collapsedSubFolderIds.value);
    if (next.has(sub.id)) next.delete(sub.id);
    else next.add(sub.id);
    collapsedSubFolderIds.value = next;
    saveSubFolderCollapse(next);
};

const showSubFolderModal = ref(false);
const subFolderModalMode = ref<'create' | 'edit'>('create');
const editingSubFolderId = ref<number | null>(null);
const subFolderForm = useForm({
    name: '',
    tournament_day_id: null as number | null,
    order: 0 as number,
    assigned_scorer_id: null as number | null,
    assigned_courts: [] as number[],
});
const pendingBulkAssignAfterCreateSubFolder = ref<number[] | null>(null);

const openCreateSubFolderModal = (opts?: { tournamentDayId?: number | null; assignIds?: number[] }) => {
    subFolderModalMode.value = 'create';
    editingSubFolderId.value = null;
    subFolderForm.name = '';
    subFolderForm.tournament_day_id = opts?.tournamentDayId ?? null;
    subFolderForm.order = 0;
    subFolderForm.assigned_scorer_id = null;
    subFolderForm.assigned_courts = [];
    subFolderForm.clearErrors();
    pendingBulkAssignAfterCreateSubFolder.value = opts?.assignIds ?? null;
    showSubFolderModal.value = true;
};

const openEditSubFolderModal = (sub: any) => {
    subFolderModalMode.value = 'edit';
    editingSubFolderId.value = sub.id;
    subFolderForm.name = sub.name ?? '';
    subFolderForm.tournament_day_id = sub.tournament_day_id ?? null;
    subFolderForm.order = sub.order ?? 0;
    subFolderForm.assigned_scorer_id = sub.assigned_scorer_id ?? null;
    subFolderForm.assigned_courts = sub.assigned_courts ?? [];
    subFolderForm.clearErrors();
    pendingBulkAssignAfterCreateSubFolder.value = null;
    showSubFolderModal.value = true;
};

const selectedSubFolderDay = computed(() => {
    if (subFolderForm.tournament_day_id == null) return null;
    return tournamentDayById.value.get(subFolderForm.tournament_day_id) ?? null;
});

const availableSubFolderCourts = computed<number[]>(() => {
    const dayCourts = selectedSubFolderDay.value?.assigned_courts;
    if (Array.isArray(dayCourts) && dayCourts.length > 0) {
        return [...dayCourts].sort((a, b) => a - b);
    }

    const totalCourts = props.courtCount ?? 1;
    return Array.from({ length: totalCourts }, (_, index) => index + 1);
});

const toggleCourtSelection = (courtNum: number) => {
    if (!availableSubFolderCourts.value.includes(courtNum)) {
        return;
    }
    const list = [...subFolderForm.assigned_courts];
    const idx = list.indexOf(courtNum);
    if (idx === -1) {
        list.push(courtNum);
    } else {
        list.splice(idx, 1);
    }
    subFolderForm.assigned_courts = list.sort((a, b) => a - b);
};

watch(
    () => subFolderForm.tournament_day_id,
    () => {
        subFolderForm.assigned_courts = subFolderForm.assigned_courts.filter((courtNum) =>
            availableSubFolderCourts.value.includes(courtNum),
        );
    },
);

const closeSubFolderModal = () => {
    showSubFolderModal.value = false;
    subFolderForm.clearErrors();
    pendingBulkAssignAfterCreateSubFolder.value = null;
};

const activeScorerAssignSubId = ref<number | null>(null);
const scorerSearchQuery = ref('');

const assignScorer = (subFolderId: number, scorerId: number | null) => {
    router.put(
        route('tournament-sub-folders.update', subFolderId),
        { assigned_scorer_id: scorerId },
        {
            preserveScroll: true,
            only: ['tournamentSubFolders'],
            onSuccess: () => {
                activeScorerAssignSubId.value = null;
            },
        },
    );
};

const submitSubFolderForm = () => {
    if (subFolderModalMode.value === 'create') {
        const pending = pendingBulkAssignAfterCreateSubFolder.value;
        subFolderForm.post(route('tournament-sub-folders.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (pending && pending.length > 0) {
                    const flashNewId = (page.props.flash as any)?.new_sub_folder_id;
                    const newSub = flashNewId
                        ? tournamentSubFoldersList.value.find((s) => s.id === flashNewId)
                        : null;
                    if (newSub) {
                        bulkAssignSubFolder(pending, newSub.id, true, () => {
                            closeSubFolderModal();
                            router.reload();
                        });
                        return;
                    }
                }
                closeSubFolderModal();
                router.reload();
            },
            onError: (errors: any) => {
                showSystemAlert(errors?.error || 'Failed to create sub-folder.', 'error');
            },
        });
    } else if (editingSubFolderId.value !== null) {
        const url = route('tournament-sub-folders.update', editingSubFolderId.value);
        subFolderForm.put(url, {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('Sub-folder updated.');
                closeSubFolderModal();
                router.reload();
            },
            onError: (errors: any) => {
                showSystemAlert(errors?.error || errors?.message || JSON.stringify(errors) || 'Failed to update sub-folder.', 'error');
            },
        });
    }
};

const deleteSubFolder = (sub: any) => {
    const name = sub.name ?? 'sub-folder';
    if (!confirm(`Delete sub-folder "${name}"? Member tournaments will be moved to Unfiled.`)) return;
    router.delete(route('tournament-sub-folders.destroy', sub.id), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload();
        },
    });
};

const deleteDay = (day: any) => {
    const name = day?.name ?? day?.date ?? 'this day';
    if (!confirm(`Delete day "${name}"? All its sub-folders will be removed and tournaments moved to Unscheduled.`)) return;
    router.delete(route('tournament-days.destroy', day.id), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload();
        },
    });
};

const bulkAssignSubFolder = (tournamentIds: number[], subFolderId: number | null, silent = false, onComplete?: () => void) => {
    router.post(
        route('tournaments.bulk-assign-sub-folder'),
        { tournament_ids: tournamentIds, tournament_sub_folder_id: subFolderId },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (!silent) {
                    const name = subFolderId === null ? 'Unfiled' : subFoldersById.value.get(subFolderId)?.name ?? 'sub-folder';
                    triggerToast(
                        `${tournamentIds.length} tournament${tournamentIds.length === 1 ? '' : 's'} moved to ${name}.`,
                    );
                }
                clearSelection();
                onComplete?.();
            },
        },
    );
};

const moveSelectedToSubFolder = (subFolderId: number | null) => {
    if (selectedIds.value.size === 0) return;
    bulkAssignSubFolder(Array.from(selectedIds.value), subFolderId, false, () => {
        router.reload();
    });
    showSubFolderMoveMenu.value = false;
};

const createSubFolderFromSelection = () => {
    const ids = Array.from(selectedIds.value);
    const dayId = firstSelectedDayId.value;
    openCreateSubFolderModal({ tournamentDayId: dayId, assignIds: ids });
};

const openCreateModal = (opts?: { tournamentDayId?: number | null; tournamentSubFolderId?: number | null }) => {
    if (!canCreateTournamentCards.value) return;
    createForm.clearErrors();
    createForm.tournament_sub_folder_id = null;
    if (opts?.tournamentDayId !== undefined) {
        createForm.tournament_day_id = opts.tournamentDayId;
    } else if (isPlayerRole.value) {
        createForm.tournament_day_id = activeDaysList.value[0]?.id ?? null;
    }
    if (opts?.tournamentSubFolderId !== undefined && !isPlayerRole.value) {
        createForm.tournament_sub_folder_id = opts.tournamentSubFolderId;
    }
    showCreateModal.value = true;
};

const openArchiveModal = () => {
    showArchiveModal.value = true;
};

const reopenDayFromArchive = (dayId: number) => {
    useForm({ status: 'active' }).put(
        route('tournament-days.update', dayId),
        { preserveScroll: true, only: ['tournamentDays', 'tournaments'] }
    );
};

const matchTeamForm = useForm({
    team1_id: null as number | null,
    team2_id: null as number | null,
    scheduled_time: '' as string,
    court_number: null as number | null,
});

// --- Swap Opponents ---
const showSwapModal = ref(false);
const swapSourceMatch = ref<any>(null);

const swapForm = useForm({
    other_match_id: null as number | null,
});

const filteredPlayers = computed(() => {
    const q = playerSearch.value.trim().toLowerCase();
    const list = q ? props.allPlayers.filter((p: any) => p.name.toLowerCase().includes(q)) : [...props.allPlayers];
    return list.sort((a: any, b: any) => {
        const aPaired = isAlreadyPaired(a.name);
        const bPaired = isAlreadyPaired(b.name);
        if (aPaired === bPaired) return a.name.localeCompare(b.name);
        return aPaired ? 1 : -1;
    });
});

const showSystemAlert = (message: string, tone: 'info' | 'error' = 'info') => {
    appAlert.value = { message, tone };
    window.setTimeout(() => {
        appAlert.value = null;
    }, 3200);
};

// --- Create Tournament Form ---
const validDeCounts = [4, 8, 16, 32];

const categoryOptions = [
    { value: 'mens', label: "Men's" },
    { value: 'female', label: "Women's" },
    { value: 'mix', label: 'Mixed' },
] as const;

const createForm = useForm({
    name: '',
    type: 'single_elimination' as string,
    category: 'mens' as 'mens' | 'female' | 'mix',
    min_players: 4,
    max_players: 16,
    schedule_enabled: true,
    start_time: '08:00',
    match_duration: 25,
    rest_time: 5,
    enable_break: false,
    break_start: '12:00',
    break_end: '13:00',
    tournament_day_id: null as number | null,
    tournament_sub_folder_id: null as number | null,
    best_of: 1 as 1 | 3 | 5,
    assigned_courts: [] as number[],
});

// When day changes, reset sub-folder to null (a sub-folder is scoped to a specific day)
watch(
    () => createForm.tournament_day_id,
    () => {
        createForm.tournament_sub_folder_id = null;
    },
);

// When type changes, snap max_players to the nearest valid slot for that bracket type
watch(
    () => createForm.type,
    (newType) => {
        if (newType === 'double_elimination' || newType === 'single_elimination') {
            const nearest = validDeCounts.find((v) => v >= createForm.max_players) || 32;
            createForm.max_players = nearest;
            createForm.min_players = nearest;
        } else if (newType === 'round_robin') {
            const rrSlots = [3, 4, 5, 8];
            const nearest = rrSlots.find((v) => v >= createForm.max_players) || 8;
            createForm.max_players = nearest;
            createForm.min_players = nearest;
        }
    },
);

const toggleCreateCourtSelection = (courtNum: number) => {
    const list = [...createForm.assigned_courts];
    const idx = list.indexOf(courtNum);
    if (idx === -1) {
        list.push(courtNum);
    } else {
        list.splice(idx, 1);
    }
    createForm.assigned_courts = list;
};

const submitCreate = () => {
    createForm.post(route('tournaments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Tournament created successfully.');
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

// --- Update Schedule Settings Form ---
const scheduleForm = useForm({
    start_time: '08:00',
    match_duration: 25,
    rest_time: 5,
    enable_break: false,
    break_start: '12:00',
    break_end: '13:00',
    assigned_courts: [] as number[],
});

const toggleScheduleCourtSelection = (courtNum: number) => {
    const list = [...scheduleForm.assigned_courts];
    const idx = list.indexOf(courtNum);
    if (idx === -1) {
        list.push(courtNum);
    } else {
        list.splice(idx, 1);
    }
    scheduleForm.assigned_courts = list;
};

const openScheduleSettingsModal = () => {
    if (!props.activeTournament) return;
    const formatTimeForInput = (timeStr?: string | null) => {
        if (!timeStr) return '';
        return timeStr.substring(0, 5);
    };
    scheduleForm.start_time = formatTimeForInput(props.activeTournament.start_time) || '08:00';
    scheduleForm.match_duration = props.activeTournament.match_duration ?? 25;
    scheduleForm.rest_time = props.activeTournament.rest_time ?? 5;
    scheduleForm.enable_break = props.activeTournament.enable_break ? true : false;
    scheduleForm.break_start = formatTimeForInput(props.activeTournament.break_start) || '12:00';
    scheduleForm.break_end = formatTimeForInput(props.activeTournament.break_end) || '13:00';
    scheduleForm.assigned_courts = props.activeTournament.assigned_courts ?? [];
    showScheduleSettingsModal.value = true;
};

const submitScheduleSettings = () => {
    if (!props.activeTournament) return;
    scheduleForm.put(route('tournaments.update-schedule-settings', props.activeTournament.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Schedule updated successfully.');
            showScheduleSettingsModal.value = false;
        },
    });
};

const closeScheduleSettingsModal = () => {
    showScheduleSettingsModal.value = false;
    scheduleForm.clearErrors();
};

// --- Update Court Settings ---
const showCourtSettingsModal = ref(false);
const courtSettingsForm = useForm({
    assigned_courts: [] as number[],
});

const toggleCourtSettingsSelection = (courtNum: number) => {
    const list = [...courtSettingsForm.assigned_courts];
    const idx = list.indexOf(courtNum);
    if (idx === -1) {
        list.push(courtNum);
    } else {
        list.splice(idx, 1);
    }
    courtSettingsForm.assigned_courts = list;
};

const openCourtSettingsModal = () => {
    if (!props.activeTournament) return;
    courtSettingsForm.assigned_courts = props.activeTournament.assigned_courts ?? [];
    showCourtSettingsModal.value = true;
};

const submitCourtSettings = () => {
    if (!props.activeTournament) return;
    courtSettingsForm.put(route('tournaments.update', props.activeTournament.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Courts updated successfully.');
            showCourtSettingsModal.value = false;
        },
    });
};

// --- Subfolder Schedule Settings ---
const showFolderScheduleModal = ref(false);
const activeSubFolderForSchedule = ref<any>(null);
const folderScheduleForm = useForm({
    start_time: '08:00',
    match_duration: 25,
    rest_time: 5,
    enable_break: false,
    break_start: '12:00',
    break_end: '13:00',
});

const openFolderScheduleModal = (sub: any) => {
    activeSubFolderForSchedule.value = sub;
    const formatTimeForInput = (timeStr?: string | null) => {
        if (!timeStr) return '';
        return timeStr.substring(0, 5);
    };
    folderScheduleForm.start_time = formatTimeForInput(sub.start_time) || '08:00';
    folderScheduleForm.match_duration = sub.match_duration ?? 25;
    folderScheduleForm.rest_time = sub.rest_time ?? 5;
    folderScheduleForm.enable_break = sub.enable_break ? true : false;
    folderScheduleForm.break_start = formatTimeForInput(sub.break_start) || '12:00';
    folderScheduleForm.break_end = formatTimeForInput(sub.break_end) || '13:00';
    showFolderScheduleModal.value = true;
};

const submitFolderScheduleSettings = () => {
    if (!activeSubFolderForSchedule.value) return;
    folderScheduleForm.put(route('tournament-sub-folders.update', activeSubFolderForSchedule.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Folder schedule and all member tournament schedules updated successfully.');
            showFolderScheduleModal.value = false;
        },
    });
};

const closeFolderScheduleModal = () => {
    showFolderScheduleModal.value = false;
    folderScheduleForm.clearErrors();
};

const getCreationOrder = (tournament: any) => {
    if (!tournament.tournament_sub_folder_id) return '';
    
    // Find all tournaments in the same subfolder
    const subFolderTournaments = (props.tournaments ?? [])
        .filter((t: any) => t.tournament_sub_folder_id === tournament.tournament_sub_folder_id);
        
    // Sort them by id ascending
    subFolderTournaments.sort((a, b) => a.id - b.id);
    
    const index = subFolderTournaments.findIndex((t: any) => t.id === tournament.id);
    if (index === -1) return '';
    
    const suffix = (i: number) => {
        const j = i % 10, k = i % 100;
        if (j === 1 && k !== 11) return 'st';
        if (j === 2 && k !== 12) return 'nd';
        if (j === 3 && k !== 13) return 'rd';
        return 'th';
    };
    
    const num = index + 1;
    return `${num}${suffix(num)}`;
};

// --- Add Team Form (select 2 players to form a team/pair) ---
const teamForm = useForm({
    player1_name: '',
    player2_name: '',
});

const isP1 = (name: string) => teamForm.player1_name === name;
const isP2 = (name: string) => teamForm.player2_name === name;

const alreadyPairedPlayers = computed(() => {
    if (!props.activeTournament?.teams) return new Set<string>();
    const names = new Set<string>();
    for (const team of props.activeTournament.teams) {
        if (team.player1_name) names.add(team.player1_name);
        if (team.player2_name) names.add(team.player2_name);
    }
    return names;
});
const isAlreadyPaired = (name: string) => alreadyPairedPlayers.value.has(name);

const clickPlayerCard = (name: string) => {
    if (isP1(name)) {
        teamForm.player1_name = '';
        return;
    }
    if (isP2(name)) {
        teamForm.player2_name = '';
        return;
    }
    if (isAlreadyPaired(name)) return;
    if (!teamForm.player1_name) {
        teamForm.player1_name = name;
        return;
    }
    if (!teamForm.player2_name && teamForm.player1_name !== name) {
        teamForm.player2_name = name;
        return;
    }
};

const canAddPair = computed(() => {
    return teamForm.player1_name && teamForm.player2_name && teamForm.player1_name !== teamForm.player2_name;
});

const resetAddTeamModal = () => {
    teamForm.reset();
    teamForm.clearErrors();
};

const openEditTeamModal = (team: any) => {
    if (!canEditTeams.value) return;
    editingTeamId.value = team.id;
    teamForm.player1_name = team.player1_name || '';
    teamForm.player2_name = team.player2_name || '';
    showAddTeamModal.value = true;
};

const submitTeam = () => {
    if (!props.activeTournament || !canAddPair.value) return;
    if (editingTeamId.value) {
        teamForm.put(route('tournaments.update-team', [props.activeTournament.id, editingTeamId.value]), {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('Team updated successfully.');
                resetAddTeamModal();
                editingTeamId.value = null;
            },
            onError: (errors: any) => {
                showSystemAlert(errors.error || 'Failed to update team.', 'error');
            },
        });
    } else {
        teamForm.post(route('tournaments.add-team', props.activeTournament.id), {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('Team added successfully.');
                resetAddTeamModal();
            },
            onError: (errors: any) => {
                showSystemAlert(errors.error || 'Failed to add team.', 'error');
            },
        });
    }
};

const closeAddTeamModal = () => {
    showAddTeamModal.value = false;
    editingTeamId.value = null;
    teamForm.reset();
    teamForm.clearErrors();
};

const removeTeam = (teamId: number) => {
    if (!props.activeTournament) return;
    router.delete(route('tournaments.remove-team', [props.activeTournament.id, teamId]), {
        preserveScroll: true,
    });
};

// --- Generate Bracket / Double Elimination Team Count Validation ---
const canStartTournament = computed(() => {
    if (!props.activeTournament) return false;
    const count = props.activeTournament.teams?.length || 0;
    if (count < props.activeTournament.min_players) return false;
    if (props.activeTournament.type === 'double_elimination') {
        return validDeCounts.includes(count);
    }
    return true;
});

const teamCountWarning = computed(() => {
    if (!props.activeTournament) return '';
    const count = props.activeTournament.teams?.length || 0;
    const max = props.activeTournament.max_players;
    if (count >= max) return '';

    const needed = max - count;
    return `Need ${needed} more player pair${needed > 1 ? 's' : ''} to start the tournament`;
});

const generateBracket = () => {
    if (!props.activeTournament) return;
    router.post(
        route('tournaments.generate', props.activeTournament.id),
        {},
        {
            preserveScroll: true,
            onError: (errors: any) => {
                showSystemAlert(errors.error || 'Failed to generate bracket.', 'error');
            },
        },
    );
};

// --- Score Modal ---
const scoreForm = useForm({
    team1_score: 0,
    team2_score: 0,
});

const showForfeitSection = ref(false);
const forfeitForm = useForm({
    winner_id: null as number | null,
    winning_score: 11,
});

const openScoreModal = (match: any) => {
    if (!match?.team1_id || !match?.team2_id) {
        showSystemAlert('Both teams must be assigned before recording a score.', 'error');
        return;
    }
    scoringMatch.value = match;
    scoreForm.team1_score = match.team1_score ?? 0;
    scoreForm.team2_score = match.team2_score ?? 0;
    showForfeitSection.value = false;
    forfeitForm.winner_id = null;
    forfeitForm.winning_score = 11;
    showScoreModal.value = true;
};

const submitScore = () => {
    if (!scoringMatch.value) return;
    scoreForm.post(route('tournaments.record-score', scoringMatch.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Score recorded successfully.');
            showScoreModal.value = false;
            scoringMatch.value = null;
        },
    });
};

const bypassMatch = () => {
    if (!scoringMatch.value) return;
    router.post(route('tournaments.bypass-match', scoringMatch.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Match bypassed/postponed.');
            showScoreModal.value = false;
            scoringMatch.value = null;
        },
        onError: (errors: any) => {
            showSystemAlert(errors.error || 'Failed to bypass match.', 'error');
        }
    });
};

const submitForfeit = () => {
    if (!scoringMatch.value || !forfeitForm.winner_id) return;
    forfeitForm.post(route('tournaments.forfeit-match', scoringMatch.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Match forfeited successfully.');
            showScoreModal.value = false;
            scoringMatch.value = null;
            showForfeitSection.value = false;
            forfeitForm.reset();
        },
        onError: (errors: any) => {
            showSystemAlert(errors.error || 'Failed to forfeit match.', 'error');
        }
    });
};

const undoMatchResult = () => {
    if (!scoringMatch.value) return;
    router.post(
        route('tournaments.reset-match', scoringMatch.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('Match result undone.');
                showScoreModal.value = false;
                scoringMatch.value = null;
            },
        },
    );
};

// --- Edit Match Teams ---
const openEditMatchModal = (match: any) => {
    if (!canEditMatchTeams(match)) return;
    editingMatch.value = match;
    matchTeamForm.team1_id = match.team1_id ?? null;
    matchTeamForm.team2_id = match.team2_id ?? null;
    matchTeamForm.scheduled_time = match.scheduled_time ?? '';
    matchTeamForm.court_number = match.court_number ?? null;
    showEditMatchModal.value = true;
};

const submitMatchTeams = () => {
    if (!editingMatch.value) return;
    matchTeamForm.put(route('tournaments.update-match-teams', editingMatch.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Match pairings updated.');
            showEditMatchModal.value = false;
            editingMatch.value = null;
            matchTeamForm.reset();
        },
    });
};

const closeEditMatchModal = () => {
    showEditMatchModal.value = false;
    editingMatch.value = null;
    matchTeamForm.reset();
};

// --- Swap Opponents ---
const isSwappableMatch = (match: any) => {
    return canEditMatchTeams(match) && match?.round === 1 && match?.bracket !== 'round_robin' && match?.bracket !== 'grand_final';
};

const openSwapModal = (match: any) => {
    if (!isSwappableMatch(match)) return;
    swapSourceMatch.value = match;
    swapForm.other_match_id = null;
    showSwapModal.value = true;
};

const closeSwapModal = () => {
    showSwapModal.value = false;
    swapSourceMatch.value = null;
    swapForm.reset();
};

const submitSwap = () => {
    if (!swapSourceMatch.value || !swapForm.other_match_id) return;
    swapForm.post(route('tournaments.swap-opponents', swapSourceMatch.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Opponents swapped successfully.');
            closeSwapModal();
        },
    });
};

const swapCandidates = computed(() => {
    if (!swapSourceMatch.value || !props.activeTournament?.matches) return [];
    const source = swapSourceMatch.value;
    return props.activeTournament.matches.filter(
        (m: any) => m.id !== source.id && m.round === 1 && m.bracket === source.bracket && m.winner_id === null,
    );
});

// --- Navigate to tournament ---
const openTournament = (id: number) => {
    router.get(route('tournaments.show', id));
};

const goBack = () => {
    router.get(route('tournaments.index'));
};

const deleteTournament = (id: number) => {
    tournamentToDelete.value = id;
    showDeleteConfirm.value = true;
};

const confirmDelete = () => {
    if (!tournamentToDelete.value) return;
    router.delete(route('tournaments.destroy', tournamentToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Tournament deleted successfully.');
            showDeleteConfirm.value = false;
            tournamentToDelete.value = null;
        },
    });
};

const bulkDeleteSelected = () => {
    if (selectedIds.value.size === 0) return;
    showBulkDeleteConfirm.value = true;
};

const confirmBulkDelete = () => {
    if (selectedIds.value.size === 0) return;
    const count = selectedIds.value.size;

    router.post(
        route('tournaments.bulk-destroy'),
        { tournament_ids: Array.from(selectedIds.value) },
        {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast(`${count} tournament(s) deleted successfully.`);
                showBulkDeleteConfirm.value = false;
                clearSelection();
            },
            onError: (errors: any) => {
                showSystemAlert(errors.error || 'Failed to delete selected tournaments.', 'error');
                showBulkDeleteConfirm.value = false;
            },
        }
    );
};

// --- Bracket helpers ---
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

const statusColor = (status: string) => {
    return (
        {
            setup: 'text-yellow-400',
            in_progress: 'text-green-400',
            completed: 'text-blue-400',
        }[status] || 'text-slate-400'
    );
};

const teamName = (team: any) => {
    if (!team) return 'TBD';
    return `${team.player1_name} & ${team.player2_name}`;
};

// Bracket data for visualization
const winnersMatches = computed(() => {
    if (!props.activeTournament?.matches) return {};
    const matches = props.activeTournament.matches.filter((m: any) => m.bracket === 'winners');
    const grouped: Record<number, any[]> = {};
    for (const m of matches) {
        if (!grouped[m.round]) grouped[m.round] = [];
        grouped[m.round].push(m);
    }
    return grouped;
});

const losersMatches = computed(() => {
    if (!props.activeTournament?.matches) return {};
    const matches = props.activeTournament.matches.filter((m: any) => m.bracket === 'losers');
    const grouped: Record<number, any[]> = {};
    for (const m of matches) {
        if (!grouped[m.round]) grouped[m.round] = [];
        grouped[m.round].push(m);
    }
    return grouped;
});

const grandFinalMatch = computed(() => {
    if (!props.activeTournament?.matches) return null;
    return props.activeTournament.matches.find((m: any) => m.bracket === 'grand_final') || null;
});

const roundRobinMatches = computed(() => {
    if (!props.activeTournament?.matches) return {};
    const matches = props.activeTournament.matches.filter((m: any) => m.bracket === 'round_robin');
    const grouped: Record<number, any[]> = {};
    for (const m of matches) {
        if (!grouped[m.round]) grouped[m.round] = [];
        grouped[m.round].push(m);
    }
    return grouped;
});

const roundRobinStandings = computed(() => {
    if (!props.activeTournament?.matches || !props.activeTournament?.teams) return [];
    const teams = props.activeTournament.teams;
    const matches = props.activeTournament.matches;
    const stats: Record<number, { team: any; wins: number; losses: number; pf: number; pa: number }> = {};

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

const winnersRounds = computed(() =>
    Object.keys(winnersMatches.value)
        .map(Number)
        .sort((a, b) => a - b),
);
const losersRounds = computed(() =>
    Object.keys(losersMatches.value)
        .map(Number)
        .sort((a, b) => a - b),
);
const rrRounds = computed(() =>
    Object.keys(roundRobinMatches.value)
        .map(Number)
        .sort((a, b) => a - b),
);

const isSetup = computed(() => props.activeTournament?.status === 'setup');
const isInProgress = computed(() => props.activeTournament?.status === 'in_progress');
const isDayFinishedMode = computed(() => {
    if (!props.activeTournament?.tournament_day_id) return false;
    const day = (props.tournamentDays ?? []).find((d: any) => d.id === props.activeTournament.tournament_day_id);
    return day?.status === 'finished' || day?.status === 'archived';
});
const isPlayerOwnedTournament = computed(() => isPlayerRole.value && props.activeTournament?.manager_user_id === currentUserId.value);
const canPlayerManageDaySection = (section: any) => isPlayerRole.value && section?.kind === 'day' && section?.id !== null && !isDayFinished(section);
const canPlayerManageSubFolder = (sub: any) => {
    if (!isPlayerRole.value || sub?.tournament_day_id == null) return false;

    const day = tournamentDayById.value.get(sub.tournament_day_id) ?? null;
    return !(day && ['finished', 'archived'].includes(day.status));
};
const canFinishCurrentDay = computed(() => isPlayerOwnedTournament.value && !!props.activeTournament?.tournament_day_id && !isDayFinishedMode.value);

const isCompleted = computed(() => props.activeTournament?.status === 'completed');
const canEditMatches = computed(() => (isInProgress.value || isCompleted.value) && !isDayFinishedMode.value);

const hasAnyPlayedMatches = computed(() => {
    if (!props.activeTournament?.matches) return false;
    return props.activeTournament.matches.some((m: any) => m.winner_id !== null);
});

const canEditTeams = computed(() => {
    if (isDayFinishedMode.value) return false;
    return isSetup.value || (isInProgress.value && !hasAnyPlayedMatches.value);
});

const canEditMatchTeams = (match: any) => {
    return isInProgress.value && !isDayFinishedMode.value && match?.winner_id === null;
};

const availableTeams = computed(() => {
    return props.activeTournament?.teams || [];
});

const tournamentChampion = computed(() => {
    if (!isCompleted.value || !props.activeTournament?.matches) return null;
    const type = props.activeTournament.type;
    if (type === 'round_robin') {
        return roundRobinStandings.value[0]?.team || null;
    }
    if (type === 'double_elimination' && grandFinalMatch.value?.winner) {
        return grandFinalMatch.value.winner;
    }
    // Single elimination: last round last match winner
    const maxRound = Math.max(...Object.keys(winnersMatches.value).map(Number));
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

// --- Shared Bracket Dimensions (unified design) ---
const bracketMatchHeight = 84;
const bracketConnectorWidth = 44;
const bracketMatchSpacing = 28; // vertical gap between sibling matches

// Friendly round name based on remaining rounds
const roundLabel = (round: number, totalRounds: number, bracket: 'winners' | 'losers' | 'rr' = 'winners'): string => {
    if (bracket === 'rr') return `Round ${round}`;
    if (bracket === 'losers') return `Losers R${round}`;
    const remaining = totalRounds - round;
    if (remaining === 0) return 'Final';
    if (remaining === 1) return 'Semi Final';
    if (remaining === 2) return 'Quarter Final';
    return `Round ${round}`;
};

// --- Single Elimination Bracket Helpers ---
const seBracketHeight = computed(() => {
    const round1Matches = winnersMatches.value[1];
    if (!round1Matches) return 320;
    const count = round1Matches.length;
    return Math.max(count * (bracketMatchHeight + bracketMatchSpacing), 320);
});

const seRoundMatchY = (round: number, matchIndex: number, totalHeight: number): number => {
    const matches = winnersMatches.value[round];
    if (!matches || matches.length === 0) return 0;
    const count = matches.length;
    return (matchIndex + 0.5) * (totalHeight / count);
};

// --- Double Elimination Bracket Helpers ---
const deMatchHeight = bracketMatchHeight;
const deConnectorWidth = bracketConnectorWidth;

const deWinnersHeight = computed(() => {
    const round1 = winnersMatches.value[1];
    if (!round1) return 240;
    return Math.max(round1.length * (deMatchHeight + bracketMatchSpacing), 240);
});

const deLosersHeight = computed(() => {
    const round1 = losersMatches.value[1];
    if (!round1) return 200;
    return Math.max(round1.length * (deMatchHeight + bracketMatchSpacing), 200);
});

const deWinnersRoundY = (round: number, matchIndex: number): number => {
    const matches = winnersMatches.value[round];
    if (!matches || matches.length === 0) return 0;
    const count = matches.length;
    const totalH = deWinnersHeight.value;
    return (matchIndex + 0.5) * (totalH / count);
};

const deLosersRoundY = (round: number, matchIndex: number): number => {
    const matches = losersMatches.value[round];
    if (!matches || matches.length === 0) return 0;
    const count = matches.length;
    const totalH = deLosersHeight.value;
    return (matchIndex + 0.5) * (totalH / count);
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

const formatDate = (dateStr?: string | null) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const getTeamSchedules = (teamId: number) => {
    if (!props.activeTournament?.matches) return [];
    const isDouble = props.activeTournament?.type === 'double_elimination';
    return props.activeTournament.matches
        .filter((m: any) => (m.team1_id === teamId || m.team2_id === teamId) && m.scheduled_time)
        .map((m: any) => {
            const prefix = isDouble
                ? (m.bracket === 'winners' 
                    ? `WR${m.round}` 
                    : m.bracket === 'losers' 
                        ? `LR${m.round}` 
                        : m.bracket === 'grand_final' 
                            ? 'GF' 
                            : `R${m.round}`)
                : `R${m.round}`;
            return {
                prefix,
                round: m.round,
                bracket: m.bracket,
                scheduled_time: m.scheduled_time
            };
        })
        .sort((a: any, b: any) => {
            const bOrder: Record<string, number> = { winners: 1, losers: 2, grand_final: 3, round_robin: 4 };
            const ap = bOrder[a.bracket] ?? 99;
            const bp = bOrder[b.bracket] ?? 99;
            if (ap !== bp) return ap - bp;
            return a.round - b.round;
        });
};

const isAnyModalOpen = computed(() => {
    return showCreateModal.value ||
        showAddTeamModal.value ||
        showScoreModal.value ||
        showDeleteConfirm.value ||
        showBulkDeleteConfirm.value ||
        showBackToSetupConfirm.value ||
        showBracketSettingsModal.value ||
        showFolderScheduleModal.value ||
        showArchiveModal.value ||
        showTournamentRequestsModal.value ||
        showEditMatchModal.value ||
        showSwapModal.value ||
        showSubFolderModal.value ||
        showDayModal.value ||
        showCourtSettingsModal.value ||
        showScheduleSettingsModal.value;
});
</script>

<template>
    <AppLayout>
        <Head title="Tournaments" />

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

        <!-- Alert -->
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition duration-200"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="appAlert"
                :class="[
                    'fixed right-4 top-4 z-[100] rounded-lg px-4 py-2 text-sm font-medium shadow-lg',
                    appAlert.tone === 'error' ? 'bg-red-500 text-white' : 'bg-indigo-500 text-white dark:bg-green-500',
                ]"
            >
                {{ appAlert.message }}
            </div>
        </Transition>

        <div class="p-3 sm:p-4 md:p-6">
            <div class="mb-4 flex flex-col gap-3 sm:mb-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        v-if="activeTournament"
                        @click="goBack"
                        class="flex-shrink-0 rounded-lg bg-slate-200 p-2 transition hover:bg-slate-300 dark:bg-[#1a1a1a] dark:hover:bg-[#2a2a2a]"
                    >
                        <ChevronLeft class="h-5 w-5 text-slate-700 dark:text-white" />
                    </button>
                    <div class="min-w-0">
                        <h1 class="flex items-center gap-2 truncate text-xl font-bold text-slate-900 dark:text-white sm:text-2xl">
                            <Trophy class="h-5 w-5 flex-shrink-0 text-yellow-400 sm:h-6 sm:w-6" />
                            <template v-if="activeTournament && renamingActive">
                                <input
                                    :ref="(el) => { if (el) renameActiveInputRef = el as HTMLInputElement; }"
                                    v-model="renameForm.name"
                                    @keydown.enter.prevent="submitRenameActive"
                                    @keydown.escape.prevent="cancelRenameActive"
                                    @click.stop
                                    @blur="submitRenameActive"
                                    maxlength="255"
                                    class="min-w-0 flex-1 rounded-md border border-blue-300 bg-white px-2 py-1 text-xl font-bold text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-2xl dark:border-green-700 dark:bg-[#0f0f0f] dark:text-white"
                                />
                                <button
                                    @click="submitRenameActive"
                                    :disabled="renameForm.processing || !renameForm.name.trim()"
                                    class="flex-shrink-0 rounded p-1 text-emerald-500 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-emerald-500/20"
                                >
                                    <CheckCircle class="h-5 w-5" />
                                </button>
                                <button
                                    @click="cancelRenameActive"
                                    class="flex-shrink-0 rounded p-1 text-slate-400 transition hover:bg-slate-100 dark:hover:bg-[#1a1a1a]"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </template>
                            <template v-else-if="activeTournament">
                                <span
                                    @click="startRenameActive"
                                    class="truncate"
                                    :class="canRenameTournament(activeTournament.status) ? 'cursor-text hover:underline' : ''"
                                    :title="canRenameTournament(activeTournament.status) ? 'Click to rename' : ''"
                                >{{ activeTournament.name }}</span>
                                <button
                                    v-if="canRenameTournament(activeTournament.status)"
                                    @click="startRenameActive"
                                    class="flex-shrink-0 rounded p-1 text-slate-400 transition hover:bg-blue-50 hover:text-blue-500 dark:text-slate-500 dark:hover:bg-green-500/20 dark:hover:text-green-400"
                                    title="Rename"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                            </template>
                            <span v-else class="truncate">Tournaments</span>
                        </h1>
                        <p v-if="activeTournament" class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
                            {{ typeLabel(activeTournament.type) }} ·
                            {{ categoryLabel(activeTournament.category) }} ·
                            <span :class="statusColor(activeTournament.status)">{{ activeTournament.status.replace('_', ' ').toUpperCase() }}</span>
                        </p>
                        <p
                            v-if="activeTournament && activeTournament.tournament_day"
                            class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400 sm:text-sm"
                        >
                            <Folder class="h-3.5 w-3.5 text-blue-600 dark:text-green-400" />
                            <span class="font-semibold">{{ activeTournament.tournament_day.name }}</span>
                            <span class="text-slate-400 dark:text-slate-500">·</span>
                            <span>{{ dayDateLabel(activeTournament.tournament_day.date) }}</span>
                        </p>
                        <p
                            v-if="activeTournament && activeTournament.sub_folder"
                            class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400 sm:text-sm"
                        >
                            <FolderOpen class="h-3.5 w-3.5 text-violet-500" />
                            <span class="font-semibold">{{ activeTournament.sub_folder.name }}</span>
                        </p>
                        <p v-else-if="!activeTournament || !activeTournament.tournament_day" class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">Create and manage tournament brackets</p>
                    </div>
                </div>
                <div v-if="!activeTournament" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <div ref="statusFilterMenuRef" class="relative w-full sm:w-auto">
                        <button
                            @click.stop="showStatusFilterMenu = !showStatusFilterMenu"
                            class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                        >
                            <Filter class="h-4 w-4" />
                            <span>{{ activeStatusFilterLabel }}</span>
                            <ChevronDown
                                class="h-3.5 w-3.5 transition-transform duration-200"
                                :class="showStatusFilterMenu ? 'rotate-180' : ''"
                            />
                        </button>
                        <div
                            v-if="showStatusFilterMenu"
                            class="absolute right-0 z-50 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                        >
                            <button
                                v-for="opt in statusFilterOptions"
                                :key="opt.value"
                                @click.stop="pickStatusFilter(opt.value)"
                                class="flex w-full items-center justify-between px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider transition"
                                :class="
                                    statusFilter === opt.value
                                        ? 'bg-blue-50/50 text-blue-600 dark:bg-green-950/20 dark:text-green-400'
                                        : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                "
                            >
                                <span>{{ opt.label }}</span>
                                <span
                                    class="rounded-md px-1.5 py-0.5 text-[10px] font-black"
                                    :class="
                                        statusFilter === opt.value
                                            ? 'bg-white/70 text-blue-600 dark:bg-black/20 dark:text-green-400'
                                            : 'bg-slate-100 text-slate-500 dark:bg-[#1a1a1a] dark:text-slate-400'
                                    "
                                >
                                    {{ filterCounts[opt.value] }}
                                </span>
                            </button>
                        </div>
                    </div>
                    <a
                        href="/tournaments/live"
                        target="_blank"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                    >
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                        </span>
                        <span>Live Brackets</span>
                        <ExternalLink class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" />
                    </a>
                    <button
                        v-if="canManageTournaments"
                        @click="showTournamentRequestsModal = true"
                        class="relative flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                    >
                        <ShieldCheck class="h-4 w-4" />
                        Requests
                        <span
                            v-if="pendingTournamentRequestsCount > 0"
                            class="inline-flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-black text-white"
                        >
                            {{ pendingTournamentRequestsCount }}
                        </span>
                    </button>
                    <button
                        v-if="canManageTournaments"
                        @click="openArchiveModal"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                    >
                        <Archive class="h-4 w-4" /> Archive
                    </button>
                    <button
                        v-if="canManageTournaments"
                        @click="openCreateDayModal()"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                    >
                        <FolderPlus class="h-4 w-4" /> New Day
                    </button>
                    <button
                        v-if="canCreateTournamentCards"
                        @click="openCreateModal()"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-500 dark:bg-green-600 dark:hover:bg-green-500 sm:w-auto"
                    >
                        <Plus class="h-4 w-4" /> New Tournament
                    </button>
                </div>
                <div v-if="activeTournament" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <button
                        v-if="isInProgress && !hasAnyPlayedMatches && !isDayFinishedMode"
                        @click="backToSetup"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-700 transition hover:bg-amber-100 dark:border-amber-700/40 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/30 sm:w-auto"
                    >
                        <RotateCcw class="h-4 w-4" />
                        <span>Back to Setup</span>
                    </button>
                    <button
                        v-if="!activeTournament?.tournament_sub_folder_id && !isDayFinishedMode"
                        @click="openScheduleSettingsModal"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                    >
                        <Clock class="h-4 w-4 text-slate-500 dark:text-slate-400" />
                        <span>Change Duration</span>
                    </button>
                    <button
                        v-if="!activeTournament?.tournament_sub_folder_id && canManageTournaments"
                        @click="openCourtSettingsModal"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                    >
                        <LayoutGrid class="h-4 w-4 text-slate-500 dark:text-slate-400" />
                        <span>Change Courts</span>
                    </button>
                    <button
                        v-if="canFinishCurrentDay"
                        @click="openPlayerFinishDayConfirm()"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-500 sm:w-auto"
                    >
                        <CheckCircle class="h-4 w-4" />
                        <span>Finish Day</span>
                    </button>
                    <button
                        v-if="isPlayerOwnedTournament && isDayFinishedMode"
                        @click="openEditAccessRequestModal(props.activeTournament)"
                        class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                    >
                        <ShieldCheck class="h-4 w-4 text-blue-600 dark:text-green-400" />
                        <span>Request Main Folder Access</span>
                    </button>
                </div>
            </div>

            <div
                v-if="activeTournament && isPlayerOwnedTournament && isDayFinishedMode"
                class="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    <p class="font-bold">This tournament workspace is now view-only.</p>
                    <p class="mt-1 text-amber-700 dark:text-amber-100/90">
                        You finished the event day already. To open your main folder again, send an access request to the scheduler.
                    </p>
                </div>
            </div>

            <!-- TOURNAMENT LIST (grouped by Tournament Day) -->
            <div v-if="!activeTournament" class="space-y-6">
                <div v-if="filteredTournaments.length === 0 && (tournaments?.length ?? 0) > 0" class="flex flex-col items-center justify-center py-16 text-slate-500">
                    <Trophy class="mb-3 h-12 w-12 opacity-40" />
                    <p class="text-lg font-medium">No tournaments match this filter</p>
                    <p class="text-sm">Try a different status</p>
                </div>
                <div v-else-if="tournaments.length === 0 || (isScorerOnly && groupedByDay.length === 0)" class="flex flex-col items-center justify-center py-20 text-slate-500">
                    <Trophy class="mb-3 h-12 w-12 opacity-40" />
                    <template v-if="isScorerOnly">
                        <p class="text-lg font-medium">Interested in organizing a tournament?</p>
                        <p class="mt-2 max-w-md text-center text-sm leading-relaxed">
                            To set up a tournament, please contact the court owner through our
                            <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-600 underline decoration-blue-300 underline-offset-2 transition hover:text-blue-500 dark:text-green-400 dark:decoration-green-700 dark:hover:text-green-300">Facebook Page</a>.
                            The owner will assist you with tournament scheduling, court reservations, and other necessary arrangements.
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-lg font-medium">No Tournaments Yet</p>
                        <p class="text-sm">
                            {{ isPlayerRole ? 'Your main folder is ready. Create your first tournament card inside it.' : 'Create one to get started' }}
                        </p>
                    </template>
                </div>

                <div v-for="section in groupedByDay" :key="section.kind === 'day' ? `day-${section.id}` : 'unscheduled'" class="space-y-3">
                    <!-- Section header (card) -->
                    <div class="flex items-stretch gap-0 px-1">
                        <button
                            v-if="section.kind === 'day' && section.id !== null"
                            type="button"
                            @click="toggleDayCollapse(section)"
                            :aria-expanded="!isDayCollapsed(section)"
                            :aria-controls="`day-section-${section.id}`"
                            class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 rounded-l-xl border border-r-0 border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition hover:bg-blue-50/40 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:hover:bg-[#0a0a0a]"
                        >
                            <ChevronDown
                                class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200 dark:text-slate-500"
                                :class="isDayCollapsed(section) ? '-rotate-90' : ''"
                            />
                            <Calendar class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500" />
                            <h3
                                class="text-xs font-bold uppercase tracking-wider transition-colors"
                                :class="isDayCollapsed(section) ? 'text-slate-400 dark:text-slate-500' : 'text-slate-700 dark:text-slate-200'"
                            >
                                {{ sectionLabel(section) }}
                            </h3>
                            <span
                                v-if="section.day?.venue?.name"
                                class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <ShieldCheck class="h-3 w-3" />
                                Venue: {{ section.day.venue.name }}
                            </span>
                            <span
                                v-if="section.day?.assigned_courts && section.day.assigned_courts.length > 0"
                                class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300"
                            >
                                <LayoutGrid class="h-3 w-3" />
                                Courts: {{ section.day.assigned_courts.join(', ') }}
                            </span>
                            <span
                                v-if="isDayFinished(section)"
                                class="ml-1 inline-flex items-center gap-1 rounded-lg bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                            >
                                <CheckCircle class="h-3 w-3" />
                                Finished
                            </span>
                            <div class="h-px flex-1 bg-gradient-to-r from-slate-200 via-slate-100 to-transparent dark:from-[#1a1a1a] dark:via-[#0f0f0f] dark:to-transparent"></div>
                        </button>
                        <div
                            v-else
                            class="flex min-w-0 flex-1 items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                        >
                            <Calendar class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                                {{ sectionLabel(section) }}
                            </h3>
                            <div class="h-px flex-1 bg-gradient-to-r from-slate-200 via-slate-100 to-transparent dark:from-[#1a1a1a] dark:via-[#0f0f0f] dark:to-transparent"></div>
                        </div>
                        <div
                            v-if="section.kind === 'day' && (canManageTournaments || canCreateTournamentCards || isPlayerRole)"
                            class="flex items-stretch overflow-hidden rounded-r-xl border border-l-0 border-slate-200 bg-white shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                        >
                            <button
                                v-if="canRequestEditAccessForSection(section)"
                                @click.stop="openEditAccessRequestModal(sectionOwnedTournament(section), section)"
                                class="border-l border-slate-200 px-3 text-blue-500 transition hover:bg-blue-50 hover:text-blue-600 dark:border-[#1a1a1a] dark:hover:bg-blue-900/20 dark:hover:text-blue-300"
                                title="Request edit access"
                            >
                                <ShieldCheck class="h-4 w-4" />
                            </button>
                            <button
                                v-if="canManageTournaments || canPlayerManageDaySection(section)"
                                @click="openEditDayModal(section.day)"
                                class="px-3 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-[#1a1a1a] dark:hover:text-white"
                                title="Edit day"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                @click="openCreateModal({ tournamentDayId: section.id })"
                                v-if="!isDayFinished(section) && canCreateTournamentCards"
                                class="border-l border-slate-200 px-3 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:border-[#1a1a1a] dark:hover:bg-[#1a1a1a] dark:hover:text-white"
                                title="Add tournament to this day"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                            <button
                                v-if="section.kind === 'day' && section.id !== null && !isDayFinished(section) && (canManageTournaments || canPlayerManageDaySection(section))"
                                @click="openCreateSubFolderModal({ tournamentDayId: section.id })"
                                class="border-l border-slate-200 px-3 text-slate-400 transition hover:bg-violet-50 hover:text-violet-600 dark:border-[#1a1a1a] dark:hover:bg-violet-500/10 dark:hover:text-violet-400"
                                title="Add sub-folder to this day"
                            >
                                <FolderPlus class="h-4 w-4" />
                            </button>
                            <button
                                v-if="section.kind === 'day' && section.id !== null && (canManageTournaments || canPlayerManageDaySection(section))"
                                @click.stop="canManageTournaments ? toggleDayFinish(section) : openPlayerFinishDayConfirm(section.id)"
                                :class="isDayFinished(section)
                                    ? 'border-l border-slate-200 px-3 text-emerald-500 transition hover:bg-slate-100 hover:text-slate-600 dark:border-[#1a1a1a] dark:hover:bg-[#1a1a1a] dark:hover:text-white'
                                    : 'border-l border-slate-200 px-3 text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-600 dark:border-[#1a1a1a] dark:hover:bg-emerald-900/20 dark:hover:text-emerald-400'"
                                :title="canManageTournaments ? (isDayFinished(section) ? 'Unfinish day' : 'Finish day') : 'Finish day'"
                            >
                                <CheckCircle class="h-4 w-4" />
                            </button>
                            <button
                                v-if="section.kind === 'day' && section.id !== null && isDayFinished(section) && canManageTournaments"
                                @click.stop="archiveDay(section.id)"
                                class="border-l border-slate-200 px-3 text-blue-400 transition hover:bg-blue-50 hover:text-blue-600 dark:border-[#1a1a1a] dark:hover:bg-blue-900/20 dark:hover:text-blue-400"
                                title="Move to archive"
                            >
                                <Archive class="h-4 w-4" />
                            </button>
                            <button
                                v-if="section.kind === 'day' && section.id !== null && !isDayFinished(section) && (canManageTournaments || canPlayerManageDaySection(section))"
                                @click="deleteDay(section.day)"
                                class="border-l border-slate-200 px-3 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:border-[#1a1a1a] dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                title="Delete this day"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Cards in section -->
                    <div
                        v-if="!isDayCollapsed(section)"
                        :id="section.kind === 'day' && section.id !== null ? `day-section-${section.id}` : undefined"
                        class="space-y-5"
                    >
                        <!-- Sub-folder sections (only for day sections) -->
                        <div
                            v-for="sub in section.subSections"
                            :key="`sub-${sub.id}`"
                            class="space-y-3 border-l-2 border-violet-200 pl-1 dark:border-violet-500/30 sm:pl-3"
                        >
                            <div class="flex items-center gap-2 px-1 sm:px-4">
                                <button
                                    type="button"
                                    @click="toggleSubFolderCollapse(sub)"
                                    :aria-expanded="!isSubFolderCollapsed(sub)"
                                    :aria-controls="`sub-section-${sub.id}`"
                                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-lg border border-violet-200 bg-white px-3 py-2 text-left shadow-sm transition hover:bg-violet-50/40 dark:border-violet-500/20 dark:bg-[#0f0f0f] dark:hover:bg-violet-500/5"
                                >
                                    <ChevronDown
                                        class="h-3.5 w-3.5 shrink-0 text-violet-400 transition-transform duration-200 dark:text-violet-500"
                                        :class="isSubFolderCollapsed(sub) ? '-rotate-90' : ''"
                                    />
                                    <FolderOpen class="h-3.5 w-3.5 shrink-0 text-violet-500 dark:text-violet-400" />
                                    <h4 class="text-[10px] font-bold uppercase tracking-wider text-violet-700 dark:text-violet-300">
                                        {{ subFolderSectionLabel(sub) }}
                                    </h4>
                                    <!-- Court assignment badges -->
                                    <template v-if="sub.assigned_courts && sub.assigned_courts.length > 0">
                                        <span
                                            v-for="c in sub.assigned_courts"
                                            :key="c"
                                            class="inline-flex items-center gap-0.5 rounded-full border border-violet-300 bg-violet-100 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-violet-700 dark:border-violet-500/40 dark:bg-violet-500/20 dark:text-violet-300"
                                        >C{{ c }}</span>
                                    </template>
                                    <div class="h-px flex-1 bg-gradient-to-r from-violet-200 via-violet-100 to-transparent dark:from-violet-500/20 dark:via-violet-500/10 dark:to-transparent"></div>
                                </button>
                                <div class="relative" data-scorer-assign>
                                    <button
                                        v-if="canManageTournaments"
                                        @click.stop="activeScorerAssignSubId = activeScorerAssignSubId === sub.id ? null : (scorerSearchQuery = '', activeScorerAssignSubId = sub.id)"
                                        class="flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[10px] font-bold transition"
                                        :class="sub.assignedScorer
                                            ? 'border-indigo-200 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-900/25 dark:text-indigo-300 dark:hover:bg-indigo-900/40'
                                            : 'border-dashed border-slate-300 text-slate-400 hover:border-indigo-300 hover:text-indigo-500 dark:border-slate-600 dark:text-slate-500 dark:hover:border-indigo-500 dark:hover:text-indigo-400'"
                                        :title="sub.assignedScorer ? 'Change scorer' : 'Assign scorer'"
                                    >
                                        <User class="h-3 w-3" />
                                        <template v-if="sub.assignedScorer">
                                            Scorer: {{ sub.assignedScorer.name }}
                                        </template>
                                        <template v-else>
                                            Assign Scorer
                                        </template>
                                    </button>
                                    <span
                                        v-else-if="sub.assignedScorer"
                                        class="flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-600 dark:border-indigo-500/30 dark:bg-indigo-900/25 dark:text-indigo-300"
                                    >
                                        <User class="h-3 w-3" />
                                        Scorer: {{ sub.assignedScorer.name }}
                                    </span>
                                    <div
                                        v-if="activeScorerAssignSubId === sub.id"
                                        class="absolute right-0 top-full z-50 mt-1 min-w-[200px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                        @click.stop
                                    >
                                        <div class="border-b border-slate-100 p-2 dark:border-[#1a1a1a]">
                                            <input
                                                v-model="scorerSearchQuery"
                                                type="text"
                                                placeholder="Search scorers..."
                                                class="w-full rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-white dark:placeholder-slate-500"
                                            />
                                        </div>
                                        <button
                                            @click="assignScorer(sub.id, null)"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold transition"
                                            :class="sub.assigned_scorer_id === null ? 'text-indigo-600 dark:text-indigo-300' : 'text-slate-400 hover:bg-slate-50 dark:text-slate-500 dark:hover:bg-[#1a1a1a]'"
                                        >
                                            <X class="h-3 w-3" />
                                            No assign
                                            <CheckCircle v-if="sub.assigned_scorer_id === null" class="ml-auto h-3 w-3 text-indigo-500" />
                                        </button>
                                        <div class="max-h-[200px] overflow-y-auto">
                                            <button
                                                v-for="s in (scorers ?? []).filter((sc: any) => !scorerSearchQuery || sc.name.toLowerCase().includes(scorerSearchQuery.toLowerCase()))"
                                                :key="s.id"
                                                @click="assignScorer(sub.id, s.id)"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold transition hover:bg-indigo-50 dark:hover:bg-indigo-900/20"
                                                :class="sub.assigned_scorer_id === s.id ? 'text-indigo-600 dark:text-indigo-300' : 'text-slate-700 dark:text-slate-300'"
                                            >
                                                <User class="h-3 w-3" />
                                                {{ s.name }}
                                                <CheckCircle v-if="sub.assigned_scorer_id === s.id" class="ml-auto h-3 w-3 text-indigo-500" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    v-if="canManageTournaments || canPlayerManageSubFolder(sub)"
                                    @click="openEditSubFolderModal(sub)"
                                    class="rounded-lg border border-violet-200 bg-white p-2 text-slate-400 transition hover:bg-violet-50 hover:text-violet-600 dark:border-violet-500/20 dark:bg-[#0f0f0f] dark:hover:bg-violet-500/10 dark:hover:text-violet-400"
                                    title="Edit sub-folder"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    v-if="canManageTournaments || canPlayerManageSubFolder(sub)"
                                    @click="openFolderScheduleModal(sub)"
                                    class="rounded-lg border border-violet-200 bg-white p-2 text-slate-400 transition hover:bg-violet-50 hover:text-violet-600 dark:border-violet-500/20 dark:bg-[#0f0f0f] dark:hover:bg-violet-500/10 dark:hover:text-violet-400"
                                    title="Change folder duration"
                                >
                                    <Clock class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    v-if="canManageTournaments || canPlayerManageSubFolder(sub)"
                                    @click="deleteSubFolder(sub)"
                                    class="rounded-lg border border-violet-200 bg-white p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-500 dark:border-violet-500/20 dark:bg-[#0f0f0f] dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                    title="Delete sub-folder"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div
                                v-if="!isSubFolderCollapsed(sub)"
                                :id="`sub-section-${sub.id}`"
                                class="grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3"
                            >
                                <div
                                    v-for="t in sub.cards"
                                    :key="t.id"
                                    @click="openTournament(t.id)"
                                    :class="[
                                        'clean-card-hover group relative cursor-pointer p-5 transition',
                                        isTournamentReadOnlyInList(t) ? 'select-none' : '',
                                        isSelected(t.id) ? 'ring-2 ring-blue-500 dark:ring-green-500' : '',
                                    ]"
                                >
                                    <!-- Selection checkbox -->
                                    <button
                                        v-if="!isTournamentReadOnlyInList(t)"
                                        @click.stop="toggleSelect(t.id)"
                                        class="absolute left-3 top-3 z-10 flex h-7 w-7 items-center justify-center rounded-md transition"
                                        :class="
                                            isSelected(t.id)
                                                ? 'bg-blue-600 text-white dark:bg-green-600'
                                                : 'bg-white/80 text-slate-300 opacity-0 group-hover:opacity-100 hover:text-slate-600 dark:bg-[#0f0f0f]/80 dark:text-slate-600 dark:hover:text-white'
                                        "
                                        :title="isSelected(t.id) ? 'Deselect' : 'Select'"
                                    >
                                        <CheckSquare v-if="isSelected(t.id)" class="h-4 w-4" />
                                        <Square v-else class="h-4 w-4" />
                                    </button>

                                    <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="mb-4 flex items-start justify-between">
                                                <div class="flex min-w-0 items-center gap-3">
                                                    <div
                                                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 dark:border-green-800 dark:bg-green-900/20"
                                                    >
                                                        <Trophy class="h-5 w-5 text-blue-600 dark:text-green-400" />
                                                    </div>
                                                    <h3
                                                        class="truncate text-base font-bold text-slate-900 transition group-hover:text-blue-600 dark:text-white dark:group-hover:text-green-400"
                                                    >
                                                        {{ t.name }}
                                                    </h3>
                                                </div>
                                                <button
                                                    v-if="canManageTournaments"
                                                    @click.stop="deleteTournament(t.id)"
                                                    class="flex-shrink-0 rounded-lg p-1.5 text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-red-50 hover:text-red-500 dark:text-slate-500 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300"
                                                >
                                                    <Swords class="h-3 w-3 text-slate-500 dark:text-slate-400" />
                                                    {{ typeLabel(t.type) }} · {{ categoryLabel(t.category) }}
                                                </span>
                                                <span
                                                    v-if="t.best_of && t.best_of > 1"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-violet-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-violet-700 dark:bg-violet-500/20 dark:text-violet-300"
                                                >
                                                    Bo{{ t.best_of }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold uppercase tracking-wider"
                                                    :class="t.status === 'in_progress' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : t.status === 'completed' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="t.status === 'in_progress' ? 'bg-emerald-500' : t.status === 'completed' ? 'bg-blue-500' : 'bg-amber-500'"></span>
                                                    {{ t.status === 'in_progress' ? 'In Progress' : t.status === 'completed' ? 'Completed' : 'Setup' }}
                                                </span>
                                                <span
                                                    v-if="t.sub_folder"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300"
                                                >
                                                    <FolderOpen class="h-3 w-3" />
                                                    {{ t.sub_folder.name }}
                                                </span>
                                                <span
                                                    v-if="!t.tournament_sub_folder_id && t.assigned_courts && t.assigned_courts.length > 0"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/20 dark:text-indigo-300"
                                                >
                                                    <LayoutGrid class="h-3 w-3" />
                                                    Courts: {{ t.assigned_courts.join(', ') }}
                                                </span>
                                                <span
                                                    v-if="getCreationOrder(t)"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400"
                                                >
                                                    {{ getCreationOrder(t) }} Created
                                                </span>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                                <span class="flex items-center gap-1">
                                                    <Users class="h-3 w-3" />
                                                    {{ t.teams_count ?? t.teams?.length ?? 0 }} teams
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <User class="h-3 w-3" />
                                                    {{ t.min_players }}-{{ t.max_players }} players
                                                </span>
                                                <span v-if="t.start_time" class="flex items-center gap-1">
                                                    <Clock class="h-3 w-3" />
                                                    {{ formatTime(t.start_time) }}
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
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-1 text-[10px] text-slate-400 dark:text-slate-500">
                                        <Calendar class="h-3 w-3" />
                                        Created {{ formatDate(t.created_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Unfiled cards (within the day) and all unscheduled cards -->
                        <div v-if="section.unfiledCards.length > 0">
                            <div
                                v-if="section.kind === 'day' && section.subSections.length > 0"
                                class="mb-2 flex items-center gap-2 px-1 sm:px-4"
                            >
                                <FolderMinus class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Unfiled · {{ section.unfiledCards.length }} {{ section.unfiledCards.length === 1 ? 'bracket' : 'brackets' }}
                                </span>
                                <div class="h-px flex-1 bg-gradient-to-r from-slate-200 via-slate-100 to-transparent dark:from-[#1a1a1a] dark:via-[#0f0f0f] dark:to-transparent"></div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
                                <div
                                    v-for="t in section.unfiledCards"
                                    :key="t.id"
                                    @click="openTournament(t.id)"
                                    :class="[
                                        'clean-card-hover group relative cursor-pointer p-5 transition',
                                        isTournamentReadOnlyInList(t) ? 'select-none' : '',
                                        isSelected(t.id) ? 'ring-2 ring-blue-500 dark:ring-green-500' : '',
                                    ]"
                                >
                                    <!-- Selection checkbox -->
                                    <button
                                        v-if="!isTournamentReadOnlyInList(t)"
                                        @click.stop="toggleSelect(t.id)"
                                        class="absolute left-3 top-3 z-10 flex h-7 w-7 items-center justify-center rounded-md transition"
                                        :class="
                                            isSelected(t.id)
                                                ? 'bg-blue-600 text-white dark:bg-green-600'
                                                : 'bg-white/80 text-slate-300 opacity-0 group-hover:opacity-100 hover:text-slate-600 dark:bg-[#0f0f0f]/80 dark:text-slate-600 dark:hover:text-white'
                                        "
                                        :title="isSelected(t.id) ? 'Deselect' : 'Select'"
                                    >
                                        <CheckSquare v-if="isSelected(t.id)" class="h-4 w-4" />
                                        <Square v-else class="h-4 w-4" />
                                    </button>

                                    <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="mb-4 flex items-start justify-between">
                                                <div class="flex min-w-0 items-center gap-3">
                                                    <div
                                                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 dark:border-green-800 dark:bg-green-900/20"
                                                    >
                                                        <Trophy class="h-5 w-5 text-blue-600 dark:text-green-400" />
                                                    </div>
                                                    <h3
                                                        class="truncate text-base font-bold text-slate-900 transition group-hover:text-blue-600 dark:text-white dark:group-hover:text-green-400"
                                                    >
                                                        {{ t.name }}
                                                    </h3>
                                                </div>
                                                <button
                                                    v-if="canManageTournaments"
                                                    @click.stop="deleteTournament(t.id)"
                                                    class="flex-shrink-0 rounded-lg p-1.5 text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-red-50 hover:text-red-500 dark:text-slate-500 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300"
                                                >
                                                    <Swords class="h-3 w-3 text-slate-500 dark:text-slate-400" />
                                                    {{ typeLabel(t.type) }} · {{ categoryLabel(t.category) }}
                                                </span>
                                                <span
                                                    v-if="t.best_of && t.best_of > 1"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-violet-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-violet-700 dark:bg-violet-500/20 dark:text-violet-300"
                                                >
                                                    Bo{{ t.best_of }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold uppercase tracking-wider"
                                                    :class="t.status === 'in_progress' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : t.status === 'completed' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="t.status === 'in_progress' ? 'bg-emerald-500' : t.status === 'completed' ? 'bg-blue-500' : 'bg-amber-500'"></span>
                                                    {{ t.status === 'in_progress' ? 'In Progress' : t.status === 'completed' ? 'Completed' : 'Setup' }}
                                                </span>
                                                <span
                                                    v-if="t.sub_folder"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300"
                                                >
                                                    <FolderOpen class="h-3 w-3" />
                                                    {{ t.sub_folder.name }}
                                                </span>
                                                <span
                                                    v-if="!t.tournament_sub_folder_id && t.assigned_courts && t.assigned_courts.length > 0"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/20 dark:text-indigo-300"
                                                >
                                                    <LayoutGrid class="h-3 w-3" />
                                                    Courts: {{ t.assigned_courts.join(', ') }}
                                                </span>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                                <span class="flex items-center gap-1">
                                                    <Users class="h-3 w-3" />
                                                    {{ t.teams_count ?? t.teams?.length ?? 0 }} teams
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <User class="h-3 w-3" />
                                                    {{ t.min_players }}-{{ t.max_players }} players
                                                </span>
                                                <span v-if="t.start_time" class="flex items-center gap-1">
                                                    <Clock class="h-3 w-3" />
                                                    {{ formatTime(t.start_time) }}
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
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-1 text-[10px] text-slate-400 dark:text-slate-500">
                                        <Calendar class="h-3 w-3" />
                                        Created {{ formatDate(t.created_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTIVE TOURNAMENT -->
            <div v-if="activeTournament">
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
                    <span
                        class="mt-2 inline-block rounded-full bg-yellow-200 px-3 py-0.5 text-xs text-yellow-800 dark:bg-yellow-600/20 dark:text-yellow-300"
                        >2v2 Doubles</span
                    >
                </div>

                <!-- TEAMS & SETUP -->
                <div v-if="isSetup || isInProgress" class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white sm:text-lg">
                            Teams ({{ activeTournament.teams?.length || 0 }} / {{ activeTournament.max_players }})
                        </h2>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button
                                v-if="isSetup"
                                @click="openBracketSettingsModal"
                                class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] sm:w-auto"
                            >
                                <Swords class="h-4 w-4 text-slate-500 dark:text-slate-400" />
                                <span>Bracket Settings</span>
                            </button>
                            <button
                                v-if="isSetup"
                                @click="showAddTeamModal = true"
                                :disabled="(activeTournament.teams?.length || 0) >= activeTournament.max_players"
                                class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500 sm:w-auto"
                            >
                                <Plus class="h-4 w-4" /> Add Pair
                            </button>
                            <button
                                v-if="isSetup"
                                @click="generateBracket"
                                :disabled="!canStartTournament"
                                class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm text-white transition hover:bg-green-500 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto"
                            >
                                <Play class="h-4 w-4" /> Start Tournament
                            </button>
                        </div>
                    </div>

                    <!-- Team count warning -->
                    <div
                        v-if="teamCountWarning && canEditTeams"
                        class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 dark:border-amber-500/30 dark:bg-amber-900/20"
                    >
                        <AlertCircle class="h-4 w-4 flex-shrink-0 text-amber-500" />
                        <span class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ teamCountWarning }}</span>
                    </div>

                    <div v-if="activeTournament.teams?.length === 0" class="flex flex-col items-center py-16 text-slate-500">
                        <Users class="mb-2 h-10 w-10 opacity-40" />
                        <p>No teams added yet. Add player pairs to begin.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="team in activeTournament.teams"
                            :key="team.id"
                            class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]/60"
                        >
                            <div class="flex flex-col min-w-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="mr-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600 dark:bg-green-600/30 dark:text-green-300"
                                        >#{{ team.seed }}</span
                                    >
                                    <Users class="h-4 w-4 flex-shrink-0 text-slate-400" />
                                    <span class="truncate font-medium text-slate-900 dark:text-white">{{ team.player1_name }}</span>
                                    <span class="mx-1 text-slate-400 dark:text-slate-500">&</span>
                                    <span class="truncate font-medium text-slate-900 dark:text-white">{{ team.player2_name }}</span>
                                </div>
                                <div v-if="getTeamSchedules(team.id).length > 0" class="mt-1.5 flex flex-wrap gap-1.5 pl-7">
                                    <span v-for="(sched, idx) in getTeamSchedules(team.id)" :key="idx" class="inline-flex items-center rounded-md bg-slate-100 dark:bg-[#1a1a1a] px-1.5 py-0.5 text-[10px] font-black uppercase text-indigo-600 dark:text-green-400 tracking-wider">
                                        {{ sched.prefix }}: {{ formatTime(sched.scheduled_time) }}
                                    </span>
                                </div>
                            </div>
                            <div class="ml-2 flex flex-shrink-0 items-center gap-1">
                                <button
                                    v-if="canEditTeams"
                                    @click="openEditTeamModal(team)"
                                    class="rounded p-1 text-slate-400 transition hover:bg-blue-50 hover:text-blue-500 dark:text-slate-500 dark:hover:bg-green-500/20 dark:hover:text-green-400"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="isSetup"
                                    @click="removeTeam(team.id)"
                                    class="rounded p-1 text-slate-400 transition hover:bg-red-50 hover:text-red-500 dark:text-slate-500 dark:hover:bg-red-500/20 dark:hover:text-red-400"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BRACKET DISPLAY: SINGLE ELIMINATION -->
                <div v-if="(isInProgress || isCompleted) && activeTournament.type === 'single_elimination'" class="mt-4">
                    <div class="mb-4 flex justify-end">
                        <div
                            class="inline-flex self-start rounded-lg border border-slate-200 bg-white p-0.5 dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]/60 sm:self-auto"
                        >
                            <button
                                @click="userViewMode = 'tree'"
                                :class="[
                                    'flex min-h-[36px] items-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition',
                                    bracketViewMode === 'tree'
                                        ? 'bg-indigo-600 text-white dark:bg-green-600'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                                ]"
                            >
                                <LayoutGrid class="h-3.5 w-3.5" /> Tree
                            </button>
                            <button
                                @click="userViewMode = 'list'"
                                :class="[
                                    'flex min-h-[36px] items-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition',
                                    bracketViewMode === 'list'
                                        ? 'bg-indigo-600 text-white dark:bg-green-600'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                                ]"
                            >
                                <List class="h-3.5 w-3.5" /> List
                            </button>
                        </div>
                    </div>

                    <!-- LIST VIEW -->
                    <div
                        v-if="bracketViewMode === 'list'"
                        class="space-y-5 rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-[#1a1a1a]/50 dark:bg-[#0f0f0f] sm:p-4"
                    >
                        <div v-for="round in winnersRounds" :key="`se-list-${round}`">
                            <div class="mb-2 flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{
                                    roundLabel(round, winnersRounds.length, 'winners')
                                }}</span>
                                <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]/40"></div>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <MatchCard
                                    v-for="match in winnersMatches[round]"
                                    :key="match.id"
                                    :match="match"
                                    variant="winners"
                                    :clickable="!!(match.team1_id && match.team2_id && canEditMatches)"
                                    :editable="canEditMatchTeams(match)"
                                    :swappable="isSwappableMatch(match)"
                                    :has-sub-folder="!!activeTournament?.tournament_sub_folder_id"
                                    @click="openScoreModal(match)"
                                    @edit="openEditMatchModal(match)"
                                    @swap="openSwapModal(match)"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- TREE VIEW -->
                    <div v-else class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#1a1a1a]/50 dark:bg-[#0f0f0f]">
                        <!-- Round headers -->
                        <div class="mb-3 flex items-stretch">
                            <template v-for="(round, rIdx) in winnersRounds" :key="`hdr-${round}`">
                                <div class="flex flex-1 items-center justify-center">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{
                                        roundLabel(round, winnersRounds.length, 'winners')
                                    }}</span>
                                </div>
                                <div v-if="rIdx < winnersRounds.length - 1" :style="{ width: `${bracketConnectorWidth}px` }"></div>
                            </template>
                        </div>

                        <!-- Bracket body -->
                        <div class="flex items-stretch" :style="{ height: `${seBracketHeight}px` }">
                            <template v-for="(round, rIdx) in winnersRounds" :key="round">
                                <div class="relative flex-1" :style="{ height: `${seBracketHeight}px` }">
                                    <div
                                        v-for="(match, mIdx) in winnersMatches[round]"
                                        :key="match.id"
                                        @click="match.team1_id && match.team2_id && canEditMatches ? openScoreModal(match) : null"
                                        :class="[
                                            'group absolute left-0 right-0 transition',
                                            match.team1_id && match.team2_id && canEditMatches ? 'cursor-pointer' : '',
                                        ]"
                                        :style="{
                                            top: `${seRoundMatchY(round, mIdx, seBracketHeight) - bracketMatchHeight / 2}px`,
                                            height: `${bracketMatchHeight}px`,
                                        }"
                                    >
                                        <button
                                            v-if="isSwappableMatch(match)"
                                            @click.stop="openSwapModal(match)"
                                            class="absolute -right-1 -top-1 z-10 rounded bg-white/90 p-1 text-slate-400 opacity-0 shadow-sm transition hover:text-amber-500 group-hover:opacity-100 dark:bg-[#0f0f0f]/90 dark:hover:text-amber-400"
                                        >
                                            <ArrowLeftRight class="h-3 w-3" />
                                        </button>
                                        <div
                                            :class="[
                                                'w-full overflow-hidden rounded-xl border shadow-sm transition',
                                                match.winner_id
                                                    ? 'border-emerald-300 dark:border-emerald-500/30'
                                                    : 'border-slate-200 dark:border-[#1a1a1a]',
                                                match.team1_id && match.team2_id && canEditMatches
                                                    ? 'group-hover:border-green-400 group-hover:shadow-green-500/20 dark:group-hover:border-green-500/40 dark:group-hover:shadow-green-500/20'
                                                    : '',
                                            ]"
                                        >
                                            <!-- Scheduled Time Header -->
                                            <div v-if="match.scheduled_time || match.court_number" class="flex h-5 items-center justify-between px-3 py-0.5 bg-slate-50/50 dark:bg-[#0a0a0a]/30 border-b border-slate-100 dark:border-[#1a1a1a]/40 text-[8px] font-black uppercase tracking-wider text-slate-400">
                                                <span>
                                                    <span v-if="match.court_number" class="font-bold text-violet-600 dark:text-violet-400 mr-1">Court {{ match.court_number }}</span>
                                                    <span v-else-if="match.team1_id && match.team2_id && !match.winner_id" class="font-bold text-amber-600 dark:text-amber-400 mr-1">Waiting for Court</span>
                                                    <span v-else>Time Slot</span>
                                                </span>
                                                <span v-if="match.scheduled_time" class="text-indigo-600 dark:text-green-400 font-bold">{{ formatTime(match.scheduled_time) }}</span>
                                            </div>
                                            <!-- Team 1 row -->
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
                                            <!-- Team 2 row -->
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

                                <!-- SVG Connector -->
                                <div
                                    v-if="rIdx < winnersRounds.length - 1"
                                    class="relative flex-shrink-0"
                                    :style="{ width: `${bracketConnectorWidth}px` }"
                                >
                                    <svg :height="seBracketHeight" :width="bracketConnectorWidth" class="absolute inset-0">
                                        <g v-for="(parentMatch, pIdx) in winnersMatches[winnersRounds[rIdx + 1]]" :key="parentMatch.id">
                                            <template v-if="winnersMatches[round] && winnersMatches[round].length >= 2 * pIdx + 2">
                                                <line
                                                    :x1="0"
                                                    :y1="seRoundMatchY(round, 2 * pIdx, seBracketHeight)"
                                                    :x2="bracketConnectorWidth / 2"
                                                    :y2="seRoundMatchY(round, 2 * pIdx, seBracketHeight)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                                <line
                                                    :x1="0"
                                                    :y1="seRoundMatchY(round, 2 * pIdx + 1, seBracketHeight)"
                                                    :x2="bracketConnectorWidth / 2"
                                                    :y2="seRoundMatchY(round, 2 * pIdx + 1, seBracketHeight)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                                <line
                                                    :x1="bracketConnectorWidth / 2"
                                                    :y1="seRoundMatchY(round, 2 * pIdx, seBracketHeight)"
                                                    :x2="bracketConnectorWidth / 2"
                                                    :y2="seRoundMatchY(round, 2 * pIdx + 1, seBracketHeight)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                                <line
                                                    :x1="bracketConnectorWidth / 2"
                                                    :y1="seRoundMatchY(winnersRounds[rIdx + 1], pIdx, seBracketHeight)"
                                                    :x2="bracketConnectorWidth"
                                                    :y2="seRoundMatchY(winnersRounds[rIdx + 1], pIdx, seBracketHeight)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                            </template>
                                        </g>
                                    </svg>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- BRACKET DISPLAY: DOUBLE ELIMINATION -->
                <div v-if="(isInProgress || isCompleted) && activeTournament.type === 'double_elimination'" class="mt-4">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white sm:text-lg">Double Elimination Bracket</h2>
                            <span
                                class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-600 dark:bg-green-600/20 dark:text-green-300"
                                >2v2 Doubles</span
                            >
                        </div>
                        <div
                            class="inline-flex self-start rounded-lg border border-slate-200 bg-white p-0.5 dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]/60 sm:self-auto"
                        >
                            <button
                                @click="userViewMode = 'tree'"
                                :class="[
                                    'flex min-h-[36px] items-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition',
                                    bracketViewMode === 'tree'
                                        ? 'bg-indigo-600 text-white dark:bg-green-600'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                                ]"
                            >
                                <LayoutGrid class="h-3.5 w-3.5" /> Tree
                            </button>
                            <button
                                @click="userViewMode = 'list'"
                                :class="[
                                    'flex min-h-[36px] items-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition',
                                    bracketViewMode === 'list'
                                        ? 'bg-indigo-600 text-white dark:bg-green-600'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                                ]"
                            >
                                <List class="h-3.5 w-3.5" /> List
                            </button>
                        </div>
                    </div>

                    <!-- LIST VIEW -->
                    <div
                        v-if="bracketViewMode === 'list'"
                        class="space-y-6 rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-[#1a1a1a]/50 dark:bg-[#0f0f0f] sm:p-4"
                    >
                        <!-- Winners Bracket -->
                        <div>
                            <div class="mb-3 flex items-center gap-2">
                                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Winners Bracket</h3>
                                <div class="h-px flex-1 bg-gradient-to-r from-emerald-500/40 to-transparent"></div>
                            </div>
                            <div class="space-y-4">
                                <div v-for="round in winnersRounds" :key="`de-w-${round}`">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{
                                            roundLabel(round, winnersRounds.length, 'winners')
                                        }}</span>
                                        <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]/40"></div>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <MatchCard
                                            v-for="match in winnersMatches[round]"
                                            :key="match.id"
                                            :match="match"
                                            variant="winners"
                                            :clickable="!!(match.team1_id && match.team2_id && canEditMatches)"
                                            :editable="canEditMatchTeams(match)"
                                            :swappable="isSwappableMatch(match)"
                                            :has-sub-folder="!!activeTournament?.tournament_sub_folder_id"
                                            @click="openScoreModal(match)"
                                            @edit="openEditMatchModal(match)"
                                            @swap="openSwapModal(match)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Losers Bracket -->
                        <div>
                            <div class="mb-3 flex items-center gap-2">
                                <div class="h-2 w-2 rounded-full bg-rose-500"></div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Losers Bracket</h3>
                                <div class="h-px flex-1 bg-gradient-to-r from-rose-500/40 to-transparent"></div>
                            </div>
                            <div class="space-y-4">
                                <div v-for="round in losersRounds" :key="`de-l-${round}`">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500"
                                            >Losers R{{ round }}</span
                                        >
                                        <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]/40"></div>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <MatchCard
                                            v-for="match in losersMatches[round]"
                                            :key="match.id"
                                            :match="match"
                                            variant="losers"
                                            :clickable="!!(match.team1_id && match.team2_id && canEditMatches)"
                                            :has-sub-folder="!!activeTournament?.tournament_sub_folder_id"
                                            @click="openScoreModal(match)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Grand Final -->
                        <div v-if="grandFinalMatch">
                            <div class="mb-3 flex items-center gap-2">
                                <Award class="h-3.5 w-3.5 text-yellow-500 dark:text-yellow-400" />
                                <h3 class="text-xs font-bold uppercase tracking-wider text-yellow-600 dark:text-yellow-400">Grand Final</h3>
                                <div class="h-px flex-1 bg-gradient-to-r from-yellow-500/40 to-transparent"></div>
                            </div>
                            <MatchCard
                                :match="grandFinalMatch"
                                variant="grand_final"
                                :clickable="!!(grandFinalMatch.team1_id && grandFinalMatch.team2_id && canEditMatches)"
                                :has-sub-folder="!!activeTournament?.tournament_sub_folder_id"
                                @click="openScoreModal(grandFinalMatch)"
                            />
                        </div>
                    </div>

                    <!-- TREE VIEW -->
                    <div v-else class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#1a1a1a]/50 dark:bg-[#0f0f0f]">
                        <!-- WINNERS BRACKET SECTION -->
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
                                <div v-if="rIdx < winnersRounds.length - 1" :style="{ width: `${deConnectorWidth}px` }"></div>
                            </template>
                        </div>

                        <!-- Winners Bracket Body -->
                        <div class="flex items-stretch" :style="{ height: `${deWinnersHeight}px` }">
                            <template v-for="(round, rIdx) in winnersRounds" :key="round">
                                <div class="relative flex-1" :style="{ height: `${deWinnersHeight}px` }">
                                    <div
                                        v-for="(match, mIdx) in winnersMatches[round]"
                                        :key="match.id"
                                        @click="match.team1_id && match.team2_id && canEditMatches ? openScoreModal(match) : null"
                                        :class="[
                                            'group absolute left-0 right-0 transition',
                                            match.team1_id && match.team2_id && canEditMatches ? 'cursor-pointer' : '',
                                        ]"
                                        :style="{ top: `${deWinnersRoundY(round, mIdx) - deMatchHeight / 2}px`, height: `${deMatchHeight}px` }"
                                    >
                                        <button
                                            v-if="isSwappableMatch(match)"
                                            @click.stop="openSwapModal(match)"
                                            class="absolute -right-1 -top-1 z-10 rounded bg-white/90 p-1 text-slate-400 opacity-0 shadow-sm transition hover:text-amber-500 group-hover:opacity-100 dark:bg-[#0f0f0f]/90 dark:hover:text-amber-400"
                                        >
                                            <ArrowLeftRight class="h-3 w-3" />
                                        </button>
                                        <div
                                            :class="[
                                                'w-full overflow-hidden rounded-xl border shadow-sm transition',
                                                match.winner_id
                                                    ? 'border-emerald-300 dark:border-emerald-500/30'
                                                    : 'border-slate-200 dark:border-[#1a1a1a]',
                                                match.team1_id && match.team2_id && canEditMatches
                                                    ? 'group-hover:border-green-400 group-hover:shadow-green-500/20 dark:group-hover:border-green-500/40 dark:group-hover:shadow-green-500/20'
                                                    : '',
                                            ]"
                                        >
                                            <!-- Scheduled Time Header -->
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
                                    :style="{ width: `${deConnectorWidth}px` }"
                                >
                                    <svg :height="deWinnersHeight" :width="deConnectorWidth" class="absolute inset-0">
                                        <g v-for="(parentMatch, pIdx) in winnersMatches[winnersRounds[rIdx + 1]]" :key="parentMatch.id">
                                            <template v-if="winnersMatches[round] && winnersMatches[round].length >= 2 * pIdx + 2">
                                                <line
                                                    :x1="0"
                                                    :y1="deWinnersRoundY(round, 2 * pIdx)"
                                                    :x2="deConnectorWidth / 2"
                                                    :y2="deWinnersRoundY(round, 2 * pIdx)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                                <line
                                                    :x1="0"
                                                    :y1="deWinnersRoundY(round, 2 * pIdx + 1)"
                                                    :x2="deConnectorWidth / 2"
                                                    :y2="deWinnersRoundY(round, 2 * pIdx + 1)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                                <line
                                                    :x1="deConnectorWidth / 2"
                                                    :y1="deWinnersRoundY(round, 2 * pIdx)"
                                                    :x2="deConnectorWidth / 2"
                                                    :y2="deWinnersRoundY(round, 2 * pIdx + 1)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                                <line
                                                    :x1="deConnectorWidth / 2"
                                                    :y1="deWinnersRoundY(winnersRounds[rIdx + 1], pIdx)"
                                                    :x2="deConnectorWidth"
                                                    :y2="deWinnersRoundY(winnersRounds[rIdx + 1], pIdx)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                            </template>
                                        </g>
                                    </svg>
                                </div>
                            </template>

                            <!-- Winners → Grand Final connector + box -->
                            <div
                                class="relative flex-shrink-0"
                                :style="{ width: `${deConnectorWidth}px`, height: `${deWinnersHeight}px` }"
                                v-if="winnersRounds.length > 0"
                            >
                                <svg :height="deWinnersHeight" :width="deConnectorWidth" class="absolute inset-0">
                                    <line
                                        :x1="0"
                                        :y1="deWinnersRoundY(winnersRounds[winnersRounds.length - 1], 0)"
                                        :x2="deConnectorWidth"
                                        :y2="deWinnersRoundY(winnersRounds[winnersRounds.length - 1], 0)"
                                        stroke="#eab308"
                                        stroke-width="2"
                                    />
                                </svg>
                            </div>

                            <!-- Grand Final column -->
                            <div class="relative flex-1" :style="{ height: `${deWinnersHeight}px` }">
                                <div
                                    v-if="grandFinalMatch"
                                    class="group absolute left-0 right-0"
                                    :style="{
                                        top: `${deWinnersRoundY(winnersRounds[winnersRounds.length - 1] || 1, 0) - deMatchHeight / 2}px`,
                                        height: `${deMatchHeight}px`,
                                    }"
                                    @click="
                                        grandFinalMatch.team1_id && grandFinalMatch.team2_id && canEditMatches
                                            ? openScoreModal(grandFinalMatch)
                                            : null
                                    "
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
                                            grandFinalMatch.team1_id && grandFinalMatch.team2_id && canEditMatches
                                                ? 'cursor-pointer hover:shadow-yellow-400 dark:hover:shadow-yellow-400/30'
                                                : '',
                                        ]"
                                    >
                                        <!-- Scheduled Time Header -->
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
                        </div>

                        <!-- LOSERS BRACKET SECTION -->
                        <div class="mb-2 mt-8 flex items-center gap-2">
                            <div class="h-2 w-2 rounded-full bg-rose-500"></div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Losers Bracket</h3>
                            <div class="h-px flex-1 bg-gradient-to-r from-rose-500/40 to-transparent"></div>
                        </div>

                        <!-- Losers Round Headers -->
                        <div class="mb-2 flex items-stretch">
                            <template v-for="(round, rIdx) in losersRounds" :key="`lhdr-${round}`">
                                <div class="flex flex-1 items-center justify-center">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500"
                                        >Losers R{{ round }}</span
                                    >
                                </div>
                                <div v-if="rIdx < losersRounds.length - 1" :style="{ width: `${deConnectorWidth}px` }"></div>
                            </template>
                        </div>

                        <!-- Losers Bracket Body -->
                        <div class="flex items-stretch" :style="{ height: `${deLosersHeight}px` }">
                            <template v-for="(round, rIdx) in losersRounds" :key="round">
                                <div class="relative flex-1" :style="{ height: `${deLosersHeight}px` }">
                                    <div
                                        v-for="(match, mIdx) in losersMatches[round]"
                                        :key="match.id"
                                        @click="match.team1_id && match.team2_id && canEditMatches ? openScoreModal(match) : null"
                                        :class="[
                                            'group absolute left-0 right-0 transition',
                                            match.team1_id && match.team2_id && canEditMatches ? 'cursor-pointer' : '',
                                        ]"
                                        :style="{ top: `${deLosersRoundY(round, mIdx) - deMatchHeight / 2}px`, height: `${deMatchHeight}px` }"
                                    >
                                        <div
                                            :class="[
                                                'w-full overflow-hidden rounded-xl border shadow-sm transition',
                                                match.winner_id
                                                    ? 'border-rose-300 dark:border-rose-500/30'
                                                    : 'border-slate-200 dark:border-[#1a1a1a]',
                                                match.team1_id && match.team2_id && canEditMatches
                                                    ? 'group-hover:border-green-400 group-hover:shadow-green-500/20 dark:group-hover:border-green-500/40 dark:group-hover:shadow-green-500/20'
                                                    : '',
                                            ]"
                                        >
                                            <!-- Scheduled Time Header -->
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
                                <div v-if="rIdx < losersRounds.length - 1" class="relative flex-shrink-0" :style="{ width: `${deConnectorWidth}px` }">
                                    <svg :height="deLosersHeight" :width="deConnectorWidth" class="absolute inset-0">
                                        <!-- Even round → next odd round: 2:1 bracket merge -->
                                        <template v-if="round % 2 === 0">
                                            <g v-for="(parentMatch, pIdx) in losersMatches[losersRounds[rIdx + 1]]" :key="parentMatch.id">
                                                <template v-if="losersMatches[round] && losersMatches[round].length >= 2 * pIdx + 2">
                                                    <line
                                                        :x1="0"
                                                        :y1="deLosersRoundY(round, 2 * pIdx)"
                                                        :x2="deConnectorWidth / 2"
                                                        :y2="deLosersRoundY(round, 2 * pIdx)"
                                                        stroke="#64748b"
                                                        stroke-width="2"
                                                    />
                                                    <line
                                                        :x1="0"
                                                        :y1="deLosersRoundY(round, 2 * pIdx + 1)"
                                                        :x2="deConnectorWidth / 2"
                                                        :y2="deLosersRoundY(round, 2 * pIdx + 1)"
                                                        stroke="#64748b"
                                                        stroke-width="2"
                                                    />
                                                    <line
                                                        :x1="deConnectorWidth / 2"
                                                        :y1="deLosersRoundY(round, 2 * pIdx)"
                                                        :x2="deConnectorWidth / 2"
                                                        :y2="deLosersRoundY(round, 2 * pIdx + 1)"
                                                        stroke="#64748b"
                                                        stroke-width="2"
                                                    />
                                                    <line
                                                        :x1="deConnectorWidth / 2"
                                                        :y1="deLosersRoundY(losersRounds[rIdx + 1], pIdx)"
                                                        :x2="deConnectorWidth"
                                                        :y2="deLosersRoundY(losersRounds[rIdx + 1], pIdx)"
                                                        stroke="#64748b"
                                                        stroke-width="2"
                                                    />
                                                </template>
                                            </g>
                                        </template>
                                        <!-- Odd round → next even round: 1:1 straight lines -->
                                        <template v-else>
                                            <g v-for="(match, mIdx) in losersMatches[round]" :key="match.id">
                                                <line
                                                    :x1="0"
                                                    :y1="deLosersRoundY(round, mIdx)"
                                                    :x2="deConnectorWidth"
                                                    :y2="deLosersRoundY(losersRounds[rIdx + 1], mIdx)"
                                                    stroke="#64748b"
                                                    stroke-width="2"
                                                />
                                            </g>
                                        </template>
                                    </svg>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- BRACKET DISPLAY: ROUND ROBIN -->
                <div v-if="(isInProgress || isCompleted) && activeTournament.type === 'round_robin'" class="mt-4">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white sm:text-lg">Round Robin</h2>
                            <span
                                class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-600 dark:bg-green-600/20 dark:text-green-300"
                                >2v2 Doubles</span
                            >
                        </div>
                    </div>

                    <!-- STANDINGS TABLE -->
                    <div
                        v-if="roundRobinStandings.length > 0"
                        class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-[#1a1a1a]/50 dark:bg-[#0f0f0f]"
                    >
                        <div
                            class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-[#1a1a1a]/40 dark:bg-[#1a1a1a]/40"
                        >
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
                                        class="border-b border-slate-100 text-slate-800 transition hover:bg-slate-50 dark:border-[#1a1a1a]/20 dark:text-slate-200 dark:hover:bg-[#1a1a1a]/30"
                                    >
                                        <td class="px-4 py-2.5">
                                            <span
                                                v-if="idx === 0"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-yellow-100 text-xs font-bold text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-300"
                                                >1</span
                                            >
                                            <span
                                                v-else-if="idx === 1"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-700 dark:bg-[#2a2a2a]/30 dark:text-slate-300"
                                                >2</span
                                            >
                                            <span
                                                v-else-if="idx === 2"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700 dark:bg-amber-600/20 dark:text-amber-300"
                                                >3</span
                                            >
                                            <span
                                                v-else
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-[#1a1a1a]/30 dark:text-slate-400"
                                                >{{ idx + 1 }}</span
                                            >
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

                    <!-- ROUNDS -->
                    <div class="space-y-5">
                        <div v-for="round in rrRounds" :key="round">
                            <div class="mb-3 flex items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600 dark:bg-[#1a1a1a] dark:text-slate-400"
                                    >Round {{ round }}</span
                                >
                                <div class="h-px flex-1 bg-slate-200 dark:bg-[#1a1a1a]/40"></div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <MatchCard
                                    v-for="match in roundRobinMatches[round]"
                                    :key="match.id"
                                    :match="match"
                                    variant="round_robin"
                                    :clickable="!!(match.team1_id && match.team2_id && canEditMatches)"
                                    :editable="canEditMatchTeams(match)"
                                    :has-sub-folder="!!activeTournament?.tournament_sub_folder_id"
                                    @click="openScoreModal(match)"
                                    @edit="openEditMatchModal(match)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CREATE TOURNAMENT MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showCreateModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                >
                    <div
                        class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl"
                    >
                        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-4 py-4 dark:border-[#1a1a1a] sm:px-6">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">CREATE TOURNAMENT</h2>
                            <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6 sm:py-6">
                            <div class="space-y-4">
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Day</label>
                                    <button
                                        v-if="canManageTournaments"
                                        type="button"
                                        @click="openCreateDayModal()"
                                        class="flex items-center gap-1 text-[10px] font-semibold text-blue-600 transition hover:text-blue-500 dark:text-green-400 dark:hover:text-green-300"
                                    >
                                        <FolderPlus class="h-3 w-3" /> New day
                                    </button>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <button
                                        v-if="!isPlayerRole"
                                        type="button"
                                        @click="createForm.tournament_day_id = null"
                                        :class="[
                                            'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                            createForm.tournament_day_id === null
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        Unscheduled
                                    </button>
                                    <button
                                        v-for="day in activeDaysList"
                                        :key="day.id"
                                        type="button"
                                        @click="createForm.tournament_day_id = day.id"
                                        :class="[
                                            'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                            createForm.tournament_day_id === day.id
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        {{ day.name }} · {{ dayDateLabel(day.date) }}
                                    </button>
                                </div>
                                <p v-if="tournamentDaysList.length === 0" class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                    {{ isPlayerRole ? 'You need an approved main folder before creating a tournament card.' : 'No tournament days yet. Click "New day" to create one.' }}
                                </p>
                                <div v-if="createForm.errors.tournament_day_id" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ createForm.errors.tournament_day_id }}
                                </div>
                            </div>

                            <div v-if="createForm.tournament_day_id !== null && !isPlayerRole">
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sub-folder</label>
                                    <button
                                        type="button"
                                        @click="openCreateSubFolderModal({ tournamentDayId: createForm.tournament_day_id })"
                                        class="flex items-center gap-1 text-[10px] font-semibold text-violet-600 transition hover:text-violet-500 dark:text-violet-400 dark:hover:text-violet-300"
                                    >
                                        <FolderPlus class="h-3 w-3" /> New sub-folder
                                    </button>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="createForm.tournament_sub_folder_id = null"
                                        :class="[
                                            'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                            createForm.tournament_sub_folder_id === null
                                                ? 'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-500 dark:bg-violet-500/20 dark:text-violet-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-violet-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-violet-500/40',
                                        ]"
                                    >
                                        Unfiled
                                    </button>
                                    <button
                                        v-for="sub in subFoldersForDay(createForm.tournament_day_id)"
                                        :key="sub.id"
                                        type="button"
                                        @click="createForm.tournament_sub_folder_id = sub.id"
                                        :class="[
                                            'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                            createForm.tournament_sub_folder_id === sub.id
                                                ? 'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-500 dark:bg-violet-500/20 dark:text-violet-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-violet-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-violet-500/40',
                                        ]"
                                    >
                                        <FolderOpen class="mr-1 inline h-3 w-3" />
                                        {{ sub.name }}
                                    </button>
                                </div>
                                <p v-if="subFoldersForDay(createForm.tournament_day_id).length === 0" class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                    No sub-folders yet for this day. Click "New sub-folder" to create one.
                                </p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Tournament Name</label>
                                <input
                                    v-model="createForm.name"
                                    type="text"
                                    placeholder="e.g. Summer Cup 2026"
                                    class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:placeholder-slate-500 dark:focus:border-green-500 sm:text-sm"
                                />
                                <div v-if="createForm.errors.name" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ createForm.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Category</label>
                                <div class="mt-2 grid grid-cols-3 gap-2">
                                    <button
                                        v-for="opt in categoryOptions"
                                        :key="opt.value"
                                        type="button"
                                        @click="createForm.category = opt.value"
                                        :class="[
                                            'min-h-[44px] rounded-lg border px-3 py-3 text-center text-sm font-bold transition',
                                            createForm.category === opt.value
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Bracket Type</label>
                                <div class="mt-2 grid grid-cols-1 gap-2">
                                    <label
                                        v-for="opt in [
                                            { value: 'single_elimination', label: 'Single Elimination', desc: 'Lose once, you\'re out' },
                                            { value: 'double_elimination', label: 'Double Elimination', desc: 'Must lose twice to be eliminated' },
                                            { value: 'round_robin', label: 'Round Robin', desc: 'Everyone plays everyone' },
                                        ]"
                                        :key="opt.value"
                                        :class="[
                                            'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition',
                                            createForm.type === opt.value
                                                ? 'border-blue-500 bg-blue-50 dark:border-green-500 dark:bg-green-600/10'
                                                : 'border-slate-200 bg-slate-50 hover:border-slate-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:hover:border-slate-700',
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            :value="opt.value"
                                            v-model="createForm.type"
                                            class="mt-1 accent-blue-500 dark:accent-green-500"
                                        />
                                        <div>
                                            <div class="text-sm font-semibold text-slate-900 dark:text-[#EDEDEC]">{{ opt.label }}</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ opt.desc }}</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Single / Double Elimination: Team Slots selector (4, 8, 16, 32) -->
                            <div v-if="createForm.type === 'single_elimination' || createForm.type === 'double_elimination'">
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Team Slots</label>
                                <p class="mb-2 mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">
                                    Select a power-of-2 team count for the bracket
                                </p>
                                <div class="grid grid-cols-4 gap-2">
                                    <button
                                        v-for="count in validDeCounts"
                                        :key="count"
                                        type="button"
                                        @click="
                                            createForm.max_players = count;
                                            createForm.min_players = count;
                                        "
                                        :class="[
                                            'min-h-[44px] rounded-lg border px-3 py-3 text-center font-bold transition',
                                            createForm.max_players === count
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        {{ count }}
                                    </button>
                                </div>
                            </div>

                            <!-- Round Robin: Team Slots selector (3, 4, 5, 8) -->
                            <div v-else>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Team Slots</label>
                                <p class="mb-2 mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">Select the number of teams for round robin</p>
                                <div class="grid grid-cols-4 gap-2">
                                    <button
                                        v-for="count in [3, 4, 5, 8]"
                                        :key="count"
                                        type="button"
                                        @click="
                                            createForm.max_players = count;
                                            createForm.min_players = count;
                                        "
                                        :class="[
                                            'min-h-[44px] rounded-lg border px-3 py-3 text-center font-bold transition',
                                            createForm.max_players === count
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        {{ count }}
                                    </button>
                                </div>
                            </div>

                            <!-- Tournament Schedule Settings -->
                            <div class="border-t border-slate-200 pt-4 dark:border-[#1a1a1a]">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">TOURNAMENT SCHEDULE SETTINGS</h3>
                                        <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">
                                            {{ createForm.schedule_enabled ? 'Customize start time, match length, and breaks' : 'Using default schedule (8:00 AM · 25 min matches · 5 min rest · no break)' }}
                                        </p>
                                    </div>
                                    <label class="relative inline-flex shrink-0 cursor-pointer items-center" :title="createForm.schedule_enabled ? 'Hide schedule options' : 'Show schedule options'">
                                        <input
                                            v-model="createForm.schedule_enabled"
                                            type="checkbox"
                                            class="peer sr-only"
                                        />
                                        <div
                                            class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-slate-700 dark:peer-checked:bg-green-600 peer-focus:outline-none"
                                        ></div>
                                    </label>
                                </div>

                                <div v-if="createForm.schedule_enabled" class="space-y-4">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Start Time</label>
                                            <input
                                                v-model="createForm.start_time"
                                                type="time"
                                                class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Duration (mins)</label>
                                            <input
                                                v-model.number="createForm.match_duration"
                                                type="number"
                                                min="1"
                                                class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Rest (mins)</label>
                                            <input
                                                v-model.number="createForm.rest_time"
                                                type="number"
                                                min="0"
                                                class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                            />
                                        </div>
                                    </div>

                                    <!-- Break settings -->
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 dark:border-[#1a1a1a]/85 dark:bg-[#0a0a0a]/30">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-xs font-semibold text-slate-900 dark:text-[#EDEDEC]">Enable Break Time</div>
                                                <div class="text-[10px] text-slate-500 dark:text-slate-400">Add a rest period during the tournament</div>
                                            </div>
                                            <label class="relative inline-flex cursor-pointer items-center">
                                                <input
                                                    type="checkbox"
                                                    v-model="createForm.enable_break"
                                                    class="peer sr-only"
                                                />
                                                <div
                                                    class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-slate-700 dark:peer-checked:bg-green-600 peer-focus:outline-none"
                                                ></div>
                                            </label>
                                        </div>

                                        <div v-if="createForm.enable_break" class="mt-4 grid grid-cols-2 gap-4 border-t border-slate-200/50 pt-3.5 dark:border-[#1a1a1a]/50">
                                            <div>
                                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Break Start</label>
                                                <input
                                                    v-model="createForm.break_start"
                                                    type="time"
                                                    class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Break End</label>
                                                <input
                                                    v-model="createForm.break_end"
                                                    type="time"
                                                    class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                                />
                                                <div v-if="createForm.errors.break_end" class="mt-1 text-[10px] text-red-500 font-semibold">
                                                    {{ createForm.errors.break_end }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Available courts -->
                                        <div v-if="canManageTournaments" class="mt-4 border-t border-slate-200/50 pt-3.5 dark:border-[#1a1a1a]/50">
                                            <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available Courts</label>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <button
                                                    v-for="courtNum in props.courtCount ?? 1"
                                                    :key="courtNum"
                                                    type="button"
                                                    @click="toggleCreateCourtSelection(courtNum)"
                                                    :class="[
                                                        'inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-2 text-xs font-bold transition-all duration-150',
                                                        createForm.assigned_courts.includes(courtNum)
                                                            ? 'border-blue-500 bg-blue-500 text-white shadow-md shadow-blue-200 dark:border-green-400 dark:bg-green-600 dark:text-white dark:shadow-green-900/40 scale-105'
                                                            : 'border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:text-blue-600 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/60 dark:hover:text-green-300',
                                                    ]"
                                                >
                                                    <CheckCircle
                                                        v-if="createForm.assigned_courts.includes(courtNum)"
                                                        class="h-3.5 w-3.5 flex-shrink-0"
                                                    />
                                                    <Square
                                                        v-else
                                                        class="h-3.5 w-3.5 flex-shrink-0 opacity-40"
                                                    />
                                                    Court {{ courtNum }}
                                                </button>
                                            </div>
                                            <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">
                                                <span v-if="createForm.assigned_courts.length > 0" class="font-semibold text-blue-600 dark:text-green-400">
                                                    {{ createForm.assigned_courts.length }} court{{ createForm.assigned_courts.length > 1 ? 's' : '' }} selected
                                                </span>
                                                <span v-else>Select which courts are allocated to this tournament.</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-white px-4 py-4 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:px-6">
                            <button
                                @click="showCreateModal = false"
                                class="min-h-[44px] flex-1 rounded-lg border border-slate-300 bg-slate-200 px-4 py-2.5 text-sm text-slate-600 transition hover:bg-slate-300 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#222] sm:flex-none"
                            >
                                CANCEL
                            </button>
                            <button
                                @click="submitCreate"
                                :disabled="!createForm.name || !createForm.category || createForm.processing"
                                class="min-h-[44px] flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500 sm:flex-none"
                            >
                                CREATE
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- EDIT SCHEDULE SETTINGS MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showScheduleSettingsModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                >
                    <div
                        class="max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl sm:p-6"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">TOURNAMENT SCHEDULE SETTINGS</h2>
                            <button @click="closeScheduleSettingsModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Start Time</label>
                                <input
                                    v-model="scheduleForm.start_time"
                                    type="time"
                                    class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                />
                                <div v-if="scheduleForm.errors.start_time" class="mt-1 text-[10px] text-red-500 font-semibold">
                                    {{ scheduleForm.errors.start_time }}
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Duration (mins)</label>
                                    <input
                                        v-model.number="scheduleForm.match_duration"
                                        type="number"
                                        min="1"
                                        class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                    />
                                    <div v-if="scheduleForm.errors.match_duration" class="mt-1 text-[10px] text-red-500 font-semibold">
                                        {{ scheduleForm.errors.match_duration }}
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Rest (mins)</label>
                                    <input
                                        v-model.number="scheduleForm.rest_time"
                                        type="number"
                                        min="0"
                                        class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                    />
                                    <div v-if="scheduleForm.errors.rest_time" class="mt-1 text-[10px] text-red-500 font-semibold">
                                        {{ scheduleForm.errors.rest_time }}
                                    </div>
                                </div>
                            </div>

                            <!-- Break settings -->
                            <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 dark:border-[#1a1a1a]/85 dark:bg-[#0a0a0a]/30">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-xs font-semibold text-slate-900 dark:text-[#EDEDEC]">Enable Break Time</div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400">Add a rest period during the tournament</div>
                                    </div>
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            v-model="scheduleForm.enable_break"
                                            class="peer sr-only"
                                        />
                                        <div
                                            class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-slate-700 dark:peer-checked:bg-green-600 peer-focus:outline-none"
                                        ></div>
                                    </label>
                                </div>

                                <div v-if="scheduleForm.enable_break" class="mt-4 grid grid-cols-2 gap-4 border-t border-slate-200/50 pt-3.5 dark:border-[#1a1a1a]/50">
                                    <div>
                                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Break Start</label>
                                        <input
                                            v-model="scheduleForm.break_start"
                                            type="time"
                                            class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                        />
                                        <div v-if="scheduleForm.errors.break_start" class="mt-1 text-[10px] text-red-500 font-semibold">
                                            {{ scheduleForm.errors.break_start }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Break End</label>
                                        <input
                                            v-model="scheduleForm.break_end"
                                            type="time"
                                            class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                        />
                                        <div v-if="scheduleForm.errors.break_end" class="mt-1 text-[10px] text-red-500 font-semibold">
                                            {{ scheduleForm.errors.break_end }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Available courts -->
                            <div v-if="canManageTournaments" class="mt-4 border-t border-slate-200/50 pt-3.5 dark:border-[#1a1a1a]/50">
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available Courts</label>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="courtNum in props.courtCount ?? 1"
                                        :key="courtNum"
                                        type="button"
                                        @click="toggleScheduleCourtSelection(courtNum)"
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-2 text-xs font-bold transition-all duration-150',
                                            scheduleForm.assigned_courts.includes(courtNum)
                                                ? 'border-blue-500 bg-blue-500 text-white shadow-md shadow-blue-200 dark:border-green-400 dark:bg-green-600 dark:text-white dark:shadow-green-900/40 scale-105'
                                                : 'border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:text-blue-600 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/60 dark:hover:text-green-300',
                                        ]"
                                    >
                                        <CheckCircle
                                            v-if="scheduleForm.assigned_courts.includes(courtNum)"
                                            class="h-3.5 w-3.5 flex-shrink-0"
                                        />
                                        <Square
                                            v-else
                                            class="h-3.5 w-3.5 flex-shrink-0 opacity-40"
                                        />
                                        Court {{ courtNum }}
                                    </button>
                                </div>
                                <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">
                                    <span v-if="scheduleForm.assigned_courts.length > 0" class="font-semibold text-blue-600 dark:text-green-400">
                                        {{ scheduleForm.assigned_courts.length }} court{{ scheduleForm.assigned_courts.length > 1 ? 's' : '' }} selected
                                    </span>
                                    <span v-else>Select which courts are allocated to matches in this tournament.</span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <button
                                @click="closeScheduleSettingsModal"
                                class="min-h-[44px] flex-1 rounded-lg border border-slate-300 bg-slate-200 px-4 py-2.5 text-sm text-slate-600 transition hover:bg-slate-300 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#222] sm:flex-none"
                            >
                                CANCEL
                            </button>
                            <button
                                @click="submitScheduleSettings"
                                :disabled="scheduleForm.processing"
                                class="min-h-[44px] flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500 sm:flex-none"
                            >
                                SAVE CHANGES
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- EDIT COURT SETTINGS MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showCourtSettingsModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                >
                    <div
                        class="max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl sm:p-6"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">TOURNAMENT COURT SETTINGS</h2>
                            <button @click="showCourtSettingsModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="space-y-4">
                            <!-- Available courts -->
                            <div v-if="canManageTournaments" class="mt-2">
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available Courts</label>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="courtNum in props.courtCount ?? 1"
                                        :key="courtNum"
                                        type="button"
                                        @click="toggleCourtSettingsSelection(courtNum)"
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-2 text-xs font-bold transition-all duration-150',
                                            courtSettingsForm.assigned_courts.includes(courtNum)
                                                ? 'border-blue-500 bg-blue-500 text-white shadow-md shadow-blue-200 dark:border-green-400 dark:bg-green-600 dark:text-white dark:shadow-green-900/40 scale-105'
                                                : 'border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:text-blue-600 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/60 dark:hover:text-green-300',
                                        ]"
                                    >
                                        <CheckCircle
                                            v-if="courtSettingsForm.assigned_courts.includes(courtNum)"
                                            class="h-3.5 w-3.5 flex-shrink-0"
                                        />
                                        <Square
                                            v-else
                                            class="h-3.5 w-3.5 flex-shrink-0 opacity-40"
                                        />
                                        Court {{ courtNum }}
                                    </button>
                                </div>
                                <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">
                                    <span v-if="courtSettingsForm.assigned_courts.length > 0" class="font-semibold text-blue-600 dark:text-green-400">
                                        {{ courtSettingsForm.assigned_courts.length }} court{{ courtSettingsForm.assigned_courts.length > 1 ? 's' : '' }} selected
                                    </span>
                                    <span v-else>Select which courts are allocated to matches in this tournament.</span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <button
                                @click="showCourtSettingsModal = false"
                                class="min-h-[44px] flex-1 rounded-lg border border-slate-300 bg-slate-200 px-4 py-2.5 text-sm text-slate-600 transition hover:bg-slate-300 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#222] sm:flex-none"
                            >
                                CANCEL
                            </button>
                            <button
                                @click="submitCourtSettings"
                                :disabled="courtSettingsForm.processing"
                                class="min-h-[44px] flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500 sm:flex-none"
                            >
                                SAVE CHANGES
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ADD PAIR MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showAddTeamModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="closeAddTeamModal"
                >
                    <div
                        class="flex max-h-[90vh] w-full flex-col rounded-t-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl sm:p-6"
                    >
                        <div class="mb-4 flex shrink-0 items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                                {{ editingTeamId ? 'EDIT PLAYER PAIR' : 'ADD PLAYER PAIR' }}
                            </h2>
                            <button @click="closeAddTeamModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Team Preview -->
                        <div class="mb-4 shrink-0 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a]/50">
                            <div class="mb-3 text-center">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Your Team</div>
                            </div>
                            <div class="flex items-center justify-center gap-3">
                                <div class="text-center">
                                    <div v-if="teamForm.player1_name" class="flex items-center gap-1.5">
                                        <span
                                            class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white"
                                            >1</span
                                        >
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ teamForm.player1_name }}</span>
                                    </div>
                                    <div v-else class="text-sm italic text-slate-400 dark:text-slate-500">Player 1</div>
                                </div>
                                <span class="text-xs text-slate-400 dark:text-slate-500">&</span>
                                <div class="text-center">
                                    <div v-if="teamForm.player2_name" class="flex items-center gap-1.5">
                                        <span
                                            class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white"
                                            >2</span
                                        >
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ teamForm.player2_name }}</span>
                                    </div>
                                    <div v-else class="text-sm italic text-slate-400 dark:text-slate-500">Player 2</div>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Name Inputs -->
                        <div class="mb-3 grid shrink-0 grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Player 1 Name</label>
                                <input
                                    v-model="teamForm.player1_name"
                                    type="text"
                                    placeholder="Type or select"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-white dark:placeholder-slate-500 dark:focus:border-green-500"
                                />
                            </div>
                            <div>
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Player 2 Name</label>
                                <input
                                    v-model="teamForm.player2_name"
                                    type="text"
                                    placeholder="Type or select"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-white dark:placeholder-slate-500"
                                />
                            </div>
                        </div>

                        <!-- Player Cards Grid (Quick Select) -->
                        <div
                            class="min-h-0 flex-1 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/50 dark:border-[#1a1a1a] dark:bg-[#1a1a1a]/30"
                        >
                            <!-- Search -->
                            <div class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#1a1a1a]">
                                <div class="relative">
                                    <input
                                        v-model="playerSearch"
                                        type="text"
                                        placeholder="Search players..."
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 pl-8 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-white dark:placeholder-slate-500 dark:focus:border-green-500"
                                    />
                                    <svg
                                        class="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <div class="p-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="p in filteredPlayers"
                                        :key="p.id"
                                        @click="clickPlayerCard(p.name)"
                                        :disabled="isAlreadyPaired(p.name) && !isP1(p.name) && !isP2(p.name)"
                                        :class="[
                                            'relative flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left text-sm font-medium transition-all',
                                            isP1(p.name)
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm shadow-indigo-500/10 dark:bg-green-600/20 dark:text-green-200'
                                                : isP2(p.name)
                                                  ? 'border-emerald-500 bg-emerald-50 text-emerald-700 shadow-sm shadow-emerald-500/10 dark:bg-emerald-600/20 dark:text-emerald-200'
                                                  : isAlreadyPaired(p.name)
                                                    ? 'cursor-not-allowed border-slate-100 bg-slate-100/50 text-slate-400 opacity-60 dark:border-[#1a1a1a] dark:bg-[#1a1a1a]/20 dark:text-slate-600'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:-translate-y-0.5 hover:border-green-300 hover:shadow-sm dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        <!-- Avatar -->
                                        <span
                                            :class="[
                                                'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-black uppercase',
                                                isP1(p.name)
                                                    ? 'bg-indigo-600 text-white'
                                                    : isP2(p.name)
                                                      ? 'bg-emerald-600 text-white'
                                                      : isAlreadyPaired(p.name)
                                                        ? 'bg-slate-300 text-white dark:bg-[#2a2a2a] dark:text-slate-300'
                                                        : 'bg-gradient-to-br from-slate-200 to-slate-300 text-slate-700 dark:from-[#1a1a1a] dark:to-[#2a2a2a] dark:text-slate-200',
                                            ]"
                                            >{{ p.name.charAt(0) }}</span
                                        >
                                        <span class="truncate">{{ p.name }}</span>

                                        <!-- Status badges -->
                                        <span
                                            v-if="isP1(p.name)"
                                            class="ml-auto inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white"
                                            >1</span
                                        >
                                        <span
                                            v-if="isP2(p.name)"
                                            class="ml-auto inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white"
                                            >2</span
                                        >
                                        <span
                                            v-if="isAlreadyPaired(p.name) && !isP1(p.name) && !isP2(p.name)"
                                            class="ml-auto inline-flex items-center rounded-full bg-slate-400/20 px-1.5 py-0.5 text-[9px] font-black text-slate-500 dark:bg-[#2a2a2a]/30 dark:text-slate-400"
                                            >PAIRED</span
                                        >
                                    </button>
                                </div>
                                <div v-if="filteredPlayers.length === 0" class="flex flex-col items-center justify-center py-10 text-slate-400">
                                    <Users class="mb-2 h-8 w-8 opacity-30" />
                                    <p class="text-xs">No players found</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex shrink-0 items-center justify-between">
                            <span class="text-xs text-slate-500">{{ props.allPlayers.length }} players</span>
                            <div class="flex gap-2">
                                <button
                                    v-if="!editingTeamId"
                                    @click="resetAddTeamModal"
                                    class="rounded-lg bg-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-300 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                                >
                                    CLEAR
                                </button>
                                <button
                                    @click="submitTeam"
                                    :disabled="!canAddPair || teamForm.processing"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500"
                                >
                                    {{ editingTeamId ? 'SAVE CHANGES' : 'ADD PAIR' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- SCORE MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showScoreModal && scoringMatch"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="showScoreModal = false"
                >
                    <div
                        class="w-full rounded-t-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl sm:p-6"
                    >
                        <!-- Header -->
                        <div class="mb-6 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Enter Score</h2>
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Record match result</p>
                            </div>
                            <button
                                @click="showScoreModal = false"
                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-[#1a1a1a] dark:hover:text-white"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Teams -->
                        <div class="space-y-3">
                            <!-- Team 1 -->
                            <div
                                class="relative rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-blue-50 p-4 dark:border-green-500/20 dark:from-green-900/20 dark:to-green-900/10"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span
                                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-black text-white"
                                            >1</span
                                        >
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                                                {{ teamName(scoringMatch.team1) }}
                                            </p>
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Team 1</p>
                                        </div>
                                    </div>
                                    <input
                                        v-model.number="scoreForm.team1_score"
                                        type="number"
                                        min="0"
                                        max="99"
                                        oninput="if(value.length > 2) value = value.slice(0, 2)"
                                        inputmode="numeric"
                                        class="h-12 w-20 rounded-xl border-2 border-indigo-200 bg-white px-3 text-center text-2xl font-black text-indigo-700 transition focus:border-indigo-500 focus:outline-none dark:border-green-500/30 dark:bg-[#1a1a1a] dark:text-green-300 dark:focus:border-green-500"
                                    />
                                </div>
                            </div>

                            <!-- VS Divider -->
                            <div class="flex items-center justify-center py-1">
                                <div class="flex items-center gap-2 rounded-full bg-slate-100 px-4 py-1.5 dark:bg-[#1a1a1a]">
                                    <Swords class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" />
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">VS</span>
                                    <Swords class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" />
                                </div>
                            </div>

                            <!-- Team 2 -->
                            <div
                                class="relative rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-teal-50 p-4 dark:border-emerald-500/20 dark:from-emerald-900/20 dark:to-teal-900/10"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span
                                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-black text-white"
                                            >2</span
                                        >
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                                                {{ teamName(scoringMatch.team2) }}
                                            </p>
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Team 2</p>
                                        </div>
                                    </div>
                                    <input
                                        v-model.number="scoreForm.team2_score"
                                        type="number"
                                        min="0"
                                        max="99"
                                        oninput="if(value.length > 2) value = value.slice(0, 2)"
                                        inputmode="numeric"
                                        class="h-12 w-20 rounded-xl border-2 border-emerald-200 bg-white px-3 text-center text-2xl font-black text-emerald-700 transition focus:border-emerald-500 focus:outline-none dark:border-emerald-500/30 dark:bg-[#1a1a1a] dark:text-emerald-300"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Tie warning -->
                        <div
                            v-if="scoreForm.team1_score === scoreForm.team2_score && scoreForm.team1_score !== ''"
                            class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-rose-500"
                        >
                            <AlertCircle class="h-3.5 w-3.5" />
                            Scores cannot be tied
                        </div>

                        <!-- Bypass and Forfeit Options -->
                        <div v-if="!scoringMatch.winner_id" class="mt-4 border-t border-slate-100 pt-4 dark:border-[#1a1a1a]/60">
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    @click="bypassMatch"
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                                >
                                    <Clock class="h-3.5 w-3.5" />
                                    {{ scoringMatch.bypass_count > 0 ? `Bypass again (${scoringMatch.bypass_count})` : 'Bypass Match' }}
                                </button>
                                <button
                                    type="button"
                                    @click="showForfeitSection = !showForfeitSection"
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50/50 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100/50 dark:border-rose-950 dark:bg-rose-950/20 dark:text-rose-400"
                                >
                                    <AlertCircle class="h-3.5 w-3.5" />
                                    Forfeit Match
                                </button>
                            </div>

                            <!-- Forfeit Form Sub-panel -->
                            <div v-if="showForfeitSection" class="mt-4 rounded-xl border border-rose-100 bg-rose-50/30 p-3.5 dark:border-rose-950/40 dark:bg-rose-950/10">
                                <h4 class="text-xs font-black uppercase tracking-wider text-rose-800 dark:text-rose-400">Forfeit Configuration</h4>
                                
                                <div class="mt-3">
                                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Select Present (Winning) Team</label>
                                    <div class="mt-1 flex flex-col gap-2">
                                        <button
                                            type="button"
                                            @click="forfeitForm.winner_id = scoringMatch.team1_id"
                                            :class="[
                                                'w-full text-left px-3 py-2 rounded-lg text-xs font-bold transition border',
                                                forfeitForm.winner_id === scoringMatch.team1_id
                                                    ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300'
                                                    : 'border-slate-200 bg-white text-slate-700 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400'
                                            ]"
                                        >
                                            {{ teamName(scoringMatch.team1) }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="forfeitForm.winner_id = scoringMatch.team2_id"
                                            :class="[
                                                'w-full text-left px-3 py-2 rounded-lg text-xs font-bold transition border',
                                                forfeitForm.winner_id === scoringMatch.team2_id
                                                    ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300'
                                                    : 'border-slate-200 bg-white text-slate-700 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400'
                                            ]"
                                        >
                                            {{ teamName(scoringMatch.team2) }}
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Winner Score</label>
                                    <input
                                        v-model.number="forfeitForm.winning_score"
                                        type="number"
                                        min="0"
                                        max="99"
                                        class="mt-1 block w-20 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-center text-sm font-bold text-slate-700 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                                    />
                                </div>

                                <button
                                    type="button"
                                    @click="submitForfeit"
                                    :disabled="!forfeitForm.winner_id || forfeitForm.processing"
                                    class="mt-4 w-full flex min-h-[36px] items-center justify-center gap-1.5 rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-md shadow-rose-500/15 hover:bg-rose-500 disabled:opacity-40"
                                >
                                    <CheckCircle class="h-3.5 w-3.5" />
                                    Confirm Forfeit (Score: {{ forfeitForm.winner_id === scoringMatch.team1_id ? forfeitForm.winning_score : 0 }} - {{ forfeitForm.winner_id === scoringMatch.team2_id ? forfeitForm.winning_score : 0 }})
                                </button>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="mt-6 flex flex-col-reverse justify-center gap-2.5 sm:flex-row">
                            <button
                                @click="showScoreModal = false"
                                class="min-h-[48px] flex-1 rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:flex-none"
                            >
                                Cancel
                            </button>
                            <button
                                v-if="scoringMatch.winner_id"
                                @click="undoMatchResult"
                                :disabled="scoreForm.processing"
                                class="flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-rose-50 px-5 py-3 text-sm font-bold text-rose-600 transition hover:bg-rose-100 disabled:opacity-40 dark:bg-rose-900/20 dark:text-rose-400 dark:hover:bg-rose-900/40 sm:flex-none"
                            >
                                <RotateCcw class="h-4 w-4" /> Undo
                            </button>
                            <button
                                @click="submitScore"
                                :disabled="scoreForm.team1_score === scoreForm.team2_score || scoreForm.processing"
                                class="flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40 sm:flex-none"
                            >
                                <CheckCircle class="h-4 w-4" /> Save Score
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- DELETE CONFIRMATION MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showDeleteConfirm"
                    class="fixed inset-0 z-[200] flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="showDeleteConfirm = false"
                >
                    <div
                        class="w-full rounded-t-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-sm sm:rounded-2xl sm:p-6"
                    >
                        <div class="flex flex-col items-center text-center">
                            <div
                                class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-500 dark:bg-rose-900/30 dark:text-rose-400"
                            >
                                <Trash2 class="h-7 w-7" />
                            </div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Delete Tournament?</h2>
                            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                                This action cannot be undone. The tournament and all its matches will be permanently removed.
                            </p>
                        </div>
                        <div class="mt-6 flex flex-col-reverse justify-center gap-2.5 sm:flex-row">
                            <button
                                @click="showDeleteConfirm = false"
                                class="min-h-[48px] flex-1 rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:flex-none"
                            >
                                Cancel
                            </button>
                            <button
                                @click="confirmDelete"
                                :disabled="!tournamentToDelete"
                                class="flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-rose-500/20 transition hover:bg-rose-500 disabled:opacity-40 sm:flex-none"
                            >
                                <Trash2 class="h-4 w-4" /> Yes, Delete
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- BULK DELETE CONFIRMATION MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showBulkDeleteConfirm"
                    class="fixed inset-0 z-[200] flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="showBulkDeleteConfirm = false"
                >
                    <div
                        class="w-full rounded-t-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-sm sm:rounded-2xl sm:p-6"
                    >
                        <div class="flex flex-col items-center text-center">
                            <div
                                class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-500 dark:bg-rose-900/30 dark:text-rose-400"
                            >
                                <Trash2 class="h-7 w-7" />
                            </div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Delete Selected Tournaments?</h2>
                            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                                This action cannot be undone. All {{ selectedIds.size }} selected tournaments and their matches will be permanently removed.
                            </p>
                        </div>
                        <div class="mt-6 flex flex-col-reverse justify-center gap-2.5 sm:flex-row">
                            <button
                                @click="showBulkDeleteConfirm = false"
                                class="min-h-[48px] flex-1 rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:flex-none"
                            >
                                Cancel
                            </button>
                            <button
                                @click="confirmBulkDelete"
                                :disabled="selectedIds.size === 0"
                                class="flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-rose-500/20 transition hover:bg-rose-500 disabled:opacity-40 sm:flex-none"
                            >
                                <Trash2 class="h-4 w-4" /> Yes, Delete All
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- BACK TO SETUP CONFIRM -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showBackToSetupConfirm"
                    class="fixed inset-0 z-[200] flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="cancelBackToSetup"
                >
                    <div
                        class="w-full rounded-t-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-sm sm:rounded-2xl sm:p-6"
                    >
                        <div class="flex flex-col items-center text-center">
                            <div
                                class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-500 dark:bg-amber-900/30 dark:text-amber-400"
                            >
                                <RotateCcw class="h-7 w-7" />
                            </div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Revert to Setup?</h2>
                            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                                The current bracket will be cleared. No matches have been played yet, so no results will be lost. You will be able to change the bracket type, max players, and teams.
                            </p>
                        </div>
                        <div class="mt-6 flex flex-col-reverse justify-center gap-2.5 sm:flex-row">
                            <button
                                @click="cancelBackToSetup"
                                class="min-h-[48px] flex-1 rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:flex-none"
                            >
                                Cancel
                            </button>
                            <button
                                @click="confirmBackToSetup"
                                class="flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-amber-500/20 transition hover:bg-amber-400 sm:flex-none"
                            >
                                <RotateCcw class="h-4 w-4" /> Yes, Revert
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- BRACKET SETTINGS MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showBracketSettingsModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="closeBracketSettingsModal"
                >
                    <div
                        class="max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl sm:p-6"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">BRACKET SETTINGS</h2>
                            <button @click="closeBracketSettingsModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Day</label>
                                    <button
                                        v-if="canCreateTournamentDays"
                                        type="button"
                                        @click="openCreateDayModal()"
                                        class="flex items-center gap-1 text-[10px] font-semibold text-blue-600 transition hover:text-blue-500 dark:text-green-400 dark:hover:text-green-300"
                                    >
                                        <FolderPlus class="h-3 w-3" /> New day
                                    </button>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="bracketSettingsForm.tournament_day_id = null"
                                        :class="[
                                            'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                            bracketSettingsForm.tournament_day_id === null
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        Unscheduled
                                    </button>
                                    <button
                                        v-for="day in activeDaysList"
                                        :key="day.id"
                                        type="button"
                                        @click="bracketSettingsForm.tournament_day_id = day.id"
                                        :class="[
                                            'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                            bracketSettingsForm.tournament_day_id === day.id
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        {{ day.name }} · {{ dayDateLabel(day.date) }}
                                    </button>
                                </div>
                                <div v-if="bracketSettingsForm.errors.tournament_day_id" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ bracketSettingsForm.errors.tournament_day_id }}
                                </div>
                            </div>

                            <div v-if="bracketSettingsForm.tournament_day_id !== null">
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sub-folder</label>
                                    <button
                                        type="button"
                                        @click="openCreateSubFolderModal({ tournamentDayId: bracketSettingsForm.tournament_day_id })"
                                        class="flex items-center gap-1 text-[10px] font-semibold text-violet-600 transition hover:text-violet-500 dark:text-violet-400 dark:hover:text-violet-300"
                                    >
                                        <FolderPlus class="h-3 w-3" /> New sub-folder
                                    </button>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="bracketSettingsForm.tournament_sub_folder_id = null"
                                        :class="[
                                            'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                            bracketSettingsForm.tournament_sub_folder_id === null
                                                ? 'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-500 dark:bg-violet-500/20 dark:text-violet-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-violet-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-violet-500/40',
                                        ]"
                                    >
                                        Unfiled
                                    </button>
                                    <button
                                        v-for="sub in subFoldersForDay(bracketSettingsForm.tournament_day_id)"
                                        :key="sub.id"
                                        type="button"
                                        @click="bracketSettingsForm.tournament_sub_folder_id = sub.id"
                                        :class="[
                                            'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                            bracketSettingsForm.tournament_sub_folder_id === sub.id
                                                ? 'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-500 dark:bg-violet-500/20 dark:text-violet-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-violet-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-violet-500/40',
                                        ]"
                                    >
                                        <FolderOpen class="mr-1 inline h-3 w-3" />
                                        {{ sub.name }}
                                    </button>
                                </div>
                                <p v-if="subFoldersForDay(bracketSettingsForm.tournament_day_id).length === 0" class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                    No sub-folders yet for this day.
                                </p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Category</label>
                                <div class="mt-2 grid grid-cols-3 gap-2">
                                    <button
                                        v-for="opt in categoryOptions"
                                        :key="opt.value"
                                        type="button"
                                        @click="bracketSettingsForm.category = opt.value"
                                        :class="[
                                            'min-h-[44px] rounded-lg border px-3 py-3 text-center text-sm font-bold transition',
                                            bracketSettingsForm.category === opt.value
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                                <div v-if="bracketSettingsForm.errors.category" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ bracketSettingsForm.errors.category }}
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Bracket Type</label>
                                <div class="mt-2 grid grid-cols-1 gap-2">
                                    <label
                                        v-for="opt in [
                                            { value: 'single_elimination', label: 'Single Elimination', desc: 'One loss and you\'re out.' },
                                            { value: 'double_elimination', label: 'Double Elimination', desc: 'Winners + losers brackets.' },
                                            { value: 'round_robin', label: 'Round Robin', desc: 'Every team plays every other team.' },
                                        ]"
                                        :key="opt.value"
                                        :class="[
                                            'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition',
                                            bracketSettingsForm.type === opt.value
                                                ? 'border-blue-500 bg-blue-50 dark:border-green-600 dark:bg-green-900/20'
                                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:hover:border-[#2a2a2a]'
                                        ]"
                                    >
                                        <input
                                            v-model="bracketSettingsForm.type"
                                            type="radio"
                                            :value="opt.value"
                                            class="mt-1 h-4 w-4 cursor-pointer accent-blue-600 dark:accent-green-500"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-bold text-slate-900 dark:text-white">{{ opt.label }}</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ opt.desc }}</div>
                                        </div>
                                    </label>
                                </div>
                                <div v-if="bracketSettingsForm.errors.type" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ bracketSettingsForm.errors.type }}
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Max Players (Slots)</label>
                                <div class="mt-2 grid grid-cols-4 gap-2">
                                    <button
                                        v-for="count in (bracketSettingsForm.type === 'round_robin' ? [3, 4, 5, 8] : validDeCounts)"
                                        :key="count"
                                        type="button"
                                        @click="bracketSettingsForm.max_players = count"
                                        :class="[
                                            'min-h-[44px] rounded-lg border px-3 py-3 text-center font-bold transition',
                                            bracketSettingsForm.max_players === count
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-green-500 dark:bg-green-600/20 dark:text-green-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/40',
                                        ]"
                                    >
                                        {{ count }}
                                    </button>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                    Must be at least the current team count ({{ activeTournament?.teams?.length || 0 }}).
                                </p>
                                <div v-if="bracketSettingsForm.errors.max_players" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ bracketSettingsForm.errors.max_players }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button
                                @click="closeBracketSettingsModal"
                                class="min-h-[44px] rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:px-5"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitBracketSettings"
                                :disabled="bracketSettingsForm.processing"
                                class="flex min-h-[44px] items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-500 disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500"
                            >
                                <CheckCircle class="h-4 w-4" /> Save
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- CHANGE FOLDER DURATION MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showFolderScheduleModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="closeFolderScheduleModal"
                >
                    <div
                        class="max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl sm:p-6"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">CHANGE FOLDER DURATION</h2>
                            <button @click="closeFolderScheduleModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Start Time</label>
                                <input
                                    v-model="folderScheduleForm.start_time"
                                    type="time"
                                    class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500"
                                />
                                <div v-if="folderScheduleForm.errors.start_time" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ folderScheduleForm.errors.start_time }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Match Duration (mins)</label>
                                    <input
                                        v-model.number="folderScheduleForm.match_duration"
                                        type="number"
                                        min="1"
                                        class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500"
                                    />
                                    <div v-if="folderScheduleForm.errors.match_duration" class="mt-1 text-[10px] font-semibold text-red-500">
                                        {{ folderScheduleForm.errors.match_duration }}
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Rest Time (mins)</label>
                                    <input
                                        v-model.number="folderScheduleForm.rest_time"
                                        type="number"
                                        min="0"
                                        class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500"
                                    />
                                    <div v-if="folderScheduleForm.errors.rest_time" class="mt-1 text-[10px] font-semibold text-red-500">
                                        {{ folderScheduleForm.errors.rest_time }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 py-1">
                                <input
                                    v-model="folderScheduleForm.enable_break"
                                    type="checkbox"
                                    id="folder_enable_break"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-[#0a0a0a] dark:focus:ring-green-500"
                                />
                                <label for="folder_enable_break" class="cursor-pointer text-xs font-semibold uppercase text-slate-500 dark:text-slate-400"
                                    >Enable Break Time</label
                                >
                            </div>

                            <Transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="opacity-0 -translate-y-2"
                                leave-active-class="transition duration-100 ease-in"
                                leave-to-class="opacity-0 -translate-y-2"
                            >
                                <div v-if="folderScheduleForm.enable_break" class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Break Start</label>
                                        <input
                                            v-model="folderScheduleForm.break_start"
                                            type="time"
                                            class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500"
                                        />
                                        <div v-if="folderScheduleForm.errors.break_start" class="mt-1 text-[10px] font-semibold text-red-500">
                                            {{ folderScheduleForm.errors.break_start }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Break End</label>
                                        <input
                                            v-model="folderScheduleForm.break_end"
                                            type="time"
                                            class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500"
                                        />
                                        <div v-if="folderScheduleForm.errors.break_end" class="mt-1 text-[10px] font-semibold text-red-500">
                                            {{ folderScheduleForm.errors.break_end }}
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button
                                @click="closeFolderScheduleModal"
                                class="min-h-[44px] rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:px-5"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitFolderScheduleSettings"
                                :disabled="folderScheduleForm.processing"
                                class="flex min-h-[44px] items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-500 disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500"
                            >
                                <CheckCircle class="h-4 w-4" /> Save
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ARCHIVE MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showArchiveModal"
                    class="fixed inset-0 z-[200] flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4"
                    @click.self="showArchiveModal = false"
                >
                    <div
                        class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-lg sm:rounded-2xl"
                    >
                        <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-[#1a1a1a]">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-green-900/20 dark:text-green-400"
                                >
                                    <Archive class="h-4 w-4" />
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Archived Folders</h2>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Folders moved to archive — reopen to restore
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="showArchiveModal = false"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-[#1a1a1a] dark:hover:text-white"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="custom-scrollbar flex-1 space-y-5 overflow-y-auto p-5">
                            <!-- Archived Days -->
                            <section>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Archived Folders ({{ archivedDays.length }})
                                    </h3>
                                </div>
                                <div
                                    v-if="archivedDays.length === 0"
                                    class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-400 dark:border-[#1a1a1a] dark:text-slate-500"
                                >
                                    No archived folders yet. Finish a day then click the archive icon to move it here.
                                </div>
                                <div v-else class="space-y-2">
                                    <div
                                        v-for="d in archivedDays"
                                        :key="`archived-day-${d.id}`"
                                        class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#1a1a1a]/40"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ d.name }}</p>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                                    {{ dayDateLabel(d.date) }} · {{ d.tournaments_count }} tournament(s)
                                                </p>
                                            </div>
                                            <button
                                                @click="reopenDayFromArchive(d.id)"
                                                class="flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-600 transition hover:bg-slate-100 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-slate-300 dark:hover:bg-[#1a1a1a]"
                                            >
                                                <ArchiveRestore class="h-3 w-3" /> Reopen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- PLAYER FINISH DAY CONFIRM -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showPlayerFinishDayConfirm"
                    class="fixed inset-0 z-[210] flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4"
                    @click.self="closePlayerFinishDayConfirm()"
                >
                    <div class="w-full rounded-t-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-lg sm:rounded-2xl">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">
                                <CheckCircle class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Finish this day?</h2>
                                <p class="text-sm text-slate-500 dark:text-slate-400">This will lock your tournament workspace into view-only mode.</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600 dark:border-[#1a1a1a] dark:bg-[#111111] dark:text-slate-300">
                            After you finish the day, you can still view your tournament, but you will no longer be able to edit teams, scores, bracket setup, or settings.
                            If you need changes later, you must send a new edit access request to the scheduler.
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                @click="closePlayerFinishDayConfirm()"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-[#1a1a1a] dark:text-slate-300"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="confirmPlayerFinishDay"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-500"
                            >
                                Yes, Finish Day
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- PLAYER EDIT ACCESS REQUEST MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showEditAccessRequestModal"
                    class="fixed inset-0 z-[210] flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4"
                    @click.self="showEditAccessRequestModal = false"
                >
                    <div class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-2xl sm:rounded-2xl">
                        <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-[#1a1a1a]">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-green-900/20 dark:text-green-400">
                                    <ShieldCheck class="h-4 w-4" />
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Request Main Folder Access</h2>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Ask the scheduler to reopen your finished tournament main folder.</p>
                                </div>
                            </div>
                            <button
                                @click="showEditAccessRequestModal = false"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-[#1a1a1a] dark:hover:text-white"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <form class="custom-scrollbar flex-1 space-y-4 overflow-y-auto p-5" @submit.prevent="submitEditAccessRequest">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Main folder</label>
                                <input
                                    :value="editAccessRequestFolderName"
                                    type="text"
                                    readonly
                                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-[#1a1a1a] dark:bg-[#111111] dark:text-slate-200"
                                />
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-[#1a1a1a] dark:bg-[#111111] dark:text-slate-300">
                                This request is only for reopening your finished main folder so you can update the tournament again.
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Reason for access</label>
                                <textarea
                                    v-model="editAccessRequestForm.notes"
                                    rows="5"
                                    placeholder="Explain why you need the main folder reopened."
                                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                                ></textarea>
                                <p v-if="editAccessRequestForm.errors.notes" class="mt-1 text-xs text-rose-500">{{ editAccessRequestForm.errors.notes }}</p>
                            </div>
                            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 dark:border-[#1a1a1a] sm:flex-row sm:justify-end">
                                <button
                                    type="button"
                                    @click="showEditAccessRequestModal = false"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-[#1a1a1a] dark:text-slate-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="editAccessRequestForm.processing"
                                    class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-500 dark:bg-green-600 dark:hover:bg-green-500"
                                >
                                    <ShieldCheck class="h-4 w-4" />
                                    Submit Access Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- TOURNAMENT REQUESTS MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showTournamentRequestsModal"
                    class="fixed inset-0 z-[210] flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4"
                    @click.self="showTournamentRequestsModal = false"
                >
                    <div
                        class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-4xl sm:rounded-2xl"
                    >
                        <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-[#1a1a1a]">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-green-900/20 dark:text-green-400"
                                >
                                    <ShieldCheck class="h-4 w-4" />
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Player Tournament Requests</h2>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Review player requests and approve access into tournament workspaces
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="showTournamentRequestsModal = false"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-[#1a1a1a] dark:hover:text-white"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Status Filter Bar -->
                        <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/50 px-5 py-3 dark:border-[#1a1a1a] dark:bg-[#0c0c0c] overflow-x-auto shrink-0">
                            <button
                                type="button"
                                @click="requestStatusFilter = 'all'"
                                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer"
                                :class="requestStatusFilter === 'all' ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900' : 'bg-white text-slate-600 hover:bg-slate-200 dark:bg-[#181818] dark:text-slate-300'"
                            >
                                All ({{ tournamentRequests.length }})
                            </button>
                            <button
                                type="button"
                                @click="requestStatusFilter = 'pending'"
                                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer inline-flex items-center gap-1.5"
                                :class="requestStatusFilter === 'pending' ? 'bg-amber-500 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-200 dark:bg-[#181818] dark:text-slate-300'"
                            >
                                <span>Pending</span>
                                <span v-if="pendingRequestsCount > 0" class="rounded-full bg-amber-600 px-1.5 py-0.5 text-[10px] text-white">{{ pendingRequestsCount }}</span>
                            </button>
                            <button
                                type="button"
                                @click="requestStatusFilter = 'approved'"
                                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer inline-flex items-center gap-1.5"
                                :class="requestStatusFilter === 'approved' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-200 dark:bg-[#181818] dark:text-slate-300'"
                            >
                                <span>In Progress</span>
                                <span v-if="approvedRequestsCount > 0" class="rounded-full bg-emerald-700 px-1.5 py-0.5 text-[10px] text-white">{{ approvedRequestsCount }}</span>
                            </button>
                            <button
                                type="button"
                                @click="requestStatusFilter = 'completed'"
                                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer inline-flex items-center gap-1.5"
                                :class="requestStatusFilter === 'completed' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-200 dark:bg-[#181818] dark:text-slate-300'"
                            >
                                <span>Completed</span>
                                <span v-if="completedRequestsCount > 0" class="rounded-full bg-blue-700 px-1.5 py-0.5 text-[10px] text-white">{{ completedRequestsCount }}</span>
                            </button>
                            <button
                                type="button"
                                @click="requestStatusFilter = 'rejected'"
                                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer inline-flex items-center gap-1.5"
                                :class="requestStatusFilter === 'rejected' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-200 dark:bg-[#181818] dark:text-slate-300'"
                            >
                                <span>Rejected</span>
                                <span v-if="rejectedRequestsCount > 0" class="rounded-full bg-rose-700 px-1.5 py-0.5 text-[10px] text-white">{{ rejectedRequestsCount }}</span>
                            </button>
                        </div>

                        <div class="custom-scrollbar flex-1 space-y-4 overflow-y-auto p-5">
                            <div
                                v-for="requestItem in filteredTournamentRequests"
                                :key="requestItem.id"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-[#1a1a1a] dark:bg-[#111111]"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="space-y-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ requestItem.name }}</h3>
                                            <span
                                                class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-wider"
                                                :class="
                                                    requestItem.status === 'approved' && requestItem.tournamentDay?.status === 'finished'
                                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'
                                                        : requestItem.status === 'approved'
                                                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                          : requestItem.status === 'rejected'
                                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'
                                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'
                                                "
                                            >
                                                {{
                                                    requestItem.status === 'approved' && requestItem.tournamentDay?.status === 'finished'
                                                        ? 'Completed'
                                                        : requestItem.status === 'approved'
                                                          ? 'In Progress'
                                                          : requestItem.status
                                                }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            Requested by <strong>{{ requestItem.user?.username || requestItem.user?.name }}</strong> ({{ requestItem.user?.email }})
                                        </p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            Request type:
                                            <strong class="text-slate-700 dark:text-slate-200">
                                                {{ requestItem.request_type === 'edit_access' ? 'Edit access' : 'New tournament' }}
                                            </strong>
                                        </p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            Venue: {{ requestItem.venue?.name }}
                                        </p>
                                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                            <span v-if="requestItem.preferred_date">🗓️ {{ requestItem.preferred_date }}</span>
                                            <span v-if="requestItem.preferred_start_time">⏰ {{ requestItem.preferred_start_time }}</span>
                                        </div>
                                        <p v-if="requestItem.notes" class="max-w-3xl text-sm text-slate-700 dark:text-slate-300">
                                            📝 <strong>Notes:</strong> {{ requestItem.notes }}
                                        </p>
                                        <p v-if="requestItem.rejection_reason" class="text-sm text-rose-600 dark:text-rose-300">
                                            Reason for rejection: {{ requestItem.rejection_reason }}
                                        </p>
                                        <p v-if="requestItem.tournamentDay && !requestItem.tournament" class="text-sm text-emerald-600 dark:text-emerald-300">
                                            Main folder: {{ requestItem.tournamentDay.name }} ({{ requestItem.tournamentDay.status }})
                                        </p>
                                        <p v-if="requestItem.tournament" class="text-sm text-emerald-600 dark:text-emerald-300">
                                            {{ requestItem.request_type === 'edit_access' ? 'Target tournament:' : 'Tournament linked:' }}
                                            {{ requestItem.tournament.name }} ({{ requestItem.tournament.status }})
                                        </p>
                                    </div>

                                    <div class="flex flex-col gap-3 lg:w-72 shrink-0">
                                        <template v-if="requestItem.status === 'pending'">
                                            <button
                                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-500 shadow-sm cursor-pointer"
                                                @click="approveTournamentRequest(requestItem.id)"
                                            >
                                                <CheckCircle class="h-4 w-4" />
                                                {{ requestItem.request_type === 'edit_access' ? 'Approve & Unlock Access' : 'Approve & Create Folder' }}
                                            </button>
                                            <button
                                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-rose-200 px-4 py-3 text-sm font-bold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10 cursor-pointer"
                                                @click="openRejectTournamentRequest(requestItem.id)"
                                            >
                                                <AlertCircle class="h-4 w-4" />
                                                Reject Request
                                            </button>
                                            <form
                                                v-if="rejectingRequestId === requestItem.id"
                                                class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-[#1a1a1a]"
                                                @submit.prevent="submitRejectTournamentRequest(requestItem.id)"
                                            >
                                                <textarea
                                                    v-model="requestRejectForm.rejection_reason"
                                                    rows="3"
                                                    placeholder="Optional reason for rejection"
                                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                                                ></textarea>
                                                <div class="flex gap-2">
                                                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white cursor-pointer">Confirm Reject</button>
                                                    <button
                                                        type="button"
                                                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-[#1a1a1a] dark:text-slate-300 cursor-pointer"
                                                        @click="rejectingRequestId = null"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </template>

                                        <template v-else-if="requestItem.status === 'approved'">
                                            <button
                                                type="button"
                                                @click="showTournamentRequestsModal = false"
                                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200 cursor-pointer"
                                            >
                                                <FolderOpen class="h-4 w-4" />
                                                View Workspace
                                            </button>
                                        </template>

                                        <template v-else-if="requestItem.status === 'rejected'">
                                            <button
                                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-500 shadow-sm cursor-pointer"
                                                @click="approveTournamentRequest(requestItem.id)"
                                            >
                                                <CheckCircle class="h-4 w-4" />
                                                Re-approve Access
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="filteredTournamentRequests.length === 0"
                                class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400 dark:border-[#1a1a1a] dark:text-slate-500"
                            >
                                No {{ requestStatusFilter === 'all' ? '' : requestStatusFilter }} tournament requests found.
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- EDIT MATCH TEAMS MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showEditMatchModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="closeEditMatchModal"
                >
                    <div
                        class="w-full rounded-t-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl sm:p-6"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Edit Match Pairing</h2>
                            <button @click="closeEditMatchModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Team 1</label>
                                <select
                                    v-model="matchTeamForm.team1_id"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
                                >
                                    <option :value="null">-- Select Team --</option>
                                    <option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ teamName(team) }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Team 2</label>
                                <select
                                    v-model="matchTeamForm.team2_id"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
                                >
                                    <option :value="null">-- Select Team --</option>
                                    <option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ teamName(team) }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Scheduled Time</label>
                                <input
                                    v-model="matchTeamForm.scheduled_time"
                                    type="time"
                                    class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
                                />
                            </div>
                            <div v-if="!activeTournament?.tournament_sub_folder_id">
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Court Assignment</label>
                                <select
                                    v-model="matchTeamForm.court_number"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-green-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
                                >
                                    <option :value="null">-- Select Court --</option>
                                    <option v-for="c in props.courtCount ?? 1" :key="c" :value="c">Court {{ c }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col-reverse justify-end gap-2.5 sm:flex-row">
                            <button
                                @click="closeEditMatchModal"
                                class="min-h-[48px] flex-1 rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:flex-none"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitMatchTeams"
                                :disabled="matchTeamForm.processing"
                                class="flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-500 disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500 sm:flex-none"
                            >
                                Save Pairing
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- SWAP OPPONENTS MODAL -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showSwapModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center"
                    @click.self="closeSwapModal"
                >
                    <div
                        class="w-full rounded-t-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl sm:p-6"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20">
                                    <ArrowLeftRight class="h-5 w-5 text-amber-500 dark:text-amber-400" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Swap Opponents</h2>
                                    <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">First round only</p>
                                </div>
                            </div>
                            <button @click="closeSwapModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Source Match -->
                        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#1a1a1a]">
                            <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Your Match</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-slate-900 dark:text-white">{{ teamName(swapSourceMatch?.team1) }}</span>
                                <span class="text-[10px] font-black uppercase text-slate-400 dark:text-slate-500">VS</span>
                                <span class="text-right text-sm font-bold text-slate-900 dark:text-white">{{
                                    teamName(swapSourceMatch?.team2)
                                }}</span>
                            </div>
                        </div>

                        <!-- Candidate Matches -->
                        <div class="mb-4">
                            <p class="mb-2 text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                                Select a match to swap opponents with
                            </p>
                            <div v-if="swapCandidates.length === 0" class="py-4 text-center text-xs text-slate-400">
                                No available matches to swap with.
                            </div>
                            <div v-else class="custom-scrollbar max-h-60 space-y-2 overflow-y-auto">
                                <button
                                    v-for="match in swapCandidates"
                                    :key="match.id"
                                    @click="swapForm.other_match_id = match.id"
                                    :class="[
                                        'flex w-full items-center justify-between rounded-xl border p-3 text-left transition',
                                        swapForm.other_match_id === match.id
                                            ? 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-900/20'
                                            : 'border-slate-200 hover:border-slate-300 dark:border-[#1a1a1a] dark:hover:border-slate-700',
                                    ]"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ teamName(match.team1) }}</span>
                                            <span class="shrink-0 text-[10px] font-black uppercase text-slate-400 dark:text-slate-500">VS</span>
                                            <span class="truncate text-right text-sm font-bold text-slate-900 dark:text-white">{{
                                                teamName(match.team2)
                                            }}</span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="swapForm.other_match_id === match.id"
                                        class="ml-3 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500"
                                    >
                                        <CheckCircle class="h-3.5 w-3.5 text-white" />
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col-reverse justify-end gap-2.5 sm:flex-row">
                            <button
                                @click="closeSwapModal"
                                class="min-h-[48px] flex-1 rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a] sm:flex-none"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitSwap"
                                :disabled="!swapForm.other_match_id || swapForm.processing"
                                class="flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-amber-500/20 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-40 sm:flex-none"
                            >
                                <ArrowLeftRight class="h-4 w-4" /> Swap Opponents
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- TOURNAMENT SUB-FOLDER MODAL (create / edit) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showSubFolderModal"
                    class="fixed inset-0 z-[220] flex items-end justify-center bg-black/60 sm:items-center"
                >
                    <div class="flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl dark:bg-[#0a0a0a] sm:rounded-2xl">
                        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-[#1a1a1a]">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-500/20">
                                    <FolderPlus v-if="subFolderModalMode === 'create'" class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                    <Pencil v-else class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                </div>
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                                    {{ subFolderModalMode === 'create' ? 'New Sub-Folder' : 'Edit Sub-Folder' }}
                                </h3>
                            </div>
                            <button
                                @click="closeSubFolderModal"
                                class="text-slate-400 hover:text-slate-600 dark:hover:text-white"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Name</label>
                                    <input
                                        v-model="subFolderForm.name"
                                        type="text"
                                        maxlength="255"
                                        placeholder="e.g. Morning Brackets"
                                        class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 placeholder-slate-400 focus:border-violet-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-[#EDEDEC] dark:placeholder-slate-500 dark:focus:border-violet-500 sm:text-sm"
                                    />
                                    <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">A sub-folder groups tournaments within a day (e.g. by time of day or skill level).</p>
                                    <div v-if="subFolderForm.errors.name" class="mt-1 text-[10px] font-semibold text-red-500">
                                        {{ subFolderForm.errors.name }}
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Day</label>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        <button
                                            v-for="day in activeDaysList"
                                            :key="day.id"
                                            type="button"
                                            :disabled="subFolderModalMode === 'edit'"
                                            @click="subFolderForm.tournament_day_id = day.id"
                                            :class="[
                                                'min-h-[40px] rounded-lg border px-3 py-2 text-xs font-bold transition',
                                                subFolderModalMode === 'edit' ? 'cursor-not-allowed opacity-60' : '',
                                                subFolderForm.tournament_day_id === day.id
                                                    ? 'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-500 dark:bg-violet-500/20 dark:text-violet-300'
                                                    : 'border-slate-200 bg-white text-slate-600 hover:border-violet-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-violet-500/40',
                                            ]"
                                        >
                                            {{ day.name }} · {{ dayDateLabel(day.date) }}
                                        </button>
                                    </div>
                                    <p v-if="tournamentDaysList.length === 0" class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                        No days exist. Create a day first.
                                    </p>
                                    <p v-if="subFolderModalMode === 'edit'" class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                        Day cannot be changed after creation.
                                    </p>
                                    <div v-if="subFolderForm.errors.tournament_day_id" class="mt-1 text-[10px] font-semibold text-red-500">
                                        {{ subFolderForm.errors.tournament_day_id }}
                                    </div>
                                </div>
                                <div v-if="canManageTournaments && subFolderModalMode === 'create'">
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Assign Scorer</label>
                                    <select
                                        v-model="subFolderForm.assigned_scorer_id"
                                        class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-violet-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-[#EDEDEC] dark:focus:border-violet-500 sm:text-sm"
                                    >
                                        <option :value="null">No scorer assigned</option>
                                        <option v-for="s in scorers ?? []" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                    <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">The assigned scorer can view and score brackets in this sub-folder.</p>
                                </div>
                                <div v-if="canManageTournaments || isPlayerRole">
                                    <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available Courts</label>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button
                                            v-for="courtNum in availableSubFolderCourts"
                                            :key="courtNum"
                                            type="button"
                                            @click="toggleCourtSelection(courtNum)"
                                            :class="[
                                                'inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-2 text-xs font-bold transition-all duration-150',
                                                subFolderForm.assigned_courts.includes(courtNum)
                                                    ? 'border-violet-500 bg-violet-500 text-white shadow-md shadow-violet-200 dark:border-violet-400 dark:bg-violet-500 dark:text-white dark:shadow-violet-900/40 scale-105'
                                                    : 'border-slate-200 bg-white text-slate-500 hover:border-violet-300 hover:text-violet-600 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-violet-500/60 dark:hover:text-violet-300',
                                            ]"
                                        >
                                            <CheckCircle
                                                v-if="subFolderForm.assigned_courts.includes(courtNum)"
                                                class="h-3.5 w-3.5 flex-shrink-0"
                                            />
                                            <Square
                                                v-else
                                                class="h-3.5 w-3.5 flex-shrink-0 opacity-40"
                                            />
                                            Court {{ courtNum }}
                                        </button>
                                    </div>
                                    <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">
                                        <span v-if="subFolderForm.assigned_courts.length > 0" class="font-semibold text-violet-600 dark:text-violet-400">
                                            {{ subFolderForm.assigned_courts.length }} court{{ subFolderForm.assigned_courts.length > 1 ? 's' : '' }} selected
                                        </span>
                                        <span v-else>Select which courts are allocated to matches in this sub-folder.</span>
                                    </p>
                                    <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                        <span v-if="selectedSubFolderDay?.assigned_courts?.length">
                                            You can only choose from the scheduler-approved courts in this main folder.
                                        </span>
                                        <span v-else>
                                            No main-folder court restriction is set yet, so you can choose from the full venue court list.
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                            <button
                                @click="submitSubFolderForm"
                                :disabled="subFolderForm.processing || !subFolderForm.name.trim() || !subFolderForm.tournament_day_id"
                                class="flex min-h-[40px] items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-violet-500 dark:hover:bg-violet-400"
                            >
                                {{ subFolderModalMode === 'create' ? 'Create Sub-Folder' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- TOURNAMENT DAY MODAL (create / edit) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showDayModal"
                    class="fixed inset-0 z-[210] flex items-end justify-center bg-black/60 sm:items-center"
                >
                    <div
                        class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:max-w-md sm:rounded-2xl"
                    >
                        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-4 py-4 dark:border-[#1a1a1a] sm:px-6">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-green-900/20 dark:text-green-400">
                                    <FolderPlus v-if="dayModalMode === 'create'" class="h-4 w-4" />
                                    <Pencil v-else class="h-4 w-4" />
                                </div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                                    {{ dayModalMode === 'create' ? 'NEW TOURNAMENT DAY' : 'EDIT TOURNAMENT DAY' }}
                                </h2>
                            </div>
                            <button @click="closeDayModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-6 sm:py-6">
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Name</label>
                                <input
                                    v-model="dayForm.name"
                                    type="text"
                                    maxlength="255"
                                    placeholder="e.g. MT — Day 1"
                                    class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:placeholder-slate-500 dark:focus:border-green-500 sm:text-sm"
                                />
                                <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                    Used as section header on the tournament list. Cards assigned to this day will be grouped below it.
                                </p>
                                <div v-if="dayForm.errors.name" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ dayForm.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Date</label>
                                <input
                                    v-model="dayForm.date"
                                    type="date"
                                    class="mt-1 min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:focus:border-green-500 sm:text-sm"
                                />
                                <div v-if="dayForm.errors.date" class="mt-1 text-[10px] font-semibold text-red-500">
                                    {{ dayForm.errors.date }}
                                </div>
                            </div>
                            <div v-if="canManageTournaments">
                                <label class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available Courts For This Main Folder</label>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="courtNum in props.courtCount ?? 1"
                                        :key="courtNum"
                                        type="button"
                                        @click="toggleDayCourtSelection(courtNum)"
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-2 text-xs font-bold transition-all duration-150',
                                            dayForm.assigned_courts.includes(courtNum)
                                                ? 'border-blue-500 bg-blue-500 text-white shadow-md shadow-blue-200 dark:border-green-400 dark:bg-green-500 dark:text-white dark:shadow-green-900/40 scale-105'
                                                : 'border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:text-blue-600 dark:border-[#2a2a2a] dark:bg-[#0a0a0a] dark:text-slate-400 dark:hover:border-green-500/60 dark:hover:text-green-300',
                                        ]"
                                    >
                                        <CheckCircle
                                            v-if="dayForm.assigned_courts.includes(courtNum)"
                                            class="h-3.5 w-3.5 flex-shrink-0"
                                        />
                                        <Square
                                            v-else
                                            class="h-3.5 w-3.5 flex-shrink-0 opacity-40"
                                        />
                                        Court {{ courtNum }}
                                    </button>
                                </div>
                                <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">
                                    <span v-if="dayForm.assigned_courts.length > 0" class="font-semibold text-blue-600 dark:text-green-400">
                                        {{ dayForm.assigned_courts.length }} court{{ dayForm.assigned_courts.length > 1 ? 's' : '' }} selected
                                    </span>
                                    <span v-else>No dedicated courts selected yet. Player workspaces under this folder can use the venue defaults.</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col-reverse gap-2 border-t border-slate-200 px-4 py-4 dark:border-[#1a1a1a] sm:flex-row sm:justify-end sm:px-6">
                            <button
                                type="button"
                                @click="submitDayForm"
                                :disabled="dayForm.processing || !dayForm.name.trim() || !dayForm.date"
                                class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-green-600 dark:hover:bg-green-500 sm:w-auto"
                            >
                                <CheckCircle class="h-4 w-4" />
                                {{ dayModalMode === 'create' ? 'Create Day' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- FLOATING MULTI-SELECT ACTION BAR -->
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 translate-y-4"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div
                v-if="showBulkBar && !activeTournament && canUseBulkActions && !isAnyModalOpen"
                class="floating-action-bar fixed bottom-4 left-1/2 z-[300] flex w-[calc(100%-2rem)] max-w-3xl -translate-x-1/2 items-center gap-1.5 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-2xl backdrop-blur dark:border-[#1a1a1a] dark:bg-[#0f0f0f]/95 sm:bottom-6 sm:gap-3 sm:p-3 sm:pl-5 transition-all duration-300 ease-in-out"
            >
                    <span class="flex flex-shrink-0 whitespace-nowrap items-center gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                        <span class="inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-blue-600 px-2 text-xs font-black text-white dark:bg-green-600">
                            {{ selectedIds.size }}
                        </span>
                        <span class="hidden xs:inline">selected</span>
                    </span>

                    <button
                        type="button"
                        @click="clearSelection"
                        class="ml-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-[#1a1a1a] dark:hover:text-white"
                        title="Clear selection"
                    >
                        <X class="h-4 w-4" />
                    </button>

                    <div class="mx-0.5 h-6 w-px flex-shrink-0 bg-slate-200 dark:bg-[#1a1a1a] sm:mx-2"></div>

                    <div v-if="canMoveTournamentsBetweenDays" class="relative flex-shrink-0">
                        <button
                            type="button"
                            @click.stop="showDayMoveMenu = !showDayMoveMenu"
                            class="flex min-h-[40px] items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] whitespace-nowrap flex-shrink-0"
                        >
                            <FolderInput class="h-4 w-4" />
                            <span class="hidden sm:inline">Move to day</span>
                            <span class="inline sm:hidden">Move</span>
                            <ChevronDown class="h-3.5 w-3.5" :class="showDayMoveMenu ? 'rotate-180' : ''" />
                        </button>
                        <div
                            v-if="showDayMoveMenu"
                            class="absolute bottom-full left-0 z-10 mb-2 w-72 max-h-80 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                        >
                            <div v-if="tournamentDaysList.length === 0" class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                No tournament days yet. Create one first.
                            </div>
                            <button
                                v-for="day in activeDaysList"
                                :key="day.id"
                                type="button"
                                @click="moveSelectedToDay(day.id)"
                                class="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-[#1a1a1a]"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold text-slate-900 dark:text-white">{{ day.name }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ dayDateLabel(day.date) }}</div>
                                </div>
                                <span class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-[#1a1a1a] dark:text-slate-400">
                                    {{ day.tournaments_count ?? 0 }}
                                </span>
                            </button>
                            <div v-if="tournamentDaysList.length > 0" class="border-t border-slate-100 dark:border-[#1a1a1a]"></div>
                            <button
                                v-if="tournamentDaysList.length > 0"
                                type="button"
                                @click="moveSelectedToDay(null)"
                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/20"
                            >
                                <X class="h-3.5 w-3.5" />
                                Remove from day
                            </button>
                        </div>
                    </div>

                    <button
                        v-if="canCreateTournamentDays"
                        type="button"
                        @click="createDayFromSelection"
                        class="flex min-h-[40px] items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-200 dark:hover:bg-[#1a1a1a] whitespace-nowrap flex-shrink-0"
                    >
                        <FolderPlus class="h-4 w-4" />
                        <span class="hidden md:inline">Create new day</span>
                        <span class="hidden sm:inline md:hidden">New day</span>
                        <span class="inline sm:hidden">Day</span>
                    </button>

                    <button
                        v-if="canBulkDeleteTournaments"
                        type="button"
                        @click="bulkDeleteSelected"
                        class="flex min-h-[40px] items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-900/30 dark:bg-[#0f0f0f] dark:text-red-400 dark:hover:bg-red-950/20 whitespace-nowrap flex-shrink-0"
                    >
                        <Trash2 class="h-4 w-4" />
                        <span class="hidden md:inline">Delete selected</span>
                        <span class="inline md:hidden">Delete</span>
                    </button>

                    <!-- Sub-folder move (only when first selected card has a day) -->
                    <div
                        v-if="firstSelectedDayId !== null"
                        class="relative flex-shrink-0"
                        data-sub-folder-move-button
                    >
                        <button
                            type="button"
                            @click.stop="showSubFolderMoveMenu = !showSubFolderMoveMenu"
                            data-sub-folder-move-button
                            class="flex min-h-[40px] items-center gap-1.5 rounded-lg border border-violet-200 bg-white px-2.5 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/30 dark:bg-[#0f0f0f] dark:text-violet-300 dark:hover:bg-violet-500/10 whitespace-nowrap flex-shrink-0"
                        >
                            <FolderInput class="h-4 w-4" />
                            <span class="hidden lg:inline">Move to sub-folder</span>
                            <span class="hidden sm:inline lg:hidden">Move sub</span>
                            <span class="inline sm:hidden">Sub</span>
                            <ChevronDown class="h-3.5 w-3.5" :class="showSubFolderMoveMenu ? 'rotate-180' : ''" />
                        </button>
                        <div
                            v-if="showSubFolderMoveMenu"
                            data-sub-folder-move-menu
                            class="absolute bottom-full left-0 z-30 mb-2 max-h-72 w-64 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                        >
                            <div v-if="firstSelectedDay" class="border-b border-slate-100 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-[#1a1a1a] dark:text-slate-400">
                                {{ firstSelectedDay.name }} · {{ dayDateLabel(firstSelectedDay.date) }}
                            </div>
                            <div v-if="subFoldersForDay(firstSelectedDayId).length === 0" class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                No sub-folders in this day yet.
                            </div>
                            <button
                                v-for="sub in subFoldersForDay(firstSelectedDayId)"
                                :key="sub.id"
                                type="button"
                                @click.stop.prevent="moveSelectedToSubFolder(sub.id)"
                                class="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm transition hover:bg-violet-50 dark:hover:bg-violet-500/10"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <FolderOpen class="h-3.5 w-3.5 shrink-0 text-violet-500" />
                                    <span class="truncate font-semibold text-slate-900 dark:text-white">{{ sub.name }}</span>
                                </div>
                                <span class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-[#1a1a1a] dark:text-slate-400">
                                    {{ sub.tournaments_count ?? 0 }}
                                </span>
                            </button>
                            <div v-if="subFoldersForDay(firstSelectedDayId).length > 0" class="border-t border-slate-100 dark:border-[#1a1a1a]"></div>
                            <button
                                type="button"
                                @click.stop.prevent="moveSelectedToSubFolder(null)"
                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/20"
                            >
                                <X class="h-3.5 w-3.5" />
                                Remove from sub-folder
                            </button>
                        </div>
                    </div>

                    <button
                        v-if="firstSelectedDayId !== null"
                        type="button"
                        @click="createSubFolderFromSelection"
                        class="flex min-h-[40px] items-center gap-1.5 rounded-lg border border-violet-200 bg-white px-2.5 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/30 dark:bg-[#0f0f0f] dark:text-violet-300 dark:hover:bg-violet-500/10 whitespace-nowrap flex-shrink-0"
                    >
                        <FolderPlus class="h-4 w-4" />
                        <span class="hidden sm:inline">New sub-folder</span>
                        <span class="inline sm:hidden">Sub+</span>
                    </button>

                    <div class="ml-auto flex items-center gap-2 flex-shrink-0">
                        <button
                            v-if="selectedIds.size < filteredTournaments.length"
                            type="button"
                            @click="selectVisible"
                            class="text-xs font-semibold text-blue-600 transition hover:text-blue-500 dark:text-green-400 dark:hover:text-green-300 whitespace-nowrap flex-shrink-0"
                        >
                            Select all {{ filteredTournaments.length }}
                        </button>
                    </div>
                </div>
            </Transition>
    </AppLayout>
</template>

<style scoped>
.floating-action-bar {
  width: max-content !important;
  max-width: calc(100% - 2rem) !important;
  transition: left 0.2s ease-linear, max-width 0.2s ease-linear !important;
}

@media (min-width: 768px) {
  :global(.floating-action-bar) {
    left: calc(50% + 8rem) !important;
    max-width: calc(100% - 20rem) !important;
  }
  
  :global(.peer[data-state="collapsed"] ~ * .floating-action-bar) {
    left: calc(50% + 1.5rem) !important;
    max-width: calc(100% - 6rem) !important;
  }
}
</style>
