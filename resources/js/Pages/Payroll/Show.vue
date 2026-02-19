<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { 
    PrinterIcon, 
    CheckBadgeIcon, 
    ArrowLeftIcon,
    BanknotesIcon,
    ShieldCheckIcon,
    PencilSquareIcon,
    XMarkIcon,
    CalculatorIcon,
    UserCircleIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
    LockClosedIcon
} from '@heroicons/vue/24/outline';
import { useToast } from '@/Composables/useToast.js';
import { usePagination } from '@/Composables/usePagination.js';
import { useConfirm } from '@/Composables/useConfirm.js';
import { watch } from 'vue';

const props = defineProps({
    payroll: Object,
    payslips: Object, // Changed from array to object (paginated)
    summary: Object,
    can: Object
});

const pagination = usePagination(props.payslips, 'payroll.show');

watch(() => props.payslips, (newData) => {
    pagination.updateData(newData);
});

const { showSuccess, showError } = useToast();
const { confirm, showConfirmModal, confirmTitle, confirmMessage, confirmButtonText, handleConfirm, handleCancel } = useConfirm();

const showEditModal = ref(false);
const editingSlip = ref(null);
const editForm = useForm({
    basic_pay: 0,
    allowances: 0,
    adjustments: 0,
    ot_pay: 0,
    late_deduction: 0,
    undertime_deduction: 0,
    sss_deduction: 0,
    philhealth_ded: 0,
    pagibig_ded: 0,
    tax_withheld: 0,
    other_deductions: 0,
    absence_deduction: 0,
    itemized_deductions: [],
    details: {},
});

const openEditModal = (slip) => {
    editingSlip.value = slip;
    editForm.basic_pay = slip.basic_pay;
    editForm.allowances = slip.allowances;
    editForm.adjustments = slip.adjustments;
    editForm.ot_pay = slip.ot_pay;
    editForm.late_deduction = slip.late_deduction;
    editForm.undertime_deduction = slip.undertime_deduction;
    editForm.sss_deduction = slip.sss_deduction;
    editForm.philhealth_ded = slip.philhealth_ded;
    editForm.pagibig_ded = slip.pagibig_ded;
    editForm.tax_withheld = slip.tax_withheld;
    editForm.other_deductions = slip.other_deductions;
    
    // Extract specialized fields from details
    editForm.absence_deduction = slip.details?.absence_deduction || 0;
    // Deep copy deductions array to avoid direct state mutation
    editForm.itemized_deductions = JSON.parse(JSON.stringify(slip.details?.deductions || []));
    editForm.details = JSON.parse(JSON.stringify(slip.details || {}));
    
    showEditModal.value = true;
};

const preventNegative = (e) => {
    if (e.key === '-') e.preventDefault();
};

const submitEdit = () => {
    // 1. Validate all fields are >= 0
    const fields = ['basic_pay', 'allowances', 'adjustments', 'ot_pay', 'late_deduction', 'undertime_deduction', 'sss_deduction', 'philhealth_ded', 'pagibig_ded', 'tax_withheld', 'absence_deduction'];
    for (const field of fields) {
        if (parseFloat(editForm[field]) < 0) {
            showError(`The ${field.replace('_', ' ')} must be greater than or equal to 0.`);
            return;
        }
    }

    // 2. Prepare the payload
    // Update the details object with new values
    editForm.details.absence_deduction = parseFloat(editForm.absence_deduction);
    editForm.details.deductions = editForm.itemized_deductions.map(d => ({
        ...d,
        amount: parseFloat(d.amount || 0)
    }));

    // 3. Recalculate other_deductions (Absences + Itemized Deductions)
    const itemizedTotal = editForm.details.deductions.reduce((sum, d) => sum + d.amount, 0);
    editForm.other_deductions = editForm.details.absence_deduction + itemizedTotal;

    editForm.put(route('payslips.update', editingSlip.value.id), {
        onSuccess: () => {
            showEditModal.value = false;
        }
    });
};

const regeneratePayroll = async () => {
    const isConfirmed = await confirm({
        title: 'Regenerate Computation',
        message: 'This will reset all manual adjustments and re-calculate every payslip based on current DTR, salary, and deduction settings. Continue?',
        confirmButtonText: 'Yes, Recalculate All'
    });

    if (isConfirmed) {
        router.post(route('payroll.regenerate', props.payroll.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Flash success handled by Layout
            }
        });
    }
};

const finalizePayroll = async () => {
    if (!props.can.approve) return;
    
    const isConfirmed = await confirm({
        title: 'Finalize Payroll',
        message: 'Are you sure you want to finalize this payroll? This will lock all values and mark them as ready for payout.',
        confirmButtonText: 'Confirm Finalization'
    });

    if (isConfirmed) {
        router.put(route('payroll.approve', props.payroll.id), {}, {
            onSuccess: () => {
                // Success handled by global flash listener
            }
        });
    }
};

const revertPayroll = async () => {
    if (!props.can.revert) return;
    
    const isConfirmed = await confirm({
        title: 'Unlock Payroll',
        message: 'This will revert the status to Draft and unlock all payslips for adjustment. It will also add back any deducted loan amounts to their respective balances. Continue?',
        confirmButtonText: 'Yes, Revert to Draft'
    });

    if (isConfirmed) {
        router.put(route('payroll.revert', props.payroll.id), {}, {
            onSuccess: () => {
                // Success handled by global flash listener
            }
        });
    }
};

const exportPdf = () => {
    window.location.href = route('payroll.export-pdf', props.payroll.id);
};

const exportExcel = () => {
    window.location.href = route('payroll.export-excel', props.payroll.id);
};

const printPayslip = (slip) => {
    window.open(route('payslips.export-pdf', slip.id), '_blank');
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
                    <button 
                        @click="exportPdf"
                        class="bg-rose-600 text-white px-4 py-2 rounded-xl font-bold text-sm flex items-center hover:bg-rose-700 transition-all shadow-lg shadow-rose-600/20"
                    >
                        <PrinterIcon class="w-4 h-4 mr-2" /> Export PDF
                    </button>
                    <button 
                        v-if="payroll.status === 'Draft' && can.approve"
                        @click="regeneratePayroll"
                        class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl font-bold text-sm flex items-center hover:bg-slate-50 transition-all shadow-sm"
                    >
                        <ArrowPathIcon class="w-4 h-4 mr-2" /> Regenerate
                    </button>
                    <button 
                        v-if="payroll.status === 'Draft' && can.approve"
                        @click="finalizePayroll"
                        class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold text-sm flex items-center hover:bg-blue-700 transition-all shadow-lg shadow-blue-600/20"
                    >
                        <ShieldCheckIcon class="w-4 h-4 mr-2" /> Finalize Payroll
                    </button>
                    <div v-else-if="payroll.status === 'Finalized'" class="flex gap-2">
                        <button 
                            v-if="can.revert"
                            @click="revertPayroll"
                            class="bg-white border border-slate-200 text-amber-600 px-4 py-2 rounded-xl font-bold text-sm flex items-center hover:bg-amber-50 transition-all shadow-sm"
                        >
                            <LockClosedIcon class="w-4 h-4 mr-2" /> Revert to Draft
                        </button>
                        <div class="bg-emerald-100 text-emerald-700 px-6 py-2 rounded-xl font-bold text-sm flex items-center border border-emerald-200">
                            <CheckBadgeIcon class="w-4 h-4 mr-2" /> FINALIZED
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Gross Earnings</p>
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
                    <DataTable
                        title="Payslip Breakdown"
                        subtitle="Individual employee computation"
                        v-model:search="pagination.search.value"
                        :data="pagination.data.value"
                        :current-page="pagination.currentPage.value"
                        :last-page="pagination.lastPage.value"
                        :per-page="pagination.perPage.value"
                        :showing-text="pagination.showingText.value"
                        :is-loading="pagination.isLoading.value"
                        :show-search="true"
                        search-placeholder="Search employee name..."
                        @go-to-page="pagination.goToPage"
                        @change-per-page="pagination.changePerPage"
                    >
                        <template #actions>
                            <button 
                                @click="exportExcel"
                                class="flex items-center text-sm font-bold text-emerald-600 hover:text-emerald-700 transition-colors bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-100"
                            >
                                <ArrowDownTrayIcon class="w-4 h-4 mr-2" /> Download Summary
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Basic</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Allowances</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Adjustments</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">OT Pay</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Gross Earnings</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Total Deductions</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Net Pay</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="slip in data" :key="slip.id" class="hover:bg-slate-50/50 group border-b border-slate-50 last:border-0">
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
                                <td class="px-6 py-4 text-right text-sm text-slate-600 font-mono">{{ formatCurrency(slip.adjustments) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-blue-600 font-mono font-medium">{{ formatCurrency(slip.ot_pay) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-slate-800 font-mono font-bold">{{ formatCurrency(slip.gross_pay) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-rose-600 font-mono">
                                    {{ formatCurrency(
                                        (parseFloat(slip.sss_deduction || 0) + 
                                         parseFloat(slip.philhealth_ded || 0) + 
                                         parseFloat(slip.pagibig_ded || 0) + 
                                         parseFloat(slip.tax_withheld || 0) + 
                                         parseFloat(slip.late_deduction || 0) + 
                                         parseFloat(slip.undertime_deduction || 0) + 
                                         parseFloat(slip.other_deductions || 0))
                                    ) }}
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
                                        <button 
                                            @click="printPayslip(slip)"
                                            class="p-1.5 text-slate-400 hover:text-slate-600 transition-colors"
                                            title="Print Payslip"
                                        >
                                            <PrinterIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>

            </div>
        </div>

        <!-- Adjust Payslip Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="2xl">
            <template v-if="editingSlip">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Adjust Individual Payslip</h3>
                        <p class="text-xs text-slate-500">Employee: {{ editingSlip.employee?.user?.name }}</p>
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
                                <input v-model="editForm.basic_pay" type="number" step="0.01" min="0" @keypress="preventNegative" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Total Allowances</label>
                                <input v-model="editForm.allowances" type="number" step="0.01" min="0" @keypress="preventNegative" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Adjustment Pay</label>
                                <input v-model="editForm.adjustments" type="number" step="0.01" min="0" @keypress="preventNegative" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Overtime Pay</label>
                                <input v-model="editForm.ot_pay" type="number" step="0.01" min="0" @keypress="preventNegative" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 font-mono text-blue-600 font-bold">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Holiday Pay (Premium)</label>
                                <div class="w-full px-4 py-2 bg-purple-50 border border-purple-100 rounded-xl text-sm font-mono text-purple-700 font-bold">
                                    {{ formatCurrency(editingSlip.details?.holiday_work_pay || 0) }}
                                </div>
                            </div>
                        </div>

                        <!-- Deductions -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-rose-600 uppercase tracking-widest flex items-center gap-2">
                                <CalculatorIcon class="w-4 h-4" /> Deductions
                            </h4>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Late / UT</label>
                                    <div class="flex gap-1">
                                        <input v-model="editForm.late_deduction" type="number" step="0.01" class="w-1/2 rounded-xl border-slate-200 text-xs font-mono text-rose-600" placeholder="Late">
                                        <input v-model="editForm.undertime_deduction" type="number" step="0.01" class="w-1/2 rounded-xl border-slate-200 text-xs font-mono text-rose-600" placeholder="UT">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tax Withheld</label>
                                    <input v-model="editForm.tax_withheld" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-xs font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">SSS</label>
                                    <input v-model="editForm.sss_deduction" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">PhilHealth</label>
                                    <input v-model="editForm.philhealth_ded" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pag-IBIG</label>
                                    <input v-model="editForm.pagibig_ded" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-xs font-mono">
                                </div>
                                                                                        <div>
                                                                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 text-rose-600">Absences ({{ editingSlip.details?.absent_days || 0 }}d)</label>
                                                                                            <input v-model="editForm.absence_deduction" type="number" step="0.01" class="w-full rounded-xl border-slate-200 text-xs font-mono text-rose-600 font-bold">
                                                                                        </div>
                                                                                        <div>
                                                                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 text-emerald-600">Paid Leave (Days)</label>
                                                                                            <div class="w-full px-4 py-2 bg-emerald-50 border border-emerald-100 rounded-xl text-xs font-mono text-emerald-700 font-bold">
                                                                                                {{ editingSlip.details?.leave_days || 0 }}
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>                            <!-- Itemized Breakdown (Editable) -->
                            <div class="bg-slate-50 rounded-xl p-3 space-y-2 border border-slate-100">
                                <p class="text-[9px] font-bold text-slate-400 uppercase border-b border-slate-200 pb-1 mb-2">Itemized Deductions (Loans/Others)</p>
                                
                                <div v-if="editForm.itemized_deductions.length > 0" v-for="(d, index) in editForm.itemized_deductions" :key="index" class="space-y-1 mb-3 last:mb-0">
                                    <div class="flex justify-between items-center">
                                        <label class="text-[10px] font-medium text-slate-600">
                                            {{ d.type }}
                                            <span v-if="d.installment" class="text-slate-400">({{ d.installment }})</span>
                                        </label>
                                        <div class="w-32">
                                            <input v-model="d.amount" type="number" step="0.01" class="w-full rounded-lg border-slate-200 text-[10px] font-mono py-1">
                                        </div>
                                    </div>
                                </div>

                                <div v-if="editForm.itemized_deductions.length === 0" class="text-[10px] text-slate-400 italic py-1">
                                    No other deductions found.
                                </div>
                            </div>
                        </div>

                        <!-- Totals Row (Aligned Horizontally) -->
                        <div class="pt-4 border-t border-slate-100 flex justify-between items-center font-bold">
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider">Total Gross Earnings</span>
                            <span class="text-lg font-mono text-slate-800">
                                {{ formatCurrency(parseFloat(editForm.basic_pay || 0) + parseFloat(editForm.allowances || 0) + parseFloat(editForm.adjustments || 0) + parseFloat(editForm.ot_pay || 0)) }}
                            </span>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex justify-between items-center font-bold">
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider">Total Deductions</span>
                            <span class="text-lg font-mono text-rose-600">
                                {{ formatCurrency(
                                    parseFloat(editForm.late_deduction || 0) + 
                                    parseFloat(editForm.undertime_deduction || 0) + 
                                    parseFloat(editForm.sss_deduction || 0) + 
                                    parseFloat(editForm.philhealth_ded || 0) + 
                                    parseFloat(editForm.pagibig_ded || 0) + 
                                    parseFloat(editForm.tax_withheld || 0) + 
                                    parseFloat(editForm.other_deductions || 0)
                                ) }}
                            </span>
                        </div>
                    </div>

                    <!-- Total Preview -->
                    <div class="mt-8 p-4 bg-slate-900 rounded-2xl flex justify-between items-center">
                        <div class="text-slate-400 text-xs font-bold uppercase tracking-widest">Calculated Net Pay</div>
                        <div class="text-2xl font-mono font-bold text-emerald-400">
                            {{ formatCurrency(
                                (parseFloat(editForm.basic_pay || 0) + parseFloat(editForm.allowances || 0) + parseFloat(editForm.adjustments || 0) + parseFloat(editForm.ot_pay || 0)) - 
                                (parseFloat(editForm.late_deduction || 0) + parseFloat(editForm.undertime_deduction || 0) + 
                                 parseFloat(editForm.sss_deduction || 0) + parseFloat(editForm.philhealth_ded || 0) + 
                                 parseFloat(editForm.pagibig_ded || 0) + parseFloat(editForm.tax_withheld || 0) + 
                                 parseFloat(editForm.other_deductions || 0))
                            ) }}
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
            </template>
        </Modal>

        <ConfirmModal 
            :show="showConfirmModal" 
            :title="confirmTitle" 
            :message="confirmMessage" 
            :confirm-button-text="confirmButtonText"
            @confirm="handleConfirm" 
            @cancel="handleCancel" 
        />
    </AppLayout>
</template>
