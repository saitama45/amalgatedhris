<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { usePagination } from '@/Composables/usePagination.js';
import { 
    BanknotesIcon, 
    ArrowDownTrayIcon,
    EyeIcon,
    CalendarIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    payslips: Object,
});

const pagination = usePagination(props.payslips, 'portal.payslips');

watch(() => props.payslips, (newData) => {
    pagination.updateData(newData);
}, { deep: true });

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
};

const formatPeriod = (payroll) => {
    if (!payroll) return 'N/A';
    const start = new Date(payroll.period_start).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    const end = new Date(payroll.period_end).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    return `${start} - ${end}`;
};
</script>

<template>
    <Head title="My Payslips - My Portal" />

    <AppLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">My Payslips</h2>
            <p class="text-sm text-slate-500 mt-1">Access and download your historical payslips.</p>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Payroll History"
                        subtitle="List of generated payslips"
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
                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Pay Period</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Gross Pay</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Deductions</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Net Pay</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="slip in data" :key="slip.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-slate-100 rounded-lg mr-3">
                                            <CalendarIcon class="w-4 h-4 text-slate-500" />
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-slate-900">{{ formatPeriod(slip.payroll) }}</div>
                                            <div class="text-[10px] text-slate-500 uppercase font-semibold">{{ slip.payroll?.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-600">{{ formatCurrency(slip.gross_pay) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-rose-600">
                                        {{ formatCurrency(parseFloat(slip.sss_deduction) + parseFloat(slip.philhealth_ded) + parseFloat(slip.pagibig_ded) + parseFloat(slip.tax_withheld) + parseFloat(slip.loan_deductions) + parseFloat(slip.other_deductions)) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-emerald-600">{{ formatCurrency(slip.net_pay) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-2">
                                        <a :href="route('payslips.export-pdf', slip.id)" target="_blank" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="View PDF">
                                            <EyeIcon class="w-5 h-5" />
                                        </a>
                                        <a :href="route('payslips.export-pdf', slip.id) + '?download=1'" class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Download">
                                            <ArrowDownTrayIcon class="w-5 h-5" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
