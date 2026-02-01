<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    deduction: Object,
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
};

// Progress Calculation
const hasTotal = props.deduction.total_amount > 0;
const total = parseFloat(props.deduction.total_amount || 0);
const balance = parseFloat(props.deduction.remaining_balance || 0);
const paid = total - balance;
const progressPercentage = hasTotal ? Math.min(100, Math.round((paid / total) * 100)) : 0;
const terms = props.deduction.terms || 0;
const amountPerDeduction = parseFloat(props.deduction.amount || 0);
const paidInstallments = (hasTotal && amountPerDeduction > 0) ? Math.floor(paid / amountPerDeduction) : 0;
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Deduction Details - ${deduction.employee.user.name}`" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6">
                    <Link :href="route('deductions.index')" class="flex items-center text-indigo-600 hover:text-indigo-900">
                        <ArrowLeftIcon class="w-4 h-4 mr-2" />
                        Back to Deductions
                    </Link>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">{{ deduction.deduction_type.name }}</h3>
                                <p class="text-sm text-gray-500">Assigned to <span class="font-semibold text-gray-700">{{ deduction.employee.user.name }}</span></p>
                            </div>
                            <span 
                                class="px-3 py-1 rounded-full text-xs font-semibold"
                                :class="{
                                    'bg-green-100 text-green-800': deduction.status === 'active',
                                    'bg-gray-100 text-gray-800': deduction.status === 'completed',
                                    'bg-red-100 text-red-800': deduction.status === 'cancelled'
                                }"
                            >
                                {{ deduction.status.toUpperCase() }}
                            </span>
                        </div>

                        <!-- Progress Section (Only for Loans/Limited Deductions) -->
                        <div v-if="hasTotal" class="mb-8 bg-blue-50 rounded-xl p-6 border border-blue-100">
                            <div class="flex justify-between items-end mb-2">
                                <div>
                                    <h4 class="text-sm font-bold text-blue-900">Repayment Progress</h4>
                                    <p class="text-xs text-blue-600 mt-1">
                                        {{ formatCurrency(paid) }} paid of {{ formatCurrency(total) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="text-2xl font-bold text-blue-700">{{ progressPercentage }}%</span>
                                    <p class="text-xs text-blue-600 font-medium" v-if="terms > 0">
                                        ~ {{ paidInstallments }} / {{ terms }} installments
                                    </p>
                                </div>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-2.5">
                                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-500" :style="{ width: progressPercentage + '%' }"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Configuration</h4>
                                <dl class="space-y-3 text-sm">
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Amount per Deduction</dt>
                                        <dd class="font-medium text-gray-900">
                                            {{ formatCurrency(deduction.amount) }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Frequency</dt>
                                        <dd class="font-medium text-gray-900 capitalize">{{ deduction.frequency.replace('_', ' ') }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Schedule</dt>
                                        <dd class="font-medium text-gray-900 capitalize">{{ deduction.schedule.replace('_', ' ') }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Balance & Dates</h4>
                                <dl class="space-y-3 text-sm">
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Total Loan Amount</dt>
                                        <dd class="font-medium text-gray-900">
                                            {{ deduction.total_amount ? formatCurrency(deduction.total_amount) : 'N/A' }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Remaining Balance</dt>
                                        <dd class="font-medium text-gray-900">
                                            {{ deduction.total_amount ? formatCurrency(deduction.remaining_balance) : 'N/A' }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Terms</dt>
                                        <dd class="font-medium text-gray-900">
                                            {{ terms > 0 ? terms + ' installments' : '-' }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Effective Date</dt>
                                        <dd class="font-medium text-gray-900">{{ formatDate(deduction.effective_date) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Placeholder for History -->
                        <div class="mt-8 border-t pt-6">
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Deduction History</h4>
                            <div class="bg-gray-50 rounded-lg p-6 text-center text-gray-500 text-sm">
                                No history available yet. (Will be linked to Payroll Processing)
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
