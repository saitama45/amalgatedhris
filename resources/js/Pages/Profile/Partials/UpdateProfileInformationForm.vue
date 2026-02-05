<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const { showSuccess, showError } = useToast();

const form = useForm({
    name: user.name,
    email: user.email,
    department: user.department,
    position: user.position,
});

const handleEmailInput = (e) => {
    const val = e.target.value.replace(/\p{Extended_Pictographic}/gu, '').replace(/[^a-zA-Z0-9@._-]/g, '');
    form.email = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const updateProfile = () => {
    form.patch(route('profile.update'), {
        onSuccess: () => {
            showSuccess('Profile updated successfully');
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'Failed to update profile';
            showError(errorMessage);
        }
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                View your professional details and update your email address.
            </p>
        </header>

        <form
            @submit.prevent="updateProfile"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Full Name (Read-only)" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full bg-gray-100 text-gray-600 cursor-not-allowed font-bold"
                    v-model="form.name"
                    readonly
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <InputLabel for="department" value="Department (Read-only)" />
                    <TextInput
                        id="department"
                        type="text"
                        class="mt-1 block w-full bg-gray-100 text-gray-600 cursor-not-allowed uppercase"
                        v-model="form.department"
                        readonly
                    />
                </div>

                <div>
                    <InputLabel for="position" value="Position (Read-only)" />
                    <TextInput
                        id="position"
                        type="text"
                        class="mt-1 block w-full bg-gray-100 text-gray-600 cursor-not-allowed uppercase"
                        v-model="form.position"
                        readonly
                    />
                </div>
            </div>

            <div>
                <InputLabel for="email" value="Email Address" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    :model-value="form.email"
                    @input="handleEmailInput"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
