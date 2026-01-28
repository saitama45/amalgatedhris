<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    UserGroupIcon, 
    BriefcaseIcon, 
    ClockIcon, 
    BanknotesIcon 
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

const modules = [
    {
        name: 'Employee Directory',
        description: 'Manage personnel records, departments, and roles.',
        icon: UserGroupIcon,
        color: 'bg-teal-500',
        textColor: 'text-teal-600',
        bgLight: 'bg-teal-50',
        stats: '142 Active Staff'
    },
    {
        name: 'Recruitment',
        description: 'Track job openings, applicants, and interviews.',
        icon: BriefcaseIcon,
        color: 'bg-indigo-500',
        textColor: 'text-indigo-600',
        bgLight: 'bg-indigo-50',
        stats: '8 Open Positions'
    },
    {
        name: 'Time & Attendance',
        description: 'Monitor daily logs, overtime requests, and shifts.',
        icon: ClockIcon,
        color: 'bg-amber-500',
        textColor: 'text-amber-600',
        bgLight: 'bg-amber-50',
        stats: '98% On-Time'
    },
    {
        name: 'Payroll Processing',
        description: 'Process salaries, tax deductions, and payslips.',
        icon: BanknotesIcon,
        color: 'bg-emerald-500',
        textColor: 'text-emerald-600',
        bgLight: 'bg-emerald-50',
        stats: 'Next: Jan 30'
    }
];

const currentDate = new Date().toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
});
</script>

<template>
    <Head title="Dashboard - HR Portal" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">HR Dashboard</h2>
                    <p class="text-sm text-slate-500 mt-1">Overview of workforce metrics and tasks</p>
                </div>
                <div class="mt-4 md:mt-0 text-sm font-medium text-slate-600 bg-white px-4 py-2 rounded-lg shadow-sm border border-slate-200 flex items-center">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                    {{ currentDate }}
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-br from-[#0B1120] to-[#1E293B] rounded-2xl p-6 md:p-10 text-white shadow-xl relative overflow-hidden border border-slate-800">
                    <!-- Abstract Decor -->
                    <div class="absolute top-0 right-0 -mt-16 -mr-16 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl"></div>
                    
                    <div class="relative z-10">
                        <h3 class="text-3xl font-bold mb-2 tracking-tight">Welcome back, <span class="text-teal-400">{{ user.name }}</span>!</h3>
                        <p class="text-slate-300 max-w-2xl text-lg font-light">
                            Here is your daily briefing. You have <span class="font-bold text-white">3 pending approvals</span> and <span class="font-bold text-white">2 new applicants</span> to review today.
                        </p>
                    </div>
                </div>

                <!-- Module Overview Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="mod in modules" :key="mod.name" class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 hover:shadow-md transition-all duration-200 group hover:-translate-y-1">
                        <div class="flex items-center justify-between mb-4">
                            <div :class="[mod.bgLight, 'p-3 rounded-lg group-hover:ring-2 ring-offset-2 ring-transparent transition-all duration-200', `group-hover:ring-${mod.color.replace('bg-', '')}`]">
                                <component :is="mod.icon" :class="[mod.textColor, 'w-6 h-6']" />
                            </div>
                            <span class="px-2 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50 rounded-full">Active</span>
                        </div>
                        <h4 class="text-lg font-bold text-slate-800 mb-1">{{ mod.name }}</h4>
                        <p class="text-sm text-slate-500 mb-4 h-10 leading-relaxed">{{ mod.description }}</p>
                        
                        <div class="border-t border-slate-100 pt-4 flex items-center justify-between">
                            <span class="text-sm font-bold text-slate-700">{{ mod.stats }}</span>
                            <span :class="['text-xs font-semibold hover:underline cursor-pointer flex items-center', mod.textColor]">
                                View Details 
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity / Notices -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Notices -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                            <h4 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                <span class="w-1.5 h-6 bg-teal-500 rounded-full"></span>
                                Company Announcements
                            </h4>
                            <button class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View Archive</button>
                        </div>
                        <div class="p-6">
                            <div class="space-y-6">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <p class="text-sm font-bold text-slate-900">New Performance Review Cycle</p>
                                            <span class="text-xs text-slate-400">2h ago</span>
                                        </div>
                                        <p class="text-sm text-slate-500 mt-1">The Q1 2026 performance review period has officially started. Please ensure all self-assessments are submitted by Feb 15th.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <p class="text-sm font-bold text-slate-900">System Maintenance</p>
                                            <span class="text-xs text-slate-400">1d ago</span>
                                        </div>
                                        <p class="text-sm text-slate-500 mt-1">The Payroll module will be undergoing scheduled maintenance this Sunday from 2:00 AM to 4:00 AM.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-100">
                            <h4 class="text-lg font-bold text-slate-800">Quick Actions</h4>
                        </div>
                         <div class="p-4 space-y-2">
                            <button class="w-full text-left px-4 py-3 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-200 transition-colors flex items-center gap-3 group">
                                <span class="p-2 rounded bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                </span>
                                <span class="text-sm font-medium text-slate-700">Add New Employee</span>
                            </button>
                            <button class="w-full text-left px-4 py-3 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-200 transition-colors flex items-center gap-3 group">
                                <span class="p-2 rounded bg-teal-50 text-teal-600 group-hover:bg-teal-600 group-hover:text-white transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </span>
                                <span class="text-sm font-medium text-slate-700">Generate Payroll Report</span>
                            </button>
                             <button class="w-full text-left px-4 py-3 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-200 transition-colors flex items-center gap-3 group">
                                <span class="p-2 rounded bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </span>
                                <span class="text-sm font-medium text-slate-700">View Attendance Logs</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>