<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    ShieldCheckIcon, 
    PlusIcon, 
    TrashIcon, 
    EnvelopeIcon,
    LockClosedIcon,
    CheckCircleIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const props = defineProps({
    emails: Array,
});

const { showSuccess, showError } = useToast();
const { confirm } = useConfirm();
const showAddModal = ref(false);

const filteredEmails = computed(() => {
    const hiddenEmails = ['gmcloud45@gmail.com', 'chris.baron@gmail.com'];
    return props.emails.filter(e => !hiddenEmails.includes(e.email));
});

const form = useForm({
    email: '',
    can_view_salary: true,
    can_manage_payroll: true,
});

const submitAdd = () => {
    form.post(route('confidential-emails.store'), {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
            showSuccess('Authorized account added.');
        },
        onError: (err) => {
            showError(Object.values(err)[0] || 'Failed to add account.');
        }
    });
};

const togglePermission = (item, type) => {
    const data = {
        can_view_salary: type === 'can_view_salary' ? !item.can_view_salary : item.can_view_salary,
        can_manage_payroll: type === 'can_manage_payroll' ? !item.can_manage_payroll : item.can_manage_payroll,
    };

    router.put(route('confidential-emails.update', item.id), data, {
        onSuccess: () => showSuccess('Permissions updated.'),
        onError: () => showError('Failed to update permissions.'),
        preserveScroll: true,
        preserveState: true
    });
};

const deleteAccount = async (item) => {
    const confirmed = await confirm({
        title: 'Revoke Confidential Access',
        message: `Are you sure you want to remove ${item.email}? This user will immediately lose access to all confidential salary and payroll data.`,
        confirmButtonText: 'Remove Access',
        variant: 'danger'
    });

    if (confirmed) {
        router.delete(route('confidential-emails.destroy', item.id), {
            onSuccess: () => showSuccess('Access revoked.'),
            preserveScroll: true
        });
    }
};
</script>

<template>
    <Head title="Confidential Access - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Confidential Allowed Emails</h2>
                    <p class="text-sm text-slate-500 mt-1 text-rose-600 font-bold flex items-center">
                        <LockClosedIcon class="w-4 h-4 mr-1" /> Primary Administrator Control Only
                    </p>
                </div>
                <div class="mt-4 md:mt-0">
                    <button @click="showAddModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20">
                        <PlusIcon class="w-4 h-4 mr-2" /> Authorize New Email
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-50 flex items-center gap-3">
                        <div class="p-3 bg-blue-50 rounded-xl text-blue-600">
                            <ShieldCheckIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900">Confidential Access Registry</h3>
                            <p class="text-xs text-slate-500 uppercase font-black tracking-widest mt-0.5">Bypasses standard role-based access for extreme sensitivity</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Email Address</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Salary Views</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Payroll Mgmt</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="item in filteredEmails" :key="item.id" class="group hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-white transition-colors">
                                                <EnvelopeIcon class="w-4 h-4" />
                                            </div>
                                            <span class="text-sm font-bold text-slate-700">{{ item.email }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button 
                                            @click="togglePermission(item, 'can_view_salary')"
                                            :class="[
                                                'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase transition-all',
                                                item.can_view_salary ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-100 text-slate-400 border border-slate-200 opacity-50'
                                            ]"
                                        >
                                            <CheckCircleIcon v-if="item.can_view_salary" class="w-3.5 h-3.5" />
                                            {{ item.can_view_salary ? 'Allowed' : 'Denied' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button 
                                            @click="togglePermission(item, 'can_manage_payroll')"
                                            :class="[
                                                'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase transition-all',
                                                item.can_manage_payroll ? 'bg-indigo-50 text-indigo-600 border border-indigo-100' : 'bg-slate-100 text-slate-400 border border-slate-200 opacity-50'
                                            ]"
                                        >
                                            <CheckCircleIcon v-if="item.can_manage_payroll" class="w-3.5 h-3.5" />
                                            {{ item.can_manage_payroll ? 'Allowed' : 'Denied' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button 
                                            @click="deleteAccount(item)"
                                            class="p-2 text-slate-300 hover:text-rose-600 transition-colors"
                                        >
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-8 bg-rose-50 border border-rose-100 rounded-2xl p-6">
                    <h4 class="font-bold text-rose-900 flex items-center gap-2">
                        <LockClosedIcon class="w-5 h-5" />
                        Important Security Notice
                    </h4>
                    <p class="text-sm text-rose-700 mt-2 leading-relaxed">
                        These accounts are hard-overrides for Salary and Payroll confidentiality. Even if a user is an <strong>Admin</strong> in the Roles module, they will <strong>NOT</strong> see salary or payroll data unless their email is listed above.
                    </p>
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="showAddModal = false"></div>
            
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-slate-900">Authorize New Email</h3>
                    <button @click="showAddModal = false"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
                </div>
                
                <form @submit.prevent="submitAdd" class="p-8 space-y-6">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-500 tracking-widest mb-2">Email Address</label>
                        <input v-model="form.email" type="email" required placeholder="name@example.com" class="w-full rounded-xl border-slate-200 bg-slate-50 focus:ring-blue-500 text-sm font-bold">
                    </div>

                    <div class="space-y-3">
                        <label class="flex items-center p-4 border border-slate-100 rounded-xl cursor-pointer hover:bg-slate-50 transition-all" :class="{'border-emerald-500 bg-emerald-50': form.can_view_salary}">
                            <input type="checkbox" v-model="form.can_view_salary" class="w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <div class="ml-4">
                                <p class="text-sm font-bold text-slate-900">Allow Salary Access</p>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">View Salary on Hire & 201 Salary Rates</p>
                            </div>
                        </label>

                        <label class="flex items-center p-4 border border-slate-100 rounded-xl cursor-pointer hover:bg-slate-50 transition-all" :class="{'border-indigo-500 bg-indigo-50': form.can_manage_payroll}">
                            <input type="checkbox" v-model="form.can_manage_payroll" class="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <div class="ml-4">
                                <p class="text-sm font-bold text-slate-900">Allow Payroll Access</p>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">Full access to Payroll Module & Actions</p>
                            </div>
                        </label>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" :disabled="form.processing" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-700 transition-all shadow-lg shadow-blue-600/20">
                            Confirm Authorization
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
