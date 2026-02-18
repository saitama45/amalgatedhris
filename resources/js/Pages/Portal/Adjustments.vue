<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { 
    BanknotesIcon, 
    ClockIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';
import { usePagination } from '@/Composables/usePagination.js';
import { watch } from 'vue';

const props = defineProps({
    adjustments: Object,
});

const pagination = usePagination(props.adjustments, 'portal.adjustments');

watch(() => props.adjustments, (newAdjustments) => {
    pagination.updateData(newAdjustments);
}, { deep: true });

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
    <Head title="My Adjustments - My Portal" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">My Adjustments</h2>
                    <p class="text-sm text-slate-500 mt-1">Monitor corrections and refunds applied to your payroll.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Info Alert -->
                <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 flex gap-3">
                    <InformationCircleIcon class="w-6 h-6 text-blue-600 shrink-0" />
                    <div>
                        <p class="text-sm text-blue-900 font-bold">About Adjustments</p>
                        <p class="text-xs text-blue-700 mt-0.5">Adjustments are special one-time additions or deductions (such as missed pay or refunds) that are applied to your scheduled payroll payout.</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Transaction History"
                        subtitle="Detailed list of adjustments"
                        :data="pagination.data.value"
                        :current-page="pagination.currentPage.value"
                        :last-page="pagination.lastPage.value"
                        :per-page="pagination.perPage.value"
                        :showing-text="pagination.showingText.value"
                        :is-loading="pagination.isLoading.value"
                        :show-search="false"
                        @go-to-page="pagination.goToPage"
                        @change-per-page="pagination.changePerPage"
                        empty-message="You have no recorded payroll adjustments."
                    >
                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Date Recorded</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Type</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Reason</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Target Payout</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="adj in data" :key="adj.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 font-mono">
                                    {{ new Date(adj.created_at).toLocaleDateString() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="adj.type === 'Addition' ? 'text-emerald-600' : 'text-rose-600'" class="text-xs font-bold">
                                        {{ adj.type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap font-mono font-bold text-sm" :class="adj.type === 'Addition' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ adj.type === 'Addition' ? '+' : '-' }}{{ formatCurrency(adj.amount) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-slate-600 max-w-sm">{{ adj.reason }}</div>
                                    <div v-if="adj.payroll" class="text-[10px] text-blue-500 mt-1 font-bold">
                                        Applied to Payout Date: {{ formatDate(adj.payroll.payout_date) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap text-xs text-slate-500">
                                    {{ formatDate(adj.payout_date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="statusColors[adj.status]" class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider">
                                        {{ adj.status }}
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>