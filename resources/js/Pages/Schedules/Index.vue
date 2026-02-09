<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from '@/Composables/useToast';
import { usePermission } from '@/Composables/usePermission';
import { 
    CalendarDaysIcon, 
    FunnelIcon, 
    UserGroupIcon,
    CheckCircleIcon,
    EyeIcon,
    XMarkIcon,
    PencilSquareIcon,
    ClockIcon,
    MagnifyingGlassIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    BookmarkSquareIcon,
    PlusIcon,
    ArrowPathIcon,
    Squares2X2Icon,
    ListBulletIcon
} from '@heroicons/vue/24/outline';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    employees: Array,
    shifts: Array,
    departments: Array,
    filters: Object,
});

const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

// View Management
const viewMode = ref('grid'); // 'grid' or 'list'

// Filters
const search = ref(props.filters.search || '');
const departmentId = ref(props.filters.department_id || '');

const applyFilters = () => {
    router.get(route('schedules.index'), { 
        search: search.value, 
        department_id: departmentId.value 
    }, { 
        preserveState: true, 
        preserveScroll: true,
        replace: true 
    });
};

watch(search, (val) => {
    applyFilters();
});

// Selection Logic
const selectedEmployees = ref([]);
const selectAll = ref(false);
const showConsole = ref(false);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedEmployees.value = props.employees.map(e => e.id);
        showConsole.value = true;
    } else {
        selectedEmployees.value = [];
        showConsole.value = false;
    }
};

// Form Logic
const form = useForm({
    employee_ids: [],
    shift_id: '',
    days_of_week: [1, 2, 3, 4, 5], // Default Mon-Fri
    grace_period_minutes: 0,
    late_policy: 'exact',
    is_ot_allowed: false
});

const daysOptions = [
    { id: 0, label: 'SUN' },
    { id: 1, label: 'MON' },
    { id: 2, label: 'TUE' },
    { id: 3, label: 'WED' },
    { id: 4, label: 'THU' },
    { id: 5, label: 'FRI' },
    { id: 6, label: 'SAT' },
];

const editSchedule = (employee) => {
    selectedEmployees.value = [employee.id];
    showConsole.value = true;
    
    // Pre-fill form if employee has existing default
    if (employee.active_employment_record?.default_shift_id) {
        form.shift_id = employee.active_employment_record.default_shift_id;
        if (employee.active_employment_record.work_days) {
            form.days_of_week = employee.active_employment_record.work_days.split(',').map(Number);
        }
        form.grace_period_minutes = employee.active_employment_record.grace_period_minutes;
        form.late_policy = employee.active_employment_record.late_policy || 'exact';
        form.is_ot_allowed = Boolean(employee.active_employment_record.is_ot_allowed);
    }
};

const submitSchedule = () => {
    if (selectedEmployees.value.length === 0) {
        showError('Please select at least one employee from the grid.');
        return;
    }

    form.employee_ids = selectedEmployees.value;
    
    form.post(route('schedules.store'), {
        onSuccess: () => {
            selectedEmployees.value = [];
            selectAll.value = false;
            showConsole.value = false;
        },
        onError: () => showError('Failed to update schedule. Check inputs.')
    });
};

// Shift Template Helpers
const applyTemplate = (shiftId) => {
    form.shift_id = shiftId;
};

// View Schedule Modal logic
const showScheduleModal = ref(false);
const viewingEmployee = ref(null);
const employeeSchedules = ref([]);
const viewMonth = ref(new Date().toISOString().substring(0, 7));

const openScheduleModal = (employee) => {
    viewingEmployee.value = employee;
    showScheduleModal.value = true;
    fetchSchedules();
};

const fetchSchedules = () => {
    if (!viewingEmployee.value) return;
    const [year, month] = viewMonth.value.split('-');
    const start = new Date(year, month - 1, 1).toISOString().split('T')[0];
    const end = new Date(year, month, 0).toISOString().split('T')[0];

    axios.get(route('schedules.show', viewingEmployee.value.id), {
        params: { start, end }
    }).then(response => {
        employeeSchedules.value = response.data;
    });
};

watch(viewMonth, () => {
    if (showScheduleModal.value) fetchSchedules();
});

const getInitials = (name) => {
    if (!name) return '??';
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};

const isWorkingDay = (employee, dayId) => {
    if (!employee.active_employment_record?.work_days) return false;
    const days = employee.active_employment_record.work_days.split(',').map(Number);
    return days.includes(dayId);
};
</script>

<template>
    <Head title="Shift Assignment - HRIS" />
    <AppLayout fluid>
        <div class="h-[calc(100vh-64px)] flex flex-col bg-slate-50 overflow-hidden">
            
            <!-- WORLD CLASS HEADER -->
            <div class="bg-white border-b border-slate-200 px-8 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shrink-0 shadow-sm z-20">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                        <div class="bg-blue-600 p-2 rounded-xl text-white shadow-lg shadow-blue-200">
                            <ClockIcon class="w-6 h-6" />
                        </div>
                        Shift Assignment
                    </h1>
                    <p class="text-slate-500 text-sm font-medium mt-1">Orchestrate your workforce standard weekly patterns.</p>
                </div>

                <div class="flex items-center gap-3 bg-slate-100 p-1 rounded-xl border border-slate-200">
                    <button 
                        @click="viewMode = 'list'"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition-all',
                            viewMode === 'list' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        ]"
                    >
                        <ListBulletIcon class="w-4 h-4" /> List
                    </button>
                    <button 
                        @click="viewMode = 'grid'"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition-all',
                            viewMode === 'grid' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        ]"
                    >
                        <Squares2X2Icon class="w-4 h-4" /> Grid
                    </button>
                </div>
            </div>

            <!-- DASHBOARD CORE -->
            <div class="flex-1 flex overflow-hidden">
                
                <!-- LEFT: Main Visualization Area -->
                <div class="flex-1 flex flex-col min-w-0 bg-slate-50">
                    
                    <!-- Toolbar -->
                    <div class="px-8 py-4 border-b border-slate-200 bg-white/50 backdrop-blur-md flex flex-wrap items-center justify-between gap-4 shrink-0">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="relative max-w-sm w-full group">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute left-3.5 top-2.5 text-slate-400 group-focus-within:text-blue-500 transition-colors" />
                                <input v-model="search" type="text" placeholder="Search employees..." 
                                    class="w-full pl-11 pr-4 py-2.5 rounded-xl border-slate-200 bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 text-sm font-medium transition-all shadow-sm">
                            </div>
                            <select v-model="departmentId" @change="applyFilters" 
                                class="w-56 py-2.5 rounded-xl border-slate-200 bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 text-sm font-bold shadow-sm">
                                <option value="">All Departments</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-widest bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                            <UserGroupIcon class="w-4 h-4" />
                            {{ selectedEmployees.length }} Employees Selected
                        </div>
                    </div>

                    <!-- The Grid (Scrollable) -->
                    <div class="flex-1 overflow-auto custom-scrollbar p-8 pt-0">
                        <div class="mt-6">
                            <table class="min-w-full w-max border-separate border-spacing-0 bg-white rounded-3xl border border-slate-200 shadow-xl shadow-slate-200/50">
                                <thead class="bg-slate-50 sticky top-0 z-30 shadow-sm">
                                    <tr>
                                        <th class="p-4 w-12 border-r border-b border-slate-100 text-center sticky left-0 bg-slate-50 z-40 rounded-tl-3xl">
                                            <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="w-5 h-5 rounded-lg border-slate-300 text-blue-600 focus:ring-blue-500 transition-all">
                                        </th>
                                        <th class="p-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest w-72 border-r border-b border-slate-100 sticky left-12 bg-slate-50 z-40">
                                            Resource Designation
                                        </th>
                                        <th v-for="day in daysOptions" :key="day.id" class="p-4 text-center border-r border-b border-slate-100 min-w-[140px] bg-slate-50">
                                            <div class="text-[10px] font-black text-slate-400 mb-1">{{ day.label }}</div>
                                            <div class="text-sm font-bold text-slate-700">Recurring</div>
                                        </th>
                                        <th class="p-4 text-right text-xs font-black text-slate-500 uppercase tracking-widest w-24 border-b border-slate-100 rounded-tr-3xl bg-slate-50">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="employee in employees" :key="employee.id" 
                                        class="group transition-all duration-200">
                                        <td class="p-4 text-center border-r border-slate-100 sticky left-0 bg-white group-hover:bg-slate-50 z-20">
                                            <input type="checkbox" v-model="selectedEmployees" :value="employee.id" 
                                                class="w-5 h-5 rounded-lg border-slate-200 text-blue-600 focus:ring-blue-500 transition-all cursor-pointer">
                                        </td>
                                        <td class="p-4 border-r border-slate-100 sticky left-12 bg-white group-hover:bg-slate-50 z-20">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 border-2 border-white shadow-sm flex items-center justify-center text-slate-600 font-black text-xs">
                                                    {{ getInitials(employee.user?.name) }}
                                                </div>
                                                <div class="overflow-hidden">
                                                    <div class="text-sm font-bold text-slate-900 truncate">{{ employee.user?.name }}</div>
                                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-tight truncate">{{ employee.active_employment_record?.department?.name || 'No Dept' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td v-for="day in daysOptions" :key="day.id" class="p-2 border-r border-slate-100 last:border-0 relative group-hover:bg-blue-50/30">
                                            <div v-if="isWorkingDay(employee, day.id)" 
                                                class="bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-xl p-2 text-center shadow-sm">
                                                <div class="text-[10px] font-black uppercase leading-none opacity-60">Working</div>
                                                <div class="text-[11px] font-bold mt-1 truncate">{{ employee.active_employment_record?.default_shift?.name }}</div>
                                            </div>
                                            <div v-else class="h-12 flex items-center justify-center border border-dashed border-slate-100 rounded-xl group/cell transition-all hover:border-blue-200 hover:bg-white">
                                                <PlusIcon class="w-4 h-4 text-slate-200 group-hover/cell:text-blue-400 opacity-0 group-hover/cell:opacity-100" />
                                            </div>
                                        </td>
                                        <td class="p-4 text-right group-hover:bg-blue-50/30">
                                            <div class="flex justify-end items-center gap-1">
                                                <button @click="editSchedule(employee)" class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-xl transition-all" title="Edit Configuration">
                                                    <PencilSquareIcon class="w-5 h-5" />
                                                </button>
                                                <button @click="openScheduleModal(employee)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all" title="Full Calendar">
                                                    <EyeIcon class="w-5 h-5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Management Sidebar -->
                <Transition name="slide-fade">
                    <aside v-if="showConsole" class="w-96 bg-white border-l border-slate-200 flex flex-col shrink-0 z-[60] shadow-2xl overflow-hidden">
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-8">
                            
                            <!-- Configuration Form -->
                            <div class="mb-10">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center gap-2">
                                        <div class="bg-blue-50 p-2 rounded-lg text-blue-600">
                                            <ArrowPathIcon class="w-5 h-5" />
                                        </div>
                                        <h3 class="text-lg font-black text-slate-900 tracking-tight">Management Console</h3>
                                    </div>
                                    <button @click="showConsole = false" class="text-slate-400 hover:text-slate-600 p-1.5 hover:bg-slate-100 rounded-lg transition-all">
                                        <XMarkIcon class="w-5 h-5" />
                                    </button>
                                </div>

                                <form @submit.prevent="submitSchedule" class="space-y-6">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Target Shift Template</label>
                                        <div class="grid grid-cols-1 gap-2">
                                            <button v-for="shift in shifts" :key="shift.id" type="button"
                                                @click="applyTemplate(shift.id)"
                                                :class="[
                                                    'text-left p-3 rounded-2xl border-2 transition-all group',
                                                    form.shift_id === shift.id 
                                                        ? 'border-blue-600 bg-blue-50/50 shadow-md shadow-blue-100' 
                                                        : 'border-slate-100 hover:border-slate-200'
                                                ]"
                                            >
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-sm font-bold text-slate-800">{{ shift.name }}</span>
                                                    <CheckCircleIcon v-if="form.shift_id === shift.id" class="w-5 h-5 text-blue-600" />
                                                </div>
                                                <div class="text-[10px] text-slate-500 font-medium flex items-center gap-1.5">
                                                    <ClockIcon class="w-3.5 h-3.5" />
                                                    {{ shift.start_time?.substring(0,5) }} - {{ shift.end_time?.substring(0,5) }}
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Work Cycle (Standard)</label>
                                        <div class="flex flex-wrap gap-2">
                                            <label v-for="day in daysOptions" :key="day.id" class="cursor-pointer">
                                                <input type="checkbox" v-model="form.days_of_week" :value="day.id" class="peer sr-only">
                                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-[10px] font-black border-2 border-slate-100 bg-white text-slate-400 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 transition-all shadow-sm hover:border-slate-200">
                                                    {{ day.label.substring(0,3) }}
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Grace (Min)</label>
                                            <input v-model="form.grace_period_minutes" type="number" min="0" 
                                                class="w-full rounded-xl border-slate-200 text-sm font-bold focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Late Policy</label>
                                            <select v-model="form.late_policy" 
                                                class="w-full rounded-xl border-slate-200 text-sm font-bold focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500">
                                                <option value="exact">Exact</option>
                                                <option value="block_30">30m Block</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                        <label class="flex items-center cursor-pointer group">
                                            <div class="relative">
                                                <input type="checkbox" v-model="form.is_ot_allowed" class="sr-only peer">
                                                <div class="w-10 h-6 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                            </div>
                                            <span class="ml-3 text-xs font-black text-slate-700 uppercase tracking-tight">Allow Overtime</span>
                                        </label>
                                    </div>

                                    <button 
                                        type="submit" 
                                        :disabled="form.processing || selectedEmployees.length === 0"
                                        class="w-full py-4 px-6 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm uppercase tracking-widest rounded-2xl shadow-xl shadow-blue-200 transition-all disabled:opacity-50 disabled:grayscale flex justify-center items-center gap-3 active:scale-[0.98]"
                                    >
                                        <CheckCircleIcon class="w-6 h-6" />
                                        Save Configuration
                                    </button>
                                    
                                    <p v-if="selectedEmployees.length === 0" class="text-[10px] text-center text-rose-500 font-black uppercase tracking-wider">
                                        Select employees from the grid first.
                                    </p>
                                </form>
                            </div>
                        </div>
                    </aside>
                </Transition>
            </div>
        </div>

        <!-- Schedule View Modal -->
        <Modal :show="showScheduleModal" @close="showScheduleModal = false" maxWidth="2xl">
            <div class="px-8 py-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center rounded-t-3xl">
                <div>
                    <h3 class="text-xl font-black text-slate-900 tracking-tight">{{ viewingEmployee?.user?.name }}</h3>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Schedule Intelligence Report</p>
                </div>
                <button @click="showScheduleModal = false" class="text-slate-400 hover:text-slate-900 transition-colors p-2 hover:bg-white rounded-xl shadow-sm border border-transparent hover:border-slate-200">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-8">
                <div class="mb-6 flex justify-between items-center bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Select Target Month</label>
                    <input v-model="viewMonth" type="month" class="rounded-xl border-slate-200 text-sm font-bold focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="border border-slate-200 rounded-3xl overflow-hidden max-h-[50vh] overflow-y-auto shadow-inner custom-scrollbar bg-white">
                    <table class="w-full border-collapse">
                        <thead class="bg-slate-50/80 backdrop-blur-sm sticky top-0 z-10 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Timeline</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Template</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="sched in employeeSchedules" :key="sched.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-slate-900 whitespace-nowrap">
                                        {{ new Date(sched.start.split('T')[0]).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) }}
                                    </div>
                                    <div class="text-[10px] font-mono text-slate-500 mt-0.5">
                                        {{ sched.extendedProps.start_time }} - {{ sched.extendedProps.end_time }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="['px-3 py-1 rounded-lg text-[10px] font-black uppercase border tracking-tight', sched.className]">
                                        {{ sched.title }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div :class="['w-2 h-2 rounded-full', sched.extendedProps.type === 'Override' ? 'bg-amber-500' : 'bg-slate-300']"></div>
                                        <span :class="['text-[10px] font-black uppercase tracking-tighter', sched.extendedProps.type === 'Override' ? 'text-amber-600' : 'text-slate-400']">
                                            {{ sched.extendedProps.type || 'Standard' }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="employeeSchedules.length === 0">
                                <td colspan="3" class="px-6 py-12 text-center text-slate-400 text-sm font-medium italic">No intelligence data for this month.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}

/* Glassmorphism support */
.backdrop-blur-md {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

/* Custom Checkbox sizing */
input[type="checkbox"] {
    width: 1.25rem;
    height: 1.25rem;
}

/* Slide Fade Transition */
.slide-fade-enter-active {
  transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.slide-fade-leave-active {
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>