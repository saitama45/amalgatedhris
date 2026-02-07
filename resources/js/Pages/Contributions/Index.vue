<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { useToast } from '@/Composables/useToast';
import { usePermission } from '@/Composables/usePermission';
import { 
    BanknotesIcon, 
    ShieldCheckIcon, 
    BuildingLibraryIcon, 
    TableCellsIcon,
    ArrowPathIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    sss: Array,
    philhealth: Object,
    pagibig: Array,
    filters: Object,
});

const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();
const activeTab = ref('sss');
const showSSSModal = ref(false);
const showPhilHealthModal = ref(false);
const showPagIbigModal = ref(false);
const selectedYear = ref(props.filters?.year || new Date().getFullYear());

watch(selectedYear, (val) => {
    router.get(route('contributions.index'), { year: val }, {
        preserveState: true,
        preserveScroll: true,
        only: ['sss', 'philhealth', 'pagibig', 'filters']
    });
});

const sssForm = useForm({
    effective_year: new Date().getFullYear(),
    min_msc: 5000,
    max_msc: 35000,
    increment: 500,
    rate: 15, // percent
    ec_low: 10,
    ec_high: 30,
    ec_threshold: 15000
});

const philHealthForm = useForm({
    effective_year: props.philhealth?.effective_year || new Date().getFullYear(),
    rate: props.philhealth?.rate ? props.philhealth.rate * 100 : 5,
    min_salary: props.philhealth?.min_salary || 10000,
    max_salary: props.philhealth?.max_salary || 100000,
    er_share: props.philhealth?.er_share_percent ? props.philhealth.er_share_percent * 100 : 50,
    ee_share: props.philhealth?.ee_share_percent ? props.philhealth.ee_share_percent * 100 : 50
});

const pagIBIGForm = useForm({
    effective_year: props.pagibig[0]?.effective_year || new Date().getFullYear(),
    max_fund_salary: props.pagibig[0]?.max_fund_salary || 10000,
    rate_low: 1, // < 1500
    rate_high: 2, // >= 1500
    threshold: 1500
});

const submitSSS = () => {
    sssForm.post(route('contributions.sss.generate'), {
        onSuccess: () => {
            showSSSModal.value = false;
        },
        onError: () => showError('Failed to generate table.')
    });
};

const submitPhilHealth = () => {
    philHealthForm.post(route('contributions.philhealth.update'), {
        onSuccess: () => {
            showPhilHealthModal.value = false;
        },
        onError: () => showError('Failed to update PhilHealth settings.')
    });
};

const submitPagIBIG = () => {
    pagIBIGForm.post(route('contributions.pagibig.update'), {
        onSuccess: () => {
            showPagIbigModal.value = false;
        },
        onError: () => showError('Failed to update Pag-IBIG settings.')
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2
    }).format(value);
};

const formatSalaryRange = (min, max) => {
    if (max > 9000000) return `${formatCurrency(min)} and above`;
    return `${formatCurrency(min)} - ${formatCurrency(max)}`;
};
</script>

<template>
    <Head title="Contribution Tables - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Contribution Tables</h2>
                    <p class="text-sm text-slate-500 mt-1">Reference tables for government mandated contributions.</p>
                </div>
                <div class="mt-4 md:mt-0 flex items-center">
                    <label class="mr-2 text-sm font-bold text-slate-600">Effective Year:</label>
                    <select v-model="selectedYear" class="border-slate-200 rounded-lg text-sm font-bold shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-white">
                        <option v-for="year in filters.available_years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Tabs -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex space-x-1 bg-slate-100 p-1 rounded-xl max-w-md w-full">
                        <button 
                            @click="activeTab = 'sss'"
                            :class="[
                                'flex-1 flex items-center justify-center py-2.5 text-sm font-bold rounded-lg transition-all',
                                activeTab === 'sss' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                            ]"
                        >
                            <ShieldCheckIcon class="w-4 h-4 mr-2" /> SSS
                        </button>
                        <button 
                            @click="activeTab = 'philhealth'"
                            :class="[
                                'flex-1 flex items-center justify-center py-2.5 text-sm font-bold rounded-lg transition-all',
                                activeTab === 'philhealth' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                            ]"
                        >
                            <BuildingLibraryIcon class="w-4 h-4 mr-2" /> PhilHealth
                        </button>
                        <button 
                            @click="activeTab = 'pagibig'"
                            :class="[
                                'flex-1 flex items-center justify-center py-2.5 text-sm font-bold rounded-lg transition-all',
                                activeTab === 'pagibig' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                            ]"
                        >
                            <BanknotesIcon class="w-4 h-4 mr-2" /> Pag-IBIG
                        </button>
                    </div>
                </div>

                <!-- SSS Table -->
                <div v-if="activeTab === 'sss'" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden animate-in fade-in slide-in-from-bottom-2 duration-300">
                    <div class="p-6 border-b border-slate-100 bg-blue-50/50 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Social Security System</h3>
                            <p class="text-xs text-slate-500">Effective Year {{ sss[0]?.effective_year || new Date().getFullYear() }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold">
                                MSC Cap: {{ formatCurrency(sss[sss.length - 1]?.msc || 35000) }}
                            </div>
                            <button v-if="hasPermission('contributions.edit')" @click="showSSSModal = true" class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                                <ArrowPathIcon class="w-3.5 h-3.5" /> Regenerate
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider font-bold">
                                    <th class="px-6 py-3 text-left">Compensation Range</th>
                                    <th class="px-6 py-3 text-center">MSC</th>
                                    <th class="px-6 py-3 text-center bg-blue-50/30">ER Share</th>
                                    <th class="px-6 py-3 text-center bg-emerald-50/30">EE Share</th>
                                    <th class="px-6 py-3 text-center">EC (ER)</th>
                                    <th class="px-6 py-3 text-center bg-slate-100">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in sss" :key="row.id" class="hover:bg-slate-50 transition-colors text-sm">
                                    <td class="px-6 py-3 font-medium text-slate-700 whitespace-nowrap">
                                        {{ formatSalaryRange(row.min_salary, row.max_salary) }}
                                    </td>
                                    <td class="px-6 py-3 text-center font-bold text-slate-800">
                                        {{ formatCurrency(row.msc) }}
                                    </td>
                                    <td class="px-6 py-3 text-center text-slate-600 bg-blue-50/10">
                                        {{ formatCurrency(row.er_share) }}
                                    </td>
                                    <td class="px-6 py-3 text-center text-slate-600 bg-emerald-50/10">
                                        {{ formatCurrency(row.ee_share) }}
                                    </td>
                                    <td class="px-6 py-3 text-center text-slate-500">
                                        {{ formatCurrency(row.ec_share) }}
                                    </td>
                                    <td class="px-6 py-3 text-center font-bold text-slate-900 bg-slate-50">
                                        {{ formatCurrency(row.total_contribution) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SSS Modal -->
                <Modal :show="showSSSModal" @close="showSSSModal = false" maxWidth="md">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-slate-900">Regenerate SSS Table</h3>
                        <button @click="showSSSModal = false"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
                    </div>
                    <form @submit.prevent="submitSSS" class="p-6 space-y-4">
                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-xs text-blue-800">
                            This will archive the current active table and generate new rows based on these parameters.
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Effective Year</label>
                            <input v-model="sssForm.effective_year" type="number" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Min MSC</label>
                                <input v-model="sssForm.min_msc" type="number" min="0" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Max MSC</label>
                                <input v-model="sssForm.max_msc" type="number" min="0" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Rate (%)</label>
                                <input v-model="sssForm.rate" type="number" step="0.1" min="0" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">MSC Step</label>
                                <input v-model="sssForm.increment" type="number" min="0" class="w-full rounded-lg border-slate-200 text-sm" value="500">
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" :disabled="sssForm.processing" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-xl transition-all shadow-lg shadow-blue-600/20">
                                Generate Table
                            </button>
                        </div>
                    </form>
                </Modal>

                <!-- PhilHealth Card -->
                <div v-if="activeTab === 'philhealth'" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden animate-in fade-in slide-in-from-bottom-2 duration-300">
                    <div class="p-6 border-b border-slate-100 bg-emerald-50/50 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">PhilHealth</h3>
                            <p class="text-xs text-slate-500">Philippine Health Insurance Corp. (Effective Year {{ philhealth?.effective_year || new Date().getFullYear() }})</p>
                        </div>
                        <button v-if="hasPermission('contributions.edit')" @click="showPhilHealthModal = true" class="flex items-center gap-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                            <ArrowPathIcon class="w-3.5 h-3.5" /> Update Settings
                        </button>
                    </div>
                    
                    <div class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100">
                                <div class="text-xs font-bold text-slate-500 uppercase mb-2">Premium Rate</div>
                                <div class="text-4xl font-black text-emerald-600">{{ (philhealth?.rate * 100).toFixed(1) }}%</div>
                                <div class="text-xs text-slate-400 mt-2">of Monthly Basic Salary</div>
                            </div>
                            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100">
                                <div class="text-xs font-bold text-slate-500 uppercase mb-2">Income Floor</div>
                                <div class="text-2xl font-bold text-slate-700">{{ formatCurrency(philhealth?.min_salary || 0) }}</div>
                                <div class="text-xs text-slate-400 mt-2">Minimum basis</div>
                            </div>
                            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100">
                                <div class="text-xs font-bold text-slate-500 uppercase mb-2">Income Ceiling</div>
                                <div class="text-2xl font-bold text-slate-700">{{ formatCurrency(philhealth?.max_salary || 0) }}</div>
                                <div class="text-xs text-slate-400 mt-2">Maximum basis</div>
                            </div>
                        </div>

                        <div class="mt-8 bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-center justify-center gap-8">
                            <div class="text-center">
                                <div class="text-xs font-bold text-blue-500 uppercase">Employer Share</div>
                                <div class="text-xl font-bold text-blue-700">{{ (philhealth?.er_share_percent * 100).toFixed(0) }}%</div>
                            </div>
                            <div class="h-8 w-px bg-blue-200"></div>
                            <div class="text-center">
                                <div class="text-xs font-bold text-blue-500 uppercase">Employee Share</div>
                                <div class="text-xl font-bold text-blue-700">{{ (philhealth?.ee_share_percent * 100).toFixed(0) }}%</div>
                            </div>
                        </div>

                        <div class="mt-6 text-center">
                            <p class="text-sm text-slate-500 italic">
                                Note: For salaries below the floor, the premium is computed based on {{ formatCurrency(philhealth?.min_salary || 0) }}.<br>
                                For salaries above the ceiling, the premium is computed based on {{ formatCurrency(philhealth?.max_salary || 0) }}.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- PhilHealth Modal -->
                <Modal :show="showPhilHealthModal" @close="showPhilHealthModal = false" maxWidth="md">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-slate-900">Update PhilHealth Settings</h3>
                        <button @click="showPhilHealthModal = false"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
                    </div>
                    <form @submit.prevent="submitPhilHealth" class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Effective Year</label>
                            <input v-model="philHealthForm.effective_year" type="number" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Premium Rate (%)</label>
                            <input v-model="philHealthForm.rate" type="number" step="0.1" min="0" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Income Floor</label>
                                <input v-model="philHealthForm.min_salary" type="number" min="0" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Income Ceiling</label>
                                <input v-model="philHealthForm.max_salary" type="number" min="0" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                        </div>
                        <div class="flex justify-end pt-4">
                            <button type="submit" :disabled="philHealthForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-xl transition-all shadow-lg shadow-emerald-600/20">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </Modal>

                <!-- Pag-IBIG Modal -->
                <Modal :show="showPagIbigModal" @close="showPagIbigModal = false" maxWidth="md">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-slate-900">Update Pag-IBIG Settings</h3>
                        <button @click="showPagIbigModal = false"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
                    </div>
                    <form @submit.prevent="submitPagIBIG" class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Effective Year</label>
                            <input v-model="pagIBIGForm.effective_year" type="number" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Max Fund Salary</label>
                            <input v-model="pagIBIGForm.max_fund_salary" type="number" min="0" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div class="flex justify-end pt-4">
                            <button type="submit" :disabled="pagIBIGForm.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl transition-all shadow-lg shadow-indigo-600/20">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </Modal>

                <!-- Pag-IBIG Table -->
                <div v-if="activeTab === 'pagibig'" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden animate-in fade-in slide-in-from-bottom-2 duration-300">
                     <div class="p-6 border-b border-slate-100 bg-indigo-50/50 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Pag-IBIG Fund (HDMF)</h3>
                            <p class="text-xs text-slate-500">Home Development Mutual Fund</p>
                        </div>
                         <div class="flex items-center gap-3">
                             <div class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold">
                                Max Fund Salary: ₱10,000
                            </div>
                            <button v-if="hasPermission('contributions.edit')" @click="showPagIbigModal = true" class="flex items-center gap-2 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                                <ArrowPathIcon class="w-3.5 h-3.5" /> Update Settings
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider font-bold">
                                    <th class="px-6 py-3 text-left">Monthly Compensation</th>
                                    <th class="px-6 py-3 text-center">Employee Rate</th>
                                    <th class="px-6 py-3 text-center">Employer Rate</th>
                                    <th class="px-6 py-3 text-center">Total Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in pagibig" :key="row.id" class="hover:bg-slate-50 transition-colors text-sm">
                                    <td class="px-6 py-4 font-medium text-slate-700">
                                        {{ formatSalaryRange(row.min_salary, row.max_salary) }}
                                    </td>
                                    <td class="px-6 py-4 text-center text-slate-600">
                                        {{ (row.ee_rate * 100).toFixed(0) }}%
                                    </td>
                                    <td class="px-6 py-4 text-center text-slate-600">
                                        {{ (row.er_rate * 100).toFixed(0) }}%
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-indigo-600">
                                        {{ ((Number(row.ee_rate) + Number(row.er_rate)) * 100).toFixed(0) }}%
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 bg-slate-50 text-xs text-slate-500 text-center border-t border-slate-100">
                        <span v-if="pagibig && pagibig.length > 0">
                            Maximum contribution basis is pegged at ₱{{ formatCurrency(pagibig[0]?.max_fund_salary || 10000).replace('₱', '') }} 
                            (Maximum total contribution of ₱{{ ((pagibig[0]?.max_fund_salary || 10000) * ((Number(pagibig[0]?.ee_rate) || 0.02) + (Number(pagibig[0]?.er_rate) || 0.02))).toFixed(2) }}).
                        </span>
                        <span v-else>
                            No Pag-IBIG data available.
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
