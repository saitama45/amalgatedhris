<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
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
    ClockIcon
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

// Selection
const selectedEmployees = ref([]);
const selectAll = ref(false);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedEmployees.value = props.employees.map(e => e.id);
    } else {
        selectedEmployees.value = [];
    }
};

const editSchedule = (employee) => {
    selectedEmployees.value = [employee.id];
    const formElement = document.querySelector('form');
    if (formElement) {
        formElement.scrollIntoView({ behavior: 'smooth' });
        formElement.classList.add('ring-2', 'ring-blue-500', 'ring-offset-2');
        setTimeout(() => formElement.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2'), 2000);
    }
    
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

// Form
const form = useForm({
    employee_ids: [],
    shift_id: '',
    days_of_week: [1, 2, 3, 4, 5], // Default Mon-Fri
    grace_period_minutes: 0,
    late_policy: 'exact',
    is_ot_allowed: false
});

const daysOptions = [
    { id: 0, label: 'Sun' },
    { id: 1, label: 'Mon' },
    { id: 2, label: 'Tue' },
    { id: 3, label: 'Wed' },
    { id: 4, label: 'Thu' },
    { id: 5, label: 'Fri' },
    { id: 6, label: 'Sat' },
];

const submitSchedule = () => {
    if (selectedEmployees.value.length === 0) {
        showError('Please select at least one employee.');
        return;
    }

    form.employee_ids = selectedEmployees.value;
    
    form.post(route('schedules.store'), {
        onSuccess: () => {
            showSuccess('Standard schedule updated successfully.');
            selectedEmployees.value = [];
            selectAll.value = false;
        },
        onError: () => showError('Failed to update schedule. Check inputs.')
    });
};

// View Schedule State
const showScheduleModal = ref(false);
const viewingEmployee = ref(null);
const employeeSchedules = ref([]);
const viewMonth = ref(new Date().toISOString().substring(0, 7)); // YYYY-MM

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
</script>

<template>
    <Head title="Shift Assignment - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Shift Assignment</h2>
                    <p class="text-sm text-slate-500 mt-1">Configure standard weekly schedules for employees.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-6">
                
                <!-- Left: Employee List -->
                <div class="w-full lg:w-2/3 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col h-[calc(100vh-200px)]">
                    <!-- Filters -->
                    <div class="p-4 border-b border-slate-100 bg-slate-50 flex gap-4">
                        <div class="relative flex-1">
                            <input v-model="search" type="text" placeholder="Search employees..." class="w-full pl-10 pr-4 py-2 rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <UserGroupIcon class="w-5 h-5 text-slate-400 absolute left-3 top-2.5" />
                        </div>
                        <select v-model="departmentId" @change="applyFilters" class="w-48 py-2 rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">All Departments</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                        </select>
                    </div>

                    <!-- Table -->
                    <div class="flex-1 overflow-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3 text-left w-10">
                                        <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Current Schedule</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100">
                                <tr v-for="employee in employees" :key="employee.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" v-model="selectedEmployees" :value="employee.id" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-bold text-slate-900">{{ employee.user?.name }}</div>
                                        <div class="text-xs text-slate-500">{{ employee.active_employment_record?.department?.name || '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <div v-if="employee.active_employment_record?.default_shift" class="flex items-center">
                                            <ClockIcon class="w-4 h-4 mr-1.5 text-blue-500" />
                                            <span class="font-medium text-slate-700">{{ employee.active_employment_record.default_shift.name }}</span>
                                        </div>
                                        <div v-else class="text-slate-400 italic text-xs">No standard shift</div>
                                    </td>
                                    <td class="px-6 py-4 text-right flex justify-end space-x-1">
                                        <button 
                                            v-if="hasPermission('schedules.manage')"
                                            @click="editSchedule(employee)"
                                            class="text-amber-600 hover:text-amber-800 p-1 rounded-full hover:bg-amber-50 transition-colors"
                                            title="Update Standard Schedule"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            @click="openScheduleModal(employee)"
                                            class="text-blue-600 hover:text-blue-800 p-1 rounded-full hover:bg-blue-50 transition-colors"
                                            title="View Calendar"
                                        >
                                            <EyeIcon class="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="employees.length === 0">
                                    <td colspan="4" class="px-6 py-8 text-center text-slate-500 text-sm italic">
                                        No employees found matching criteria.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-t border-slate-100 bg-slate-50 text-xs text-slate-500 flex justify-between">
                        <span>Showing {{ employees.length }} employees</span>
                        <span class="font-bold text-blue-600">{{ selectedEmployees.length }} selected</span>
                    </div>
                </div>

                <!-- Right: Assignment Form -->
                <div class="w-full lg:w-1/3 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 h-fit sticky top-6">
                    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                        <CalendarDaysIcon class="w-5 h-5 mr-2 text-blue-600" />
                        Update Standard Schedule
                    </h3>
                    <p class="text-xs text-slate-500 mb-6">
                        Define the recurring weekly work pattern for selected employees. This will apply indefinitely.
                    </p>

                    <form @submit.prevent="submitSchedule" class="space-y-6">
                        
                        <!-- Shift Selection -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Select Shift Template</label>
                            <select v-model="form.shift_id" required class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50 font-bold">
                                <option value="" disabled>Choose a Shift...</option>
                                <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                                    {{ shift.name }} ({{ shift.start_time?.substring(0,5) }} - {{ shift.end_time?.substring(0,5) }})
                                </option>
                            </select>
                        </div>

                        <!-- Days of Week -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-3 uppercase">Working Days</label>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="day in daysOptions" :key="day.id" class="cursor-pointer group">
                                    <input type="checkbox" v-model="form.days_of_week" :value="day.id" class="peer sr-only">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-xs font-bold border-2 border-slate-100 bg-white text-slate-400 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 transition-all select-none hover:border-blue-200">
                                        {{ day.label.substring(0,3) }}
                                    </span>
                                </label>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 italic">Unselected days will be automatically treated as Rest Days.</p>
                        </div>

                        <!-- Attendance Rules -->
                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Grace Period (Mins)</label>
                                <input v-model="form.grace_period_minutes" type="number" min="0" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Late Policy</label>
                                <select v-model="form.late_policy" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50 font-bold">
                                    <option value="exact">Exact Minute</option>
                                    <option value="block_30">30-Min Block</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.is_ot_allowed" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4 mr-2">
                                <span class="text-xs font-bold text-slate-700">Allow Overtime</span>
                            </label>
                        </div>

                        <div class="pt-4 border-t border-slate-100">
                            <button 
                                type="submit" 
                                :disabled="form.processing || selectedEmployees.length === 0"
                                class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-600/20 transition-all disabled:opacity-50 disabled:shadow-none flex justify-center items-center"
                            >
                                <CheckCircleIcon class="w-5 h-5 mr-2" />
                                Save Configuration
                            </button>
                            
                            <p v-if="selectedEmployees.length === 0" class="text-xs text-center text-rose-500 font-medium mt-3">
                                Select employees from the list first.
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Schedule View Modal -->
        <Modal :show="showScheduleModal" @close="showScheduleModal = false" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ viewingEmployee?.user?.name }}</h3>
                    <p class="text-sm text-slate-500">Schedule Calendar View</p>
                </div>
                <button @click="showScheduleModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <div class="mb-4 flex justify-between items-center">
                    <label class="block text-xs font-bold text-slate-500 uppercase">Viewing Month</label>
                    <input v-model="viewMonth" type="month" class="rounded-lg border-slate-200 text-sm">
                </div>

                <div class="border border-slate-200 rounded-xl overflow-hidden max-h-[60vh] overflow-y-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">Shift</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">Time</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">Source</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <tr v-for="sched in employeeSchedules" :key="sched.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm font-medium text-slate-900 whitespace-nowrap">
                                    {{ new Date(sched.start.split('T')[0]).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span :class="['px-2 py-1 rounded text-xs font-bold border', sched.className]">
                                        {{ sched.title }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-mono text-slate-600 whitespace-nowrap">
                                    <span v-if="sched.extendedProps.start_time">
                                        {{ sched.extendedProps.start_time }} - {{ sched.extendedProps.end_time }}
                                    </span>
                                    <span v-else class="text-slate-400 italic">-</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    <span v-if="sched.extendedProps.type === 'Override'" class="text-amber-600 font-bold">Manual Override</span>
                                    <span v-else class="text-slate-400">Standard</span>
                                </td>
                            </tr>
                            <tr v-if="employeeSchedules.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400 text-sm">No schedules found for this month.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>