<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { 
    BanknotesIcon, 
    PlusIcon, 
    CalendarIcon, 
    BuildingOfficeIcon,
    TrashIcon,
    EyeIcon
} from '@heroicons/vue/24/outline';
import { useConfirm } from '@/Composables/useConfirm.js';
import { usePagination } from '@/Composables/usePagination.js';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    payrolls: Object,
    companies: Array,
    filters: Object,
    can: Object
});

const pagination = usePagination(props.payrolls, 'payroll.index');

watch(() => props.payrolls, (newData) => {
    pagination.updateData(newData);
});

const { confirm, showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();

const deletePayroll = async (id) => {
    const isConfirmed = await confirm({
        title: 'Delete Payroll',
        message: 'Are you sure? This will delete all generated payslips for this period.'
    });

    if (isConfirmed) {
        router.delete(route('payroll.destroy', id));
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount || 0);
};
</script>

<template>
    <Head title="Payroll Management - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Payroll Processing</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage cut-off periods and generate payslips.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Payroll History"
                        subtitle="List of generated payrolls"
                        :data="pagination.data.value"
                        :current-page="pagination.currentPage.value"
                        :last-page="pagination.lastPage.value"
                        :per-page="pagination.perPage.value"
                        :showing-text="pagination.showingText.value"
                        :is-loading="pagination.isLoading.value"
                        :show-search="false"
                        @go-to-page="pagination.goToPage"
                        @change-per-page="pagination.changePerPage"
                        empty-message="No payroll history found. Click 'Generate Payroll' to start your first period."
                    >
                        <template #actions>
                            <Link 
                                v-if="can.create"
                                :href="route('payroll.create')" 
                                class="bg-emerald-600 text-white px-4 py-2 rounded-xl hover:bg-emerald-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-emerald-600/20"
                            >
                                <PlusIcon class="w-4 h-4 mr-2" /> Generate Payroll
                            </Link>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Company</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Cut-off Period</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Payout Date</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="payroll in data" :key="payroll.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 border border-slate-200">
                                            <BuildingOfficeIcon class="w-4 h-4" />
                                        </div>
                                        <div class="ml-3 text-sm font-bold text-slate-900">{{ payroll.company?.name || 'Unknown' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-slate-700">
                                        {{ formatDate(payroll.cutoff_start) }} - {{ formatDate(payroll.cutoff_end) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center text-sm font-bold text-slate-700">
                                        <CalendarIcon class="w-4 h-4 mr-2 text-slate-400" />
                                        {{ formatDate(payroll.payout_date) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="payroll.status === 'Finalized' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'" class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider">
                                        {{ payroll.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        <Link :href="route('payroll.show', payroll.id)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="View Details">
                                            <EyeIcon class="w-5 h-5" />
                                        </Link>
                                        <button v-if="can.delete" @click="deletePayroll(payroll.id)" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Delete">
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

        <ConfirmModal 
            :show="showConfirmModal" 
            :title="confirmTitle" 
            :message="confirmMessage" 
            @confirm="handleConfirm" 
            @cancel="handleCancel" 
        />
    </AppLayout>
</template>
