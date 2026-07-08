<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LogOut, RefreshCw, Settings } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    user: User;
}

const props = defineProps<Props>();
const isSwitching = ref(false);

const handleSwitchRole = () => {
    isSwitching.value = true;
    router.post(
        route('switch-role'),
        {
            role: props.user.role === 'scheduler' ? 'scorer' : 'scheduler',
        },
        {
            onFinish: () => {
                isSwitching.value = false;
            },
        },
    );
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />

    <DropdownMenuGroup v-if="user.db_role === 'scheduler_scorer'">
        <DropdownMenuItem @select.prevent="handleSwitchRole" :disabled="isSwitching" class="cursor-pointer">
            <div class="flex w-full items-center">
                <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': isSwitching }" />
                <span>Switch to {{ user.role === 'scheduler' ? 'Scorer' : 'Scheduler' }} View</span>
            </div>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator v-if="user.db_role === 'scheduler_scorer'" />

    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
