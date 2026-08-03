<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Calendar,
    ChevronDown,
    Clock,
    Crown,
    History,
    LayoutGrid,
    Medal,
    Search,
    Swords,
    TrendingUp,
    Trophy,
    User,
    X,
    MapPin,
    Facebook,
    Globe,
    Instagram,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface LeaderboardGroup {
    key: string;
    label: string;
    venue_label?: string | null;
    players: any[];
}

const props = defineProps<{
    players: any[];
    matches?: any[];
    myStats?: any | null;
    venueLabel?: string | null;
    leaderboardGroups?: LeaderboardGroup[];
    defaultLeaderboardGroupKey?: string | null;
    currentUserId?: number | null;
    currentUserVenueCount?: number | null;
    settings?: { scoring_win_points: number; scoring_loss_penalty: number };
    venues?: Array<{ id: number; name: string }>;
    selectedVenueId?: string | number;
}>();

const onVenueChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;
    router.visit(`/all-time-stats?venue_id=${val}`, {
        preserveState: false,
        preserveScroll: true,
    });
};

const displayPlayerName = (player: any) => player?.username || player?.name || 'Unknown';

const activePreset = ref<'overview' | 'leaderboard' | 'history'>('leaderboard');
const selectedPlayerId = ref<string | null>(null);
const showPlayerModal = ref(false);
const showExpandedPlayerAvatar = ref(false);
const leaderboardGroups = computed<LeaderboardGroup[]>(() =>
    props.leaderboardGroups?.length
        ? props.leaderboardGroups
        : [
              {
                  key: 'default',
                  label: props.venueLabel ?? 'Leaderboard',
                  venue_label: props.venueLabel ?? null,
                  players: props.players,
              },
          ],
);
const activeLeaderboardGroupKey = ref<string>(props.defaultLeaderboardGroupKey ?? leaderboardGroups.value[0]?.key ?? 'default');

const page = usePage();
const isSchedulerUser = computed(() => {
    const user = (page.props as any).auth?.user;
    if (!user) return false;
    return user.role === 'scheduler' || user.role === 'scheduler_scorer' || !!user.is_scheduler;
});

const presets = computed(() => {
    const list = [
        { key: 'overview' as const, label: 'Overview', icon: BarChart3 },
        { key: 'leaderboard' as const, label: 'Leaderboard', icon: Trophy },
    ];
    if (!isSchedulerUser.value) {
        list.push({ key: 'history' as const, label: 'Match History', icon: History });
    }
    return list;
});

watch(
    isSchedulerUser,
    (sched) => {
        if (sched && activePreset.value === 'history') {
            activePreset.value = 'leaderboard';
        }
    },
    { immediate: true },
);

// Match History
const historySearch = ref('');
const historyMonthFilter = ref('');
const showMonthDropdown = ref(false);
const monthDropdownRef = ref<HTMLElement | null>(null);
const handleMonthDropdownClickOutside = (e: MouseEvent) => {
    if (monthDropdownRef.value && !monthDropdownRef.value.contains(e.target as Node)) {
        showMonthDropdown.value = false;
    }
};

const showVenueDropdown = ref(false);
const venueDropdownRef = ref<HTMLElement | null>(null);
const handleVenueDropdownClickOutside = (e: MouseEvent) => {
    if (venueDropdownRef.value && !venueDropdownRef.value.contains(e.target as Node)) {
        showVenueDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleMonthDropdownClickOutside);
    document.addEventListener('click', handleVenueDropdownClickOutside);
});
onUnmounted(() => {
    document.removeEventListener('click', handleMonthDropdownClickOutside);
    document.removeEventListener('click', handleVenueDropdownClickOutside);
});
const showDateModal = ref(false);
const selectedDateGroup = ref<any>(null);
const modalCategory = ref<'all' | 'booking' | 'walkin' | 'reclub'>('all');

const showTopPlayersModal = ref(false);
const expandedDays = ref<Record<string, boolean>>({});
const toggleDay = (date: string) => {
    expandedDays.value = { ...expandedDays.value, [date]: !expandedDays.value[date] };
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
    const winPoints = props.settings?.scoring_win_points || 10;
    const lossPenalty = props.settings?.scoring_loss_penalty || 5;
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

const getWalkinLeaderboard = (matches: any[]) => {
    return getSessionLeaderboard(matches.filter((m: any) => m.is_walkin));
};

const getBookingLeaderboard = (matches: any[]) => {
    return getSessionLeaderboard(matches.filter((m: any) => !m.is_walkin && m.booking_type !== 'reclub'));
};

const getReclubLeaderboard = (matches: any[]) => {
    return getSessionLeaderboard(matches.filter((m: any) => !m.is_walkin && m.booking_type === 'reclub'));
};

const formatDateGroupDate = (dateStr?: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr.includes('T') ? dateStr : dateStr + 'T00:00:00');
    if (isNaN(date.getTime())) return dateStr;
    return date.toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const topPlayersInDateMatches = computed(() => {
    if (!selectedDateGroup.value) return [];
    const matches = selectedDateGroup.value.matches || [];
    const matchPlayerIds = new Set<number>();
    const matchPlayerNames = new Set<string>();

    matches.forEach((m: any) => {
        (m.team1?.player_ids || []).forEach((id: number) => matchPlayerIds.add(Number(id)));
        (m.team2?.player_ids || []).forEach((id: number) => matchPlayerIds.add(Number(id)));
        (m.team1?.players || []).forEach((p: string) => matchPlayerNames.add(p.toLowerCase()));
        (m.team2?.players || []).forEach((p: string) => matchPlayerNames.add(p.toLowerCase()));
    });

    const pool = activeLeaderboardGroup.value?.players ?? props.players ?? [];
    return pool
        .filter(p => matchPlayerIds.has(Number(p.id)) || matchPlayerNames.has((p.name || '').toLowerCase()) || matchPlayerNames.has(displayPlayerName(p).toLowerCase()))
        .sort((a, b) => b.points - a.points);
});

const getMatchesCountForDate = (player: any) => {
    if (!selectedDateGroup.value) return 0;
    const matches = selectedDateGroup.value.matches || [];
    const pid = typeof player === 'object' ? Number(player.id) : null;
    const nameLower = (typeof player === 'string' ? player : displayPlayerName(player)).toLowerCase();

    return matches.filter((m: any) => {
        if (pid && ((m.team1?.player_ids || []).map(Number).includes(pid) || (m.team2?.player_ids || []).map(Number).includes(pid))) {
            return true;
        }
        return (m.team1?.players || []).some((p: string) => p.toLowerCase() === nameLower) ||
               (m.team2?.players || []).some((p: string) => p.toLowerCase() === nameLower);
    }).length;
};

const findMatchingPlayer = (playerOrName: any) => {
    const pool = activeLeaderboardGroup.value?.players ?? props.players ?? [];
    if (typeof playerOrName === 'object' && playerOrName?.id) {
        const found = pool.find(p => Number(p.id) === Number(playerOrName.id));
        if (found) return found;
    }
    if (typeof playerOrName === 'number' || !isNaN(Number(playerOrName))) {
        const found = pool.find(p => Number(p.id) === Number(playerOrName));
        if (found) return found;
    }
    const nameLower = String(playerOrName).toLowerCase();
    return pool.find(p => (p.name || '').toLowerCase() === nameLower || displayPlayerName(p).toLowerCase() === nameLower) ?? null;
};

const handlePlayerClick = (playerOrName: any) => {
    const playerObj = findMatchingPlayer(playerOrName);
    if (playerObj) {
        openPlayerDetails(playerObj);
    }
};

const availableMonths = computed(() => {
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

const monthLabel = (ym: string) => {
    const [y, m] = ym.split('-');
    const date = new Date(`${y}-${m}-01`);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
};

const selectedDateMatches = computed(() => {
    if (!selectedDateGroup.value) return [];
    const matches = selectedDateGroup.value.matches || [];
    if (modalCategory.value === 'all') return matches;
    if (modalCategory.value === 'walkin') {
        return matches.filter((m: any) => Boolean(m.is_walkin) || m.booking_type === 'walk-in' || m.booking_type === 'walkin');
    }
    if (modalCategory.value === 'reclub') {
        return matches.filter((m: any) => !m.is_walkin && m.booking_type === 'reclub');
    }
    return matches.filter((m: any) => !m.is_walkin && m.booking_type !== 'reclub' && m.booking_type !== 'walk-in' && m.booking_type !== 'walkin');
});

const groupedMatches = computed(() => {
    if (!props.matches) return [];
    let filtered = props.matches;

    // Player search filter
    if (historySearch.value.trim()) {
        const q = historySearch.value.trim().toLowerCase();
        filtered = filtered.filter(
            (m) =>
                m.team1.players.some((n: string) => n.toLowerCase().includes(q)) || m.team2.players.some((n: string) => n.toLowerCase().includes(q)),
        );
    }

    // Month filter
    if (historyMonthFilter.value) {
        const [fy, fm] = historyMonthFilter.value.split('-');
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

const searchQuery = ref('');
const podiumCount = ref(3);
const podiumTransition = ref<'up' | 'down'>('up');
const setPodiumCount = (n: number) => {
    if (n === podiumCount.value) return;
    podiumTransition.value = n > podiumCount.value ? 'up' : 'down';
    podiumCount.value = n;
};

const selectLeaderboardGroup = (groupKey: string) => {
    activeLeaderboardGroupKey.value = groupKey;
};

const activeLeaderboardGroup = computed(
    () => leaderboardGroups.value.find((group) => group.key === activeLeaderboardGroupKey.value) ?? leaderboardGroups.value[0] ?? null,
);
const activeLeaderboardPlayers = computed(() => activeLeaderboardGroup.value?.players ?? props.players);
const activeVenueLabel = computed(() => activeLeaderboardGroup.value?.venue_label ?? props.venueLabel ?? null);

const allSorted = computed(() =>
    [...activeLeaderboardPlayers.value].sort((a, b) => {
        if (b.points !== a.points) return b.points - a.points;
        if (b.total_matches !== a.total_matches) return b.total_matches - a.total_matches;
        return b.win_rate - a.win_rate;
    }),
);

const sortedPlayers = computed(() => {
    if (!searchQuery.value) return allSorted.value;
    const q = searchQuery.value.toLowerCase();
    return allSorted.value.filter((p) => p.name.toLowerCase().includes(q));
});

const activeMyStats = computed(() => {
    if (props.currentUserId === null || props.currentUserId === undefined) {
        return props.myStats ?? null;
    }

    const stat = activeLeaderboardPlayers.value.find((player) => player.user_id === props.currentUserId);
    if (!stat) {
        return null;
    }

    return {
        ...stat,
        venues_played: props.currentUserVenueCount ?? props.myStats?.venues_played ?? stat.venue_count ?? 0,
    };
});

watch(
    leaderboardGroups,
    (groups) => {
        if (groups.length === 0) {
            activeLeaderboardGroupKey.value = 'default';
            return;
        }

        if (!groups.some((group) => group.key === activeLeaderboardGroupKey.value)) {
            activeLeaderboardGroupKey.value = props.defaultLeaderboardGroupKey ?? groups[0].key;
        }
    },
    { immediate: true },
);

watch(
    sortedPlayers,
    (players) => {
        if (players.length === 0) {
            selectedPlayerId.value = null;
            return;
        }

        if (!selectedPlayerId.value || !players.some((player) => String(player.id) === selectedPlayerId.value)) {
            selectedPlayerId.value = String(players[0].id);
        }
    },
    { immediate: true },
);

const selectedPlayer = computed(() => sortedPlayers.value.find((player) => String(player.id) === selectedPlayerId.value) ?? null);
const selectedPlayerInformationRows = computed(() => {
    const player = selectedPlayer.value;
    if (!player) return [];

    const profile = player.profile_details ?? {};
    const contact = player.contact_details ?? {};

    return [
        { label: 'First name', value: profile.first_name },
        { label: 'Last name', value: profile.last_name },
        { label: 'Middle name', value: profile.middle_name },
        { label: 'Suffix', value: profile.suffix },
        { label: 'Gender', value: profile.gender },
        { label: 'Username', value: profile.username },
        { label: 'Birthday', value: formatDetailDate(contact.birthday), isFormatted: true },
        { label: 'Address', value: contact.address, breakWords: true, fullWidth: true },
    ].filter((item) => {
        if (item.isFormatted) {
            return item.value && item.value !== 'Not provided';
        }

        return typeof item.value === 'string' ? item.value.trim().length > 0 : Boolean(item.value);
    });
});

const selectedPlayerSocialLinks = computed(() => {
    const player = selectedPlayer.value;
    if (!player) return [];

    const profile = player.profile_details ?? {};
    if (profile.social_links && Array.isArray(profile.social_links) && profile.social_links.length > 0) {
        return profile.social_links.slice(0, 3).filter((l: any) => l && l.url).map((item: any) => {
            const platformName = item.platform || 'website';
            let icon = Globe;
            if (platformName === 'instagram') icon = Instagram;
            else if (platformName === 'facebook') icon = Facebook;

            return {
                platform: platformName,
                label: platformName.charAt(0).toUpperCase() + platformName.slice(1),
                icon,
                url: item.url,
            };
        });
    }

    const links: Array<{ platform: string; label: string; icon: any; url: string }> = [];

    if (profile.facebook_url) {
        links.push({
            platform: 'facebook',
            label: 'Facebook',
            icon: Facebook,
            url: profile.facebook_url,
        });
    }
    if (profile.instagram_url) {
        links.push({
            platform: 'instagram',
            label: 'Instagram',
            icon: Instagram,
            url: profile.instagram_url,
        });
    }
    if (profile.website_url) {
        links.push({
            platform: 'website',
            label: 'Website',
            icon: Globe,
            url: profile.website_url,
        });
    }

    return links.slice(0, 3);
});

const selectedPlayerStatusRows = computed(() => {
    const player = selectedPlayer.value;
    if (!player) return [];

    return [
        { label: 'Membership expires', value: formatDetailDate(player.membership_details?.membership_expires_at), isFormatted: true },
        { label: 'Last due paid', value: formatDetailDate(player.membership_details?.last_monthly_due_paid_at), isFormatted: true },
    ].filter((item) => item.value && item.value !== 'Not provided');
});

const hasSelectedPlayerCombinedDetails = computed(() => selectedPlayerInformationRows.value.length > 0 || selectedPlayerSocialLinks.value.length > 0 || selectedPlayerStatusRows.value.length > 0);

watch(
    selectedPlayer,
    (player) => {
        if (!player) {
        }
    },
    { immediate: true },
);

const openPlayerDetails = (player: any) => {
    const nextId = String(player.id);
    selectedPlayerId.value = nextId;
    showPlayerModal.value = true;
};

const openExpandedPlayerAvatar = () => {
    if (!selectedPlayer.value?.avatar) return;
    showExpandedPlayerAvatar.value = true;
};

const closeExpandedPlayerAvatar = () => {
    showExpandedPlayerAvatar.value = false;
};

const closePlayerDetails = () => {
    showPlayerModal.value = false;
    showExpandedPlayerAvatar.value = false;
};

const formatDetailValue = (value?: string | number | null, fallback = 'Not provided') => {
    if (value === null || value === undefined) return fallback;
    if (typeof value === 'string') {
        const trimmed = value.trim();
        return trimmed || fallback;
    }

    return String(value);
};

const formatDetailDate = (value?: string | null, fallback = 'Not provided') => {
    if (!value) return fallback;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const selectedPlayerSubtitle = computed(() => {
    const profile = selectedPlayer.value?.profile_details;
    if (!profile) return 'Player details';

    const fullName = [profile.first_name, profile.middle_name, profile.last_name, profile.suffix]
        .filter((value: string | null | undefined) => Boolean(value && String(value).trim()))
        .join(' ');

    return fullName || 'Player details';
});

const podium = computed(() => {
    const n = Math.min(podiumCount.value, allSorted.value.length);
    const top = allSorted.value.slice(0, n);
    if (top.length < 2) return top;
    return [top[1], top[0], ...top.slice(2)];
});

const mobilePodium = computed(() => {
    const n = Math.min(podiumCount.value, allSorted.value.length);
    return allSorted.value.slice(0, n);
});

const podiumOrder = computed(() => {
    const n = Math.min(podiumCount.value, allSorted.value.length);
    if (n < 2) return [0];
    return [1, 0, ...Array.from({ length: n - 2 }, (_, i) => i + 2)];
});

const totalMatches = computed(() => (activeLeaderboardPlayers.value.reduce((s, p) => s + (p.total_matches ?? 0), 0) / 2) | 0);
const totalPlayers = computed(() => activeLeaderboardPlayers.value.length);
const mostMatches = computed(() => allSorted.value[0]?.total_matches ?? 0);
const rankMap = computed(() => {
    const map = new Map<number, number>();
    allSorted.value.forEach((p, i) => map.set(p.id, i));
    return map;
});

const sizeTier = computed(() => {
    if (podiumCount.value <= 3) return 'large';
    if (podiumCount.value <= 7) return 'medium';
    return 'compact';
});

const rankMeta = [
    {
        label: '1st',
        accent: 'from-amber-400 to-yellow-500',
        ring: 'ring-amber-400/60',
        text: 'text-amber-400',
        bg: 'bg-amber-400/10',
        glow: 'shadow-amber-500/30',
        height: 'h-52',
    },
    {
        label: '2nd',
        accent: 'from-slate-400 to-slate-500',
        ring: 'ring-slate-400/60',
        text: 'text-slate-400',
        bg: 'bg-slate-400/10',
        glow: 'shadow-slate-500/20',
        height: 'h-40',
    },
    {
        label: '3rd',
        accent: 'from-orange-400 to-amber-600',
        ring: 'ring-orange-400/60',
        text: 'text-orange-400',
        bg: 'bg-orange-400/10',
        glow: 'shadow-orange-500/20',
        height: 'h-36',
    },
    {
        label: '4th',
        accent: 'from-rose-400 to-rose-500',
        ring: 'ring-rose-400/40',
        text: 'text-rose-400',
        bg: 'bg-rose-400/10',
        glow: 'shadow-rose-500/20',
        height: 'h-32',
    },
    {
        label: '5th',
        accent: 'from-cyan-400 to-cyan-500',
        ring: 'ring-cyan-400/40',
        text: 'text-cyan-400',
        bg: 'bg-cyan-400/10',
        glow: 'shadow-cyan-500/20',
        height: 'h-28',
    },
    {
        label: '6th',
        accent: 'from-purple-400 to-purple-500',
        ring: 'ring-purple-400/40',
        text: 'text-purple-400',
        bg: 'bg-purple-400/10',
        glow: 'shadow-purple-500/20',
        height: 'h-24',
    },
    {
        label: '7th',
        accent: 'from-pink-400 to-pink-500',
        ring: 'ring-pink-400/40',
        text: 'text-pink-400',
        bg: 'bg-pink-400/10',
        glow: 'shadow-pink-500/20',
        height: 'h-24',
    },
    {
        label: '8th',
        accent: 'from-teal-400 to-teal-500',
        ring: 'ring-teal-400/40',
        text: 'text-teal-400',
        bg: 'bg-teal-400/10',
        glow: 'shadow-teal-500/20',
        height: 'h-24',
    },
    {
        label: '9th',
        accent: 'from-lime-400 to-lime-500',
        ring: 'ring-lime-400/40',
        text: 'text-lime-400',
        bg: 'bg-lime-400/10',
        glow: 'shadow-lime-500/20',
        height: 'h-24',
    },
    {
        label: '10th',
        accent: 'from-gray-400 to-gray-500',
        ring: 'ring-gray-400/40',
        text: 'text-gray-400',
        bg: 'bg-gray-400/10',
        glow: 'shadow-gray-500/20',
        height: 'h-24',
    },
];
let pollInterval: ReturnType<typeof setInterval> | null = null;
const POLL_RELOAD = ['players', 'matches'];

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
    startPoll();
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handlePollVisibility);
    stopPoll();
});
</script>

<template>
    <Head title="All-Time Stats" />

    <AppLayout>
        <div class="p-3 sm:p-6 lg:h-[calc(100vh-64px)] lg:overflow-hidden lg:p-8">
            <div class="flex w-full flex-col gap-4 lg:h-full lg:flex-row lg:gap-6">
                <!-- Sidebar Presets -->
                <div class="flex flex-col lg:w-56 lg:shrink-0">
                    <div class="mb-3 sm:mb-6">
                        <h1 class="text-xl font-black text-slate-900 dark:text-white sm:text-2xl">All-Time Stats</h1>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Career records &amp; rankings</p>
                        <div class="mt-4" ref="venueDropdownRef">
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Select Venue</label>
                            <div class="relative">
                                <button
                                    type="button"
                                    @click.stop="showVenueDropdown = !showVenueDropdown"
                                    class="flex min-h-[44px] w-full items-center justify-between rounded-xl border border-transparent bg-slate-100 px-3.5 py-2.5 text-xs font-bold text-slate-900 transition-all hover:border-slate-200 dark:bg-[#1a1a1a] dark:text-white dark:hover:border-[#2a2a2a]"
                                >
                                    <span>
                                        {{ props.selectedVenueId === 'overall' ? '🏆 Overall (All Venues)' : `📍 ${props.venueLabel || 'Selected Venue'}` }}
                                    </span>
                                    <ChevronDown
                                        class="h-4 w-4 text-slate-400 transition-transform duration-200"
                                        :class="showVenueDropdown ? 'rotate-180' : ''"
                                    />
                                </button>
                                <div
                                    v-if="showVenueDropdown"
                                    class="absolute left-0 z-50 mt-2 w-full min-w-[200px] overflow-hidden rounded-2xl border border-slate-200 bg-white p-1 shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                >
                                    <button
                                        type="button"
                                        @click.stop="
                                            router.visit('/all-time-stats?venue_id=overall', { preserveState: false, preserveScroll: true });
                                            showVenueDropdown = false;
                                        "
                                        class="w-full rounded-xl px-4 py-2.5 text-left text-xs font-bold transition-all"
                                        :class="
                                            props.selectedVenueId === 'overall'
                                                ? 'bg-blue-50/50 text-blue-600 dark:bg-transparent dark:text-green-500'
                                                : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                        "
                                    >
                                        🏆 Overall (All Venues)
                                    </button>
                                    <div class="my-1 border-t border-slate-100 dark:border-[#1a1a1a]"></div>
                                    <button
                                        v-for="venue in props.venues"
                                        :key="venue.id"
                                        type="button"
                                        @click.stop="
                                            router.visit(`/all-time-stats?venue_id=${venue.id}`, { preserveState: false, preserveScroll: true });
                                            showVenueDropdown = false;
                                        "
                                        class="w-full rounded-xl px-4 py-2.5 text-left text-xs font-bold transition-all"
                                        :class="
                                            props.selectedVenueId == venue.id
                                                ? 'bg-blue-50/50 text-blue-600 dark:bg-transparent dark:text-green-500'
                                                : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                        "
                                    >
                                        📍 {{ venue.name }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="-mx-3 flex gap-2 overflow-x-auto px-3 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0">
                        <button
                            v-for="preset in presets"
                            :key="preset.key"
                            @click="activePreset = preset.key"
                            class="flex min-h-[44px] flex-shrink-0 items-center gap-2 whitespace-nowrap rounded-2xl px-3 py-2.5 text-xs font-bold transition-all sm:gap-3 sm:px-4 sm:py-3 sm:text-sm lg:w-full"
                            :class="
                                activePreset === preset.key
                                    ? 'bg-slate-900 text-white shadow-lg dark:bg-white dark:text-slate-900'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-[#1a1a1a]'
                            "
                        >
                            <component :is="preset.icon" class="h-4 w-4 sm:h-5 sm:w-5" />
                            {{ preset.label }}
                        </button>
                    </div>
                    <div
                        class="mt-auto hidden rounded-2xl border border-indigo-100 bg-indigo-50 p-4 dark:border-green-800 dark:bg-green-900/20 lg:block"
                    >
                        <p class="text-[10px] leading-relaxed text-indigo-600 dark:text-green-400">
                            Players: <strong>{{ totalPlayers }}</strong>
                        </p>
                        <p class="mt-1 text-[10px] leading-relaxed text-indigo-600 dark:text-green-400">
                            Matches: <strong>{{ totalMatches }}</strong>
                        </p>
                    </div>
                </div>

                <!-- Content Panel -->
                <div class="glass-card flex flex-1 flex-col overflow-hidden bg-slate-50 dark:bg-[#0a0a0a]">
                    <!-- Header -->
                    <div
                        v-if="activePreset === 'leaderboard'"
                        class="flex shrink-0 flex-col gap-3 border-b border-slate-200 bg-white p-3 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:flex-row sm:items-center sm:justify-between sm:p-5"
                    >
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <LayoutGrid class="h-4 w-4 text-slate-400 sm:h-5 sm:w-5" />
                                <h2 class="text-base font-bold text-slate-900 dark:text-white sm:text-lg">Leaderboard</h2>
                            </div>
                            <div v-if="leaderboardGroups.length > 1" class="flex flex-wrap items-center gap-2">
                                <button
                                    v-for="group in leaderboardGroups"
                                    :key="`header-${group.key}`"
                                    type="button"
                                    class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider transition-all"
                                    :class="
                                        activeLeaderboardGroupKey === group.key
                                            ? 'bg-emerald-500 text-white shadow-sm'
                                            : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#222]'
                                    "
                                    @click="selectLeaderboardGroup(group.key)"
                                >
                                    {{ group.label }}
                                </button>
                            </div>
                        </div>
                        <div class="relative w-full sm:w-64">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search players…"
                                class="min-h-[44px] w-full rounded-xl border border-transparent bg-slate-100 py-2.5 pl-10 pr-4 text-base font-semibold text-slate-900 placeholder-slate-400 outline-none ring-indigo-500/50 transition-all focus:ring-2 dark:bg-[#1a1a1a] dark:text-white dark:focus:ring-green-500/50 sm:text-sm"
                            />
                        </div>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="custom-scrollbar flex-1 overflow-y-auto">
                        <!-- OVERVIEW PRESET -->
                        <div v-if="activePreset === 'overview'" class="flex h-full flex-col">
                            <div v-if="activeMyStats" class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-4 sm:gap-3 sm:p-4">
                                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">My Wins</p>
                                    <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ activeMyStats.wins }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">My Losses</p>
                                    <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ activeMyStats.losses }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">My Points</p>
                                    <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ activeMyStats.points }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Venues Played</p>
                                    <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ activeMyStats.venues_played ?? 0 }}</p>
                                </div>
                            </div>
                            <!-- Stat Cards Row -->
                            <div class="grid shrink-0 grid-cols-1 gap-2 p-3 sm:grid-cols-3 sm:gap-3 sm:p-4">
                                <div
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:gap-3 sm:rounded-2xl sm:px-5 sm:py-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-green-900/20 sm:h-11 sm:w-11 sm:rounded-xl"
                                    >
                                        <User class="h-3.5 w-3.5 text-indigo-500 dark:text-green-400 sm:h-5 sm:w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 sm:text-[10px]">Players</p>
                                        <p class="text-lg font-black text-slate-900 dark:text-white sm:text-2xl">{{ totalPlayers }}</p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:gap-3 sm:rounded-2xl sm:px-5 sm:py-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-green-900/20 sm:h-11 sm:w-11 sm:rounded-xl"
                                    >
                                        <Swords class="h-3.5 w-3.5 text-blue-500 dark:text-green-400 sm:h-5 sm:w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 sm:text-[10px]">Matches</p>
                                        <p class="text-lg font-black text-slate-900 dark:text-white sm:text-2xl">{{ totalMatches }}</p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:gap-3 sm:rounded-2xl sm:px-5 sm:py-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-green-900/20 sm:h-11 sm:w-11 sm:rounded-xl"
                                    >
                                        <TrendingUp class="h-3.5 w-3.5 text-emerald-500 dark:text-green-400 sm:h-5 sm:w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-[9px] font-black uppercase tracking-widest text-slate-400 sm:text-[10px]">
                                            Top Matches
                                        </p>
                                        <p class="text-lg font-black text-slate-900 dark:text-white sm:text-2xl">{{ mostMatches }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Podium Count Control -->
                            <div class="flex items-center justify-center gap-1.5">
                                <button
                                    v-for="n in [1, 3, 5, 10]"
                                    :key="n"
                                    @click="setPodiumCount(n)"
                                    class="flex h-6 w-6 items-center justify-center rounded-md text-[10px] font-black transition-all duration-200 sm:h-7 sm:w-7 sm:text-xs"
                                    :class="
                                        podiumCount === n
                                            ? 'scale-105 bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900'
                                            : 'text-slate-500 hover:scale-110 hover:bg-slate-100 dark:hover:bg-[#1a1a1a]'
                                    "
                                >
                                    {{ n }}
                                </button>
                            </div>

                            <!-- Podium: Mobile (vertical stack) -->
                            <div v-if="mobilePodium.length > 0" class="flex flex-col gap-3 p-3 sm:hidden">
                                <div
                                    v-for="(player, i) in mobilePodium"
                                    :key="`m-${player.id}`"
                                    class="w-full"
                                >
                                    <div
                                        class="flex flex-col items-center rounded-2xl border p-4 shadow-md"
                                        :class="
                                            i === 0
                                                ? 'border-amber-200 bg-gradient-to-b from-amber-50 to-white dark:border-amber-800/50 dark:from-amber-900/20 dark:to-[#0f0f0f]'
                                                : 'border-slate-200 bg-white dark:border-[#1a1a1a] dark:bg-[#0f0f0f]'
                                        "
                                    >
                                        <!-- Avatar -->
                                        <div class="relative mb-2">
                                            <div
                                                class="flex h-12 w-12 items-center justify-center rounded-full text-lg font-black text-white shadow-lg ring-[3px]"
                                                :class="[rankMeta[i].ring, rankMeta[i].glow]"
                                                :style="`background: linear-gradient(135deg, #1a1a1a, #0f0f0f)`"
                                            >
                                                {{ displayPlayerName(player).charAt(0).toUpperCase() }}
                                            </div>
                                            <div
                                                v-if="i < 3"
                                                class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white dark:border-[#0f0f0f]"
                                                :class="`bg-gradient-to-br ${rankMeta[i].accent}`"
                                            >
                                                <Crown v-if="i === 0" class="h-2.5 w-2.5 text-white" />
                                                <Medal v-else class="h-2.5 w-2.5 text-white" />
                                            </div>
                                        </div>
                                        <!-- Name -->
                                        <p
                                            class="mb-0.5 w-full truncate px-1 text-center text-sm font-black capitalize text-slate-900 dark:text-white"
                                        >
                                            {{ displayPlayerName(player) }}
                                        </p>
                                        <p
                                            class="mb-2 text-[9px] font-black uppercase tracking-widest"
                                            :class="rankMeta[i].text"
                                        >
                                            {{ rankMeta[i].label }}
                                        </p>
                                        <!-- Stats -->
                                        <p class="mb-0.5 text-2xl font-black" :class="rankMeta[i].text">{{ player.points }}</p>
                                        <p class="mb-2 text-[10px] text-slate-500">Win Rate {{ player.win_rate }}%</p>
                                        <div class="mt-auto flex w-full gap-2 border-t border-slate-100 pt-2 dark:border-[#1a1a1a]">
                                            <div class="flex-1 text-center">
                                                <p class="mb-0.5 text-[8px] font-black uppercase text-blue-500 dark:text-green-400">W</p>
                                                <p class="text-sm font-black text-slate-700 dark:text-slate-200">{{ player.wins }}</p>
                                            </div>
                                            <div class="w-px bg-slate-100 dark:bg-[#1a1a1a]"></div>
                                            <div class="flex-1 text-center">
                                                <p class="mb-0.5 text-[8px] font-black uppercase text-rose-500">L</p>
                                                <p class="text-sm font-black text-slate-700 dark:text-slate-200">{{ player.losses }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Podium: Desktop -->
                            <div class="hidden flex-1 items-center justify-center overflow-hidden p-4 sm:flex">
                                <TransitionGroup
                                    v-if="podium.length > 0"
                                    tag="div"
                                    :class="[
                                        'w-full gap-4',
                                        podiumCount <= 3
                                            ? 'flex items-end justify-center'
                                            : 'grid max-w-5xl grid-cols-5 items-end justify-items-center',
                                    ]"
                                    enter-active-class="transition-all duration-500 ease-out"
                                    :enter-from-class="
                                        podiumTransition === 'up' ? 'opacity-0 scale-90 translate-y-4' : 'opacity-0 scale-90 -translate-y-3'
                                    "
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                    leave-active-class="transition-all duration-300 ease-in"
                                    leave-from-class="opacity-100 scale-100 translate-y-0"
                                    :leave-to-class="
                                        podiumTransition === 'up' ? 'opacity-0 scale-90 -translate-y-2' : 'opacity-0 scale-90 translate-y-2'
                                    "
                                    move-class="transition-all duration-500 ease-out"
                                >
                                    <div
                                        v-for="(player, i) in podium"
                                        :key="player.id"
                                        class="relative flex flex-col items-center"
                                        :class="[
                                            podiumOrder[i] === 0 ? 'z-10' : 'z-0',
                                            sizeTier === 'large' ? 'w-40' : sizeTier === 'medium' ? 'w-36' : 'w-28',
                                        ]"
                                    >
                                        <!-- Avatar -->
                                        <div class="relative mb-2" :class="sizeTier === 'large' && podiumOrder[i] === 0 ? '-mt-4' : ''">
                                            <div
                                                class="overflow-hidden rounded-full shadow-lg ring-[3px]"
                                                :class="[
                                                    rankMeta[podiumOrder[i]].ring,
                                                    rankMeta[podiumOrder[i]].glow,
                                                    sizeTier === 'large'
                                                        ? podiumOrder[i] === 0
                                                            ? 'h-16 w-16 text-2xl'
                                                            : 'h-12 w-12 text-lg'
                                                        : sizeTier === 'medium'
                                                          ? podiumOrder[i] === 0
                                                              ? 'h-[60px] w-[60px] text-xl'
                                                              : 'h-12 w-12 text-lg'
                                                          : podiumOrder[i] === 0
                                                            ? 'h-12 w-12 text-lg'
                                                            : 'h-10 w-10 text-sm',
                                                ]"
                                            >
                                                <img
                                                    v-if="player.avatar"
                                                    :src="player.avatar"
                                                    :alt="displayPlayerName(player)"
                                                    class="h-full w-full object-cover"
                                                />
                                                <div
                                                    v-else
                                                    class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#1a1a1a,#0f0f0f)] font-black text-white"
                                                >
                                                    {{ displayPlayerName(player).charAt(0).toUpperCase() }}
                                                </div>
                                            </div>
                                            <div
                                                v-if="podiumOrder[i] < 3"
                                                class="absolute -right-1 -top-1 flex items-center justify-center rounded-full border-2 border-white dark:border-[#0f0f0f]"
                                                :class="`bg-gradient-to-br ${rankMeta[podiumOrder[i]].accent} ${sizeTier === 'large' ? 'h-6 w-6' : 'h-5 w-5'}`"
                                            >
                                                <Crown
                                                    v-if="podiumOrder[i] === 0"
                                                    class="text-white"
                                                    :class="sizeTier === 'large' ? 'h-3 w-3' : 'h-2.5 w-2.5'"
                                                />
                                                <Medal v-else class="text-white" :class="sizeTier === 'large' ? 'h-3 w-3' : 'h-2.5 w-2.5'" />
                                            </div>
                                        </div>

                                        <!-- Name -->
                                        <p
                                            class="mb-0.5 w-full truncate px-1 text-center font-black capitalize text-slate-900 dark:text-white"
                                            :class="sizeTier === 'large' ? 'text-sm' : 'text-xs'"
                                        >
                                            {{ displayPlayerName(player) }}
                                        </p>
                                        <p
                                            class="mb-2 font-black uppercase tracking-widest"
                                            :class="[rankMeta[podiumOrder[i]].text, sizeTier === 'large' ? 'text-[10px]' : 'text-[9px]']"
                                        >
                                            {{ rankMeta[podiumOrder[i]].label }}
                                        </p>

                                        <!-- Card -->
                                        <div
                                            class="flex w-full flex-col items-center rounded-xl border shadow-md"
                                            :class="[
                                                podiumOrder[i] === 0
                                                    ? 'border-amber-200 bg-gradient-to-b from-amber-50 to-white dark:border-amber-800/50 dark:from-amber-900/20 dark:to-[#0f0f0f]'
                                                    : 'border-slate-200 bg-white dark:border-[#1a1a1a] dark:bg-[#0f0f0f]',
                                                sizeTier === 'large' ? 'p-4' : sizeTier === 'medium' ? 'p-3' : 'p-2',
                                            ]"
                                        >
                                            <p
                                                class="font-black uppercase tracking-widest text-slate-400"
                                                :class="
                                                    sizeTier === 'large'
                                                        ? 'mb-1 text-[10px]'
                                                        : sizeTier === 'medium'
                                                          ? 'mb-1 text-[10px]'
                                                          : 'mb-0.5 text-[7px]'
                                                "
                                            >
                                                Points
                                            </p>
                                            <p
                                                class="font-black"
                                                :class="[
                                                    rankMeta[podiumOrder[i]].text,
                                                    sizeTier === 'large'
                                                        ? 'mb-1 text-2xl'
                                                        : sizeTier === 'medium'
                                                          ? 'mb-1 text-xl'
                                                          : 'mb-0.5 text-base',
                                                ]"
                                            >
                                                {{ player.points }}
                                            </p>
                                            <p
                                                class="text-slate-500"
                                                :class="
                                                    sizeTier === 'large'
                                                        ? 'mb-2 text-[10px]'
                                                        : sizeTier === 'medium'
                                                          ? 'mb-2 text-[10px]'
                                                          : 'mb-1.5 text-[9px]'
                                                "
                                            >
                                                Win Rate {{ player.win_rate }}%
                                            </p>
                                            <div
                                                class="flex w-full border-t border-slate-100 dark:border-[#1a1a1a]"
                                                :class="sizeTier === 'large' ? 'gap-2 pt-2' : sizeTier === 'medium' ? 'gap-2 pt-2' : 'gap-1.5 pt-1.5'"
                                            >
                                                <div class="flex-1 text-center">
                                                    <p
                                                        class="font-black uppercase text-blue-500 dark:text-green-400"
                                                        :class="
                                                            sizeTier === 'large'
                                                                ? 'mb-1 text-[10px]'
                                                                : sizeTier === 'medium'
                                                                  ? 'mb-1 text-[10px]'
                                                                  : 'mb-0 text-[8px]'
                                                        "
                                                    >
                                                        W
                                                    </p>
                                                    <p
                                                        class="font-black text-slate-700 dark:text-slate-200"
                                                        :class="sizeTier === 'large' ? 'text-lg' : sizeTier === 'medium' ? 'text-base' : 'text-xs'"
                                                    >
                                                        {{ player.wins }}
                                                    </p>
                                                </div>
                                                <div class="w-px bg-slate-100 dark:bg-[#1a1a1a]"></div>
                                                <div class="flex-1 text-center">
                                                    <p
                                                        class="font-black uppercase text-rose-500"
                                                        :class="
                                                            sizeTier === 'large'
                                                                ? 'mb-1 text-[10px]'
                                                                : sizeTier === 'medium'
                                                                  ? 'mb-1 text-[10px]'
                                                                  : 'mb-0 text-[8px]'
                                                        "
                                                    >
                                                        L
                                                    </p>
                                                    <p
                                                        class="font-black text-slate-700 dark:text-slate-200"
                                                        :class="sizeTier === 'large' ? 'text-lg' : sizeTier === 'medium' ? 'text-base' : 'text-xs'"
                                                    >
                                                        {{ player.losses }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </TransitionGroup>
                            </div>
                        </div>

                        <!-- LEADERBOARD PRESET -->
                        <div v-if="activePreset === 'leaderboard'" class="flex h-full flex-col">
                            <!-- Mobile Card List -->
                            <div class="custom-scrollbar flex-1 space-y-2.5 overflow-y-auto p-3 sm:hidden">
                                <div
                                    v-for="player in sortedPlayers"
                                    :key="`m-lb-${player.id}`"
                                    @click="openPlayerDetails(player)"
                                    class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition-all dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                    :class="
                                        selectedPlayerId === String(player.id)
                                            ? 'border-slate-900 ring-2 ring-slate-900/10 dark:border-white dark:ring-white/10'
                                            : 'hover:border-slate-300 dark:hover:border-[#2a2a2a]'
                                    "
                                >
                                    <!-- Rank -->
                                    <div class="flex w-9 flex-shrink-0 justify-center">
                                        <span
                                            v-if="rankMap.get(player.id) === 0"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-yellow-500 text-xs font-black text-slate-900 shadow-md shadow-amber-400/30"
                                            >1</span
                                        >
                                        <span
                                            v-else-if="rankMap.get(player.id) === 1"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-slate-300 to-slate-400 text-xs font-black text-slate-900 shadow-sm"
                                            >2</span
                                        >
                                        <span
                                            v-else-if="rankMap.get(player.id) === 2"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-orange-400 to-amber-600 text-xs font-black text-white shadow-sm"
                                            >3</span
                                        >
                                        <span v-else class="inline-flex h-8 w-8 items-center justify-center text-sm font-black text-slate-400"
                                            >#{{ (rankMap.get(player.id) ?? 0) + 1 }}</span
                                        >
                                    </div>
                                    <!-- Avatar + Name -->
                                    <div class="flex min-w-0 flex-1 items-center gap-2.5">
                                        <div
                                            v-if="player.avatar"
                                            class="h-9 w-9 shrink-0 overflow-hidden rounded-full ring-2 ring-white/70 shadow-sm dark:ring-[#1a1a1a]"
                                        >
                                            <img :src="player.avatar" :alt="displayPlayerName(player)" class="h-full w-full object-cover" />
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 text-sm font-black text-white shadow-sm dark:from-green-600 dark:to-green-700"
                                        >
                                            {{ displayPlayerName(player).charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-black capitalize text-slate-900 dark:text-white">{{ displayPlayerName(player) }}</p>
                                            <div class="mt-0.5 flex items-center gap-2">
                                                <span class="text-[10px] font-semibold text-slate-500">{{ player.total_matches }} matches</span>
                                                <span class="text-[10px] text-slate-300">•</span>
                                                <span
                                                    class="text-[10px] font-black"
                                                    :class="player.win_rate >= 50 ? 'text-emerald-500' : 'text-amber-500'"
                                                    >{{ player.win_rate }}%</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Points + W/L -->
                                    <div class="flex-shrink-0 text-right">
                                        <p class="text-lg font-black leading-none text-indigo-600 dark:text-green-400">{{ player.points }}</p>
                                        <p class="mt-0.5 text-[10px] font-semibold text-slate-400">pts</p>
                                    </div>
                                </div>
                                <div v-if="sortedPlayers.length === 0" class="flex flex-col items-center justify-center py-20 text-center opacity-50">
                                    <User class="mb-2 h-12 w-12 text-slate-300 dark:text-[#1a1a1a]" />
                                    <p class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">No players found</p>
                                </div>
                            </div>

                            <!-- Desktop Table -->
                            <div class="hidden flex-1 overflow-auto sm:block">
                                <table class="w-full border-collapse text-left">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-slate-50 dark:bg-[#0a0a0a]/60">
                                            <th class="w-20 px-6 py-3 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                Rank
                                            </th>
                                            <th class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Player</th>
                                            <th
                                                class="px-6 py-3 text-center text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-green-400"
                                            >
                                                Matches
                                            </th>
                                            <th class="px-6 py-3 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                Wins
                                            </th>
                                            <th class="px-6 py-3 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                Losses
                                            </th>
                                            <th
                                                class="px-6 py-3 text-center text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-green-400"
                                            >
                                                Points
                                            </th>
                                            <th class="px-6 py-3 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                Win Rate
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="player in sortedPlayers"
                                            :key="player.id"
                                            @click="openPlayerDetails(player)"
                                            class="group cursor-pointer border-t border-slate-100 transition-colors hover:bg-slate-50 dark:border-[#1a1a1a]/70 dark:hover:bg-[#1a1a1a]/40"
                                            :class="selectedPlayerId === String(player.id) ? 'bg-slate-50 dark:bg-[#1a1a1a]/50' : ''"
                                        >
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    v-if="rankMap.get(player.id) === 0"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-yellow-500 text-xs font-black text-slate-900 shadow-md shadow-amber-400/30"
                                                    >1</span
                                                >
                                                <span
                                                    v-else-if="rankMap.get(player.id) === 1"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-slate-300 to-slate-400 text-xs font-black text-slate-900 shadow-sm"
                                                    >2</span
                                                >
                                                <span
                                                    v-else-if="rankMap.get(player.id) === 2"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-orange-400 to-amber-600 text-xs font-black text-white shadow-sm"
                                                    >3</span
                                                >
                                                <span v-else class="inline-flex h-8 w-8 items-center justify-center text-sm font-black text-slate-400"
                                                    >#{{ (rankMap.get(player.id) ?? 0) + 1 }}</span
                                                >
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        v-if="player.avatar"
                                                        class="h-9 w-9 shrink-0 overflow-hidden rounded-full ring-2 ring-white/70 shadow-sm dark:ring-[#1a1a1a]"
                                                    >
                                                        <img :src="player.avatar" :alt="displayPlayerName(player)" class="h-full w-full object-cover" />
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 text-sm font-black text-white shadow-sm dark:from-green-600 dark:to-green-700"
                                                    >
                                                        {{ displayPlayerName(player).charAt(0).toUpperCase() }}
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-black capitalize text-slate-900 dark:text-white">{{ displayPlayerName(player) }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-sm font-black text-slate-700 dark:bg-[#1a1a1a] dark:text-slate-300"
                                                    >{{ player.total_matches }}</span
                                                >
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    class="inline-flex min-w-[36px] items-center justify-center rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-black text-blue-600 dark:bg-green-900/20 dark:text-green-400"
                                                    >{{ player.wins }}</span
                                                >
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    class="inline-flex min-w-[36px] items-center justify-center rounded-lg bg-rose-50 px-3 py-1.5 text-sm font-black text-rose-500 dark:bg-rose-900/20 dark:text-rose-400"
                                                    >{{ player.losses }}</span
                                                >
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-sm font-black text-indigo-600 dark:bg-green-900/20 dark:text-green-400"
                                                    >{{ player.points }}</span
                                                >
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center gap-3">
                                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-[#1a1a1a]">
                                                        <div
                                                            class="h-full rounded-full transition-all duration-700"
                                                            :class="
                                                                player.win_rate >= 50
                                                                    ? 'bg-gradient-to-r from-emerald-400 to-emerald-500'
                                                                    : 'bg-gradient-to-r from-amber-400 to-amber-500'
                                                            "
                                                            :style="{ width: player.win_rate + '%' }"
                                                        ></div>
                                                    </div>
                                                    <span
                                                        class="w-12 text-right text-sm font-black"
                                                        :class="player.win_rate >= 50 ? 'text-emerald-500' : 'text-amber-500'"
                                                    >
                                                        {{ player.win_rate }}%
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                v-if="sortedPlayers.length === 0"
                                class="hidden flex-col items-center justify-center py-20 text-center opacity-50 sm:flex"
                            >
                                <User class="mb-3 h-14 w-14 text-slate-300 dark:text-[#1a1a1a]" />
                                <p class="text-sm font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">No players found</p>
                            </div>
                        </div>

                        <!-- MATCH HISTORY PRESET -->
                        <div v-if="activePreset === 'history'" class="flex h-full flex-col">
                            <!-- Header -->
                            <div
                                class="flex shrink-0 flex-col gap-3 border-b border-slate-200 bg-white p-3 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:flex-row sm:items-center sm:justify-between sm:p-5"
                            >
                                <div class="flex items-center gap-2">
                                    <History class="h-4 w-4 text-slate-400 sm:h-5 sm:w-5" />
                                    <h2 class="text-base font-bold text-slate-900 dark:text-white sm:text-lg">Match History</h2>
                                </div>
                                <div class="flex w-full items-center gap-2 sm:w-auto">
                                    <!-- Month Filter -->
                                    <div class="relative flex-1 sm:w-44" ref="monthDropdownRef">
                                        <button
                                            @click.stop="showMonthDropdown = !showMonthDropdown"
                                            class="flex min-h-[44px] w-full items-center justify-between rounded-xl border border-transparent bg-slate-100 px-3 py-2.5 text-sm font-semibold text-slate-900 transition-all hover:border-slate-300 dark:bg-[#1a1a1a] dark:text-white dark:hover:border-[#2a2a2a]"
                                        >
                                            <span>{{ historyMonthFilter ? monthLabel(historyMonthFilter) : 'All Months' }}</span>
                                            <ChevronDown
                                                class="h-4 w-4 text-slate-400 transition-transform duration-200"
                                                :class="showMonthDropdown ? 'rotate-180' : ''"
                                            />
                                        </button>
                                        <div
                                            v-if="showMonthDropdown"
                                            class="absolute right-0 z-50 mt-2 w-full min-w-[200px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                        >
                                            <button
                                                @click.stop="
                                                    historyMonthFilter = '';
                                                    showMonthDropdown = false;
                                                "
                                                class="w-full px-4 py-2.5 text-left text-xs font-bold transition-all"
                                                :class="
                                                    historyMonthFilter === ''
                                                        ? 'bg-blue-50/50 text-blue-600 dark:bg-transparent dark:text-green-500'
                                                        : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                                "
                                            >
                                                All Months
                                            </button>
                                            <div class="border-t border-slate-100 dark:border-[#1a1a1a]"></div>
                                            <button
                                                v-for="month in availableMonths"
                                                :key="month"
                                                @click.stop="
                                                    historyMonthFilter = month;
                                                    showMonthDropdown = false;
                                                "
                                                class="w-full px-4 py-2.5 text-left text-xs font-bold transition-all"
                                                :class="
                                                    historyMonthFilter === month
                                                        ? 'bg-blue-50/50 text-blue-600 dark:bg-transparent dark:text-green-500'
                                                        : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                                "
                                            >
                                                {{ monthLabel(month) }}
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Player Search -->
                                    <div class="relative flex-1 sm:w-52">
                                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            v-model="historySearch"
                                            type="text"
                                            placeholder="Search player..."
                                            class="min-h-[44px] w-full rounded-xl border border-transparent bg-slate-100 py-2.5 pl-10 pr-4 text-sm font-semibold text-slate-900 placeholder-slate-400 outline-none ring-indigo-500/50 transition-all focus:ring-2 dark:bg-[#1a1a1a] dark:text-white dark:focus:ring-green-500/50"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Scrollable Content -->
                            <div class="custom-scrollbar flex-1 space-y-3 overflow-y-auto p-3 sm:p-5">
                                <!-- Empty State -->
                                <div
                                    v-if="groupedMatches.length === 0"
                                    class="flex flex-col items-center justify-center py-20 text-center opacity-50"
                                >
                                    <History class="mb-3 h-14 w-14 text-slate-300 dark:text-[#1a1a1a]" />
                                    <p class="text-sm font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">No matches found</p>
                                </div>

                                <!-- Date Table -->
                                <div class="space-y-3">
                                    <div
                                        v-for="group in groupedMatches"
                                        :key="group.date"
                                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                    >
                                        <!-- Folder Header -->
                                        <div
                                        @click="toggleDay(group.date)"
                                            class="flex cursor-pointer items-center justify-between px-4 py-3.5 transition-colors hover:bg-slate-50 dark:hover:bg-[#1a1a1a]/60 sm:px-5 sm:py-4"
                                        >
                                            <div class="flex items-center gap-3">
                                                <ChevronDown
                                                    class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                                                    :class="expandedDays[group.date] ? '' : '-rotate-90'"
                                                />
                                                <Calendar class="h-4 w-4 shrink-0 text-indigo-500 dark:text-green-400" />
                                                <span class="text-sm font-black text-slate-900 dark:text-white">{{
                                                    new Date(group.date).toLocaleDateString('en-US', {
                                                        weekday: 'short',
                                                        year: 'numeric',
                                                        month: 'long',
                                                        day: 'numeric',
                                                    })
                                                }}</span>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-2">
                                                <div class="flex shrink-0 items-center gap-2 text-[10px] font-black uppercase tracking-wider">
                                                    <span
                                                        v-if="group.walkinCount > 0"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                                                    >
                                                        <MapPin class="h-3 w-3" />
                                                        {{ group.walkinCount }} Walk-in
                                                    </span>
                                                    <span
                                                        v-if="group.bookingCount > 0"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300"
                                                    >
                                                        <MapPin class="h-3 w-3" />
                                                        {{ group.bookingCount }} Booking
                                                    </span>
                                                    <span
                                                        v-if="group.reclubCount > 0"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-purple-200 bg-purple-50 px-2.5 py-1 text-purple-700 dark:border-purple-800 dark:bg-purple-900/20 dark:text-purple-300"
                                                    >
                                                        <MapPin class="h-3 w-3" />
                                                        {{ group.reclubCount }} Reclub
                                                    </span>
                                                </div>
                                                <button
                                                    @click.stop="
                                                        modalCategory = 'all';
                                                        selectedDateGroup = group;
                                                        showDateModal = true;
                                                    "
                                                    class="rounded-md bg-indigo-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-600 transition-all hover:bg-indigo-100 dark:bg-green-950/20 dark:text-green-400 dark:hover:bg-green-900/30"
                                                >
                                                    Details
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Expanded Content -->
                                        <div v-if="expandedDays[group.date]" class="border-t border-slate-100 dark:border-[#1a1a1a]">
                                            <div
                                                v-for="session in getDaySessions(group.matches)"
                                                :key="session.key"
                                                class="border-b border-slate-100 last:border-b-0 dark:border-[#1a1a1a]"
                                            >
                                                <div class="flex items-center gap-2 bg-slate-50 px-4 py-2.5 dark:bg-[#1a1a1a]/40 sm:px-5">
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
                                                        class="text-[11px] font-bold"
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
                                                <div class="bg-white px-4 py-2.5 dark:bg-[#0f0f0f] sm:px-5">
                                                    <div class="grid grid-cols-[1fr_60px_50px] gap-2 border-b border-slate-100 pb-1 text-[9px] font-black uppercase tracking-wider text-slate-400 dark:border-[#1a1a1a]">
                                                        <span>Player</span>
                                                        <span class="text-center">W/L</span>
                                                        <span class="text-right">Points</span>
                                                    </div>
                                                    <div
                                                        v-for="(s, si) in getSessionLeaderboard(session.matches)"
                                                        :key="s.name"
                                                        class="grid grid-cols-[1fr_60px_50px] gap-2 border-b border-slate-50 py-1.5 last:border-b-0 dark:border-[#1a1a1a]/50"
                                                    >
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="w-3.5 text-center font-mono text-[9px] font-bold text-slate-400">{{ si + 1 }}.</span>
                                                            <span class="min-w-0 flex-1 truncate text-[11px] font-bold text-slate-900 dark:text-white">{{ s.name }}</span>
                                                        </div>
                                                        <span class="text-center text-[10px] font-semibold">
                                                            <span class="text-emerald-600 dark:text-emerald-400">{{ s.wins }}W</span>
                                                            <span class="text-slate-400">/</span>
                                                            <span class="text-rose-500">{{ s.losses }}L</span>
                                                        </span>
                                                        <span class="text-right text-[10px] font-bold text-indigo-600 dark:text-green-400">{{ s.points }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Player Detail Modal -->
        <div v-if="showPlayerModal && selectedPlayer" class="fixed inset-0 z-[160] flex items-center justify-center p-4 sm:p-6">
            <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="closePlayerDetails"></div>
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-3 dark:border-[#1a1a1a] sm:px-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <button
                            v-if="selectedPlayer.avatar"
                            type="button"
                            class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl ring-2 ring-slate-100 dark:ring-[#1a1a1a]"
                            @click="openExpandedPlayerAvatar"
                        >
                            <img
                                :src="selectedPlayer.avatar"
                                :alt="displayPlayerName(selectedPlayer)"
                                class="h-full w-full object-cover transition duration-200 hover:scale-105"
                            />
                        </button>
                        <div
                            v-else
                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-xl font-black text-white shadow-sm dark:from-green-600 dark:to-green-700"
                        >
                            {{ displayPlayerName(selectedPlayer).charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-xl font-black text-slate-900 dark:text-white">{{ displayPlayerName(selectedPlayer) }}</p>
                            <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ selectedPlayerSubtitle }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider"
                                    :class="
                                        selectedPlayer.is_member
                                            ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400'
                                            : 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400'
                                    "
                                >
                                    {{ selectedPlayer.is_member ? 'Member' : 'Non-member' }}
                                </span>
                                <span
                                    class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider"
                                    :class="
                                        selectedPlayer.email_verified
                                            ? 'bg-blue-50 text-blue-600 dark:bg-green-900/20 dark:text-green-400'
                                            : 'bg-slate-100 text-slate-500 dark:bg-[#1a1a1a] dark:text-slate-300'
                                    "
                                >
                                    {{ selectedPlayer.email_verified ? 'Email verified' : 'Email unverified' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <button
                        @click="closePlayerDetails"
                        class="rounded-xl p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-[#1a1a1a] dark:hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 px-5 py-4 sm:px-6">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Wins</p>
                            <p class="mt-2 text-[1.75rem] font-black leading-none text-emerald-500">{{ selectedPlayer.wins }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Losses</p>
                            <p class="mt-2 text-[1.75rem] font-black leading-none text-rose-500">{{ selectedPlayer.losses }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Matches</p>
                            <p class="mt-2 text-[1.75rem] font-black leading-none text-slate-900 dark:text-white">{{ selectedPlayer.total_matches }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Points / Win rate</p>
                            <p class="mt-2 text-base font-black text-slate-900 dark:text-white">{{ selectedPlayer.points }} pts</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ selectedPlayer.win_rate }}% win rate</p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div v-if="!hasSelectedPlayerCombinedDetails" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-slate-400">
                            This player chose not to display profile information in All-Time Stats.
                        </div>
                        <div
                            v-else
                            class="grid grid-cols-1 gap-3"
                            :class="selectedPlayerStatusRows.length > 0 ? 'lg:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.85fr)]' : ''"
                        >
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                                <div class="mb-3 border-b border-slate-200 pb-2.5 dark:border-[#1a1a1a]">
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Information</span>: Visible profile and contact details.
                                    </p>
                                </div>
                                <div v-if="selectedPlayerInformationRows.length === 0 && selectedPlayerSocialLinks.length === 0" class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500 dark:border-[#1a1a1a] dark:text-slate-400">
                                    No profile details are visible for this player.
                                </div>
                                <div v-else class="space-y-4">
                                    <div v-if="selectedPlayerInformationRows.length > 0" class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                        <div
                                            v-for="item in selectedPlayerInformationRows"
                                            :key="item.label"
                                            class="min-w-0 pb-1"
                                            :class="item.fullWidth ? 'sm:col-span-2 md:col-span-3 lg:col-span-4' : ''"
                                        >
                                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ item.label }}</p>
                                            <p class="mt-2 text-base font-bold text-slate-900 dark:text-white" :class="item.breakWords ? 'break-words' : ''">
                                                {{ item.value }}
                                            </p>
                                        </div>
                                    </div>

                                    <div v-if="selectedPlayerSocialLinks.length > 0" :class="selectedPlayerInformationRows.length > 0 ? 'border-t border-slate-200 pt-3 dark:border-[#1a1a1a]' : ''">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Social Links</p>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <a
                                                v-for="link in selectedPlayerSocialLinks"
                                                :key="link.platform"
                                                :href="link.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                :title="link.label"
                                                class="inline-flex items-center justify-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:scale-105 hover:bg-slate-100 hover:text-blue-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/15 dark:hover:text-blue-400"
                                            >
                                                <component :is="link.icon" class="h-4 w-4 shrink-0 text-blue-500 dark:text-blue-400" />
                                                <span class="text-xs font-bold">{{ link.label }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="selectedPlayerStatusRows.length > 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                                <div class="mb-3 flex items-center justify-between gap-3 border-b border-slate-200 pb-2.5 dark:border-[#1a1a1a]">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Status</p>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Membership and account standing.</p>
                                    </div>
                                </div>
                                <div class="space-y-2.5">
                                    <div
                                        v-for="item in selectedPlayerStatusRows"
                                        :key="item.label"
                                        class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-[#1a1a1a] dark:bg-[#111]"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ item.label }}</p>
                                        <p class="mt-2 text-base font-bold text-slate-900 dark:text-white">{{ item.value }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showExpandedPlayerAvatar && selectedPlayer?.avatar"
            class="fixed inset-0 z-[170] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
            @click="closeExpandedPlayerAvatar"
        >
            <button
                type="button"
                class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20"
                @click.stop="closeExpandedPlayerAvatar"
            >
                <X class="h-5 w-5" />
            </button>
            <img
                :src="selectedPlayer.avatar"
                :alt="displayPlayerName(selectedPlayer)"
                class="max-h-[85vh] max-w-[90vw] rounded-3xl object-contain shadow-2xl"
                @click.stop
            />
        </div>

        <!-- Date Detail Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showDateModal && selectedDateGroup"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                    @click.self="showDateModal = false"
                >
                    <div class="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-[#0f0f0f]">
                        <!-- Modal Header -->
                        <div
                            class="flex shrink-0 flex-col items-stretch justify-between gap-3 border-b border-slate-200 bg-slate-50 p-4 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] sm:flex-row sm:items-center sm:p-5"
                        >
                            <div class="flex items-center gap-3">
                                <Calendar class="h-5 w-5 text-indigo-500 dark:text-green-400" />
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                                    {{ formatDateGroupDate(selectedDateGroup.date) }}
                                </h2>
                            </div>
                            <div class="flex items-center justify-between gap-2 sm:justify-normal">
                                <button
                                    @click="showTopPlayersModal = true"
                                    class="rounded-md bg-indigo-50 hover:bg-indigo-100 dark:bg-green-950/20 dark:hover:bg-green-900/30 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-green-400 transition-all flex items-center gap-1"
                                >
                                    <Trophy class="h-3.5 w-3.5 text-amber-500" />
                                    Top Players
                                </button>
                                <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 dark:bg-[#1a1a1a]">
                                    <button
                                        @click="modalCategory = 'all'"
                                        class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                        :class="
                                            modalCategory === 'all'
                                                ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900'
                                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                        "
                                    >
                                        All
                                    </button>
                                    <button
                                        @click="modalCategory = 'booking'"
                                        class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                        :class="
                                            modalCategory === 'booking'
                                                ? 'bg-emerald-500 text-white shadow-sm'
                                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                        "
                                    >
                                        Court Booking
                                    </button>
                                    <button
                                        @click="modalCategory = 'walkin'"
                                        class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                        :class="
                                            modalCategory === 'walkin'
                                                ? 'bg-amber-500 text-white shadow-sm'
                                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                        "
                                    >
                                        Court Walk-in
                                    </button>
                                    <button
                                        @click="modalCategory = 'reclub'"
                                        class="rounded-md px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition-all"
                                        :class="
                                            modalCategory === 'reclub'
                                                ? 'bg-purple-500 text-white shadow-sm'
                                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                        "
                                    >
                                        Reclub
                                    </button>
                                </div>
                                <button
                                    @click="showDateModal = false"
                                    class="ml-1 flex h-8 w-8 items-center justify-center rounded-lg transition-colors hover:bg-slate-200 dark:hover:bg-[#1a1a1a]"
                                >
                                    <span class="text-xl leading-none text-slate-400">&times;</span>
                                </button>
                            </div>
                        </div>

                        <!-- Modal Content -->
                        <div class="custom-scrollbar flex-1 space-y-3 overflow-y-auto p-4 sm:p-5">
                            <!-- Empty State -->
                            <div
                                v-if="selectedDateMatches.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center opacity-50"
                            >
                                <History class="mb-2 h-10 w-10 text-slate-300 dark:text-[#1a1a1a]" />
                                <p class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
                                    No {{ modalCategory === 'walkin' ? 'walk-in' : modalCategory === 'reclub' ? 'reclub' : modalCategory === 'booking' ? 'booking' : '' }} matches
                                </p>
                            </div>

                            <div
                                v-for="match in selectedDateMatches"
                                :key="match.id"
                                class="rounded-xl border bg-white p-3 transition-all dark:bg-[#0f0f0f] sm:p-4"
                                :class="
                                    match.is_walkin 
                                        ? 'border-amber-200 dark:border-amber-800/50' 
                                        : (match.booking_type === 'reclub' 
                                            ? 'border-purple-200 dark:border-purple-800/50' 
                                            : 'border-emerald-200 dark:border-emerald-800/50')
                                "
                            >
                                <!-- Court Type Badge -->
                                <div class="mb-3 flex items-center justify-between">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                                        :class="
                                            match.is_walkin
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                                                : (match.booking_type === 'reclub'
                                                    ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'
                                                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300')
                                        "
                                    >
                                        <MapPin class="h-3 w-3" />
                                        {{ match.is_walkin ? 'Court Walk-in' : (match.booking_type === 'reclub' ? 'Reclub' : 'Court Booking') }}
                                    </span>
                                    <div class="flex items-center gap-2.5">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500 dark:text-slate-400">
                                            <Clock class="h-3 w-3" />
                                            {{ match.booking_time || (match.created_at ? new Date(match.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '') }}
                                        </span>
                                        <span v-if="match.is_walkin && match.fee_amount" class="text-[10px] font-black text-slate-500">
                                            ₱{{ match.fee_amount }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Match Teams -->
                                <div class="flex items-center justify-between gap-3">
                                    <!-- Team 1 -->
                                    <div
                                        class="flex-1 rounded-lg p-2 text-center"
                                        :class="
                                            match.team1.won
                                                ? 'border border-blue-100 bg-blue-50 dark:border-green-800/50 dark:bg-green-900/20'
                                                : 'bg-slate-50 dark:bg-[#1a1a1a]/50'
                                        "
                                    >
                                        <p
                                            class="mb-1 text-[10px] font-black uppercase tracking-wider"
                                            :class="match.team1.won ? 'text-blue-500 dark:text-green-400' : 'text-slate-400'"
                                        >
                                            {{ match.team1.won ? 'Winner' : 'Team A' }}
                                        </p>
                                        <div class="flex flex-wrap items-center justify-center gap-1 text-sm font-black capitalize text-slate-900 dark:text-white">
                                            <template v-for="(pName, pIdx) in match.team1.players" :key="pIdx">
                                                <button
                                                    type="button"
                                                    @click="handlePlayerClick(match.team1.player_ids?.[pIdx] || pName)"
                                                    class="hover:underline hover:text-indigo-600 dark:hover:text-green-400 transition-colors"
                                                >
                                                    {{ pName }}
                                                </button>
                                                <span v-if="pIdx < match.team1.players.length - 1" class="text-slate-400 font-normal">&amp;</span>
                                            </template>
                                        </div>
                                        <p
                                            class="mt-1 text-lg font-black"
                                            :class="match.team1.won ? 'text-blue-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400'"
                                        >
                                            {{ match.team1.score }}
                                        </p>
                                    </div>

                                    <!-- VS -->
                                    <div class="flex-shrink-0">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">vs</span>
                                    </div>

                                    <!-- Team 2 -->
                                    <div
                                        class="flex-1 rounded-lg p-2 text-center"
                                        :class="
                                            match.team2.won
                                                ? 'border border-blue-100 bg-blue-50 dark:border-green-800/50 dark:bg-green-900/20'
                                                : 'bg-slate-50 dark:bg-[#1a1a1a]/50'
                                        "
                                    >
                                        <p
                                            class="mb-1 text-[10px] font-black uppercase tracking-wider"
                                            :class="match.team2.won ? 'text-blue-500 dark:text-green-400' : 'text-slate-400'"
                                        >
                                            {{ match.team2.won ? 'Winner' : 'Team B' }}
                                        </p>
                                        <div class="flex flex-wrap items-center justify-center gap-1 text-sm font-black capitalize text-slate-900 dark:text-white">
                                            <template v-for="(pName, pIdx) in match.team2.players" :key="pIdx">
                                                <button
                                                    type="button"
                                                    @click="handlePlayerClick(match.team2.player_ids?.[pIdx] || pName)"
                                                    class="hover:underline hover:text-indigo-600 dark:hover:text-green-400 transition-colors"
                                                >
                                                    {{ pName }}
                                                </button>
                                                <span v-if="pIdx < match.team2.players.length - 1" class="text-slate-400 font-normal">&amp;</span>
                                            </template>
                                        </div>
                                        <p
                                            class="mt-1 text-lg font-black"
                                            :class="match.team2.won ? 'text-blue-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400'"
                                        >
                                            {{ match.team2.score }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Top Players Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showTopPlayersModal && selectedDateGroup"
                    class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4"
                    @click.self="showTopPlayersModal = false"
                >
                    <div class="flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-[#0f0f0f]">
                        <!-- Modal Header -->
                        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50 p-4 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]">
                            <div class="flex items-center gap-2">
                                <Trophy class="h-5 w-5 text-amber-500" />
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Top Players of the Day</h2>
                            </div>
                            <button
                                @click="showTopPlayersModal = false"
                                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors hover:bg-slate-200 dark:hover:bg-[#1a1a1a]"
                            >
                                <span class="text-xl leading-none text-slate-400">&times;</span>
                            </button>
                        </div>

                        <!-- Modal Content -->
                        <div class="custom-scrollbar flex-1 overflow-y-auto p-4 space-y-4">
                            <!-- Date Preview Header -->
                            <div class="rounded-xl bg-slate-50 dark:bg-[#1a1a1a]/55 p-3 text-center border border-slate-100 dark:border-[#2a2a2a]/55">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Date Standings</p>
                                <p class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                    {{ formatDateGroupDate(selectedDateGroup.date) }}
                                </p>
                            </div>

                            <!-- Player List Ranked Overall -->
                            <div class="space-y-2">
                                <div
                                    v-for="player in topPlayersInDateMatches"
                                    :key="player.id"
                                    @click="handlePlayerClick(player)"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition-all hover:border-indigo-300 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:hover:border-green-800/60"
                                >
                                    <!-- Overall standing rank badges -->
                                    <div class="flex w-9 flex-shrink-0 justify-center">
                                        <span
                                            v-if="rankMap.get(player.id) === 0"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-amber-400 to-yellow-500 text-[10px] font-black text-slate-900 shadow-md shadow-amber-400/30"
                                            >1</span
                                        >
                                        <span
                                            v-else-if="rankMap.get(player.id) === 1"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-slate-300 to-slate-400 text-[10px] font-black text-slate-900 shadow-sm"
                                            >2</span
                                        >
                                        <span
                                            v-else-if="rankMap.get(player.id) === 2"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-amber-600 text-[10px] font-black text-white shadow-sm"
                                            >3</span
                                        >
                                        <span v-else class="inline-flex h-7 w-7 items-center justify-center text-xs font-black text-slate-400"
                                            >#{{ (rankMap.get(player.id) ?? 0) + 1 }}</span
                                        >
                                    </div>

                                    <!-- Avatar + Name -->
                                    <div class="flex min-w-0 flex-1 items-center gap-2">
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 text-xs font-black text-white shadow-sm dark:from-green-600 dark:to-green-700"
                                        >
                                            {{ displayPlayerName(player).charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-black capitalize text-slate-900 dark:text-white">{{ displayPlayerName(player) }}</p>
                                            <div class="mt-0.5 flex items-center gap-1.5">
                                                <span class="text-[9px] font-semibold text-slate-500">{{ getMatchesCountForDate(player) }} matches today</span>
                                                <span class="text-[9px] text-slate-300">•</span>
                                                <span
                                                    class="text-[9px] font-black"
                                                    :class="player.win_rate >= 50 ? 'text-emerald-500' : 'text-amber-500'"
                                                    >{{ player.win_rate }}% WR</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Points -->
                                    <div class="flex-shrink-0 text-right">
                                        <p class="text-sm font-black leading-none text-indigo-600 dark:text-green-400">{{ player.points }}</p>
                                        <p class="mt-0.5 text-[8px] font-semibold text-slate-400">pts</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
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
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
