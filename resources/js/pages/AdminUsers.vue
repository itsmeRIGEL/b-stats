<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, CheckCircle, ChevronDown, Lock, Pencil, Plus, Search, ShieldCheck, Trash2, UserCog, Users, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    scheduler_total: number;
    scorer_total: number;
    total_users: number;
    unverified_total: number;
    is_admin: boolean;
    users: {
        id: number;
        name: string;
        email: string;
        role: 'scheduler' | 'scorer' | 'scheduler_scorer' | 'admin';
        email_verified_at: string | null;
        allow_unverified_access: boolean;
    }[];
}>();

const creatableRoles = computed<('scheduler' | 'scorer' | 'scheduler_scorer' | 'admin')[]>(() => {
    return props.is_admin
        ? ['admin', 'scheduler', 'scorer', 'scheduler_scorer']
        : ['scorer'];
});

const editableRoles = computed<('scheduler' | 'scorer' | 'scheduler_scorer' | 'admin')[]>(() => {
    return props.is_admin
        ? ['admin', 'scheduler', 'scorer', 'scheduler_scorer']
        : ['scorer'];
});

const filterableRoles = computed<('scheduler' | 'scorer' | 'scheduler_scorer' | 'admin')[]>(() => {
    return props.is_admin
        ? ['admin', 'scheduler', 'scorer', 'scheduler_scorer']
        : ['scheduler', 'scorer', 'scheduler_scorer'];
});

const editingUserId = ref<number | null>(null);

const searchQuery = ref('');
const roleFilter = ref('all');

const filteredUsers = computed(() => {
    return props.users.filter((user) => {
        const nameMatch = (user.name || '').toLowerCase().includes(searchQuery.value.toLowerCase());
        const emailMatch = (user.email || '').toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesSearch = nameMatch || emailMatch;
        const matchesRole = roleFilter.value === 'all' || user.role === roleFilter.value;
        return matchesSearch && matchesRole;
    });
});

const getAvatarBgClass = (role: string) => {
    switch (role) {
        case 'admin':
            return 'bg-gradient-to-br from-rose-500 to-red-600 shadow-rose-500/20';
        case 'scheduler_scorer':
            return 'bg-gradient-to-br from-violet-500 to-purple-600 shadow-purple-500/20';
        case 'scheduler':
            return 'bg-gradient-to-br from-blue-500 to-indigo-600 shadow-indigo-500/20';
        case 'scorer':
            return 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-orange-500/20';
        default:
            return 'bg-gradient-to-br from-slate-500 to-slate-600 shadow-slate-500/20';
    }
};

const getRoleBadgeClass = (role: string) => {
    switch (role) {
        case 'admin':
            return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/20 dark:text-rose-400';
        case 'scheduler_scorer':
            return 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950/20 dark:text-purple-400';
        case 'scheduler':
            return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/20 dark:text-blue-400';
        case 'scorer':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-400';
        default:
            return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/20 dark:text-slate-300';
    }
};

const getStatusBadgeClass = (verified: boolean) => {
    if (verified) {
        return 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/30 dark:bg-green-950/25 dark:text-green-400';
    }
    return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/30 dark:bg-rose-950/25 dark:text-rose-400';
};

const showCreateRoleDropdown = ref(false);
const showFilterRoleDropdown = ref(false);
const showEditRoleDropdown = ref(false);

const createRoleDropdownRef = ref<HTMLElement | null>(null);
const filterRoleDropdownRef = ref<HTMLElement | null>(null);
const editRoleDropdownRef = ref<HTMLElement | null>(null);

const handleUsersDropdownClickOutside = (e: MouseEvent) => {
    const target = e.target as Node;
    if (createRoleDropdownRef.value && !createRoleDropdownRef.value.contains(target)) {
        showCreateRoleDropdown.value = false;
    }
    if (filterRoleDropdownRef.value && !filterRoleDropdownRef.value.contains(target)) {
        showFilterRoleDropdown.value = false;
    }
    if (editRoleDropdownRef.value && !editRoleDropdownRef.value.contains(target)) {
        showEditRoleDropdown.value = false;
    }
};

onMounted(() => document.addEventListener('click', handleUsersDropdownClickOutside));
onUnmounted(() => document.removeEventListener('click', handleUsersDropdownClickOutside));

const roleLabel = (role: string) => {
    switch (role) {
        case 'scheduler':
            return 'Scheduler';
        case 'scorer':
            return 'Scorer';
        case 'scheduler_scorer':
            return 'Scheduler & Scorer';
        case 'admin':
            return 'Admin';
        default:
            return role;
    }
};

const canManageUser = (user: { role: string }) => props.is_admin || user.role === 'scorer';

// Toast notification state
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref<'success' | 'error' | 'info'>('success');

const form = useForm({
    name: '',
    email: '',
    role: (props.is_admin ? 'scheduler' : 'scorer') as 'scheduler' | 'scorer' | 'scheduler_scorer' | 'admin',
    password: '',
    password_confirmation: '',
    allow_unverified_access: false,
});

const createForm = useForm({
    name: '',
    email: '',
    role: (props.is_admin ? 'scheduler' : 'scorer') as 'scheduler' | 'scorer' | 'scheduler_scorer' | 'admin',
    password: '',
    password_confirmation: '',
    allow_unverified_access: false,
});

const startEdit = (user: {
    id: number;
    name: string;
    email: string;
    role: 'scheduler' | 'scorer' | 'scheduler_scorer' | 'admin';
    allow_unverified_access: boolean;
}) => {
    editingUserId.value = user.id;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.password = '';
    form.password_confirmation = '';
    form.allow_unverified_access = !!user.allow_unverified_access;
};

const cancelEdit = () => {
    editingUserId.value = null;
    form.reset();
    form.clearErrors();
};

// Toast helper functions
const showSuccessToast = (message: string) => {
    toastMessage.value = message;
    toastType.value = 'success';
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

const saveUser = (userId: number) => {
    form.put(route('admin-users.update', userId), {
        preserveScroll: true,
        onSuccess: () => {
            cancelEdit();
            showSuccessToast('User updated successfully!');
        },
        onError: () => {
            toastMessage.value = 'Failed to update user';
            toastType.value = 'error';
            showToast.value = true;
            setTimeout(() => {
                showToast.value = false;
            }, 3000);
        },
    });
};

const createUser = () => {
    createForm.post(route('admin-users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.role = props.is_admin ? 'scheduler' : 'scorer';
            showSuccessToast('User created successfully!');
        },
        onError: () => {
            toastMessage.value = 'Failed to create user';
            toastType.value = 'error';
            showToast.value = true;
            setTimeout(() => {
                showToast.value = false;
            }, 3000);
        },
    });
};

const deleteUser = (userId: number, name: string) => {
    if (!window.confirm(`Delete user ${name}?`)) {
        return;
    }

    router.delete(route('admin-users.destroy', userId), {
        preserveScroll: true,
    });
};
let pollInterval: ReturnType<typeof setInterval> | null = null;
const POLL_RELOAD = ['scheduler_total', 'scorer_total', 'total_users', 'unverified_total', 'users'];

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
    <Head title="Users" />

    <AppLayout>
        <div class="min-h-screen space-y-8 bg-[#FDFDFC] p-4 dark:bg-[#0a0a0a] sm:p-6 lg:p-8">
            <!-- Header Section -->
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-[#1b1b18] dark:text-[#EDEDEC]">Users Management</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage user roles, access, and accounts.</p>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid w-full grid-cols-1 gap-6 sm:grid-cols-3">
                <!-- Scheduler Stat -->
                <div
                    class="relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div class="rounded-xl bg-blue-50 p-3 text-blue-600 dark:bg-green-900/20 dark:text-green-400">
                            <UserCog class="h-6 w-6" />
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Scheduler</p>
                    <h3 class="mt-1 text-3xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]">{{ props.scheduler_total }}</h3>
                </div>

                <!-- Scorer Stat -->
                <div
                    class="relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div class="rounded-xl bg-amber-50 p-3 text-amber-600 dark:bg-amber-950/20 dark:text-amber-400">
                            <ShieldCheck class="h-6 w-6" />
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Scorer</p>
                    <h3 class="mt-1 text-3xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]">{{ props.scorer_total }}</h3>
                </div>

                <!-- Total Users Stat -->
                <div
                    class="relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div class="rounded-xl bg-slate-50 p-3 text-slate-600 dark:bg-[#1a1a1a] dark:text-slate-400">
                            <Users class="h-6 w-6" />
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Total Users</p>
                    <h3 class="mt-1 text-3xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]">{{ props.total_users }}</h3>
                </div>
            </div>

            <!-- Create User Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]">
                <h2 class="mb-5 text-lg font-bold text-[#1b1b18] dark:text-[#EDEDEC]">Create New User</h2>
                <form class="space-y-4" @submit.prevent="createUser">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                            <Input
                                v-model="createForm.name"
                                type="text"
                                placeholder="Full name"
                                class="border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:border-green-500 dark:focus:ring-green-500"
                            />
                            <InputError :message="createForm.errors.name" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Email Address</label>
                            <Input
                                v-model="createForm.email"
                                type="email"
                                placeholder="email@example.com"
                                class="border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:border-green-500 dark:focus:ring-green-500"
                            />
                            <InputError :message="createForm.errors.email" />
                        </div>
                    </div>
                    <div class="relative space-y-1.5" ref="createRoleDropdownRef">
                        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Role</label>
                        <button
                            type="button"
                            @click.stop="showCreateRoleDropdown = !showCreateRoleDropdown"
                            class="flex min-h-[40px] w-full items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900 transition-all hover:border-slate-300 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:text-white dark:hover:border-[#2a2a2a]"
                        >
                            <span>{{ roleLabel(createForm.role) }}</span>
                            <ChevronDown
                                class="h-4 w-4 text-slate-400 transition-transform duration-200"
                                :class="showCreateRoleDropdown ? 'rotate-180' : ''"
                            />
                        </button>
                        <div
                            v-if="showCreateRoleDropdown"
                            class="absolute left-0 z-50 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                        >
                            <button
                                type="button"
                                v-for="r in creatableRoles"
                                :key="r"
                                @click.stop="
                                    createForm.role = r;
                                    showCreateRoleDropdown = false;
                                "
                                class="w-full px-4 py-2.5 text-left text-xs font-bold transition-all"
                                :class="
                                    createForm.role === r
                                        ? 'bg-blue-50/50 text-blue-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                                        : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                "
                            >
                                {{ roleLabel(r) }}
                            </button>
                        </div>
                        <InputError :message="createForm.errors.role" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
                            <Input
                                v-model="createForm.password"
                                type="password"
                                placeholder="Password"
                                class="border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:border-green-500 dark:focus:ring-green-500"
                            />
                            <InputError :message="createForm.errors.password" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Confirm Password</label>
                            <Input
                                v-model="createForm.password_confirmation"
                                type="password"
                                placeholder="Confirm"
                                class="border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:border-green-500 dark:focus:ring-green-500"
                            />
                        </div>
                    </div>
                    <div
                        class="flex flex-col justify-between gap-4 border-t border-slate-100 pt-4 dark:border-[#1a1a1a]/30 sm:flex-row sm:items-center"
                    >
                        <div class="flex items-center gap-2">
                            <input
                                v-model="createForm.allow_unverified_access"
                                type="checkbox"
                                id="create_allow_unverified"
                                class="h-4 w-4 cursor-pointer rounded border-slate-200 bg-white text-blue-600 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:text-green-600 dark:focus:ring-green-500"
                            />
                            <label
                                for="create_allow_unverified"
                                class="cursor-pointer select-none text-sm font-medium text-slate-600 dark:text-slate-300"
                                >Allow access even if email is unverified</label
                            >
                        </div>
                        <Button
                            type="submit"
                            :disabled="createForm.processing"
                            class="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-6 text-white shadow-md shadow-blue-600/10 transition-all hover:bg-blue-700 dark:bg-green-600 dark:shadow-green-600/10 dark:hover:bg-green-700 sm:w-auto"
                        >
                            <Plus class="h-4 w-4" />
                            <span>Add User</span>
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Users List Table -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#1a1a1a]/60 dark:bg-[#0f0f0f]">
                <!-- Directory Header with Search and Role Filter -->
                <div
                    class="flex flex-col justify-between gap-4 border-b border-slate-100 px-4 py-4 dark:border-[#1a1a1a]/60 sm:px-6 sm:py-5 md:flex-row md:items-center"
                >
                    <div>
                        <h2 class="text-lg font-bold text-[#1b1b18] dark:text-[#EDEDEC]">User Directory</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Manage existing users and their permissions.</p>
                    </div>
                    <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                        <!-- Search Input -->
                        <div class="relative w-full sm:w-64">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by name or email..."
                                class="h-9 rounded-xl border-slate-200 bg-slate-50 pl-9 pr-4 text-xs focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:ring-green-500"
                            />
                        </div>
                        <!-- Role Filter -->
                        <div class="relative w-full sm:w-44" ref="filterRoleDropdownRef">
                            <button
                                type="button"
                                @click.stop="showFilterRoleDropdown = !showFilterRoleDropdown"
                                class="flex min-h-[36px] w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-900 transition-all hover:border-slate-300 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:text-white dark:hover:border-[#2a2a2a]"
                            >
                                <span>{{ roleFilter === 'all' ? 'All Roles' : roleLabel(roleFilter) }}</span>
                                <ChevronDown
                                    class="h-4 w-4 text-slate-400 transition-transform duration-200"
                                    :class="showFilterRoleDropdown ? 'rotate-180' : ''"
                                />
                            </button>
                            <div
                                v-if="showFilterRoleDropdown"
                                class="absolute right-0 z-50 mt-1 w-full min-w-[160px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                            >
                                <button
                                    type="button"
                                    @click.stop="
                                        roleFilter = 'all';
                                        showFilterRoleDropdown = false;
                                    "
                                    class="w-full px-4 py-2.5 text-left text-xs font-bold text-slate-600 transition-all hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]"
                                    :class="roleFilter === 'all' ? 'bg-blue-50/50 text-blue-600 dark:bg-emerald-950/20 dark:text-emerald-400' : ''"
                                >
                                    All Roles
                                </button>
                                <div class="border-t border-slate-100 dark:border-[#1a1a1a]"></div>
                                <button
                                    type="button"
                                    v-for="r in filterableRoles"
                                    :key="r"
                                    @click.stop="
                                        roleFilter = r;
                                        showFilterRoleDropdown = false;
                                    "
                                    class="w-full px-4 py-2.5 text-left text-xs font-bold transition-all"
                                    :class="
                                        roleFilter === r
                                            ? 'bg-blue-50/50 text-blue-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                                            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                    "
                                >
                                    {{ roleLabel(r) }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile card list -->
                <div v-if="filteredUsers.length > 0" class="divide-y divide-slate-100 md:hidden dark:divide-[#1a1a1a]/30">
                    <div
                        v-for="user in filteredUsers"
                        :key="user.id"
                        class="space-y-3 px-4 py-4 transition-colors hover:bg-slate-50/50 dark:hover:bg-[#1a1a1a]/40 sm:px-6"
                    >
                        <!-- Top row: avatar + name/email + actions -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-black uppercase text-white shadow-sm"
                                    :class="getAvatarBgClass(user.role)"
                                >
                                    {{ user.name ? user.name.charAt(0) : 'U' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-bold text-slate-800 dark:text-slate-200">{{ user.name }}</p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ user.email }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <Button
                                    v-if="canManageUser(user)"
                                    size="sm"
                                    variant="ghost"
                                    class="h-9 w-9 rounded-lg p-0 text-slate-500 transition-colors hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-green-950/20 dark:hover:text-green-400"
                                    title="Edit Properties"
                                    @click="startEdit(user)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    v-if="canManageUser(user)"
                                    size="sm"
                                    variant="ghost"
                                    class="h-9 w-9 rounded-lg p-0 text-slate-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/20 dark:hover:text-rose-400"
                                    title="Delete User"
                                    @click="deleteUser(user.id, user.name)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                        <!-- Bottom row: role + status badges -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold"
                                :class="getRoleBadgeClass(user.role)"
                            >
                                {{ roleLabel(user.role) }}
                            </span>
                            <span
                                v-if="user.email_verified_at"
                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold"
                                :class="getStatusBadgeClass(true)"
                            >
                                Verified
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold"
                                :class="getStatusBadgeClass(false)"
                            >
                                Unverified
                            </span>
                            <span
                                v-if="!user.email_verified_at && user.allow_unverified_access"
                                class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/25 dark:text-amber-400"
                            >
                                Access Allowed
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Mobile empty state -->
                <div v-else class="flex flex-col items-center justify-center px-4 py-12 md:hidden">
                    <Users class="mb-3 h-10 w-10 text-slate-300 opacity-40 dark:text-slate-600" />
                    <p class="text-sm font-bold text-slate-500 dark:text-slate-400">No users match your criteria</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Try modifying your search or role filter.</p>
                </div>

                <!-- Desktop table -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a]/40">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Name
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Email
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Role
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Password
                                </th>
                                <th class="px-6 py-4 text-right text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-[#1a1a1a]/30">
                            <tr
                                v-for="user in filteredUsers"
                                :key="user.id"
                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-[#1a1a1a]/40"
                            >
                                <!-- Name with Initials Avatar -->
                                <td class="whitespace-nowrap px-6 py-4 text-[#1b1b18] dark:text-[#EDEDEC]">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-black uppercase text-white shadow-sm"
                                            :class="getAvatarBgClass(user.role)"
                                        >
                                            {{ user.name ? user.name.charAt(0) : 'U' }}
                                        </div>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ user.name }}</span>
                                    </div>
                                </td>
                                <!-- Email -->
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-slate-600 dark:text-slate-300">
                                    {{ user.email }}
                                </td>
                                <!-- Role Badge -->
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold transition-colors"
                                        :class="
                                            user.role === 'admin'
                                                ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/20 dark:text-rose-400'
                                                : user.role === 'scheduler_scorer'
                                                  ? 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950/20 dark:text-purple-400'
                                                  : user.role === 'scheduler'
                                                    ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/20 dark:text-blue-400'
                                                    : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-400'
                                        "
                                    >
                                        {{
                                            user.role === 'scheduler_scorer'
                                                ? 'Scheduler & Scorer'
                                                : user.role === 'scheduler'
                                                  ? 'Scheduler'
                                                  : user.role === 'scorer'
                                                    ? 'Scorer'
                                                    : 'Admin'
                                        }}
                                    </span>
                                </td>
                                <!-- Status Badges -->
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span
                                            v-if="user.email_verified_at"
                                            class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-[10px] font-bold text-green-700 dark:border-green-900/30 dark:bg-green-950/25 dark:text-green-400"
                                        >
                                            Verified
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700 dark:border-rose-900/30 dark:bg-rose-950/25 dark:text-rose-400"
                                        >
                                            Unverified
                                        </span>
                                        <span
                                            v-if="!user.email_verified_at && user.allow_unverified_access"
                                            class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/25 dark:text-amber-400"
                                        >
                                            Access Allowed
                                        </span>
                                    </div>
                                </td>
                                <!-- Password indicator -->
                                <td class="whitespace-nowrap px-6 py-4 text-slate-400 dark:text-slate-600">
                                    <span class="inline-flex items-center gap-1.5 text-xs">
                                        <Lock class="h-3.5 w-3.5 text-slate-300 dark:text-slate-700" />
                                        <span class="font-black tracking-widest opacity-40">••••••••</span>
                                    </span>
                                </td>
                                <!-- Read-only Actions -->
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            v-if="canManageUser(user)"
                                            size="sm"
                                            variant="ghost"
                                            class="h-8 w-8 rounded-lg p-0 text-slate-500 transition-colors hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-green-950/20 dark:hover:text-green-400"
                                            title="Edit Properties"
                                            @click="startEdit(user)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            v-if="canManageUser(user)"
                                            size="sm"
                                            variant="ghost"
                                            class="h-8 w-8 rounded-lg p-0 text-slate-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/20 dark:hover:text-rose-400"
                                            title="Delete User"
                                            @click="deleteUser(user.id, user.name)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <Users class="mb-3 h-10 w-10 text-slate-300 opacity-40 dark:text-slate-600" />
                                        <p class="text-sm font-bold">No users match your criteria</p>
                                        <p class="mt-1 text-xs">Try modifying your search or role filter.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Edit User Modal teleports to body -->
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
                        v-if="editingUserId !== null"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 backdrop-blur-sm"
                        @click.self="cancelEdit"
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
                                v-if="editingUserId !== null"
                                class="mx-4 w-full max-w-md space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                            >
                                <!-- Modal Header -->
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-[#1a1a1a]/60">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-green-900/20">
                                            <UserCog class="h-5 w-5 text-blue-600 dark:text-green-500" />
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-black text-slate-900 dark:text-white">Edit User Properties</h3>
                                            <p class="text-[10px] font-medium text-slate-400">Update account properties and access</p>
                                        </div>
                                    </div>
                                    <button
                                        @click="cancelEdit"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-[#1a1a1a]"
                                    >
                                        <X class="h-5 w-5 text-slate-400" />
                                    </button>
                                </div>

                                <!-- Modal Form -->
                                <form @submit.prevent="saveUser(editingUserId)" class="space-y-4">
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Full Name</label>
                                        <Input
                                            v-model="form.name"
                                            type="text"
                                            placeholder="Full name"
                                            class="border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:border-green-500 dark:focus:ring-green-500"
                                        />
                                        <InputError :message="form.errors.name" />
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Email Address</label>
                                        <Input
                                            v-model="form.email"
                                            type="email"
                                            placeholder="email@example.com"
                                            class="border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:border-green-500 dark:focus:ring-green-500"
                                        />
                                        <InputError :message="form.errors.email" />
                                    </div>

                                    <div class="relative space-y-1.5" ref="editRoleDropdownRef">
                                        <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Role</label>
                                        <button
                                            type="button"
                                            @click.stop="showEditRoleDropdown = !showEditRoleDropdown"
                                            class="flex min-h-[40px] w-full items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900 transition-all hover:border-slate-300 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:text-white dark:hover:border-[#2a2a2a]"
                                        >
                                            <span>{{ roleLabel(form.role) }}</span>
                                            <ChevronDown
                                                class="h-4 w-4 text-slate-400 transition-transform duration-200"
                                                :class="showEditRoleDropdown ? 'rotate-180' : ''"
                                            />
                                        </button>
                                        <div
                                            v-if="showEditRoleDropdown"
                                            class="absolute left-0 z-50 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-[#1a1a1a] dark:bg-[#0f0f0f]"
                                        >
                                            <button
                                                type="button"
                                                v-for="r in editableRoles"
                                                :key="r"
                                                @click.stop="
                                                    form.role = r;
                                                    showEditRoleDropdown = false;
                                                "
                                                class="w-full px-4 py-2.5 text-left text-xs font-bold transition-all"
                                                :class="
                                                    form.role === r
                                                        ? 'bg-blue-50/50 text-blue-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                        : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#1a1a1a]'
                                                "
                                            >
                                                {{ roleLabel(r) }}
                                            </button>
                                        </div>
                                        <InputError :message="form.errors.role" />
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div class="space-y-1.5">
                                            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">New Password (opt)</label>
                                            <Input
                                                v-model="form.password"
                                                type="password"
                                                placeholder="New password"
                                                class="border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:border-green-500 dark:focus:ring-green-500"
                                            />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Confirm Password</label>
                                            <Input
                                                v-model="form.password_confirmation"
                                                type="password"
                                                placeholder="Confirm"
                                                class="border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:focus:border-green-500 dark:focus:ring-green-500"
                                            />
                                        </div>
                                    </div>
                                    <InputError :message="form.errors.password" />

                                    <div class="flex items-center gap-2 pb-2 pt-2">
                                        <input
                                            v-model="form.allow_unverified_access"
                                            type="checkbox"
                                            id="edit_allow_unverified"
                                            class="h-4 w-4 cursor-pointer rounded border-slate-200 bg-white text-blue-600 focus:ring-blue-500 dark:border-[#1a1a1a]/60 dark:bg-[#1a1a1a] dark:text-green-600 dark:focus:ring-green-500"
                                        />
                                        <label
                                            for="edit_allow_unverified"
                                            class="cursor-pointer select-none text-xs font-semibold text-slate-600 dark:text-slate-300"
                                            >Allow access even if email is unverified</label
                                        >
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4 dark:border-[#1a1a1a]/60">
                                        <button
                                            type="button"
                                            @click="cancelEdit"
                                            class="h-10 rounded-xl border border-slate-200 px-5 text-sm font-bold text-slate-600 transition-all hover:bg-slate-100 dark:border-[#1a1a1a] dark:text-slate-400 dark:hover:bg-[#1a1a1a]"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="form.processing"
                                            class="flex h-10 items-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-black uppercase tracking-wider text-white shadow-lg shadow-blue-500/20 transition-all hover:bg-blue-700 dark:bg-green-600 dark:shadow-green-500/20 dark:hover:bg-green-700"
                                        >
                                            <Check class="h-4 w-4" /> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </Transition>
                    </div>
                </Transition>
            </Teleport>
        </div>
    </AppLayout>

    <!-- Toast Notification -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="transform translate-y-2 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-2 opacity-0"
    >
        <div
            v-if="showToast"
            class="fixed right-4 top-4 z-[120] flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-bold tracking-wide shadow-xl"
            :class="toastType === 'error' ? 'border-rose-700 bg-rose-950/95 text-rose-100' : 'border-emerald-700 bg-emerald-950/95 text-emerald-100'"
        >
            <CheckCircle v-if="toastType === 'success'" class="h-5 w-5 shrink-0" />
            <X v-else class="h-5 w-5 shrink-0" />
            <span>{{ toastMessage }}</span>
        </div>
    </Transition>
</template>

<style scoped>
.users-flat {
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}
</style>
