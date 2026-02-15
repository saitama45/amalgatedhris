<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { usePagination } from '@/Composables/usePagination';
import { 
    ClockIcon, 
    FunnelIcon,
    CalendarIcon,
    CameraIcon,
    MapPinIcon,
    XMarkIcon,
    UserCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    logs: Object,
    filters: Object,
});

// Filters
const filterForm = ref({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

const pagination = usePagination(props.logs, 'portal.attendance', () => ({
    ...filterForm.value
}));

// Keep pagination in sync with props
watch(() => props.logs, (newLogs) => {
    pagination.updateData(newLogs);
});

// Helper for consistent date display
const formatDisplayDate = (dateStr) => {
    if (!dateStr) return '';
    const cleanDate = String(dateStr).split('T')[0].split(' ')[0];
    const [year, month, day] = cleanDate.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
    return date.toLocaleDateString('en-US', options);
};

const applyFilters = () => {
    pagination.performSearch(route('portal.attendance'), filterForm.value);
};

// OB Details Modal
const showOBModal = ref(false);
const obDetails = ref(null);

const viewOBDetails = (log) => {
    obDetails.value = log;
    showOBModal.value = true;
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
    if (!log.time_in || !log.time_out) return '0.00';
    let inTime = new Date(log.time_in);
    let outTime = new Date(log.time_out);

    const shift = log.employee?.active_employment_record?.default_shift;
    if (shift) {
        const logDate = String(log.date).split('T')[0];
        const shiftStart = new Date(`${logDate}T${shift.start_time}`);
        const breakStart = new Date(shiftStart.getTime() + (4 * 60 * 60 * 1000));
        const breakEnd = new Date(breakStart.getTime() + (shift.break_minutes * 60 * 1000));

        const lateMinutes = Math.floor((inTime - shiftStart) / (1000 * 60));
        if (lateMinutes > 120 && lateMinutes <= 300) {
            inTime = new Date(breakEnd);
        }

        const morningStart = inTime < breakStart ? inTime : breakStart;
        const morningEnd = outTime < breakStart ? outTime : breakStart;
        const morningMs = Math.max(0, morningEnd - morningStart);

        const afternoonStart = inTime > breakEnd ? inTime : breakEnd;
        const afternoonEnd = outTime > breakEnd ? outTime : breakEnd;
        const afternoonMs = Math.max(0, afternoonEnd - afternoonStart);

        return ((morningMs + afternoonMs) / (1000 * 60 * 60)).toFixed(2);
    }
    
    return ((outTime - inTime) / (1000 * 60 * 60)).toFixed(2);
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

    if (workHours >= 4 && outTime) {
        const afternoonCutoff = new Date(`${logDate}T13:01:00`);
        if (outTime < afternoonCutoff) return 0;
    }

    if (lateMinutes <= 5) return 0;
    if (lateMinutes > 120 && lateMinutes <= 300) return 0;

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

    const workHours = parseFloat(calculateWorkHours(log));
    const lateMinutes = Math.floor((timeIn - shiftStart) / (1000 * 60));

    const afternoonCutoff = new Date(`${logDate}T13:01:00`);
    if (workHours >= 4 && outTime < afternoonCutoff) return 0;
    if (lateMinutes > 120 && lateMinutes <= 300) return 0;

    let shiftEnd = new Date(`${logDate}T${shift.end_time}`);
    if (shiftEnd < shiftStart) shiftEnd.setDate(shiftEnd.getDate() + 1);

    const expectedMs = (shiftEnd - shiftStart) - (shift.break_minutes * 60 * 1000);
    const expectedHours = expectedMs / (1000 * 60 * 60);
    const actualHours = parseFloat(calculateWorkHours(log));
    const undertime = expectedHours - actualHours;
    return undertime > 0.02 ? (undertime * 60).toFixed(0) : 0;
};

const formatHours = (minutes) => {
    return parseFloat((minutes / 60).toFixed(2)) + ' hrs';
};
</script>

<template>
    <Head title="My Attendance - My Portal" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">My Attendance Logs</h2>
                    <p class="text-sm text-slate-500 mt-1">Review your daily time records and attendance status.</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 mb-6">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Date Range</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="filterForm.start_date" type="date" class="w-full rounded-lg border-slate-200 text-sm">
                                <input v-model="filterForm.end_date" type="date" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                        </div>
                        <button @click="applyFilters" class="bg-slate-800 hover:bg-slate-700 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center gap-2">
                            <FunnelIcon class="w-4 h-4" /> Filter
                        </button>
                        <a 
                            :href="route('portal.attendance.export', filterForm)" 
                            target="_blank"
                            class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center gap-2"
                        >
                            <ClockIcon class="w-4 h-4" /> Export DTR
                        </a>
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
                        title="Attendance History"
                        subtitle="Your recorded logs"
                    >
                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Date</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Time In</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Time Out</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Work Hrs</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Late / UT / OT</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Lock</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="log in data" :key="log.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                    {{ formatDisplayDate(log.date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center font-mono text-sm text-slate-600">
                                    {{ log.time_in ? new Date(log.time_in).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '--:--' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center font-mono text-sm text-slate-600">
                                    {{ log.time_out ? new Date(log.time_out).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '--:--' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center font-mono text-sm font-bold text-slate-700">
                                    {{ calculateWorkHours(log) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <div v-if="calculateLate(log) > 0" class="text-[10px] leading-none text-rose-600 font-bold bg-rose-50 px-1.5 py-1 rounded border border-rose-100 w-16 text-center">
                                            {{ formatHours(calculateLate(log)) }} Late
                                        </div>
                                        <div v-if="calculateUndertime(log) > 0" class="text-[10px] leading-none text-amber-600 font-bold bg-amber-50 px-1.5 py-1 rounded border border-amber-100 w-16 text-center">
                                            {{ formatHours(calculateUndertime(log)) }} UT
                                        </div>
                                        <div v-if="log.ot_minutes > 0" class="text-[10px] leading-none text-blue-600 font-bold bg-blue-50 px-1.5 py-1 rounded border border-blue-100 w-16 text-center">
                                            {{ formatHours(log.ot_minutes) }} OT
                                        </div>
                                        <div v-if="calculateLate(log) == 0 && calculateUndertime(log) == 0 && log.ot_minutes == 0" class="text-xs text-slate-400">-</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span :class="['px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wide border border-transparent', statusClass(calculateWorkHours(log) === '4.00' ? 'Half Day' : log.status)]">
                                            {{ calculateWorkHours(log) === '4.00' ? 'Half Day' : log.status }}
                                        </span>
                                        <div v-if="log.is_ob" class="flex items-center gap-1">
                                            <a v-if="log.in_location_url || log.out_location_url" :href="log.in_location_url || log.out_location_url" target="_blank" class="bg-indigo-50 text-indigo-600 p-1 rounded border border-indigo-100 hover:bg-indigo-100 transition-colors" title="View on Google Maps">
                                                <MapPinIcon class="w-4 h-4" />
                                            </a>
                                            <button @click="viewOBDetails(log)" class="text-slate-400 hover:text-indigo-600 transition-colors" title="View OB Photos">
                                                <CameraIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div v-if="log.is_locked" class="inline-flex items-center text-slate-400" title="Locked by Finalized Payroll">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div v-else class="text-xs text-slate-300 italic">-</div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- OB Details Modal -->
        <Modal :show="showOBModal" @close="showOBModal = false" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">Official Business Verification</h3>
                <button @click="showOBModal = false"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-6">
                    <!-- Time In OB -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Time In</h4>
                            <span class="text-xs font-mono font-bold text-slate-700">{{ obDetails?.time_in ? new Date(obDetails.time_in).toLocaleTimeString() : 'N/A' }}</span>
                        </div>
                        <div class="aspect-video bg-slate-100 rounded-xl overflow-hidden border border-slate-200 relative">
                            <img v-if="obDetails?.in_photo_path" :src="'/storage/' + obDetails.in_photo_path" class="w-full h-full object-cover">
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-400 text-xs">No photo</div>
                        </div>
                        <a v-if="obDetails?.in_location_url" :href="obDetails.in_location_url" target="_blank" class="flex items-center justify-center gap-2 w-full py-2 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-bold hover:bg-indigo-100 transition-colors">
                            <MapPinIcon class="w-4 h-4" /> View Map Location
                        </a>
                    </div>

                    <!-- Time Out OB -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Time Out</h4>
                            <span class="text-xs font-mono font-bold text-slate-700">{{ obDetails?.time_out ? new Date(obDetails.time_out).toLocaleTimeString() : 'N/A' }}</span>
                        </div>
                        <div class="aspect-video bg-slate-100 rounded-xl overflow-hidden border border-slate-200 relative">
                            <img v-if="obDetails?.out_photo_path" :src="'/storage/' + obDetails.out_photo_path" class="w-full h-full object-cover">
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-400 text-xs">No photo</div>
                        </div>
                        <a v-if="obDetails?.out_location_url" :href="obDetails.out_location_url" target="_blank" class="flex items-center justify-center gap-2 w-full py-2 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-bold hover:bg-indigo-100 transition-colors">
                            <MapPinIcon class="w-4 h-4" /> View Map Location
                        </a>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <div class="flex items-center gap-3">
                        <UserCircleIcon class="w-10 h-10 text-slate-400" />
                        <div>
                            <p class="text-sm font-bold text-slate-800">{{ obDetails?.employee?.user?.name }}</p>
                            <p class="text-xs text-slate-500">{{ formatDisplayDate(obDetails?.date) }} • {{ obDetails?.status }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
