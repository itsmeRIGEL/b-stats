import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface BookingInvitation {
    booking_id: number;
    venue_name?: string | null;
    lead_name: string;
    court_number: number;
    booking_date: string;
    start_time: string;
    end_time: string;
    invited_by: string;
    player_name?: string | null;
    status: 'pending' | 'accepted' | 'declined';
}

export interface SharedData {
    name: string;
    appLogo: string | null;
    currentVenue?: { id: number; name: string } | null;
    quote: { message: string; author: string };
    auth: Auth;
    bookingInvitations?: BookingInvitation[];
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    username?: string | null;
    first_name?: string | null;
    middle_name?: string | null;
    last_name?: string | null;
    suffix?: string | null;
    gender?: 'male' | 'female' | 'other' | null;
    gender_other?: string | null;
    email: string;
    avatar?: string;
    facebook_url?: string | null;
    instagram_url?: string | null;
    website_url?: string | null;
    role: 'admin' | 'scheduler' | 'scorer' | 'scheduler_scorer' | 'player';
    db_role?: 'admin' | 'scheduler' | 'scorer' | 'scheduler_scorer' | 'player';
    scheduler_id?: number | null;
    venue_id?: number | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

