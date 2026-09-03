<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Eye, Filter, MapPin, Search, ShieldCheck, Swords, X, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface RequestItem {
    id: number;
    name: string;
    category?: string | null;
    status: string;
    notes?: string | null;
    preferred_date?: string | null;
    preferred_start_time?: string | null;
    rejection_reason?: string | null;
    receipt_url?: string | null;
    total_cost?: number | null;
    payment_status?: string | null;
    user?: { id: number; name: string; username?: string | null; email: string } | null;
    venue?: { id: number; name: string; address?: string | null } | null;
    approver?: { id: number; name: string } | null;
    tournament?: { id: number; name: string; status: string } | null;
    tournamentDay?: { id: number; name: string; date?: string | null; status: string } | null;
}

const props = defineProps<{
    requests: RequestItem[];
}>();

const activeTab = ref<'all' | 'pending' | 'approved' | 'rejected'>('pending');
const searchQuery = ref('');
const previewReceiptUrl = ref<string | null>(null);

const rejectingId = ref<number | null>(null);
const rejectForm = useForm({
    rejection_reason: '',
});

const pendingCount = computed(() => props.requests.filter((r) => r.status === 'pending').length);
const approvedCount = computed(() => props.requests.filter((r) => r.status === 'approved').length);
const rejectedCount = computed(() => props.requests.filter((r) => r.status === 'rejected').length);
const allCount = computed(() => props.requests.length);

const filteredRequests = computed(() => {
    return props.requests.filter((r) => {
        const matchesTab = activeTab.value === 'all' || r.status === activeTab.value;
        if (!matchesTab) return false;

        if (!searchQuery.value.trim()) return true;
        const q = searchQuery.value.toLowerCase();
        const userName = (r.user?.username || r.user?.name || '').toLowerCase();
        const userEmail = (r.user?.email || '').toLowerCase();
        const reqName = (r.name || '').toLowerCase();
        const venueName = (r.venue?.name || '').toLowerCase();

        return userName.includes(q) || userEmail.includes(q) || reqName.includes(q) || venueName.includes(q);
    });
});

const approve = (requestId: number) => {
    router.post(route('tournament-requests.approve', requestId), {}, {
        preserveScroll: true,
    });
};

const startReject = (requestId: number) => {
    rejectingId.value = requestId;
    rejectForm.rejection_reason = '';
};

const submitReject = (requestId: number) => {
    rejectForm.post(route('tournament-requests.reject', requestId), {
        preserveScroll: true,
        onSuccess: () => {
            rejectingId.value = null;
            rejectForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Tournament Requests Hub" />

    <AppLayout :breadcrumbs="[{ title: 'Tournament Requests', href: '/tournament-requests' }]">
        <div class="space-y-6 p-4 sm:p-6">
            <!-- Header Banner -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-green-950/40 dark:text-green-400">
                            <ShieldCheck class="h-6 w-6" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 dark:text-white">Tournament Requests Hub</h1>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Review player venue requests, verify court allocations and payment receipts, and grant tournament edit access.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Status Filter Tabs & Search Bar -->
                <div class="mt-6 flex flex-col gap-4 border-t border-slate-100 pt-5 dark:border-[#1a1a1a] md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="activeTab = 'pending'"
                            class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-bold transition-all"
                            :class="activeTab === 'pending' ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#151515] dark:text-slate-300'"
                        >
                            <Clock class="h-3.5 w-3.5" />
                            Pending ({{ pendingCount }})
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'approved'"
                            class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-bold transition-all"
                            :class="activeTab === 'approved' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#151515] dark:text-slate-300'"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            Active Access ({{ approvedCount }})
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'rejected'"
                            class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-bold transition-all"
                            :class="activeTab === 'rejected' ? 'bg-rose-600 text-white shadow-md shadow-rose-600/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#151515] dark:text-slate-300'"
                        >
                            <XCircle class="h-3.5 w-3.5" />
                            Rejected ({{ rejectedCount }})
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'all'"
                            class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-bold transition-all"
                            :class="activeTab === 'all' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#151515] dark:text-slate-300'"
                        >
                            <Filter class="h-3.5 w-3.5" />
                            All Requests ({{ allCount }})
                        </button>
                    </div>

                    <div class="relative w-full md:w-72">
                        <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search player or venue..."
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-medium text-slate-900 outline-none transition focus:border-blue-500 dark:border-[#1a1a1a] dark:bg-[#111] dark:text-white"
                        />
                    </div>
                </div>
            </div>

            <!-- Request Cards List -->
            <div class="space-y-4">
                <div
                    v-for="requestItem in filteredRequests"
                    :key="requestItem.id"
                    class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-slate-300 dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                >
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-3.5 flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ requestItem.name }}</h2>
                                <span
                                    class="rounded-full px-3.5 py-1 text-[11px] font-black uppercase tracking-wider"
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
                                <span v-if="requestItem.category" class="rounded-full bg-slate-100 px-3 py-0.5 text-xs font-bold text-slate-600 dark:bg-[#1c1c1c] dark:text-slate-300">
                                    {{ requestItem.category }}
                                </span>
                            </div>

                            <div class="grid gap-2 text-sm text-slate-600 dark:text-slate-300 sm:grid-cols-2">
                                <p>
                                    Requested by: <strong class="text-slate-900 dark:text-white">{{ requestItem.user?.username || requestItem.user?.name }}</strong>
                                    <span class="text-xs text-slate-400"> ({{ requestItem.user?.email }})</span>
                                </p>
                                <p class="inline-flex items-center gap-1.5">
                                    <MapPin class="h-4 w-4 text-blue-500" />
                                    <span>{{ requestItem.venue?.name }}</span>
                                </p>
                                <p v-if="requestItem.preferred_date" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                                    <Clock class="h-3.5 w-3.5 text-slate-400" />
                                    <span>Requested Date: {{ requestItem.preferred_date }} {{ requestItem.preferred_start_time ? `(${requestItem.preferred_start_time})` : '' }}</span>
                                </p>
                                <p v-if="requestItem.total_cost" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                    Cost: PHP {{ Number(requestItem.total_cost).toFixed(2) }}
                                </p>
                            </div>

                            <p v-if="requestItem.notes" class="max-w-3xl rounded-2xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600 dark:bg-[#141414] dark:text-slate-300">
                                <strong>Notes:</strong> {{ requestItem.notes }}
                            </p>

                            <p v-if="requestItem.rejection_reason" class="text-xs font-semibold text-rose-600 dark:text-rose-300">
                                <strong>Rejection Reason:</strong> {{ requestItem.rejection_reason }}
                            </p>

                            <!-- Linked Tournament & Main Folder Access -->
                            <div v-if="requestItem.status === 'approved'" class="flex flex-wrap items-center gap-3 pt-2">
                                <p v-if="requestItem.tournamentDay" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                    ✓ Main folder active: {{ requestItem.tournamentDay.name }}
                                </p>
                                <Link
                                    v-if="requestItem.tournament?.id || requestItem.tournamentDay?.id"
                                    :href="route('tournaments.index')"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-50 px-3.5 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-100 transition dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-950/70"
                                >
                                    <Swords class="h-3.5 w-3.5" />
                                    Inspect Brackets & Scoring →
                                </Link>
                            </div>
                        </div>

                        <!-- Action Column & Receipt Thumbnail -->
                        <div class="flex flex-col items-end gap-3 shrink-0 lg:w-72">
                            <button
                                v-if="requestItem.receipt_url"
                                type="button"
                                @click="previewReceiptUrl = requestItem.receipt_url"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:underline dark:text-blue-400"
                            >
                                <Eye class="h-3.5 w-3.5" /> View Payment Receipt
                            </button>

                            <div v-if="requestItem.status === 'pending'" class="flex flex-col gap-2.5 w-full">
                                <button
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-xs font-bold text-white transition hover:bg-emerald-500 shadow-md shadow-emerald-600/10"
                                    @click="approve(requestItem.id)"
                                >
                                    <CheckCircle2 class="h-4 w-4" />
                                    {{ requestItem.tournament ? 'Approve & Unlock Access' : 'Approve & Create Main Folder' }}
                                </button>
                                <button
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-rose-200 px-4 py-2.5 text-xs font-bold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                    @click="startReject(requestItem.id)"
                                >
                                    <XCircle class="h-4 w-4" />
                                    Reject Request
                                </button>

                                <form v-if="rejectingId === requestItem.id" class="mt-2 space-y-3 rounded-2xl border border-slate-200 p-3.5 dark:border-[#1a1a1a]" @submit.prevent="submitReject(requestItem.id)">
                                    <textarea
                                        v-model="rejectForm.rejection_reason"
                                        rows="2"
                                        placeholder="Reason for rejection..."
                                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs outline-none focus:border-rose-500 dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                                    ></textarea>
                                    <div class="flex gap-2">
                                        <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-bold text-white">Confirm</button>
                                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 dark:border-[#1a1a1a] dark:text-slate-300" @click="rejectingId = null">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="filteredRequests.length === 0" class="rounded-3xl border border-dashed border-slate-200 bg-white p-12 text-center text-sm text-slate-500 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-400">
                    No tournament requests found matching your current filter.
                </div>
            </div>
        </div>

        <!-- Receipt Modal -->
        <div v-if="previewReceiptUrl" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="previewReceiptUrl = null">
            <div class="relative max-w-lg overflow-hidden rounded-3xl bg-white p-4 shadow-2xl dark:bg-[#111]">
                <button @click="previewReceiptUrl = null" class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200 dark:bg-[#222] dark:text-white">
                    <X class="h-4 w-4" />
                </button>
                <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Payment Receipt</h3>
                <img :src="previewReceiptUrl" alt="Payment receipt" class="max-h-[75vh] w-full rounded-2xl object-contain" />
            </div>
        </div>
    </AppLayout>
</template>
