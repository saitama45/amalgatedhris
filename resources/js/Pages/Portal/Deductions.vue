<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    CreditCardIcon, 
    InformationCircleIcon,
    ArrowPathIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    deductions: Array,
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val);
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusClass = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-100 text-emerald-700';
        case 'completed': return 'bg-blue-100 text-blue-700';
        default: return 'bg-slate-100 text-slate-700';
    }
};
</script>

<template>
    <Head title="My Deductions - My Portal" />

    <AppLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">Deductions & Loans Ledger</h2>
            <p class="text-sm text-slate-500 mt-1">Track your active loans and recurring deductions.</p>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div v-if="deductions.length === 0" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 text-center">
                    <CreditCardIcon class="w-16 h-16 text-slate-200 mx-auto mb-4" />
                    <h3 class="text-lg font-bold text-slate-900">No active deductions found</h3>
                    <p class="text-slate-500 mt-1">You don't have any active loans or recurring deductions at the moment.</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="d in deductions" :key="d.id" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-slate-50 flex justify-between items-start">
                            <div>
                                <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider mb-2 inline-block', statusClass(d.status)]">
                                    {{ d.status }}
                                </span>
                                <h3 class="text-lg font-bold text-slate-900">{{ d.deduction_type?.name }}</h3>
                                <p class="text-xs text-slate-500 mt-1">{{ d.deduction_type?.description || 'No description provided' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Amount</p>
                                <p class="text-xl font-black text-slate-900">{{ formatCurrency(d.amount) }}</p>
                                <p class="text-[10px] text-slate-500 font-medium">per {{ d.frequency === 'semimonthly' ? 'payout' : 'month' }}</p>
                            </div>
                        </div>
                        
                        <div class="p-6 bg-slate-50/50 flex-1">
                            <div class="space-y-4">
                                <!-- Progress for Loans -->
                                <div v-if="d.total_amount > 0">
                                    <div class="flex justify-between text-xs font-bold mb-1.5">
                                        <span class="text-slate-500 uppercase tracking-wider">Payment Progress</span>
                                        <span class="text-slate-900">{{ Math.round(((parseFloat(d.total_amount) - parseFloat(d.remaining_balance)) / parseFloat(d.total_amount)) * 100) }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2">
                                        <div 
                                            class="bg-blue-600 h-2 rounded-full transition-all duration-500" 
                                            :style="{ width: Math.round(((parseFloat(d.total_amount) - parseFloat(d.remaining_balance)) / parseFloat(d.total_amount)) * 100) + '%' }"
                                        ></div>
                                    </div>
                                    <div class="flex justify-between mt-2 text-[10px] font-bold">
                                        <div class="text-slate-500">PAID: {{ formatCurrency(parseFloat(d.total_amount) - parseFloat(d.remaining_balance)) }}</div>
                                        <div class="text-slate-500 text-right">BALANCE: {{ formatCurrency(d.remaining_balance) }}</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 pt-2">
                                    <div class="bg-white p-3 rounded-xl border border-slate-100">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Effective Date</p>
                                        <p class="text-sm font-bold text-slate-700">{{ formatDate(d.effective_date) }}</p>
                                    </div>
                                    <div class="bg-white p-3 rounded-xl border border-slate-100">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Schedule</p>
                                        <p class="text-sm font-bold text-slate-700">
                                            {{ d.schedule === 'both' ? '15th & 30th' : (d.schedule === 'first_half' ? '15th' : '30th') }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Payment History Ledger -->
                                <div v-if="d.payment_history && d.payment_history.length > 0" class="mt-4">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                                        <ArrowPathIcon class="w-3 h-3" />
                                        Payment History (Finalized)
                                    </p>
                                    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
                                        <table class="min-w-full divide-y divide-slate-100">
                                            <thead class="bg-slate-50">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-[10px] font-bold text-slate-500 uppercase">Date</th>
                                                    <th class="px-3 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-50">
                                                <tr v-for="(payment, idx) in d.payment_history" :key="idx">
                                                    <td class="px-3 py-2 text-[10px] text-slate-600">
                                                        {{ formatDate(payment.date) }}
                                                        <div class="text-[8px] text-slate-400">{{ payment.payroll_name }}</div>
                                                    </td>
                                                    <td class="px-3 py-2 text-[10px] font-bold text-slate-900 text-right">
                                                        {{ formatCurrency(payment.amount) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 bg-white border-t border-slate-50 text-[10px] text-slate-400 flex items-center gap-2">
                            <InformationCircleIcon class="w-3.5 h-3.5" />
                            <span>Total Loan Amount: {{ formatCurrency(d.total_amount || 0) }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
