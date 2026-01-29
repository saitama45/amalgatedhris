<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
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
    charts: Object
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

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
                        <h3 class="text-xl font-bold text-slate-800">Welcome back, {{ user.name }}!</h3>
                        <p class="text-slate-500 text-sm mt-1">Here is your workforce summary for today.</p>
                     </div>
                </div>

                <!-- Stats Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Employees -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex items-center justify-between relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Total Employees</p>
                            <h4 class="text-3xl font-bold text-slate-800 mt-2">{{ stats?.totalEmployees || 0 }}</h4>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-blue-500/5 rounded-full blur-2xl"></div>
                    </div>

                    <!-- For Evaluation -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex items-center justify-between relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">For Evaluation</p>
                            <h4 class="text-3xl font-bold text-slate-800 mt-2">{{ stats?.forEvaluation || 0 }}</h4>
                            <p class="text-xs text-slate-400 mt-1">Approaching 6th month</p>
                        </div>
                        <div class="p-3 bg-amber-50 rounded-lg text-amber-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl"></div>
                    </div>

                    <!-- New Hires -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex items-center justify-between relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">New Hires</p>
                            <h4 class="text-3xl font-bold text-slate-800 mt-2">{{ stats?.newHires || 0 }}</h4>
                            <p class="text-xs text-slate-400 mt-1">This month</p>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-lg text-emerald-600">
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
    </AppLayout>
</template>
