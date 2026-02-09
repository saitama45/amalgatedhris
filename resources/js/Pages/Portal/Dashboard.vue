<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    CalendarIcon, 
    ClockIcon, 
    BanknotesIcon, 
    CreditCardIcon,
    ChevronRightIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    employee: Object,
    recentLeaves: Array,
    recentOvertime: Array,
    leaveCredits: Object
});

const page = usePage();
const authUser = computed(() => page.props.auth.user);

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusClass = (status) => {
    switch (status) {
        case 'Approved': return 'bg-emerald-100 text-emerald-700';
        case 'Rejected': return 'bg-rose-100 text-rose-700';
        default: return 'bg-amber-100 text-amber-700';
    }
};
</script>

<template>
    <AppLayout>
        <Head title="My Portal" />

        <template #header>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">My Portal</h2>
            <p class="text-sm text-slate-500 mt-1">Overview of your employment and requests.</p>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto space-y-8">
                
                <!-- Welcome Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-100">
                    <div class="p-8 bg-gradient-to-r from-[#0B1120] to-[#1E293B] text-white">
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-teal-500/20 rounded-2xl flex items-center justify-center border border-teal-500/30">
                                <span class="text-3xl font-bold text-teal-400">{{ authUser.name?.charAt(0) }}</span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold">Welcome back, {{ authUser.name }}!</h1>
                                <p class="text-slate-400 mt-1">
                                    {{ employee?.active_employment_record?.position?.name || 'Authorized User' }} 
                                    <span v-if="employee?.active_employment_record?.department?.name"> • {{ employee.active_employment_record.department.name }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x border-t border-slate-100">
                        <div class="p-6 text-center">
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Total Entitlement</p>
                            <p class="text-3xl font-black text-slate-900">{{ leaveCredits.total }} <span class="text-sm font-medium text-slate-400">Days</span></p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Overall Used</p>
                            <p class="text-3xl font-black text-slate-900">{{ leaveCredits.used }} <span class="text-sm font-medium text-slate-400">Days</span></p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Available Balance</p>
                            <p class="text-3xl font-black text-teal-600">{{ leaveCredits.balance }} <span class="text-sm font-medium text-teal-400">Days</span></p>
                        </div>
                    </div>
                </div>

                <!-- Leave Credits Breakdown -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <CalendarIcon class="w-5 h-5 text-teal-500" />
                            Leave Balance Breakdown
                        </h3>
                        <p class="text-xs text-slate-400 font-medium">Year {{ new Date().getFullYear() }}</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="type in leaveCredits.breakdown" :key="type.name" class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-3">
                                <p class="text-sm font-bold text-slate-800">{{ type.name }}</p>
                                <div class="flex gap-1">
                                    <span v-if="type.is_cumulative" title="Cumulative" class="w-2 h-2 rounded-full bg-blue-400"></span>
                                    <span v-if="type.is_convertible" title="Convertible" class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                </div>
                            </div>
                            <div class="flex items-end justify-between">
                                <div>
                                    <p class="text-2xl font-black text-slate-900">{{ type.balance }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Remaining Days</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold text-slate-500">{{ type.used }} / {{ type.total }}</p>
                                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-tight">Used / Total</p>
                                </div>
                            </div>
                            <!-- Progress Bar -->
                            <div class="mt-4 w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                <div 
                                    class="h-full bg-teal-500 transition-all duration-500" 
                                    :style="{ width: (type.total > 0 ? (type.balance / type.total) * 100 : 0) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <!-- Recent Leaves -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                                    <CalendarIcon class="w-5 h-5" />
                                </div>
                                <h3 class="font-bold text-slate-800">Recent Leave Filings</h3>
                            </div>
                            <Link :href="route('portal.leaves')" class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1">
                                View All <ChevronRightIcon class="w-3 h-3" />
                            </Link>
                        </div>
                        <div class="flex-1">
                            <div v-if="recentLeaves.length === 0" class="p-12 text-center text-slate-400">
                                <InformationCircleIcon class="w-12 h-12 mx-auto mb-3 opacity-20" />
                                <p>No leave requests found.</p>
                            </div>
                            <div v-else class="divide-y divide-slate-50">
                                <div v-for="leave in recentLeaves" :key="leave.id" class="p-4 hover:bg-slate-50 transition-colors flex justify-between items-center">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-bold text-slate-900">{{ leave.leave_type?.name }}</p>
                                            <span v-if="leave.processed" class="px-1.5 py-0.5 bg-slate-100 text-slate-600 text-[8px] font-bold rounded uppercase border border-slate-200">Processed</span>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-0.5">{{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }}</p>
                                    </div>
                                    <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider', statusClass(leave.status)]">
                                        {{ leave.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Overtime -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                                    <ClockIcon class="w-5 h-5" />
                                </div>
                                <h3 class="font-bold text-slate-800">Recent Overtime</h3>
                            </div>
                            <Link :href="route('portal.overtime')" class="text-xs font-bold text-amber-600 hover:text-amber-700 flex items-center gap-1">
                                View All <ChevronRightIcon class="w-3 h-3" />
                            </Link>
                        </div>
                        <div class="flex-1">
                            <div v-if="recentOvertime.length === 0" class="p-12 text-center text-slate-400">
                                <InformationCircleIcon class="w-12 h-12 mx-auto mb-3 opacity-20" />
                                <p>No overtime requests found.</p>
                            </div>
                            <div v-else class="divide-y divide-slate-50">
                                <div v-for="ot in recentOvertime" :key="ot.id" class="p-4 hover:bg-slate-50 transition-colors flex justify-between items-center">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-bold text-slate-900">{{ formatDate(ot.date) }}</p>
                                            <span v-if="ot.processed" class="px-1.5 py-0.5 bg-slate-100 text-slate-600 text-[8px] font-bold rounded uppercase border border-slate-200">Processed</span>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-0.5">{{ ot.hours_requested }} Hours Requested</p>
                                    </div>
                                    <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider', statusClass(ot.status)]">
                                        {{ ot.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <Link :href="route('portal.leaves')" class="p-6 bg-blue-600 text-white rounded-2xl shadow-lg shadow-blue-600/20 hover:bg-blue-700 transition-all group">
                            <CalendarIcon class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" />
                            <p class="font-bold">File Leave</p>
                            <p class="text-xs text-blue-100 mt-1 opacity-80">Request time off</p>
                        </Link>
                        <Link :href="route('portal.overtime')" class="p-6 bg-amber-500 text-white rounded-2xl shadow-lg shadow-amber-500/20 hover:bg-amber-600 transition-all group">
                            <ClockIcon class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" />
                            <p class="font-bold">Request OT</p>
                            <p class="text-xs text-amber-50 mt-1 opacity-80">Render extra hours</p>
                        </Link>
                        <Link :href="route('portal.payslips')" class="p-6 bg-emerald-600 text-white rounded-2xl shadow-lg shadow-emerald-600/20 hover:bg-emerald-700 transition-all group">
                            <BanknotesIcon class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" />
                            <p class="font-bold">My Payslips</p>
                            <p class="text-xs text-emerald-50 mt-1 opacity-80">View pay history</p>
                        </Link>
                        <Link :href="route('portal.deductions')" class="p-6 bg-slate-800 text-white rounded-2xl shadow-lg shadow-slate-800/20 hover:bg-slate-900 transition-all group">
                            <CreditCardIcon class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" />
                            <p class="font-bold">Deductions</p>
                            <p class="text-xs text-slate-400 mt-1 opacity-80">Loan & ledger</p>
                        </Link>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
