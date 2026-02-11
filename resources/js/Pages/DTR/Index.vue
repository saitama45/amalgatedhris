<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { usePagination } from '@/Composables/usePagination';
import { usePermission } from '@/Composables/usePermission';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { 
    ClockIcon, 
    ArrowUpTrayIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    PlusIcon, 
    XMarkIcon,
    FunnelIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    logs: Object,
    filters: Object,
    options: Object,
});

const { hasPermission } = usePermission();
const { confirm: confirmAction } = useConfirm();
const { showError } = useToast();

// Filters
const filterForm = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    department_id: props.filters.department_id || '',
    company_id: props.filters.company_id || '',
});

const pagination = usePagination(props.logs, 'dtr.index', () => ({
    ...filterForm.value
}));

// Keep pagination in sync with props (fix for filter not updating table)
watch(() => props.logs, (newLogs) => {
    pagination.updateData(newLogs);
});

// Helper for consistent date display (Avoids timezone shifts)
const formatDisplayDate = (dateStr) => {
    if (!dateStr) return '';
    // If it's YYYY-MM-DD (standard from our updated model cast)
    const cleanDate = String(dateStr).split('T')[0].split(' ')[0];
    const [year, month, day] = cleanDate.split('-').map(Number);
    
    // Using UTC components to display exactly what's in the string
    // or just manually formatting to be 100% sure
    const date = new Date(year, month - 1, day);
    const options = { weekday: 'short', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
};

const applyFilters = () => {
    const params = {
        ...filterForm.value,
        search: pagination.search.value,
        per_page: pagination.perPage.value // Explicitly include per_page
    };
    pagination.performSearch(route('dtr.index'), params);
};

// Form (Add/Edit)
const showModal = ref(false);
const isEditing = ref(false);
const editingLog = ref(null);

const getLocalDate = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const form = useForm({
    employee_id: '',
    date: getLocalDate(),
    time_in: '',
    time_out: '',
});

// Autocomplete State
const employeeSearch = ref('');
const showEmployeeDropdown = ref(false);
const highlightedIndex = ref(-1);

const filteredEmployees = ref([]);

const searchEmployees = () => {
    if (!employeeSearch.value) {
        filteredEmployees.value = props.options.employees.slice(0, 50);
        return;
    }
    
    const term = employeeSearch.value.toLowerCase();
    filteredEmployees.value = props.options.employees.filter(emp => 
        emp.name.toLowerCase().includes(term)
    ).slice(0, 50);
};

// Solution: Use a temporary flag `isSelecting`.
const isSelecting = ref(false);

watch(employeeSearch, () => {
    if (!isSelecting.value) {
        form.employee_id = '';
    }
    searchEmployees();
    highlightedIndex.value = -1;
    isSelecting.value = false; // Reset flag
});

const selectEmployee = (employee) => {
    isSelecting.value = true;
    form.employee_id = employee.id;
    employeeSearch.value = employee.name;
    showEmployeeDropdown.value = false;
};

const handleEmployeeSearchBlur = () => {
    setTimeout(() => {
        showEmployeeDropdown.value = false;
    }, 200);
};


const openCreateModal = () => {
    isEditing.value = false;
    editingLog.value = null;
    form.reset();
    form.clearErrors();
    form.date = getLocalDate(); // Ensure it's fresh
    employeeSearch.value = '';
    filteredEmployees.value = props.options.employees.slice(0, 50);
    showModal.value = true;
};

const openEditModal = (log) => {
    isEditing.value = true;
    editingLog.value = log;
    form.employee_id = log.employee_id;
    
    // The backend now sends "YYYY-MM-DD" exactly.
    form.date = String(log.date).split('T')[0].split(' ')[0];

    const extractTimePart = (dateStr) => {
        if (!dateStr) return '';
        const match = String(dateStr).match(/(\d{2}):(\d{2})/);
        return match ? match[0] : '';
    };

    form.time_in = extractTimePart(log.time_in);
    form.time_out = extractTimePart(log.time_out);
    
    form.clearErrors();
    showModal.value = true;
};

const submitForm = () => {
    // Validation: Time In should not be later than Time Out
    if (form.time_in && form.time_out && form.time_in >= form.time_out) {
        showError('Time Out must be later than Time In.');
        return;
    }

    // Schedule Check: Employee must have a schedule before manual entry
    const selectedEmployee = props.options.employees.find(e => e.id == form.employee_id);
    if (!selectedEmployee?.active_employment_record?.default_shift_id) {
        showError('This employee does not have an assigned schedule yet. Please configure it in the Schedules module.');
        return;
    }

    if (isEditing.value) {
        form.put(route('dtr.update', editingLog.value.id), {
            onSuccess: () => {
                showModal.value = false;
                pagination.updateData(props.logs);
            },
            onError: (errors) => {
                showError(Object.values(errors)[0]);
            }
        });
    } else {
        form.post(route('dtr.store'), {
            onSuccess: () => {
                showModal.value = false;
                pagination.updateData(props.logs);
            },
            onError: (errors) => {
                showError(Object.values(errors)[0]);
            }
        });
    }
};

const deleteLog = async (log) => {
    const confirmed = await confirmAction({
        title: 'Delete Attendance Log',
        message: 'Are you sure you want to delete this log? This action cannot be undone.'
    });

    if (confirmed) {
        router.delete(route('dtr.destroy', log.id));
    }
};

// Import
const showImportModal = ref(false);
const importForm = useForm({
    file: null,
});

const submitImport = () => {
    importForm.post(route('dtr.import'), {
        onSuccess: () => {
            showImportModal.value = false;
        },
    });
};

// Helper for status colors
const statusClass = (status) => {
    switch (status) {
        case 'Present': return 'bg-emerald-100 text-emerald-700';
        case 'Half Day': return 'bg-indigo-100 text-indigo-700';
        case 'Late': return 'bg-amber-100 text-amber-700';
        case 'Absent': return 'bg-rose-100 text-rose-700';
        case 'Incomplete': return 'bg-slate-100 text-slate-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};

const calculateWorkHours = (log) => {
    if (!log.time_in || !log.time_out) return 0;
    let inTime = new Date(log.time_in);
    let outTime = new Date(log.time_out);

    const shift = log.employee?.active_employment_record?.default_shift;
    if (shift) {
        const logDate = String(log.date).split('T')[0];
        const shiftStart = new Date(`${logDate}T${shift.start_time}`);
        
        // Calculate break window (assuming break starts 4 hours after shift start)
        const breakStart = new Date(shiftStart.getTime() + (4 * 60 * 60 * 1000));
        const breakEnd = new Date(breakStart.getTime() + (shift.break_minutes * 60 * 1000));

        // Afternoon Amnesty Check (10:01 AM - 1:00 PM arrival)
        const lateMinutes = Math.floor((inTime - shiftStart) / (1000 * 60));
        if (lateMinutes > 120 && lateMinutes <= 300) {
            inTime = new Date(breakEnd); // Start at 1:00 PM
        }

        // Calculate Morning Work Block
        const morningStart = inTime < breakStart ? inTime : breakStart;
        const morningEnd = outTime < breakStart ? outTime : breakStart;
        const morningMs = Math.max(0, morningEnd - morningStart);

        // Calculate Afternoon Work Block
        const afternoonStart = inTime > breakEnd ? inTime : breakEnd;
        const afternoonEnd = outTime > breakEnd ? outTime : breakEnd;
        const afternoonMs = Math.max(0, afternoonEnd - afternoonStart);

        return parseFloat(((morningMs + afternoonMs) / (1000 * 60 * 60)).toFixed(2));
    }
    
    return parseFloat(((outTime - inTime) / (1000 * 60 * 60)).toFixed(2));
};

const calculateLate = (log) => {
    if (!log.time_in) return 0;
    const shift = log.employee?.active_employment_record?.default_shift;
    if (!shift) return 0;

    const logDate = String(log.date).split('T')[0];
    const shiftStart = new Date(`${logDate}T${shift.start_time}`);
    const timeIn = new Date(log.time_in);
    const outTime = log.time_out ? new Date(log.time_out) : null;

    const workHours = parseFloat(calculateWorkHours(log));
    const lateMinutes = Math.floor((timeIn - shiftStart) / (1000 * 60));

    // Morning Half Day Amnesty: If worked 4 hours and timed out before 1:01 PM
    if (workHours >= 4 && outTime) {
        const afternoonCutoff = new Date(`${logDate}T13:01:00`);
        if (outTime < afternoonCutoff) return 0;
    }

    if (lateMinutes <= 5) return 0;

    // Afternoon Amnesty: 10:01 AM to 1:00 PM
    if (lateMinutes > 120 && lateMinutes <= 300) {
        return 0;
    }

    return Math.ceil(lateMinutes / 30) * 30;
};

const calculateUndertime = (log) => {
    if (!log.time_in || !log.time_out) return 0;
    
    const shift = log.employee?.active_employment_record?.default_shift;
    if (!shift) return 0;

    const logDate = String(log.date).split('T')[0];
    const shiftStart = new Date(`${logDate}T${shift.start_time}`);
    const timeIn = new Date(log.time_in);
    const outTime = new Date(log.time_out);

    const actualHours = calculateWorkHours(log);
    const lateMinutes = Math.floor((timeIn - shiftStart) / (1000 * 60));

    // Afternoon Amnesty: If started during the afternoon window (10:01 AM - 1:00 PM)
    // they are expected to work 4 hours. If they worked 4 hours, UT is 0.
    if (lateMinutes > 120 && lateMinutes <= 300) {
        return actualHours >= 4 ? 0 : Math.round((4 - actualHours) * 60);
    }

    // Morning Half Day Amnesty: No UT if timed out at 1:00 PM or earlier and worked 4 hours
    const afternoonCutoff = new Date(`${logDate}T13:01:00`);
    if (actualHours >= 4 && outTime < afternoonCutoff) {
        return 0;
    }

    let shiftEnd = new Date(`${logDate}T${shift.end_time}`);
    
    if (shiftEnd < shiftStart) {
        shiftEnd.setDate(shiftEnd.getDate() + 1);
    }

    const expectedMs = (shiftEnd - shiftStart) - (shift.break_minutes * 60 * 1000);
    const expectedHours = expectedMs / (1000 * 60 * 60);
    
    const undertime = expectedHours - actualHours;
    return undertime > 0.02 ? (undertime * 60).toFixed(0) : 0; // 0.02 tolerance for rounding
};
</script>

<template>
    <Head title="DTR Management - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Daily Time Records</h2>
                    <p class="text-sm text-slate-500 mt-1">Monitor attendance logs, tardiness, and overtime.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Date Range</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="filterForm.start_date" type="date" class="w-full rounded-lg border-slate-200 text-sm">
                                <input v-model="filterForm.end_date" type="date" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                        </div>
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Company</label>
                            <select v-model="filterForm.company_id" class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">All Companies</option>
                                <option v-for="comp in options.companies" :key="comp.id" :value="comp.id">{{ comp.name }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Department</label>
                            <select v-model="filterForm.department_id" class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">All Departments</option>
                                <option v-for="dept in options.departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <button @click="applyFilters" class="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                                <FunnelIcon class="w-4 h-4" /> Filter Logs
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        :search="pagination.search.value"
                        :data="pagination.data.value"
                        :current-page="pagination.currentPage.value"
                        :last-page="pagination.lastPage.value"
                        :per-page="pagination.perPage.value"
                        :showing-text="pagination.showingText.value"
                        :is-loading="pagination.isLoading.value"
                        @update:search="pagination.search.value = $event"
                        @go-to-page="pagination.goToPage"
                        @change-per-page="pagination.changePerPage"
                        title="Attendance Logs"
                        subtitle="Detailed daily logs"
                    >
                        <template #actions>
                            <div class="flex gap-2">
                                <button
                                    v-if="hasPermission('dtr.create')"
                                    @click="showImportModal = true"
                                    class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-xl transition-all font-bold text-sm flex items-center shadow-sm"
                                >
                                    <ArrowUpTrayIcon class="w-4 h-4 mr-2" /> Import
                                </button>
                                <button
                                    v-if="hasPermission('dtr.create')"
                                    @click="openCreateModal"
                                    class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20"
                                >
                                    <PlusIcon class="w-4 h-4 mr-2" /> Add Log
                                </button>
                            </div>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Time In</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Time Out</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Work Hrs</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Late / UT / OT</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="log in data" :key="log.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                    {{ formatDisplayDate(log.date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-slate-900">{{ log.employee?.user?.name }}</div>
                                    <div class="text-xs text-slate-500">{{ log.employee?.active_employment_record?.department?.name || '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ log.employee?.active_employment_record?.company?.name || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center font-mono text-sm text-slate-600">
                                    {{ log.time_in ? new Date(log.time_in).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '--:--' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center font-mono text-sm text-slate-600">
                                    {{ log.time_out ? new Date(log.time_out).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '--:--' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center font-mono text-sm font-bold text-slate-700">
                                    {{ calculateWorkHours(log).toFixed(2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <div v-if="calculateLate(log) > 0" class="text-[10px] leading-none text-rose-600 font-bold bg-rose-50 px-1.5 py-1 rounded border border-rose-100 w-16 text-center">
                                            {{ calculateLate(log) }}m Late
                                        </div>
                                        <div v-if="calculateUndertime(log) > 0" class="text-[10px] leading-none text-amber-600 font-bold bg-amber-50 px-1.5 py-1 rounded border border-amber-100 w-16 text-center">
                                            {{ calculateUndertime(log) }}m UT
                                        </div>
                                        <div v-if="log.ot_minutes > 0" class="text-[10px] leading-none text-blue-600 font-bold bg-blue-50 px-1.5 py-1 rounded border border-blue-100 w-16 text-center">
                                            {{ log.ot_minutes }}m OT
                                        </div>
                                        <div v-if="calculateLate(log) == 0 && calculateUndertime(log) == 0 && log.ot_minutes == 0" class="text-xs text-slate-400">-</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="['px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wide border border-transparent', statusClass(log.status)]">
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        <template v-if="!log.is_locked">
                                            <button @click="openEditModal(log)" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </button>
                                            <button @click="deleteLog(log)" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </template>
                                        <div v-else class="p-1.5 text-slate-300" title="Locked by Finalized Payroll">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Update Log' : 'Manual Entry' }}</h3>
                <button @click="showModal = false"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
            </div>
            <form @submit.prevent="submitForm" class="p-6 space-y-4">
                <div v-if="!isEditing" class="relative">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Employee</label>
                    <input 
                        type="text" 
                        v-model="employeeSearch"
                        @focus="showEmployeeDropdown = true; searchEmployees()"
                        @blur="handleEmployeeSearchBlur"
                        placeholder="Type to search..."
                        class="w-full rounded-lg border-slate-200 text-sm"
                    >
                    <!-- Dropdown -->
                    <div v-if="showEmployeeDropdown && filteredEmployees.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <ul class="py-1">
                            <li 
                                v-for="emp in filteredEmployees" 
                                :key="emp.id"
                                @mousedown.prevent="selectEmployee(emp)"
                                class="px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer"
                            >
                                {{ emp.name }}
                            </li>
                        </ul>
                    </div>
                    <div v-else-if="showEmployeeDropdown && filteredEmployees.length === 0" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg p-3 text-sm text-slate-500 text-center">
                        No employees found.
                    </div>
                </div>
                <div v-else>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Employee</label>
                    <div class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700 font-medium">
                        {{ editingLog?.employee?.user?.name || 'Unknown' }}
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Date</label>
                    <input v-model="form.date" type="date" class="w-full rounded-lg border-slate-200 text-sm" :disabled="isEditing">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Time In</label>
                        <input v-model="form.time_in" type="time" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Time Out</label>
                        <input v-model="form.time_out" type="time" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                </div>
                <div class="flex justify-end pt-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-xl transition-all">
                        Save Log
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Import Modal -->
        <Modal :show="showImportModal" @close="showImportModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">Import Biometric Data</h3>
                <button @click="showImportModal = false"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
            </div>
            <form @submit.prevent="submitImport" class="p-6 space-y-4">
                <div class="bg-amber-50 border border-amber-100 rounded-lg p-3 flex gap-3 items-start">
                    <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                    <p class="text-xs text-amber-700">Ensure the file is a CSV export from your ZKTeco device or use the provided Excel template.</p>
                </div>
                
                <div class="flex justify-between items-center bg-blue-50 p-3 rounded-lg border border-blue-100">
                    <div class="text-xs text-blue-700 font-medium">Need a format guide?</div>
                    <a :href="route('dtr.template')" class="text-xs bg-white text-blue-600 hover:text-blue-800 border border-blue-200 px-3 py-1.5 rounded-lg font-bold flex items-center shadow-sm transition-all hover:shadow-md">
                        <DocumentTextIcon class="w-4 h-4 mr-1.5" /> Download Template
                    </a>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Select File</label>
                    <input type="file" @input="importForm.file = $event.target.files[0]" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all">
                </div>
                <div class="flex justify-end pt-4">
                    <button type="submit" :disabled="importForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-xl transition-all flex items-center">
                        <ArrowUpTrayIcon class="w-4 h-4 mr-2" /> Upload & Process
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>