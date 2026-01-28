<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
            showPassword.value = false;
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Login - HR Portal" />

        <div v-if="status" class="mb-6 text-sm font-medium text-emerald-600 bg-emerald-50 p-4 rounded-md border border-emerald-100">
            {{ status }}
        </div>

        <!-- Header Section -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Welcome back</h2>
            <p class="mt-2 text-sm text-slate-500">Please enter your work details to sign in.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Email Field -->
            <div class="space-y-1">
                <InputLabel for="email" value="Work Email" class="text-slate-700 font-medium text-xs uppercase tracking-wider" />
                <div class="relative">
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full py-3 px-4 bg-white border-slate-200 text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm transition-all text-sm"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="name@company.com"
                    />
                </div>
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <!-- Password Field -->
            <div class="space-y-1">
                 <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Password" class="text-slate-700 font-medium text-xs uppercase tracking-wider" />
                </div>
                
                <div class="relative">
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full py-3 px-4 bg-white border-slate-200 text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm transition-all text-sm"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                    >
                         <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <!-- Options -->
             <div class="flex items-center justify-between pt-2">
                <label class="flex items-center cursor-pointer group">
                    <Checkbox name="remember" v-model:checked="form.remember" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" />
                    <span class="ml-2 text-sm text-slate-600 group-hover:text-slate-800 transition-colors">Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
                >
                    Forgot password?
                </Link>
            </div>

            <!-- Submit Button -->
            <PrimaryButton
                class="w-full justify-center py-3 px-4 text-sm font-bold rounded-lg text-white bg-slate-900 hover:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-900 shadow-md transition-all duration-200 mt-6"
                :class="{ 'opacity-75 cursor-wait': form.processing }"
                :disabled="form.processing"
            >
                 <span v-if="form.processing" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Verifying...
                    </span>
                    <span v-else>Sign In</span>
            </PrimaryButton>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center">
             <p class="text-xs text-slate-400">
                Protected by Amalgated Enterprise Security
            </p>
        </div>
    </GuestLayout>
</template>