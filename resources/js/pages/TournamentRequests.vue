<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, MapPin, ShieldCheck, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    requests: Array<{
        id: number;
        name: string;
        category?: string | null;
        status: string;
        notes?: string | null;
        preferred_date?: string | null;
        preferred_start_time?: string | null;
        rejection_reason?: string | null;
        user?: { id: number; name: string; username?: string | null; email: string } | null;
        venue?: { id: number; name: string; address?: string | null } | null;
        approver?: { id: number; name: string } | null;
        tournament?: { id: number; name: string; status: string } | null;
        tournamentDay?: { id: number; name: string; date?: string | null; status: string } | null;
    }>;
}>();

const rejectingId = ref<number | null>(null);
const rejectForm = useForm({
    rejection_reason: '',
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
    <Head title="Tournament Requests" />

    <AppLayout :breadcrumbs="[{ title: 'Tournament Requests', href: '/tournament-requests' }]">
        <div class="space-y-6 p-4 sm:p-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]">
                <div class="flex items-center gap-3">
                    <ShieldCheck class="h-5 w-5 text-blue-600 dark:text-green-400" />
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white">Tournament Requests</h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Review player tournament requests and approve them into player main folders or reopen edit access when needed.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div
                    v-for="requestItem in requests"
                    :key="requestItem.id"
                    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ requestItem.name }}</h2>
                                <span
                                    class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-wider"
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
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Requested by <strong>{{ requestItem.user?.username || requestItem.user?.name }}</strong> ({{ requestItem.user?.email }})
                            </p>
                            <p class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                <MapPin class="h-4 w-4" />
                                {{ requestItem.venue?.name }}
                            </p>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                <span v-if="requestItem.preferred_date">{{ requestItem.preferred_date }}</span>
                                <span v-if="requestItem.preferred_start_time">{{ requestItem.preferred_start_time }}</span>
                            </div>
                            <p v-if="requestItem.notes" class="max-w-3xl text-sm text-slate-700 dark:text-slate-300">{{ requestItem.notes }}</p>
                            <p v-if="requestItem.rejection_reason" class="text-sm text-rose-600 dark:text-rose-300">{{ requestItem.rejection_reason }}</p>
                            <p v-if="requestItem.tournamentDay && !requestItem.tournament" class="text-sm text-emerald-600 dark:text-emerald-300">
                                Main folder ready: {{ requestItem.tournamentDay.name }} ({{ requestItem.tournamentDay.status }})
                            </p>
                            <p v-if="requestItem.tournament" class="text-sm text-emerald-600 dark:text-emerald-300">
                                Tournament linked: {{ requestItem.tournament.name }} ({{ requestItem.tournament.status }})
                            </p>
                        </div>

                        <div v-if="requestItem.status === 'pending'" class="flex flex-col gap-3 lg:w-80">
                            <button
                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-500"
                                @click="approve(requestItem.id)"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                {{ requestItem.tournament ? 'Approve And Unlock Access' : 'Approve And Create Main Folder' }}
                            </button>
                            <button
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-rose-200 px-4 py-3 text-sm font-bold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                @click="startReject(requestItem.id)"
                            >
                                <XCircle class="h-4 w-4" />
                                Reject
                            </button>
                            <form v-if="rejectingId === requestItem.id" class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-[#1a1a1a]" @submit.prevent="submitReject(requestItem.id)">
                                <textarea
                                    v-model="rejectForm.rejection_reason"
                                    rows="3"
                                    placeholder="Optional reason for rejection"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[#1a1a1a] dark:bg-[#0a0a0a] dark:text-white"
                                ></textarea>
                                <div class="flex gap-2">
                                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white">Confirm Reject</button>
                                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-[#1a1a1a] dark:text-slate-300" @click="rejectingId = null">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div v-if="requests.length === 0" class="rounded-3xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-[#1a1a1a] dark:bg-[#0f0f0f] dark:text-slate-400">
                    No tournament requests yet.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
