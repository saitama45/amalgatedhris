<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import Autocomplete from '@/Components/Autocomplete.vue';
import { 
    BanknotesIcon, 
    PlusIcon, 
    TrashIcon,
    InformationCircleIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ClockIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';
import { useToast } from '@/Composables/useToast.js';
import { usePagination } from '@/Composables/usePagination.js';
import { useConfirm } from '@/Composables/useConfirm.js';

const props = defineProps({
    adjustments: Object,
    employees: Array,
    filters: Object,
});

const { confirm } = useConfirm();
const pagination = usePagination(props.adjustments, 'payroll-adjustments.index');

watch(() => props.adjustments, (newAdjustments) => {
    pagination.updateData(newAdjustments);
}, { deep: true });

const showAddModal = ref(false);
const form = useForm({
    employee_id: '',
    type: 'Addition',
    amount: '',
    reason: '',
    is_taxable: false,
    payout_date: '',
});

const submit = () => {
    form.post(route('payroll-adjustments.store'), {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
};

const deleteAdjustment = async (id) => {
    const isConfirmed = await confirm({
        title: 'Delete Adjustment',
        message: 'Are you sure? This will permanently remove this pending adjustment.'
    });

    if (isConfirmed) {
        router.delete(route('payroll-adjustments.destroy', id));
    }
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount || 0);
};

const formatDate = (date) => {
    if (!date) return 'Next Payroll';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusColors = {
    'Pending': 'bg-amber-100 text-amber-700',
    'Processed': 'bg-emerald-100 text-emerald-700',
    'Cancelled': 'bg-slate-100 text-slate-600',
};
</script>

<template>
    <Head title="Payroll Adjustments - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Payroll Adjustments</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage refunds, missed pay, and special corrections.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Adjustment Ledger"
                        subtitle="Pending and processed adjustments"
                        :data="pagination.data.value"
                        :current-page="pagination.currentPage.value"
                        :last-page="pagination.lastPage.value"
                        :per-page="pagination.perPage.value"
                        :showing-text="pagination.showingText.value"
                        :is-loading="pagination.isLoading.value"
                        v-model:search="pagination.search.value"
                        @go-to-page="pagination.goToPage"
                        @change-per-page="pagination.changePerPage"
                    >
                        <template #actions>
                            <button 
                                @click="showAddModal = true"
                                class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20"
                            >
                                <PlusIcon class="w-4 h-4 mr-2" /> New Adjustment
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Type</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Reason</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Sched Payout</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="adj in data" :key="adj.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-slate-900">{{ adj.employee?.user?.name }}</div>
                                    <div class="text-xs text-slate-500">{{ adj.employee?.employee_code }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="adj.type === 'Addition' ? 'text-emerald-600' : 'text-rose-600'" class="text-xs font-bold">
                                        {{ adj.type }}
                                        <span v-if="adj.is_taxable" class="ml-1 text-[9px] bg-slate-100 text-slate-500 px-1 rounded">Taxable</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap font-mono font-bold" :class="adj.type === 'Addition' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ adj.type === 'Addition' ? '+' : '-' }}{{ formatCurrency(adj.amount) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-slate-600 max-w-xs truncate" :title="adj.reason">{{ adj.reason }}</div>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap text-xs text-slate-500">
                                    {{ formatDate(adj.payout_date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="statusColors[adj.status]" class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider">
                                        {{ adj.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button 
                                        v-if="adj.status === 'Pending'"
                                        @click="deleteAdjustment(adj.id)" 
                                        class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                    >
                                        <TrashIcon class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <Modal :show="showAddModal" @close="showAddModal = false" maxWidth="lg">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">New Payroll Adjustment</h3>
                <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submit" class="p-6 space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Employee</label>
                    <Autocomplete 
                        v-model="form.employee_id"
                        :options="employees"
                        placeholder="Search employee..."
                        label-key="name"
                        value-key="id"
                    />
                    <p v-if="form.errors.employee_id" class="text-xs text-rose-500 mt-1">{{ form.errors.employee_id }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Type</label>
                        <select v-model="form.type" class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500">
                            <option value="Addition">Addition (Earnings)</option>
                            <option value="Deduction">Deduction (Refund/Recovery)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Amount</label>
                        <input v-model="form.amount" type="number" step="0.01" min="0.01" required class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500 font-mono">
                    </div>
                </div>

                <div class="flex items-center space-x-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <input type="checkbox" v-model="form.is_taxable" id="is_taxable" class="rounded text-blue-600 focus:ring-blue-500">
                    <label for="is_taxable" class="text-xs font-bold text-slate-700">Subject to Withholding Tax?</label>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Reason / Description</label>
                    <textarea v-model="form.reason" rows="3" required placeholder="e.g. Missed overtime from last cutoff, Refund of excess PhilHealth deduction..." class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Target Payout Date (Optional)</label>
                    <input v-model="form.payout_date" type="date" class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500">
                    <p class="text-[10px] text-slate-500 mt-1 italic">Leave blank to apply to the very next payroll generated for this employee.</p>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="showAddModal = false" class="px-4 py-2 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50">Save Adjustment</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>