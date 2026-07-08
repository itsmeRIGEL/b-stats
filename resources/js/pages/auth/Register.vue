<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'player',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Create an account" description="Create a player or scheduler account to access the system">
        <Head title="Register" />
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-900/40 dark:bg-blue-950/30 dark:text-blue-300">
            Player accounts can view personal stats and use scoring during active bookings. Scorer accounts are still created by a scheduler.
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label>Account type</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="form.role = 'player'"
                            class="rounded-xl border px-4 py-3 text-left text-sm font-semibold transition-all"
                            :class="
                                form.role === 'player'
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-950/30 dark:text-blue-300'
                                    : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'
                            "
                        >
                            Player
                        </button>
                        <button
                            type="button"
                            @click="form.role = 'scheduler'"
                            class="rounded-xl border px-4 py-3 text-left text-sm font-semibold transition-all"
                            :class="
                                form.role === 'scheduler'
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-950/30 dark:text-blue-300'
                                    : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'
                            "
                        >
                            Scheduler
                        </button>
                    </div>
                    <InputError :message="form.errors.role" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            type="text"
                            required
                            autofocus
                            tabindex="1"
                            autocomplete="given-name"
                            v-model="form.first_name"
                            placeholder="First name"
                        />
                        <InputError :message="form.errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            type="text"
                            required
                            tabindex="2"
                            autocomplete="family-name"
                            v-model="form.last_name"
                            placeholder="Last name"
                        />
                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="middle_name">Middle name <span class="text-xs text-slate-500">(Optional)</span></Label>
                    <Input
                        id="middle_name"
                        type="text"
                        tabindex="3"
                        autocomplete="additional-name"
                        v-model="form.middle_name"
                        placeholder="Middle name"
                    />
                    <InputError :message="form.errors.middle_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="username">Username</Label>
                    <Input
                        id="username"
                        type="text"
                        required
                        tabindex="4"
                        autocomplete="username"
                        v-model="form.username"
                        placeholder="username"
                    />
                    <InputError :message="form.errors.username" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" required tabindex="5" autocomplete="email" v-model="form.email" placeholder="email@example.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        required
                        tabindex="6"
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        tabindex="7"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="8" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="9">Log in</TextLink>
                <span class="mx-2 text-slate-300 dark:text-slate-600">·</span>
                <TextLink :href="route('admin.login')" :tabindex="10">Admin Login</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
