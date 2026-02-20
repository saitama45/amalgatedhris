<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    ChevronLeftIcon, 
    PencilSquareIcon, 
    DocumentArrowDownIcon,
    BuildingOfficeIcon,
    UserCircleIcon,
    CalendarDaysIcon,
    CheckBadgeIcon,
    ChartPieIcon,
    ArrowUpRightIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    salf: Object,
    overallEfficiency: Number,
    isPortal: Boolean,
});

const processedItems = computed(() => {
    let counter = 1;
    return props.salf.items.map(item => {
        if (item.is_header) {
            return { ...item, displayIndex: null };
        }
        return { ...item, displayIndex: counter++ };
    });
});

const getBackRoute = () => {
    return props.isPortal ? route('portal.salf') : route('salf.index');
};

const getProgressColor = (percent) => {
    if (percent >= 100) return 'bg-emerald-500';
    if (percent >= 75) return 'bg-blue-500';
    if (percent >= 50) return 'bg-amber-500';
    return 'bg-rose-500';
};

const getTextColor = (percent) => {
    if (percent >= 100) return 'text-emerald-600';
    if (percent >= 75) return 'text-blue-600';
    if (percent >= 50) return 'text-amber-600';
    return 'text-rose-600';
};
</script>

<template>
    <Head :title="`SALF - ${salf.employee.first_name} ${salf.employee.last_name}`" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div class="flex items-center space-x-4">
                    <Link :href="getBackRoute()" class="p-2 bg-white rounded-xl shadow-sm border border-slate-100 text-slate-400 hover:text-blue-600 transition-all">
                        <ChevronLeftIcon class="w-6 h-6" />
                    </Link>
                    <div>
                        <h2 class="font-bold text-2xl text-slate-800 leading-tight">Strategic Action Layout Form</h2>
                        <div class="flex items-center mt-1 space-x-2">
                             <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border', 
                                salf.status === 'approved' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-blue-100 text-blue-700 border-blue-200']">
                                {{ salf.status }}
                            </span>
                            <span class="text-xs text-slate-400 font-medium">Last updated {{ new Date(salf.updated_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a
                        :href="route('salf.export-pdf', salf.id)"
                        class="bg-emerald-50 text-emerald-600 px-5 py-2.5 rounded-xl hover:bg-emerald-100 transition-all duration-200 flex items-center space-x-2 text-sm font-bold border border-emerald-100"
                        target="_blank"
                    >
                        <DocumentArrowDownIcon class="w-5 h-5" />
                        <span>Export PDF</span>
                    </a>
                    <Link
                        :href="route('salf.edit', salf.id)"
                        class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-bold shadow-lg shadow-blue-600/20"
                    >
                        <PencilSquareIcon class="w-5 h-5" />
                        <span>Edit Form</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
                        <div class="relative flex items-center space-x-4">
                            <div class="p-3 bg-blue-100 rounded-xl text-blue-600">
                                <UserCircleIcon class="w-6 h-6" />
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Employee</p>
                                <p class="text-sm font-bold text-slate-800 truncate w-40">{{ salf.employee.first_name }} {{ salf.employee.last_name }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-indigo-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
                        <div class="relative flex items-center space-x-4">
                            <div class="p-3 bg-indigo-100 rounded-xl text-indigo-600">
                                <BuildingOfficeIcon class="w-6 h-6" />
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Department</p>
                                <p class="text-sm font-bold text-slate-800">{{ salf.department?.name || 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
                        <div class="relative flex items-center space-x-4">
                            <div class="p-3 bg-amber-100 rounded-xl text-amber-600">
                                <CalendarDaysIcon class="w-6 h-6" />
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Period</p>
                                <p class="text-sm font-bold text-slate-800">{{ salf.period_covered }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
                        <div class="relative">
                            <div class="flex items-center space-x-4">
                                <div class="p-3 bg-emerald-100 rounded-xl text-emerald-600">
                                    <ChartPieIcon class="w-6 h-6" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Overall Eff.</p>
                                    <p class="text-xl font-black text-emerald-600">{{ overallEfficiency }}%</p>
                                </div>
                            </div>
                            <div class="mt-3 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full transition-all duration-1000" :style="`width: ${overallEfficiency}%`"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Items Table -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/30 flex items-center justify-between">
                         <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-white rounded-xl shadow-sm border border-slate-100 flex items-center justify-center text-blue-600">
                                <CheckBadgeIcon class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="font-black text-slate-800">Action Plan Details</h3>
                                <p class="text-xs text-slate-500 font-medium">Strategic goals and progress monitoring</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left bg-slate-50/50">
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">#</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Area of Concern</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Action Plan</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Support</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Deadline</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Progress</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <template v-for="(item, index) in processedItems" :key="item.id">
                                    <tr v-if="item.is_header" class="bg-slate-50/80">
                                        <td colspan="7" class="px-8 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-1.5 h-6 bg-indigo-600 rounded-full"></div>
                                                <span class="text-sm font-black text-indigo-900 uppercase tracking-wider">{{ item.section }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-else class="hover:bg-blue-50/20 transition-colors group">
                                        <td class="px-4 py-6 text-center">
                                            <span class="text-xs font-black text-slate-300 group-hover:text-blue-400 transition-colors">{{ item.displayIndex }}</span>
                                        </td>
                                        <td class="px-6 py-6">
                                            <div class="max-w-xs">
                                                <div class="text-sm font-bold text-slate-800 group-hover:text-blue-600 transition-colors leading-relaxed">
                                                    {{ item.area_of_concern }}
                                                </div>
                                                <div v-if="item.section" class="mt-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500 uppercase tracking-tight">
                                                    {{ item.section }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-6">
                                            <p class="text-sm text-slate-600 leading-relaxed max-w-sm">{{ item.action_plan }}</p>
                                        </td>
                                        <td class="px-6 py-6 text-center">
                                            <div v-if="item.support_group" class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-blue-50 text-blue-600 uppercase tracking-wide border border-blue-100">
                                                {{ item.support_group }}
                                            </div>
                                            <span v-else class="text-slate-300 font-medium text-xs">None</span>
                                        </td>
                                        <td class="px-6 py-6 text-center">
                                            <div v-if="item.target_date" class="inline-flex flex-col items-center">
                                                <span class="text-xs font-black text-slate-800">{{ new Date(item.target_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                                            </div>
                                            <span v-else class="text-slate-300">-</span>
                                        </td>
                                        <td class="px-6 py-6">
                                            <div class="flex flex-col items-center min-w-[120px]">
                                                <div class="flex items-end space-x-1 mb-2">
                                                    <span :class="['text-xl font-black leading-none', getTextColor(item.efficiency)]">{{ Math.round(item.efficiency) }}</span>
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase mb-0.5">%</span>
                                                </div>
                                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden shadow-inner">
                                                    <div 
                                                        :class="['h-full rounded-full transition-all duration-1000 shadow-sm', getProgressColor(item.efficiency)]" 
                                                        :style="`width: ${Math.min(item.efficiency, 100)}%`"
                                                    ></div>
                                                </div>
                                                <div class="flex justify-between w-full mt-2 text-[10px] font-bold text-slate-400">
                                                    <span>{{ item.actual_value }}</span>
                                                    <span>{{ item.target_value }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-6">
                                            <p class="text-xs text-slate-500 italic font-medium max-w-[150px]">{{ item.remarks || 'No remarks' }}</p>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Signatures Mockup -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8 px-4">
                    <div class="border-t-2 border-slate-100 pt-6">
                        <div class="h-16 flex items-end justify-start pb-2">
                             <div class="font-serif italic text-2xl text-slate-300">Signature</div>
                        </div>
                        <p class="text-sm font-bold text-slate-800">{{ salf.employee.first_name }} {{ salf.employee.last_name }}</p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Employee Signature</p>
                    </div>
                    <div class="border-t-2 border-slate-100 pt-6">
                        <div class="h-16 flex items-end justify-start pb-2">
                            <div v-if="salf.status === 'approved'" class="text-blue-600 flex items-center space-x-2">
                                <CheckBadgeIcon class="w-8 h-8" />
                                <span class="font-serif italic text-2xl">Approved</span>
                            </div>
                            <div v-else class="font-serif italic text-2xl text-slate-300">Awaiting Approval</div>
                        </div>
                        <p class="text-sm font-bold text-slate-800">{{ salf.approved_by || 'Not Assigned' }}</p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Approved By</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
