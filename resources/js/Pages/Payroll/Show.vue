<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { 
    PrinterIcon, 
    CheckBadgeIcon, 
    ArrowLeftIcon,
    BanknotesIcon,
    ShieldCheckIcon,
    PencilSquareIcon,
    XMarkIcon,
    CalculatorIcon,
    UserCircleIcon
} from '@heroicons/vue/24/outline';
import { useToast } from '@/Composables/useToast.js';

const props = defineProps({
    payroll: Object,
    summary: Object,
    can: Object
});

const { showSuccess, showError } = useToast();

const showEditModal = ref(false);
const editingSlip = ref(null);
const editForm = useForm({
    basic_pay: 0,
    allowances: 0,
    ot_pay: 0,
    late_deduction: 0,
    undertime_deduction: 0,
    sss_deduction: 0,
    philhealth_ded: 0,
    pagibig_ded: 0,
    tax_withheld: 0,
    other_deductions: 0,
});

const openEditModal = (slip) => {
    editingSlip.value = slip;
    editForm.basic_pay = slip.basic_pay;
    editForm.allowances = slip.allowances;
    editForm.ot_pay = slip.ot_pay;
    editForm.late_deduction = slip.late_deduction;
    editForm.undertime_deduction = slip.undertime_deduction;
    editForm.sss_deduction = slip.sss_deduction;
    editForm.philhealth_ded = slip.philhealth_ded;
    editForm.pagibig_ded = slip.pagibig_ded;
    editForm.tax_withheld = slip.tax_withheld;
    editForm.other_deductions = slip.other_deductions;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('payslips.update', editingSlip.value.id), {
        onSuccess: () => {
            showEditModal.value = false;
            showSuccess('Payslip adjusted.');
        }
    });
};

const finalizePayroll = () => {
    if (!props.can.approve) return;
    if (confirm('Finalize this payroll? This will lock all values and mark them as ready for payout.')) {
        router.put(route('payroll.approve', props.payroll.id), {}, {
            onSuccess: () => showSuccess('Payroll finalized successfully.')
        });
    }
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount || 0);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<template>
    <Head title="Payroll Details - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('payroll.index')" class="p-2 bg-white rounded-lg border border-slate-200 text-slate-400 hover:text-slate-600 transition-all shadow-sm">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <h2 class="font-bold text-2xl text-slate-800 leading-tight">Payroll Preview</h2>
                        <p class="text-sm text-slate-500 mt-1">
                            {{ payroll.company?.name }} • {{ formatDate(payroll.cutoff_start) }} to {{ formatDate(payroll.cutoff_end) }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl font-bold text-sm flex items-center hover:bg-slate-50 transition-all shadow-sm">
                        <PrinterIcon class="w-4 h-4 mr-2" /> Export PDF
                    </button>
                    <button 
                        v-if="payroll.status === 'Draft' && can.approve"
                        @click="finalizePayroll"
                        class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold text-sm flex items-center hover:bg-blue-700 transition-all shadow-lg shadow-blue-600/20"
                    >
                        <ShieldCheckIcon class="w-4 h-4 mr-2" /> Finalize Payroll
                    </button>
                    <div v-else-if="payroll.status === 'Finalized'" class="bg-emerald-100 text-emerald-700 px-6 py-2 rounded-xl font-bold text-sm flex items-center border border-emerald-200">
                        <CheckBadgeIcon class="w-4 h-4 mr-2" /> FINALIZED
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Gross Pay</p>
                        <p class="text-2xl font-bold text-slate-800 mt-1">{{ formatCurrency(summary.total_gross) }}</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Net Pay</p>
                        <p class="text-2xl font-bold text-emerald-600 mt-1">{{ formatCurrency(summary.total_net) }}</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Employees Processed</p>
                        <p class="text-2xl font-bold text-blue-600 mt-1">{{ summary.employee_count }}</p>
                    </div>
                </div>

                <!-- Payslip Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-800">Payslip Breakdown</h3>
                        <button class="flex items-center text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">
                            <PrinterIcon class="w-4 h-4 mr-2" /> Print Summary
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Employee</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Basic</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Allowances</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">OT Pay</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Gross</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Deductions</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Net Pay</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="slip in payroll.payslips" :key="slip.id" class="hover:bg-slate-50/50 group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center mr-3 text-slate-400">
                                                <UserCircleIcon class="w-6 h-6" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-sm text-slate-900">{{ slip.employee?.user?.name }}</div>
                                                <div class="text-[10px] text-slate-500 uppercase tracking-tighter">{{ slip.employee?.employee_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-slate-600 font-mono">{{ formatCurrency(slip.basic_pay) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-slate-600 font-mono">{{ formatCurrency(slip.allowances) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-blue-600 font-mono font-medium">{{ formatCurrency(slip.ot_pay) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-slate-800 font-mono font-bold">{{ formatCurrency(slip.gross_pay) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-rose-600 font-mono">
                                        {{ formatCurrency((parseFloat(slip.sss_deduction) + parseFloat(slip.philhealth_ded) + parseFloat(slip.pagibig_ded) + parseFloat(slip.tax_withheld) + parseFloat(slip.late_deduction) + parseFloat(slip.undertime_deduction) + parseFloat(slip.other_deductions))) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-emerald-600 font-mono font-bold text-lg">{{ formatCurrency(slip.net_pay) }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button 
                                                v-if="payroll.status === 'Draft' && can.edit_payslip"
                                                @click="openEditModal(slip)"
                                                class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-all"
                                                title="Adjust Values"
                                            >
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </button>
                                            <button class="p-1.5 text-slate-400 hover:text-slate-600 transition-colors">
                                                <PrinterIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Adjust Payslip Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Adjust Individual Payslip</h3>
                    <p class="text-xs text-slate-500">Employee: {{ editingSlip?.employee?.user?.name }}</p>
                </div>
                <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submitEdit" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    
                    <!-- Earnings -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold text-emerald-600 uppercase tracking-widest flex items-center gap-2">
                            <CalculatorIcon class="w-4 h-4" /> Earnings
                        </h4>
                        
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Basic Pay (Period)</label>
                            <input v-model="editForm.basic_pay" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Total Allowances</label>
                            <input v-model="editForm.allowances" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Overtime Pay</label>
                            <input v-model="editForm.ot_pay" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 font-mono text-blue-600 font-bold">
                        </div>
                    </div>

                    <!-- Deductions -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold text-rose-600 uppercase tracking-widest flex items-center gap-2">
                            <CalculatorIcon class="w-4 h-4" /> Deductions
                        </h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Late</label>
                                <input v-model="editForm.late_deduction" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-rose-500 font-mono text-rose-600">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Undertime</label>
                                <input v-model="editForm.undertime_deduction" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-rose-500 font-mono text-rose-600">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 border-t border-slate-50 pt-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">SSS</label>
                                <input v-model="editForm.sss_deduction" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-rose-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">PhilHealth</label>
                                <input v-model="editForm.philhealth_ded" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-rose-500 font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pag-IBIG</label>
                                <input v-model="editForm.pagibig_ded" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-rose-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Withholding Tax</label>
                                <input v-model="editForm.tax_withheld" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-rose-500 font-mono">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Other Deductions</label>
                            <input v-model="editForm.other_deductions" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-sm focus:ring-rose-500 font-mono">
                        </div>
                    </div>
                </div>

                <!-- Total Preview -->
                <div class="mt-8 p-4 bg-slate-900 rounded-2xl flex justify-between items-center">
                    <div class="text-slate-400 text-xs font-bold uppercase tracking-widest">Calculated Net Pay</div>
                    <div class="text-2xl font-mono font-bold text-emerald-400">
                        {{ formatCurrency(parseFloat(editForm.basic_pay) + parseFloat(editForm.allowances) + parseFloat(editForm.ot_pay) - (parseFloat(editForm.late_deduction) + parseFloat(editForm.undertime_deduction) + parseFloat(editForm.sss_deduction) + parseFloat(editForm.philhealth_ded) + parseFloat(editForm.pagibig_ded) + parseFloat(editForm.tax_withheld) + parseFloat(editForm.other_deductions))) }}
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 mt-6 border-t border-slate-100">
                    <button type="button" @click="showEditModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="editForm.processing" class="px-8 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all flex items-center gap-2">
                        <span v-if="editForm.processing" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></span>
                        Save Adjustments
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
