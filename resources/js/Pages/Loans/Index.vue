<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { usePagination } from '@/Composables/usePagination.js';
import { useToast } from '@/Composables/useToast.js';
import { useConfirm } from '@/Composables/useConfirm.js';
import { 
    PlusIcon, 
    TrashIcon,
    PencilSquareIcon,
    BanknotesIcon,
    XMarkIcon,
    UserIcon,
    FunnelIcon,
    CreditCardIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    loans: Object,
    employees: Array,
    filters: Object
});

const { showSuccess, showError } = useToast();
const { confirm, showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();

const pagination = usePagination(props.loans, 'loans.index');

watch(() => props.loans, (newData) => {
    pagination.updateData(newData);
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    employee_id: '',
    loan_type: '',
    principal: '',
    amortization: '',
    balance: '',
    status: 'Active'
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (loan) => {
    isEditing.value = true;
    editingId.value = loan.id;
    form.employee_id = loan.employee_id;
    form.loan_type = loan.loan_type;
    form.principal = loan.principal;
    form.amortization = loan.amortization;
    form.balance = loan.balance;
    form.status = loan.status;
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('loans.update', editingId.value), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Loan updated.');
            }
        });
    } else {
        form.post(route('loans.store'), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Loan added successfully.');
            }
        });
    }
};

const deleteLoan = async (id) => {
    const isConfirmed = await confirm({
        title: 'Delete Loan',
        message: 'Are you sure? This only works if no payments have been made.'
    });

    if (isConfirmed) {
        router.delete(route('loans.destroy', id), {
            onSuccess: () => showSuccess('Loan removed.')
        });
    }
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount || 0);
};
</script>

<template>
    <Head title="Loans Management - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Employee Loans</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage employee cash advances and government loans.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Active Loans"
                        subtitle="Ongoing employee amortizations"
                        :data="pagination.data.value"
                        :search="pagination.search.value"
                        :current-page="pagination.currentPage.value"
                        :last-page="pagination.lastPage.value"
                        :per-page="pagination.perPage.value"
                        :showing-text="pagination.showingText.value"
                        :is-loading="pagination.isLoading.value"
                        @update:search="pagination.search.value = $event"
                        @go-to-page="pagination.goToPage"
                        @change-per-page="pagination.changePerPage"
                    >
                        <template #actions>
                            <button @click="openCreateModal" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20">
                                <PlusIcon class="w-4 h-4 mr-2" /> New Loan
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Type</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Principal</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Amortization</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Balance</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="loan in data" :key="loan.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 border border-slate-200">
                                            <UserIcon class="w-4 h-4" />
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-bold text-slate-900">{{ loan.employee?.user?.name }}</div>
                                            <div class="text-[10px] text-slate-500 uppercase font-semibold">ID: {{ loan.employee?.employee_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-700">
                                    {{ loan.loan_type }}
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap text-sm text-slate-600 font-mono">{{ formatCurrency(loan.principal) }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap text-sm text-blue-600 font-bold font-mono">{{ formatCurrency(loan.amortization) }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap text-sm text-emerald-600 font-bold font-mono">{{ formatCurrency(loan.balance) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="loan.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'" class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider">
                                        {{ loan.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        <button @click="openEditModal(loan)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Edit">
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button @click="deleteLoan(loan.id)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Delete">
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Modal :show="showModal" @close="showModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Loan Record' : 'Register New Loan' }}</h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submit" class="p-6 space-y-5">
                <div v-if="!isEditing">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Employee</label>
                    <select v-model="form.employee_id" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        <option value="" disabled>Select Employee</option>
                        <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Loan Type</label>
                    <select v-model="form.loan_type" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        <option value="" disabled>Select Type</option>
                        <option value="SSS Loan">SSS Loan</option>
                        <option value="Pag-IBIG Loan">Pag-IBIG Loan</option>
                        <option value="Cash Advance">Cash Advance</option>
                        <option value="Company Loan">Company Loan</option>
                        <option value="Uniform">Uniform</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Principal Amount</label>
                        <input 
                            v-model="form.principal" 
                            type="number" 
                            step="0.01" 
                            required 
                            min="0.01" 
                            @keypress="(e) => { if(e.key === '-') e.preventDefault(); }"
                            class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Amortization (Per Payout)</label>
                        <input 
                            v-model="form.amortization" 
                            type="number" 
                            step="0.01" 
                            required 
                            min="0.01" 
                            @keypress="(e) => { if(e.key === '-') e.preventDefault(); }"
                            class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                        >
                    </div>
                </div>

                <div v-if="isEditing" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Current Balance</label>
                        <input 
                            v-model="form.balance" 
                            type="number" 
                            step="0.01" 
                            required 
                            min="0" 
                            @keypress="(e) => { if(e.key === '-') e.preventDefault(); }"
                            class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono text-emerald-600 font-bold"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status</label>
                        <select v-model="form.status" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-bold">
                            <option value="Active">Active</option>
                            <option value="Paid">Paid</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-50">
                    <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors mr-3">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-8 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        {{ isEditing ? 'Update Loan' : 'Register Loan' }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmModal 
            :show="showConfirmModal" 
            :title="confirmTitle" 
            :message="confirmMessage" 
            @confirm="handleConfirm" 
            @cancel="handleCancel" 
        />

    </AppLayout>
</template>
