<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { usePagination } from '@/Composables/usePagination.js';
import { useToast } from '@/Composables/useToast.js';
import { useConfirm } from '@/Composables/useConfirm.js';
import { 
    ClockIcon, 
    CheckCircleIcon, 
    XCircleIcon, 
    PlusIcon, 
    TrashIcon,
    CalendarIcon,
    UserIcon,
    PencilSquareIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    requests: Object,
    rates: Array,
});

const page = usePage();
const { showSuccess, showError } = useToast();
const { confirm, showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();

const pagination = usePagination(props.requests, 'portal.overtime');

watch(() => props.requests, (newData) => {
    pagination.updateData(newData);
}, { deep: true });

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
    form.date = req.date.substring(0, 10);
    form.start_time = req.start_time.substring(0, 5); 
    form.end_time = req.end_time.substring(0, 5);
    form.reason = req.reason;
    showCreateModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('portal.overtime.update', editingId.value), {
            onSuccess: () => {
                showCreateModal.value = false;
            },
            onError: () => showError('Failed to update request.')
        });
    } else {
        form.post(route('portal.overtime.store'), {
            onSuccess: () => {
                showCreateModal.value = false;
            },
            onError: () => showError('Failed to submit request.')
        });
    }
};

const deleteRequest = async (id) => {
    const isConfirmed = await confirm({
        title: 'Cancel OT Request',
        message: 'Are you sure you want to cancel this overtime request?',
        confirmButtonText: 'Cancel Request'
    });

    if (isConfirmed) {
        router.delete(route('portal.overtime.destroy', id), {
            onError: () => showError('Failed to cancel.')
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
    <Head title="My Overtime - My Portal" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">My Overtime Requests</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage your overtime filings and track approval status.</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="My OT Records"
                        subtitle="History of your overtime renderings"
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
                            <button @click="openCreateModal" class="bg-amber-500 text-white px-4 py-2 rounded-xl hover:bg-amber-600 transition-all font-bold text-sm flex items-center shadow-lg shadow-amber-500/20">
                                <PlusIcon class="w-4 h-4 mr-2" /> Request Overtime
                            </button>
                        </template>
                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Date</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Schedule</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Hours</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="req in data" :key="req.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex flex-col">
                                            <div class="flex items-center text-sm font-bold text-slate-900">
                                                <CalendarIcon class="w-4 h-4 mr-2 text-slate-400" />
                                                {{ formatDate(req.date) }}
                                            </div>
                                            <div v-if="req.processed" class="mt-1">
                                                <span class="px-1.5 py-0.5 bg-slate-100 text-slate-600 text-[8px] font-bold rounded uppercase border border-slate-200">Processed in Payroll</span>
                                            </div>
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
                                    <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border', statusClass(req.status)]">
                                        {{ req.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        <button v-if="req.status === 'Pending'" @click="openEditModal(req)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Edit">
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button v-if="req.status === 'Pending'" @click="deleteRequest(req.id)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Cancel">
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                        <span v-else class="text-xs text-slate-300 italic px-2">Processed</span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Create Request Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Overtime Request' : 'Request Overtime' }}</h3>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submit" class="p-6 space-y-5">
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
                        {{ isEditing ? 'Update Request' : 'Submit Request' }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmModal 
            :show="showConfirmModal" 
            :title="confirmTitle" 
            :message="confirmMessage" 
            @confirm="handleConfirm" 
            @cancel="handleCancel" 
        />

    </AppLayout>
</template>
