<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import {
    UserGroupIcon,
    IdentificationIcon,
    XMarkIcon,
    ChevronRightIcon
} from '@heroicons/vue/24/outline';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    ArcElement
} from 'chart.js';
import { Bar, Pie } from 'vue-chartjs';

// Register ChartJS components
ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

const props = defineProps({
    birthdays: Array,
    stats: Object,
    charts: Object,
    evaluationEmployees: {
        type: Array,
        default: () => []
    },
    totalEmployeesList: {
        type: Array,
        default: () => []
    },
    newHiresList: {
        type: Array,
        default: () => []
    }
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const firstName = computed(() => user.value.name?.split(' ')[0] || 'User');

const showEvaluationModal = ref(false);
const showTotalEmployeesModal = ref(false);
const showNewHiresModal = ref(false);

const currentDate = new Date().toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
});

// Prepare Bar Chart Data
const barChartData = computed(() => {
    if (!props.charts?.barGraph) return { labels: [], datasets: [] };

    const companies = Object.keys(props.charts.barGraph);
    const allDepts = new Set();
    
    companies.forEach(comp => {
        Object.keys(props.charts.barGraph[comp]).forEach(dept => allDepts.add(dept));
    });
    
    const departments = Array.from(allDepts);
    const colors = [
        '#f87171', '#fb923c', '#fbbf24', '#a3e635', '#4ade80', 
        '#34d399', '#2dd4bf', '#22d3ee', '#38bdf8', '#60a5fa', 
        '#818cf8', '#a78bfa', '#c084fc', '#e879f9', '#f472b6'
    ];

    const datasets = departments.map((dept, index) => {
        return {
            label: dept,
            backgroundColor: colors[index % colors.length],
            data: companies.map(comp => props.charts.barGraph[comp][dept] || 0)
        };
    });

    return {
        labels: companies,
        datasets: datasets
    };
});

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
        title: { display: false }
    },
    scales: {
        x: { stacked: true },
        y: { stacked: true }
    }
};

// Prepare Pie Chart Data
const pieChartData = computed(() => {
    if (!props.charts?.pieChart) return { labels: [], datasets: [] };

    return {
        labels: props.charts.pieChart.labels,
        datasets: [{
            backgroundColor: ['#0ea5e9', '#22c55e', '#eab308', '#f97316', '#ef4444', '#8b5cf6', '#ec4899'],
            data: props.charts.pieChart.data
        }]
    };
});

const pieChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'right' }
    }
};
</script>

<template>
    <Head title="Dashboard - HR Portal" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">HR Dashboard</h2>
                    <p class="text-sm text-slate-500 mt-1">Workforce Analytics & Overview</p>
                </div>
                <div class="mt-4 md:mt-0 text-sm font-medium text-slate-600 bg-white px-4 py-2 rounded-lg shadow-sm border border-slate-200 flex items-center">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                    {{ currentDate }}
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Welcome Section -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex items-center justify-between">
                     <div>
                        <h3 class="text-xl font-bold text-slate-800">Welcome back, {{ firstName }}!</h3>
                        <p class="text-slate-500 text-sm mt-1">Here is your workforce summary for today.</p>
                     </div>
                </div>

                <!-- Stats Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Employees -->
                    <div 
                        @click="showTotalEmployeesModal = true"
                        class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex items-center justify-between relative overflow-hidden cursor-pointer hover:border-blue-300 transition-all group"
                    >
                        <div class="relative z-10">
                            <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Total Employees</p>
                            <h4 class="text-3xl font-bold text-slate-800 mt-2">{{ stats?.totalEmployees || 0 }}</h4>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg text-blue-600 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-blue-500/5 rounded-full blur-2xl"></div>
                    </div>

                    <!-- For Evaluation -->
                    <div 
                        @click="showEvaluationModal = true"
                        class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex items-center justify-between relative overflow-hidden cursor-pointer hover:border-amber-300 transition-all group"
                    >
                        <div class="relative z-10">
                            <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">For Evaluation</p>
                            <h4 class="text-3xl font-bold text-slate-800 mt-2">{{ stats?.forEvaluation || 0 }}</h4>
                            <p class="text-xs text-slate-400 mt-1">Currently on 5th month</p>
                        </div>
                        <div class="p-3 bg-amber-50 rounded-lg text-amber-600 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl"></div>
                    </div>

                    <!-- New Hires -->
                    <div 
                        @click="showNewHiresModal = true"
                        class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex items-center justify-between relative overflow-hidden cursor-pointer hover:border-emerald-300 transition-all group"
                    >
                        <div class="relative z-10">
                            <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">New Hires</p>
                            <h4 class="text-3xl font-bold text-slate-800 mt-2">{{ stats?.newHires || 0 }}</h4>
                            <p class="text-xs text-slate-400 mt-1">This month</p>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-lg text-emerald-600 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        </div>
                        <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-emerald-500/5 rounded-full blur-2xl"></div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Left Column: Charts -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Bar Chart -->
                        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                            <h4 class="text-lg font-bold text-slate-800 mb-4">Department Distribution</h4>
                            <div class="h-80 w-full">
                                <Bar :data="barChartData" :options="barChartOptions" />
                            </div>
                        </div>

                        <!-- Pie Chart -->
                        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                            <h4 class="text-lg font-bold text-slate-800 mb-4">Company Distribution</h4>
                            <div class="h-64 w-full flex justify-center">
                                <Pie :data="pieChartData" :options="pieChartOptions" />
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Sidebar (Birthdays) -->
                    <div class="space-y-6">
                         <!-- Birthday Celebrants -->
                        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                                <h4 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                    <span class="w-1.5 h-6 bg-rose-500 rounded-full"></span>
                                    Birthdays ({{ currentDate.split(',')[1].trim().split(' ')[0] }})
                                </h4>
                            </div>
                            <div class="p-4">
                                <div v-if="birthdays && birthdays.length > 0" class="space-y-4">
                                    <div v-for="employee in birthdays" :key="employee.id" class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 text-sm font-bold shrink-0 shadow-sm border border-rose-200">
                                            {{ employee.name.charAt(0) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">{{ employee.name }}</p>
                                            <p class="text-xs text-rose-500 font-medium">{{ employee.date }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                                    <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mb-2">
                                        <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"></path></svg>
                                    </div>
                                    <p class="text-sm text-slate-500">No birthdays this month.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Evaluation List Modal -->
        <Modal :show="showEvaluationModal" @close="showEvaluationModal = false" maxWidth="3xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Employees for Evaluation</h3>
                    <p class="text-sm text-slate-500">Employees currently on their 5th month of service.</p>
                </div>
                <button @click="showEvaluationModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <div class="overflow-hidden border border-slate-100 rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase">Employee</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase">Position & Dept</th>
                                <th class="px-4 py-3 text-center text-[10px] font-bold text-slate-500 uppercase">Hire Date</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold text-slate-500 uppercase w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-50">
                            <tr v-for="emp in evaluationEmployees" :key="emp.id" class="hover:bg-slate-50 group transition-colors">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 bg-amber-100 rounded-full flex items-center justify-center text-amber-700 font-bold text-xs">
                                            {{ emp.name.charAt(0) }}
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-bold text-slate-900">{{ emp.name }}</div>
                                            <div class="text-[10px] text-slate-500 font-mono">{{ emp.employee_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-700 font-medium">{{ emp.position }}</div>
                                    <div class="text-[10px] text-slate-500">{{ emp.department }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold rounded border border-blue-100">
                                        {{ emp.hire_date }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Link 
                                        :href="route('employees.index', { search: emp.employee_code })"
                                        class="text-slate-400 hover:text-blue-600 transition-colors"
                                        title="View Profile"
                                    >
                                        <ChevronRightIcon class="w-5 h-5" />
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="evaluationEmployees.length === 0">
                                <td colspan="4" class="px-4 py-12 text-center text-slate-400 text-sm italic">
                                    No employees found for evaluation this month.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                <button 
                    @click="showEvaluationModal = false"
                    class="px-4 py-2 bg-white border border-slate-200 text-slate-700 font-bold rounded-lg hover:bg-slate-50 transition-all text-sm"
                >
                    Close
                </button>
            </div>
        </Modal>

        <!-- Total Employees Modal -->
        <Modal :show="showTotalEmployeesModal" @close="showTotalEmployeesModal = false" maxWidth="3xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Total Active Employees</h3>
                    <p class="text-sm text-slate-500">Current active roster of the company.</p>
                </div>
                <button @click="showTotalEmployeesModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <div class="overflow-hidden border border-slate-100 rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase">Employee</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase">Position & Dept</th>
                                <th class="px-4 py-3 text-center text-[10px] font-bold text-slate-500 uppercase">Hire Date</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold text-slate-500 uppercase w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-50">
                            <tr v-for="emp in totalEmployeesList" :key="emp.id" class="hover:bg-slate-50 group transition-colors">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-700 font-bold text-xs">
                                            {{ emp.name.charAt(0) }}
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-bold text-slate-900">{{ emp.name }}</div>
                                            <div class="text-[10px] text-slate-500 font-mono">{{ emp.employee_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-700 font-medium">{{ emp.position }}</div>
                                    <div class="text-[10px] text-slate-500">{{ emp.department }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2 py-1 bg-slate-50 text-slate-700 text-[10px] font-bold rounded border border-slate-100">
                                        {{ emp.hire_date }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Link 
                                        :href="route('employees.index', { search: emp.employee_code })"
                                        class="text-slate-400 hover:text-blue-600 transition-colors"
                                        title="View Profile"
                                    >
                                        <ChevronRightIcon class="w-5 h-5" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                <button 
                    @click="showTotalEmployeesModal = false"
                    class="px-4 py-2 bg-white border border-slate-200 text-slate-700 font-bold rounded-lg hover:bg-slate-50 transition-all text-sm"
                >
                    Close
                </button>
            </div>
        </Modal>

        <!-- New Hires Modal -->
        <Modal :show="showNewHiresModal" @close="showNewHiresModal = false" maxWidth="3xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">New Hires (This Month)</h3>
                    <p class="text-sm text-slate-500">Employees who joined in {{ new Date().toLocaleString('default', { month: 'long', year: 'numeric' }) }}.</p>
                </div>
                <button @click="showNewHiresModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <div class="overflow-hidden border border-slate-100 rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase">Employee</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase">Position & Dept</th>
                                <th class="px-4 py-3 text-center text-[10px] font-bold text-slate-500 uppercase">Hire Date</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold text-slate-500 uppercase w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-50">
                            <tr v-for="emp in newHiresList" :key="emp.id" class="hover:bg-slate-50 group transition-colors">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-700 font-bold text-xs">
                                            {{ emp.name.charAt(0) }}
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-bold text-slate-900">{{ emp.name }}</div>
                                            <div class="text-[10px] text-slate-500 font-mono">{{ emp.employee_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-700 font-medium">{{ emp.position }}</div>
                                    <div class="text-[10px] text-slate-500">{{ emp.department }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded border border-emerald-100">
                                        {{ emp.hire_date }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Link 
                                        :href="route('employees.index', { search: emp.employee_code })"
                                        class="text-slate-400 hover:text-blue-600 transition-colors"
                                        title="View Profile"
                                    >
                                        <ChevronRightIcon class="w-5 h-5" />
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="newHiresList.length === 0">
                                <td colspan="4" class="px-4 py-12 text-center text-slate-400 text-sm italic">
                                    No new hires recorded for this month.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                <button 
                    @click="showNewHiresModal = false"
                    class="px-4 py-2 bg-white border border-slate-200 text-slate-700 font-bold rounded-lg hover:bg-slate-50 transition-all text-sm"
                >
                    Close
                </button>
            </div>
        </Modal>
    </AppLayout>
</template>
