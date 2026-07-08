<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    Calendar,
    CheckCircle,
    CreditCard,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Search,
    ShieldCheck,
    Trash2,
    User,
    UserPlus,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const searchQuery = ref('');
const activeCount = computed(() => props.players.filter((p) => p.is_member).length);
const inactiveCount = computed(() => props.players.filter((p) => !p.is_member).length);
const filteredPlayers = computed(() => {
    if (!searchQuery.value) return props.players;
    const q = searchQuery.value.toLowerCase();
    return props.players.filter((p) => 
        p.name.toLowerCase().includes(q) || 
        (p.full_name && p.full_name.toLowerCase().includes(q)) ||
        (p.phone && p.phone.toLowerCase().includes(q))
    );
});

const props = defineProps<{
    players: any[];
    settings?: Record<string, string>;
}>();

const displayPlayerName = (player: any) => player?.username || player?.name || 'Player';
const displayUsername = (player: any) => player?.username || player?.name || 'Not provided';
const hasLinkedAccount = (player: any) => Boolean(player?.user_id);

const playerForm = useForm({
    name: '',
    full_name: '',
    phone: '',
    birthday: '',
    address: '',
});

const submitPlayer = async () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const response = await fetch(route('players.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({
                name: playerForm.name,
                full_name: playerForm.full_name,
                phone: playerForm.phone,
                birthday: playerForm.birthday,
                address: playerForm.address,
                from_membership: true,
            }),
        });
        if (!response.ok) throw new Error('Failed to save player');
        playerForm.reset();
        showAddModal.value = false;
        triggerToast('Player saved successfully.');
        router.reload({ only: ['players'] });
    } catch (err: any) {
        triggerToast(err.message || 'Something went wrong.');
    }
};

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

const toggleMembership = (player: any) => {
    const isGrant = !player.is_member;
    useForm({}).post(route('memberships.toggle', player.id), {
        onSuccess: () => triggerToast(isGrant ? `Membership granted to ${player.name}.` : `Membership revoked from ${player.name}.`),
    });
};

const payDue = (player: any) => {
    useForm({}).post(route('memberships.pay-due', player.id), {
        onSuccess: () => triggerToast(`Monthly due paid for ${player.name}.`),
    });
};

const revokeDue = (player: any) => {
    useForm({}).post(route('memberships.revoke-due', player.id), {
        onSuccess: () => triggerToast(`Monthly due revoked for ${player.name}.`),
    });
};

const deletePlayer = (player: any) => {
    const playerName = displayPlayerName(player);
    if (!window.confirm(`Delete ${playerName} from the roster?`)) {
        return;
    }

    router.delete(route('players.destroy', player.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedPlayer.value?.id === player.id) {
                showDetailModal.value = false;
                selectedPlayer.value = null;
                isEditing.value = false;
            }
            triggerToast(`${playerName} deleted from the roster.`);
        },
        onError: () => {
            triggerToast(`Failed to delete ${playerName}.`);
        },
    });
};

const hasPaidThisMonth = (player: any) => {
    if (!player.last_monthly_due_paid_at) return false;
    const paid = new Date(player.last_monthly_due_paid_at);
    const now = new Date();
    return paid.getFullYear() === now.getFullYear() && paid.getMonth() === now.getMonth();
};

const monthlyDueExpires = (player: any) => {
    if (!player.last_monthly_due_paid_at) return null;
    const paid = new Date(player.last_monthly_due_paid_at);
    const endOfMonth = new Date(paid.getFullYear(), paid.getMonth() + 1, 0);
    return endOfMonth;
};

const formatExpiryDate = (date: Date | null) => {
    if (!date) return '—';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const showAddModal = ref(false);
const showDetailModal = ref(false);
const selectedPlayer = ref<any>(null);
const isEditing = ref(false);

const editForm = useForm({
    name: '',
    full_name: '',
    phone: '',
    birthday: '',
    address: '',
    membership_expires_at: '',
    last_monthly_due_paid_at: '',
    created_at: '',
});

const openPlayerDetail = (player: any) => {
    selectedPlayer.value = player;
    isEditing.value = false;
    showDetailModal.value = true;
};

const formatDateToInput = (dateVal: any) => {
    if (!dateVal) return '';
    const dateObj = new Date(dateVal);
    if (isNaN(dateObj.getTime())) return '';
    return dateObj.toISOString().split('T')[0];
};

const startEditing = () => {
    if (!selectedPlayer.value) return;
    editForm.name = selectedPlayer.value.name || '';
    editForm.full_name = selectedPlayer.value.full_name || '';
    editForm.phone = selectedPlayer.value.phone || '';
    editForm.birthday = selectedPlayer.value.birthday || '';
    editForm.address = selectedPlayer.value.address || '';
    editForm.membership_expires_at = formatDateToInput(selectedPlayer.value.membership_expires_at);
    editForm.last_monthly_due_paid_at = formatDateToInput(selectedPlayer.value.last_monthly_due_paid_at);
    editForm.created_at = formatDateToInput(selectedPlayer.value.created_at);
    isEditing.value = true;
};

const cancelEditing = () => {
    isEditing.value = false;
};

const saveEdit = () => {
    if (!selectedPlayer.value) return;
    editForm.put(route('players.update', selectedPlayer.value.id), {
        onSuccess: () => {
            isEditing.value = false;
            // Update the local selected player data
            selectedPlayer.value.name = editForm.name;
            selectedPlayer.value.full_name = editForm.full_name;
            selectedPlayer.value.phone = editForm.phone;
            selectedPlayer.value.birthday = editForm.birthday;
            selectedPlayer.value.address = editForm.address;
            selectedPlayer.value.membership_expires_at = editForm.membership_expires_at;
            selectedPlayer.value.last_monthly_due_paid_at = editForm.last_monthly_due_paid_at;
            selectedPlayer.value.created_at = editForm.created_at;
            triggerToast(`${editForm.name} details saved successfully.`);
        },
    });
};

const monthlyDue = computed(() => Number(props.settings?.membership_monthly_fee ?? 15));
const yearlyDue = computed(() => Number(props.settings?.membership_yearly_fee ?? 50));
const formatPeso = (v: number) => `₱${v.toLocaleString()}`;
let pollInterval: ReturnType<typeof setInterval> | null = null;
const POLL_RELOAD = ['players'];

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
    <Head title="Memberships" />

    <AppLayout>
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

        <div class="p-3 sm:p-6 lg:h-[calc(100vh-64px)] lg:overflow-hidden lg:p-8">
            <div class="flex w-full flex-col gap-4 sm:gap-6 lg:h-full">
                <!-- Stat Cards -->
                <div class="grid shrink-0 grid-cols-3 gap-2 sm:gap-4">
                    <div
                        class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2.5 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:gap-4 sm:p-5"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 dark:bg-green-900/20 sm:h-11 sm:w-11">
                            <Users class="h-4 w-4 text-indigo-500 dark:text-green-400 sm:h-5 sm:w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-[9px] font-black uppercase tracking-widest text-slate-400 sm:text-[10px]">Total Players</p>
                            <p class="text-lg font-black text-slate-900 dark:text-white sm:text-2xl">{{ props.players.length }}</p>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:gap-4 sm:p-5"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 sm:h-11 sm:w-11"
                        >
                            <ShieldCheck class="h-4 w-4 text-emerald-500 sm:h-5 sm:w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-[9px] font-black uppercase tracking-widest text-slate-400 sm:text-[10px]">Member</p>
                            <p class="text-lg font-black text-slate-900 dark:text-white sm:text-2xl">{{ activeCount }}</p>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:gap-4 sm:p-5"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 dark:bg-[#1a1a1a] sm:h-11 sm:w-11">
                            <Activity class="h-4 w-4 text-slate-400 sm:h-5 sm:w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-[9px] font-black uppercase tracking-widest text-slate-400 sm:text-[10px]">Non-Member</p>
                            <p class="text-lg font-black text-slate-900 dark:text-white sm:text-2xl">{{ inactiveCount }}</p>
                        </div>
                    </div>
                </div>

                <!-- Roster Panel -->
                <div class="clean-card flex min-h-0 flex-1 flex-col overflow-hidden">
                    <!-- Panel Header -->
                    <div
                        class="flex shrink-0 flex-col gap-3 border-b border-slate-200 p-3 dark:border-[#1a1a1a] sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:p-5"
                    >
                        <h2 class="text-base font-bold text-slate-900 dark:text-white sm:text-lg">Roster</h2>
                        <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-3">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search players..."
                                    class="min-h-[44px] w-full rounded-xl border border-transparent bg-slate-100 py-2.5 pl-9 pr-4 text-base font-semibold text-slate-900 placeholder-slate-400 outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-white dark:ring-green-500/30 sm:w-56 sm:text-sm"
                                />
                            </div>
                            <button
                                @click="showAddModal = true"
                                class="flex min-h-[44px] shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-black text-white shadow-md transition-all hover:scale-105 dark:bg-white dark:text-slate-900"
                            >
                                <UserPlus class="h-4 w-4" />
                                Add Player
                            </button>
                        </div>
                    </div>
                    <div class="custom-scrollbar flex-1 space-y-2 overflow-y-auto p-2">
                        <!-- Header Row (hidden on mobile) -->
                        <div
                            class="hidden grid-cols-[1fr_130px_140px_140px_170px] gap-4 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-400 md:grid"
                        >
                            <div>Player</div>
                            <div class="text-center">Status</div>
                            <div class="text-center">Membership Expires</div>
                            <div class="text-center">Due Expires</div>
                            <div class="text-right">Action</div>
                        </div>

                        <!-- Player Cards -->
                        <div
                            v-for="player in filteredPlayers"
                            :key="player.id"
                            @click="openPlayerDetail(player)"
                            class="group relative flex cursor-pointer flex-col gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 transition-all hover:border-indigo-300 hover:shadow-md dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:hover:border-green-700 sm:grid sm:grid-cols-[1fr_130px_140px_140px_170px] sm:items-center sm:gap-4 sm:px-5 sm:py-3.5"
                        >
                            <!-- Left accent bar -->
                            <div
                                class="absolute bottom-3 left-0 top-3 w-[3px] rounded-full bg-indigo-500 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-green-500"
                            ></div>

                            <!-- Mobile Top / Desktop Player -->
                            <div class="flex items-center justify-between gap-2 pl-1 sm:justify-start sm:gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 text-sm font-black text-white shadow-md dark:from-green-500 dark:to-green-600"
                                    >
                                        {{ displayPlayerName(player).charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ displayPlayerName(player) }}</p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider"
                                                :class="
                                                    hasLinkedAccount(player)
                                                        ? 'border border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-800/50 dark:bg-blue-900/20 dark:text-blue-400'
                                                        : 'border border-slate-200 bg-slate-100 text-slate-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400'
                                                "
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full" :class="hasLinkedAccount(player) ? 'bg-blue-500' : 'bg-slate-400'"></span>
                                                {{ hasLinkedAccount(player) ? 'Player account' : 'No account' }}
                                            </span>
                                        </div>
                                        <p v-if="player.is_member" class="text-[10px] font-medium text-slate-400">
                                            {{ formatPeso(monthlyDue) }}/month
                                        </p>
                                    </div>
                                </div>

                                <!-- Mobile Status Badge -->
                                <div class="sm:hidden">
                                    <span
                                        v-if="player.is_member"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-600 dark:border-emerald-800/50 dark:bg-emerald-900/20 dark:text-emerald-400"
                                    >
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Member
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Non-Member
                                    </span>
                                </div>

                                <!-- Mobile Due Expires (below status) -->
                                <div v-if="player.is_member" class="sm:hidden mt-1">
                                    <span class="text-[9px] font-medium text-slate-400">
                                        Due: {{ formatExpiryDate(monthlyDueExpires(player)) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Desktop Status -->
                            <div class="hidden justify-center sm:flex">
                                <span
                                    v-if="player.is_member"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-emerald-600 dark:border-emerald-800/50 dark:bg-emerald-900/20 dark:text-emerald-400"
                                >
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Member
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Non-Member
                                </span>
                            </div>

                            <!-- Expiry (hidden on mobile) -->
                            <div class="hidden items-center justify-center gap-1.5 md:flex">
                                <Calendar v-if="player.is_member" class="h-3.5 w-3.5 text-slate-400" />
                                <span v-if="player.is_member" class="text-sm font-bold text-slate-600 dark:text-slate-300">
                                    {{
                                        new Date(player.membership_expires_at).toLocaleDateString('en-US', {
                                            month: 'short',
                                            day: 'numeric',
                                            year: 'numeric',
                                        })
                                    }}
                                </span>
                                <span v-else class="text-sm text-slate-300 dark:text-slate-600">—</span>
                            </div>

                            <!-- Due Expires (hidden on mobile) -->
                            <div class="hidden items-center justify-center gap-1.5 md:flex">
                                <CreditCard v-if="player.is_member" class="h-3.5 w-3.5 text-slate-400" />
                                <span v-if="player.is_member && monthlyDueExpires(player)" class="text-sm font-bold text-slate-600 dark:text-slate-300">
                                    {{ formatExpiryDate(monthlyDueExpires(player)) }}
                                </span>
                                <span v-else class="text-sm text-slate-300 dark:text-slate-600">—</span>
                            </div>

                            <!-- Mobile Bottom / Desktop Actions -->
                            <div class="flex items-center justify-between gap-2 sm:justify-end">
                                <!-- Paid badge (mobile only) -->
                                <button
                                    v-if="player.is_member && hasPaidThisMonth(player)"
                                    @click.stop="revokeDue(player)"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-emerald-200 bg-emerald-100 px-2 py-1 text-[9px] font-black uppercase tracking-wider text-emerald-700 transition-all hover:border-rose-300 hover:bg-rose-100 hover:text-rose-700 sm:hidden"
                                    title="Click to revoke monthly payment"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Paid
                                </button>

                                <div class="flex justify-end gap-2">
                                    <template v-if="player.is_member">
                                        <button
                                            v-if="!hasPaidThisMonth(player)"
                                            @click.stop="payDue(player)"
                                            class="flex items-center gap-1.5 rounded-xl bg-emerald-500 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white shadow-sm shadow-emerald-500/20 transition-all hover:bg-emerald-600 active:scale-95"
                                        >
                                            <CreditCard class="h-3 w-3" />
                                            <span class="hidden sm:inline">Pay Due</span>
                                            <span class="sm:hidden">Pay</span>
                                        </button>
                                        <button
                                            @click.stop="toggleMembership(player)"
                                            class="flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-rose-600 transition-all hover:border-rose-300 hover:bg-rose-100 active:scale-95 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-400 dark:hover:bg-rose-900/30"
                                        >
                                            <XCircle class="h-3 w-3" />
                                        </button>
                                    </template>
                                    <button
                                        v-else
                                        @click.stop="toggleMembership(player)"
                                        class="flex items-center gap-1.5 rounded-xl bg-blue-500 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white shadow-sm shadow-blue-500/20 transition-all hover:bg-blue-600 active:scale-95 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500"
                                    >
                                        <ShieldCheck class="h-3 w-3" />
                                        Grant
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="filteredPlayers.length === 0" class="py-16 text-center">
                            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-[#1a1a1a]">
                                <Search class="h-6 w-6 text-slate-400" />
                            </div>
                            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">No players found</p>
                            <p class="mt-1 text-xs text-slate-400">Try a different search term</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Player Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center" @click.self="showAddModal = false">
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-4"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 translate-y-4"
                    >
                        <div
                            v-if="showAddModal"
                            class="flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl border border-slate-200/60 bg-white shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:mx-4 sm:max-h-[90vh] sm:rounded-3xl"
                        >
                            <!-- Header with gradient -->
                            <div class="relative bg-gradient-to-br from-indigo-600 to-violet-600 px-6 py-5 dark:from-green-600 dark:to-emerald-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20">
                                            <UserPlus class="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-white">New Player</p>
                                            <p class="text-[10px] font-medium text-indigo-100 dark:text-green-100">Add to roster</p>
                                        </div>
                                    </div>
                                    <button
                                        @click="showAddModal = false"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 transition-colors hover:bg-white/20"
                                    >
                                        <XCircle class="h-5 w-5 text-white/80" />
                                    </button>
                                </div>
                            </div>

                            <form @submit.prevent="submitPlayer" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-6 pb-8">
                                <div>
                                    <label class="mb-2 flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-400">
                                        <User class="h-3.5 w-3.5 text-indigo-500 dark:text-green-500" />
                                        Username
                                    </label>
                                    <input
                                        v-model="playerForm.name"
                                        type="text"
                                        required
                                        placeholder="cruz, alex, rigel..."
                                        class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-500/30 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/30"
                                    />
                                </div>
                                <div>
                                    <label class="mb-2 flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-400">
                                        <User class="h-3.5 w-3.5 text-indigo-500 dark:text-green-500" />
                                        Full Name <span class="font-normal text-slate-400">(Optional)</span>
                                    </label>
                                    <input
                                        v-model="playerForm.full_name"
                                        type="text"
                                        placeholder="Juan Cruz Dela Cruz"
                                        class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-500/30 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/30"
                                    />
                                </div>
                                <div>
                                    <label class="mb-2 flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-400">
                                        <Phone class="h-3.5 w-3.5 text-indigo-500 dark:text-green-500" />
                                        Phone <span class="font-normal text-slate-400">(Optional)</span>
                                    </label>
                                    <input
                                        v-model="playerForm.phone"
                                        type="tel"
                                        placeholder="+63 912 345 6789"
                                        class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-500/30 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/30"
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="mb-2 flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-400">
                                            <Calendar class="h-3.5 w-3.5 text-indigo-500 dark:text-green-500" />
                                            Birthday
                                        </label>
                                        <input
                                            v-model="playerForm.birthday"
                                            type="date"
                                            class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-500/30 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/30"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-2 flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-400">
                                            <MapPin class="h-3.5 w-3.5 text-indigo-500 dark:text-green-500" />
                                            Address
                                        </label>
                                        <input
                                            v-model="playerForm.address"
                                            type="text"
                                            placeholder="City, Country"
                                            class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-500/30 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white dark:focus:border-green-400 dark:focus:ring-green-500/30"
                                        />
                                    </div>
                                </div>
                                <div class="flex gap-3 pt-3">
                                    <button
                                        type="button"
                                        @click="showAddModal = false"
                                        class="flex-1 rounded-xl bg-slate-100 px-4 py-3 text-xs font-black uppercase tracking-widest text-slate-600 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-300 dark:hover:bg-[#2a2a2a]"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="playerForm.processing"
                                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-3 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-500/25 transition-all hover:from-indigo-700 hover:to-violet-700 dark:from-green-600 dark:to-emerald-700 dark:shadow-green-500/25 dark:hover:from-green-500 dark:hover:to-emerald-600"
                                    >
                                        <CheckCircle class="h-4 w-4" />
                                        Save Player
                                    </button>
                                </div>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Player Detail Modal -->
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
                    v-if="showDetailModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
                    @click.self="showDetailModal = false"
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
                            v-if="showDetailModal && selectedPlayer"
                            class="flex h-full w-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f] sm:mx-4 sm:h-auto sm:max-h-[85vh] sm:max-w-lg sm:p-6"
                        >
                            <div class="mb-6 flex items-center justify-between">
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-green-500">
                                    {{ isEditing ? 'Edit Player' : 'Player Details' }}
                                </p>
                                <button
                                    @click="showDetailModal = false"
                                    class="text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-200"
                                >
                                    <XCircle class="h-5 w-5" />
                                </button>
                            </div>

                            <div class="mb-6 flex items-center gap-4">
                                <div
                                    class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 text-2xl font-black text-white shadow-lg dark:from-green-500 dark:to-green-600"
                                >
                                    {{ displayPlayerName(selectedPlayer).charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-xl font-black text-slate-900 dark:text-white">
                                        {{ displayPlayerName(selectedPlayer) }}
                                    </p>
                                    <span
                                        class="mt-1 inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-wider"
                                        :class="
                                            hasLinkedAccount(selectedPlayer)
                                                ? 'border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-800/50 dark:bg-blue-900/20 dark:text-blue-400'
                                                : 'border-slate-200 bg-slate-100 text-slate-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400'
                                        "
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="hasLinkedAccount(selectedPlayer) ? 'bg-blue-500' : 'bg-slate-400'"></span>
                                        {{ hasLinkedAccount(selectedPlayer) ? 'Has player account' : 'No player account' }}
                                    </span>
                                    <span
                                        v-if="selectedPlayer.is_member"
                                        class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-600 dark:border-emerald-800/50 dark:bg-emerald-900/20 dark:text-emerald-400"
                                    >
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Member
                                    </span>
                                    <span
                                        v-else
                                        class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-slate-400"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Non-Member
                                    </span>
                                </div>
                            </div>

                            <!-- View Mode -->
                            <div v-if="!isEditing" class="min-h-0 flex-1 space-y-4 overflow-y-auto">
                                <div
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                >
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-green-900/20">
                                        <User class="h-4 w-4 text-violet-500 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Full Name</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                                            {{ selectedPlayer.full_name || displayPlayerName(selectedPlayer) || 'Not provided' }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                >
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-900/20">
                                        <Mail class="h-4 w-4 text-sky-500 dark:text-sky-400" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Email</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ selectedPlayer.email || 'Not provided' }}</p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                >
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-green-900/20">
                                        <Phone class="h-4 w-4 text-blue-500 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Phone</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ selectedPlayer.phone || 'Not provided' }}</p>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                >
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/20">
                                        <Calendar class="h-4 w-4 text-amber-500" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Birthday</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                                            {{
                                                selectedPlayer.birthday
                                                    ? new Date(selectedPlayer.birthday).toLocaleDateString('en-US', {
                                                          month: 'long',
                                                          day: 'numeric',
                                                          year: 'numeric',
                                                      })
                                                    : 'Not provided'
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                >
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                                        <MapPin class="h-4 w-4 text-emerald-500" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Address</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ selectedPlayer.address || 'Not provided' }}</p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#1a1a1a] dark:bg-[#0a0a0a]"
                                >
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-900/20">
                                        <Users class="h-4 w-4 text-rose-500 dark:text-rose-400" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Gender</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ selectedPlayer.gender || 'Not provided' }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <div
                                        class="rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-center dark:border-green-800/40 dark:bg-green-900/20"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400 dark:text-green-400">
                                            Monthly Due
                                        </p>
                                        <p v-if="selectedPlayer.is_member" class="text-lg font-black text-indigo-600 dark:text-green-400">
                                            {{ formatPeso(monthlyDue) }}
                                        </p>
                                        <p v-else class="text-lg font-black text-slate-300 dark:text-slate-600">—</p>
                                    </div>
                                    <div
                                        class="rounded-xl border border-violet-100 bg-violet-50 p-3 text-center dark:border-green-800/40 dark:bg-green-900/20"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-widest text-violet-400 dark:text-green-400">Yearly Due</p>
                                        <p v-if="selectedPlayer.is_member" class="text-lg font-black text-violet-600 dark:text-green-400">
                                            {{ formatPeso(yearlyDue) }}
                                        </p>
                                        <p v-else class="text-lg font-black text-slate-300 dark:text-slate-600">—</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div
                                        v-if="selectedPlayer.membership_expires_at"
                                        class="rounded-xl border border-rose-100 bg-rose-50 p-3 text-center dark:border-rose-800/40 dark:bg-rose-900/20"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-widest text-rose-400">Membership Expires</p>
                                        <p class="text-lg font-black text-rose-600 dark:text-rose-400">
                                            {{
                                                new Date(selectedPlayer.membership_expires_at).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                })
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="selectedPlayer.is_member && monthlyDueExpires(selectedPlayer)"
                                        class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-center dark:border-amber-800/40 dark:bg-amber-900/20"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-400">Due Expires</p>
                                        <p class="text-lg font-black text-amber-600 dark:text-amber-400">
                                            {{ formatExpiryDate(monthlyDueExpires(selectedPlayer)) }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="selectedPlayer.is_member && selectedPlayer.created_at"
                                    class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-center dark:border-emerald-800/40 dark:bg-emerald-900/20"
                                >
                                    <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Started Member</p>
                                    <p class="text-lg font-black text-emerald-600 dark:text-emerald-400">
                                        {{
                                            new Date(selectedPlayer.created_at).toLocaleDateString('en-US', {
                                                month: 'short',
                                                day: 'numeric',
                                                year: 'numeric',
                                            })
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- Fixed buttons (View Mode) -->
                            <div v-if="!isEditing" class="flex shrink-0 gap-3 pb-safe pt-4">
                                <button
                                    @click="deletePlayer(selectedPlayer)"
                                    class="flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-[11px] font-black uppercase tracking-widest text-rose-600 transition-all hover:border-rose-300 hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-400 dark:hover:bg-rose-900/30"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Delete
                                </button>
                                <button
                                    @click="startEditing"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3.5 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-500/20 transition-all hover:bg-indigo-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500"
                                >
                                    <Pencil class="h-4 w-4" />
                                    Edit
                                </button>
                                <button
                                    @click="showDetailModal = false"
                                    class="flex-1 rounded-xl bg-slate-100 px-4 py-3.5 text-[11px] font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-200 dark:hover:bg-[#2a2a2a]"
                                >
                                    Close
                                </button>
                            </div>

                            <!-- Edit Mode -->
                            <form v-else @submit.prevent="saveEdit" class="min-h-0 flex-1 space-y-4 overflow-y-auto pb-6">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Username</label>
                                    <input
                                        v-model="editForm.name"
                                        type="text"
                                        required
                                        class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500/30"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400"
                                        >Full Name <span class="font-normal text-slate-400">(Optional)</span></label
                                    >
                                    <input
                                        v-model="editForm.full_name"
                                        type="text"
                                        class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500/30"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Phone (Optional)</label>
                                    <input
                                        v-model="editForm.phone"
                                        type="tel"
                                        class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500/30"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Birthday</label>
                                    <input
                                        v-model="editForm.birthday"
                                        type="date"
                                        class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500/30"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Address</label>
                                    <input
                                        v-model="editForm.address"
                                        type="text"
                                        class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500/30"
                                    />
                                </div>
                                <template v-if="selectedPlayer.is_member">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Started Member Date</label>
                                        <input
                                            v-model="editForm.created_at"
                                            type="date"
                                            class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500/30"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Membership Expiry Date</label>
                                        <input
                                            v-model="editForm.membership_expires_at"
                                            type="date"
                                            class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500/30"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Last Monthly Due Payment Date</label>
                                        <input
                                            v-model="editForm.last_monthly_due_paid_at"
                                            type="date"
                                            class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold outline-none ring-indigo-500/30 transition-all focus:ring-2 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:focus:ring-green-500/30"
                                        />
                                    </div>
                                </template>
                                <div class="flex gap-3 pt-2">
                                    <button
                                        type="button"
                                        @click="cancelEditing"
                                        class="flex-1 rounded-xl bg-slate-100 px-4 py-3 text-[11px] font-black uppercase tracking-widest text-slate-700 transition-all hover:bg-slate-200 dark:bg-[#1a1a1a] dark:text-slate-200 dark:hover:bg-[#2a2a2a]"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="editForm.processing"
                                        class="flex-1 rounded-xl bg-indigo-600 px-4 py-3 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-500/20 transition-all hover:bg-indigo-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-500"
                                    >
                                        Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.clean-card {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 1.5rem;
    box-shadow: 0 20px 35px -25px rgba(15, 23, 42, 0.1);
}
.dark .clean-card {
    background: rgba(15, 15, 15, 0.95);
    border-color: rgba(42, 42, 42, 0.6);
    box-shadow: 0 20px 35px -25px rgba(0, 0, 0, 0.6);
}
</style>
