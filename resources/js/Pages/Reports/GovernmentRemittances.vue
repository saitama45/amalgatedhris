<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    BanknotesIcon, 
    FunnelIcon, 
    ArrowDownTrayIcon,
    ExclamationCircleIcon,
    BuildingOfficeIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: Array,
    companies: Array,
    filters: Object,
});

const form = ref({
    year: props.filters.year,
    month: props.filters.month,
    company_id: props.filters.company_id || '',
    type: props.filters.type,
});

const years = ref([]);
const currentYear = new Date().getFullYear();
for (let i = currentYear; i >= currentYear - 5; i--) {
    years.value.push(i);
}

const months = [
    { id: 1, name: 'January' }, { id: 2, name: 'February' }, { id: 3, name: 'March' },
    { id: 4, name: 'April' }, { id: 5, name: 'May' }, { id: 6, name: 'June' },
    { id: 7, name: 'July' }, { id: 8, name: 'August' }, { id: 9, name: 'September' },
    { id: 10, name: 'October' }, { id: 11, name: 'November' }, { id: 12, name: 'December' },
];

const reportTypes = [
    { id: 'sss', name: 'SSS Contributions' },
    { id: 'philhealth', name: 'PhilHealth Premiums' },
    { id: 'pagibig', name: 'Pag-IBIG Savings' },
    { id: 'tax', name: 'Withholding Tax (BIR)' },
];

const applyFilters = () => {
    router.get(route('government-remittances.index'), form.value, {
        preserveState: true,
        replace: true
    });
};

const exportReport = () => {
    window.location.href = route('government-remittances.export', form.value);
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value || 0);
};
</script>

<template>
    <Head title="Government Remittances - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Government Remittances</h2>
                    <p class="text-sm text-slate-500 mt-1">Generate and export government mandated contribution reports.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filters -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Report Type</label>
                            <select v-model="form.type" @change="applyFilters" class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 font-medium">
                                <option v-for="type in reportTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Year</label>
                            <select v-model="form.year" @change="applyFilters" class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 font-medium">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Month</label>
                            <select v-model="form.month" @change="applyFilters" class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 font-medium">
                                <option v-for="m in months" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Company</label>
                            <select v-model="form.company_id" @change="applyFilters" class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 font-medium">
                                <option value="">All Companies</option>
                                <option v-for="comp in companies" :key="comp.id" :value="comp.id">{{ comp.name }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <button @click="exportReport" :disabled="reportData.length === 0" class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold py-2.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20">
                                <ArrowDownTrayIcon class="w-4 h-4" /> Export Excel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest">Employee</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest">ID Number</th>
                                    <template v-if="form.type === 'tax'">
                                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest">Taxable Income</th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest">Tax Withheld</th>
                                    </template>
                                    <template v-else>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest">EE Share</th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest">ER Share</th>
                                        <th v-if="form.type === 'sss'" class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest">EC</th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest">Total</th>
                                    </template>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest">Payout Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="(row, index) in reportData" :key="index" class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-slate-900">{{ row.employee_name }}</div>
                                        <div class="text-[10px] text-slate-500 font-bold uppercase tracking-tight">{{ row.company }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-slate-600">
                                        {{ row.id_no || '-' }}
                                    </td>
                                    
                                    <template v-if="form.type === 'tax'">
                                        <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-sm">
                                            {{ formatCurrency(row.taxable_income) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-sm font-bold text-slate-900">
                                            {{ formatCurrency(row.tax_withheld) }}
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-sm">
                                            {{ formatCurrency(row.ee_share) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-sm">
                                            {{ formatCurrency(row.er_share) }}
                                        </td>
                                        <td v-if="form.type === 'sss'" class="px-6 py-4 whitespace-nowrap text-right font-mono text-sm text-slate-500">
                                            {{ formatCurrency(row.ec_share) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-sm font-bold text-slate-900">
                                            {{ formatCurrency(row.total) }}
                                        </td>
                                    </template>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 font-medium">
                                        {{ row.payout_date }}
                                    </td>
                                </tr>
                                <tr v-if="reportData.length === 0">
                                    <td colspan="10" class="px-6 py-12 text-center text-slate-400">
                                        <div class="flex flex-col items-center">
                                            <ExclamationCircleIcon class="w-10 h-10 mb-2 opacity-20" />
                                            <p class="font-medium">No finalized payroll records found for this period.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="reportData.length > 0" class="bg-slate-50/50 font-bold">
                                <tr>
                                    <td colspan="2" class="px-6 py-4 text-right text-xs text-slate-500 uppercase tracking-widest">Totals</td>
                                    <template v-if="form.type === 'tax'">
                                        <td class="px-6 py-4 text-right font-mono">
                                            {{ formatCurrency(reportData.reduce((sum, r) => sum + Number(r.taxable_income || 0), 0)) }}
                                        </td>
                                        <td class="px-6 py-4 text-right font-mono text-blue-600">
                                            {{ formatCurrency(reportData.reduce((sum, r) => sum + Number(r.tax_withheld || 0), 0)) }}
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td class="px-6 py-4 text-right font-mono">
                                            {{ formatCurrency(reportData.reduce((sum, r) => sum + Number(r.ee_share || 0), 0)) }}
                                        </td>
                                        <td class="px-6 py-4 text-right font-mono">
                                            {{ formatCurrency(reportData.reduce((sum, r) => sum + Number(r.er_share || 0), 0)) }}
                                        </td>
                                        <td v-if="form.type === 'sss'" class="px-6 py-4 text-right font-mono">
                                            {{ formatCurrency(reportData.reduce((sum, r) => sum + Number(r.ec_share || 0), 0)) }}
                                        </td>
                                        <td class="px-6 py-4 text-right font-mono text-blue-600">
                                            {{ formatCurrency(reportData.reduce((sum, r) => sum + Number(r.total || 0), 0)) }}
                                        </td>
                                    </template>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
