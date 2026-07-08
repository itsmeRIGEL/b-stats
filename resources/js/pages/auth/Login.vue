<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const selectedRole = ref<'scheduler' | 'scorer' | 'scheduler_scorer' | 'player'>('scheduler');

const form = useForm({
    email: '',
    password: '',
    remember: false,
    role: 'scheduler',
});

const submit = () => {
    form.role = selectedRole.value;
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Log in to your account" description="Select your role and enter your credentials">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <!-- Role tabs -->
        <div class="mb-4 flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
            <button
                type="button"
                @click="selectedRole = 'scheduler'"
                :class="
                    selectedRole === 'scheduler'
                        ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-700 dark:text-blue-400'
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                "
                class="flex-1 rounded-lg py-2 text-sm font-semibold transition-all"
            >
                Scheduler
            </button>
            <button
                type="button"
                @click="selectedRole = 'scorer'"
                :class="
                    selectedRole === 'scorer'
                        ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-700 dark:text-blue-400'
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                "
                class="flex-1 rounded-lg py-2 text-sm font-semibold transition-all"
            >
                Scorer
            </button>
            <button
                type="button"
                @click="selectedRole = 'player'"
                :class="
                    selectedRole === 'player'
                        ? 'bg-white text-blue-600 shadow-sm dark:bg-slate-700 dark:text-blue-400'
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                "
                class="flex-1 rounded-lg py-2 text-sm font-semibold transition-all"
            >
                Player
            </button>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" :tabindex="5">
                            Forgot password?
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-4 w-full" tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Log in
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                <TextLink :href="route('admin.login')" :tabindex="6">Admin Login</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
