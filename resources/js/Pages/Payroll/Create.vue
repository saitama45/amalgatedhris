<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    BanknotesIcon, 
    ArrowRightIcon, 
    CalendarIcon,
    BuildingOfficeIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    companies: Array,
});

const form = useForm({
    company_id: '',
    cutoff_start: '',
    cutoff_end: '',
    payout_date: '',
});

const submit = () => {
    form.post(route('payroll.store'));
};
</script>

<template>
    <Head title="Generate Payroll - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('payroll.index')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <span class="text-sm font-bold">&larr; Back</span>
                </Link>
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Generate Payroll</h2>
                    <p class="text-sm text-slate-500 mt-1">Select period and company to process.</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
                    <div class="p-8">
                        <form @submit.prevent="submit">
                            
                            <!-- Company Selection -->
                            <div class="mb-8">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Select Company</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <BuildingOfficeIcon class="h-5 w-5 text-slate-400" />
                                    </div>
                                    <select v-model="form.company_id" class="block w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-medium text-slate-700">
                                        <option value="" disabled>Choose an entity...</option>
                                        <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                                    </select>
                                </div>
                                <p v-if="form.errors.company_id" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.company_id }}</p>
                            </div>

                            <div class="border-t border-slate-100 my-8"></div>

                            <!-- Dates -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Cut-off Start Date</label>
                                    <div class="relative">
                                        <input type="date" v-model="form.cutoff_start" class="block w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-bold text-slate-700">
                                    </div>
                                    <p v-if="form.errors.cutoff_start" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.cutoff_start }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Cut-off End Date</label>
                                    <div class="relative">
                                        <input type="date" v-model="form.cutoff_end" class="block w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-bold text-slate-700">
                                    </div>
                                    <p v-if="form.errors.cutoff_end" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.cutoff_end }}</p>
                                </div>
                            </div>

                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-8 flex gap-3">
                                <InformationCircleIcon class="w-6 h-6 text-blue-600 shrink-0" />
                                <p class="text-sm text-blue-800">The system will automatically fetch DTR logs, approved OT, and active salary rates for all employees in the selected company within this date range.</p>
                            </div>

                            <div class="mb-8">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Payout Date</label>
                                <input type="date" v-model="form.payout_date" class="block w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-bold text-slate-700">
                                <p v-if="form.errors.payout_date" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.payout_date }}</p>
                            </div>

                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-lg rounded-xl shadow-xl shadow-emerald-600/20 transition-all flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed"
                            >
                                <span v-if="form.processing" class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></span>
                                <span v-else>Process & Generate Preview</span>
                                <ArrowRightIcon v-if="!form.processing" class="w-5 h-5" />
                            </button>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
