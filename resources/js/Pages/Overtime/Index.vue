<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { usePagination } from '@/Composables/usePagination.js';
import { usePermission } from '@/Composables/usePermission.js';
import { useToast } from '@/Composables/useToast.js';
import { useConfirm } from '@/Composables/useConfirm.js';
import { 
    ClockIcon, 
    CheckCircleIcon, 
    XCircleIcon, 
    PlusIcon, 
    TrashIcon,
    CalendarIcon,
    ChatBubbleBottomCenterTextIcon,
    BanknotesIcon,
    UserIcon,
    FunnelIcon,
    PencilSquareIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    requests: Object,
    rates: Array,
    filters: Object,
    can: Object
});

const { hasPermission } = usePermission();
const { showSuccess, showError } = useToast();
const { confirm, showConfirmModal, confirmTitle, confirmMessage, confirmButtonText, confirmVariant, handleConfirm, handleCancel } = useConfirm();

const filterForm = ref({
    status: props.filters.status || '',
});

const pagination = usePagination(props.requests, 'overtime.index', () => ({
    ...filterForm.value
}));

watch(() => props.requests, (newRequests) => {
    pagination.updateData(newRequests);
});

const applyFilters = () => {
    pagination.performSearch(route('overtime.index'), filterForm.value);
};

const showCreateModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const getToday = () => new Date().toISOString().split('T')[0];

const form = useForm({
    date: getToday(),
    start_time: '',
    end_time: '',
    reason: ''
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.date = getToday();
    showCreateModal.value = true;
};

const openEditModal = (req) => {
    isEditing.value = true;
    editingId.value = req.id;
    // Strictly extract YYYY-MM-DD from the serialized date string
    form.date = req.date.substring(0, 10);
    form.start_time = req.start_time.substring(0, 5); 
    form.end_time = req.end_time.substring(0, 5);
    form.reason = req.reason;
    showCreateModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('overtime.update', editingId.value), {
            onSuccess: () => {
                showCreateModal.value = false;
                form.reset();
            },
            onError: () => showError('Failed to update request.')
        });
    } else {
        form.post(route('overtime.store'), {
            onSuccess: () => {
                showCreateModal.value = false;
                form.reset();
            },
            onError: () => showError('Failed to submit request.')
        });
    }
};

const approve = async (id) => {
    const isConfirmed = await confirm({
        title: 'Approve Overtime',
        message: 'Are you sure you want to approve this request?',
        confirmButtonText: 'Approve',
        variant: 'success'
    });

    if (isConfirmed) {
        router.put(route('overtime.approve', id), {}, {
            onError: () => showError('Failed to approve request.')
        });
    }
};

const rejectReason = ref('');
const rejectingId = ref(null);
const showRejectModal = ref(false);

const openRejectModal = (id) => {
    rejectingId.value = id;
    showRejectModal.value = true;
};

const submitReject = () => {
    router.put(route('overtime.reject', rejectingId.value), {
        rejection_reason: rejectReason.value
    }, {
        onSuccess: () => {
            showRejectModal.value = false;
            rejectReason.value = '';
            rejectingId.value = null;
        },
        onError: () => showError('Failed to reject request.')
    });
};

const deleteRequest = async (id) => {
    const isConfirmed = await confirm({
        title: 'Delete OT Request',
        message: 'Are you sure you want to delete this overtime request? This cannot be undone.',
        confirmButtonText: 'Delete Request'
    });

    if (isConfirmed) {
        router.delete(route('overtime.destroy', id), {
            onError: () => showError('Failed to delete.')
        });
    }
};

const statusClass = (status) => {
    switch (status) {
        case 'Approved': return 'bg-emerald-100 text-emerald-700 border-emerald-200';
        case 'Rejected': return 'bg-rose-100 text-rose-700 border-rose-200';
        default: return 'bg-amber-100 text-amber-700 border-amber-200';
    }
};

const formatTime = (time) => {
    if (!time) return '--:--';
    // Handle both H:i:s and Date strings
    const parts = time.split(':');
    if (parts.length >= 2) {
        let h = parseInt(parts[0]);
        const m = parts[1];
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${m} ${ampm}`;
    }
    return time;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    // Prevent timezone shift by parsing parts manually
    const parts = dateStr.split('T')[0].split('-');
    const year = parseInt(parts[0]);
    const month = parseInt(parts[1]) - 1;
    const day = parseInt(parts[2]);
    
    const date = new Date(year, month, day);
    return date.toLocaleDateString('en-US', { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
};
</script>

<template>
    <Head title="Overtime Requests - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Overtime Management</h2>
                    <p class="text-sm text-slate-500 mt-1">Review and approve employee overtime requests with automatic computation.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 mb-6">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status Filter</label>
                            <select v-model="filterForm.status" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <button @click="applyFilters" class="bg-slate-800 hover:bg-slate-700 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center gap-2">
                            <FunnelIcon class="w-4 h-4" /> Filter
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="OT Computation Records"
                        subtitle="Request history and computations"
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
                    >
                        <template #actions>
                            <button v-if="can.create" @click="openCreateModal" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20">
                                <PlusIcon class="w-4 h-4 mr-2" /> New OT Request
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Schedule</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Hours</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Multiplier</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Est. Payable</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="req in data" :key="req.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center text-sm font-bold text-slate-900">
                                        <CalendarIcon class="w-4 h-4 mr-2 text-slate-400" />
                                        {{ formatDate(req.date) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 border border-slate-200">
                                            <UserIcon class="w-4 h-4" />
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-bold text-slate-900">{{ req.user?.name }}</div>
                                            <div class="text-[10px] text-slate-500 uppercase font-semibold">{{ req.user?.employee?.active_employment_record?.department?.name || 'N/A' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-xs font-mono text-slate-600 bg-slate-50 px-2 py-1 rounded border border-slate-100 inline-block">
                                        {{ formatTime(req.start_time) }} - {{ formatTime(req.end_time) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm font-bold text-slate-900">{{ parseFloat(req.hours_requested).toFixed(2) }} <span class="text-[10px] text-slate-400">HRS</span></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span v-if="req.multiplier" class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">
                                        x{{ req.multiplier }}
                                    </span>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div v-if="req.payable_amount" class="text-sm font-bold text-emerald-600 font-mono">
                                        ₱{{ parseFloat(req.payable_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                    </div>
                                    <div v-else class="text-slate-300 font-mono text-xs">--</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border', statusClass(req.status)]">
                                        {{ req.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        <button v-if="hasPermission('overtime.approve') && req.status === 'Pending'" @click="approve(req.id)" class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Approve">
                                            <CheckCircleIcon class="w-5 h-5" />
                                        </button>
                                        <button v-if="hasPermission('overtime.reject') && req.status === 'Pending'" @click="openRejectModal(req.id)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Reject">
                                            <XCircleIcon class="w-5 h-5" />
                                        </button>
                                        <button v-if="req.status === 'Pending' && hasPermission('overtime.edit')" @click="openEditModal(req)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Edit">
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button v-if="req.status !== 'Approved' && hasPermission('overtime.delete')" @click="deleteRequest(req.id)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-all" title="Delete">
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Confirm Modal -->
        <ConfirmModal 
            :show="showConfirmModal" 
            :title="confirmTitle" 
            :message="confirmMessage" 
            :confirm-button-text="confirmButtonText"
            :variant="confirmVariant"
            @confirm="handleConfirm" 
            @cancel="handleCancel" 
        />

        <!-- Create Request Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Overtime Request' : 'Request Overtime' }}</h3>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submit" class="p-6 space-y-5">
                <div v-if="!isEditing" class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex gap-3">
                    <ClockIcon class="w-6 h-6 text-blue-600 shrink-0" />
                    <div class="flex-1">
                        <h4 class="font-bold text-blue-900 text-sm">OT Rates Policy</h4>
                        <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                            <div v-for="rate in rates" :key="rate.id" class="flex justify-between text-[10px]">
                                <span class="text-blue-700 font-medium">{{ rate.name }}</span>
                                <span class="text-blue-900 font-bold">x{{ parseFloat(rate.rate).toFixed(2) }}</span>
                            </div>
                        </div>
                        <p class="text-[10px] text-blue-600 mt-2 italic border-t border-blue-200 pt-1">* Min. 1 hour required for rendering.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Overtime Date</label>
                    <input v-model="form.date" type="date" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-bold">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Start Time</label>
                        <input v-model="form.start_time" type="time" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">End Time</label>
                        <input v-model="form.end_time" type="time" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Purpose / Reason</label>
                    <textarea v-model="form.reason" rows="3" required placeholder="Describe the task to be performed..." class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"></textarea>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-50">
                    <button type="button" @click="showCreateModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors mr-3">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-8 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        Submit Request
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Reject Modal -->
        <Modal :show="showRejectModal" @close="showRejectModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-rose-100 bg-rose-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-rose-900">Reject Request</h3>
                <button @click="showRejectModal = false" class="text-rose-400 hover:text-rose-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Reason for Rejection</label>
                    <textarea v-model="rejectReason" rows="3" required placeholder="Please provide a reason..." class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-50">
                    <button @click="showRejectModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button @click="submitReject" class="px-8 py-2.5 bg-rose-600 text-white font-bold rounded-xl hover:bg-rose-700 shadow-lg shadow-rose-600/20 transition-all">
                        Confirm Reject
                    </button>
                </div>
            </div>
        </Modal>

    </AppLayout>
</template>