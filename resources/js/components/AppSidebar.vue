<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarRail } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Activity, Calendar, CreditCard, LayoutGrid, MapPin, Settings, ShieldCheck, Swords, TrendingUp, Trophy, User, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const userRole = computed(() => page.props.auth.user.role);
const hasVenue = computed(() => Boolean(page.props.currentVenue));
const isScheduler = computed(() => userRole.value === 'scheduler' || userRole.value === 'scheduler_scorer');
const isScorer = computed(() => userRole.value === 'scorer' || userRole.value === 'scheduler_scorer');
const isPlayer = computed(() => userRole.value === 'player');

const allNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Users',
        href: '/admin-users',
        icon: Users,
    },
    {
        title: 'Bookings',
        href: '/bookings',
        icon: Calendar,
    },
    {
        title: 'Memberships',
        href: '/memberships',
        icon: CreditCard,
    },
    {
        title: 'Scoring & Stats',
        href: '/scoring',
        icon: Trophy,
    },
    {
        title: 'All-Time Stats',
        href: '/all-time-stats',
        icon: Activity,
    },
    {
        title: 'Tournaments',
        href: '/tournaments',
        icon: Swords,
    },
    {
        title: 'Tournament Requests',
        href: '/tournament-requests',
        icon: ShieldCheck,
    },
    {
        title: 'Sales Report',
        href: '/sales-report',
        icon: TrendingUp,
    },
    {
        title: 'Settings',
        href: '/pickleball-settings',
        icon: Settings,
    },
];

const schedulerAllowed = ['/dashboard', '/admin-users', '/bookings', '/memberships', '/all-time-stats', '/tournament-requests', '/sales-report', '/pickleball-settings'];
const scorerAllowed = ['/scoring', '/all-time-stats', '/tournaments'];
const mainNavItems = computed(() => {
    if (isScheduler.value && !hasVenue.value) {
        return [
            {
                title: 'Venue Setup',
                href: '/venue-setup',
                icon: Settings,
            },
        ];
    }
    if (isScheduler.value) {
        return [
            {
                title: 'Venue Setup',
                href: '/venue-setup',
                icon: Settings,
            },
            ...allNavItems.filter((item) => schedulerAllowed.includes(item.href)),
        ];
    }
    if (isScorer.value) {
        return allNavItems.filter((item) => scorerAllowed.includes(item.href));
    }
    if (isPlayer.value) {
        return [
            {
                title: 'Venues',
                href: '/venues',
                icon: MapPin,
            },
            {
                title: 'My Tournaments',
                href: '/tournaments',
                icon: Swords,
            },
            {
                title: 'Scoring',
                href: '/scoring',
                icon: Trophy,
            },
            {
                title: 'My Stats',
                href: '/all-time-stats',
                icon: Activity,
            },
            {
                title: 'Profile',
                href: '/settings/profile',
                icon: User,
            },
        ];
    }
    return allNavItems;
});

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('home')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
</template>
