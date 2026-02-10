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
    PlusIcon, 
    TrashIcon,
    PencilSquareIcon,
    XMarkIcon,
    CheckCircleIcon,
    XCircleIcon,
    UserIcon,
    CalendarIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    requests: Object,
    leaveTypes: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const employee = computed(() => page.props.auth.user.employee);

const { showSuccess, showError } = useToast();
const { confirm, showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();

const pagination = usePagination(props.requests, 'portal.leaves');

const filteredLeaveTypes = computed(() => {
    const gender = employee.value?.gender;
    return props.leaveTypes.filter(type => {
        const name = type.name.toLowerCase();
        if (gender === 'Male' && name.includes('maternity')) return false;
        if (gender === 'Female' && name.includes('paternity')) return false;
        return true;
    });
});

watch(() => props.requests, (newData) => {
    pagination.updateData(newData);
}, { deep: true });

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    employee_id: employee.value?.id,
    leave_type_id: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
    reason: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.employee_id = employee.value?.id;
    showModal.value = true;
};

const openEditModal = (req) => {
    isEditing.value = true;
    editingId.value = req.id;
    
    form.employee_id = req.employee_id;
    form.leave_type_id = req.leave_type_id;
    form.start_date = req.start_date.split('T')[0];
    form.end_date = req.end_date.split('T')[0];
    form.reason = req.reason;
    
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('portal.leaves.update', editingId.value), {
            onSuccess: () => {
                showModal.value = false;
            }
        });
    } else {
        form.post(route('portal.leaves.store'), {
            onSuccess: () => {
                showModal.value = false;
            }
        });
    }
};

const deleteRequest = async (id) => {
    const isConfirmed = await confirm({
        title: 'Cancel Leave Request',
        message: 'Are you sure you want to cancel this request?',
        confirmButtonText: 'Cancel Request'
    });

    if (isConfirmed) {
        router.delete(route('portal.leaves.destroy', id), {
            onError: () => showError('Failed to cancel request.')
        });
    }
};

const statusClass = (status) => {
    switch (status) {
        case 'Approved': return 'bg-emerald-100 text-emerald-700';
        case 'Rejected': return 'bg-rose-100 text-rose-700';
        default: return 'bg-amber-100 text-amber-700';
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<template>
    <Head title="My Leaves - My Portal" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">My Leave Requests</h2>
                    <p class="text-sm text-slate-500 mt-1">View and file your leave applications.</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="My Filing History"
                        subtitle="Detailed list of your leave requests"
                        :data="pagination.data.value"
                        :search="pagination.search.value"
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
                            <button @click="openCreateModal" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20">
                                <PlusIcon class="w-4 h-4 mr-2" /> File Leave Request
                            </button>
                        </template>
                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Leave Type</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Period</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Reason</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="req in data" :key="req.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg border border-blue-100">
                                            {{ req.leave_type?.name }}
                                        </span>
                                        <span v-if="req.processed" class="px-1.5 py-0.5 bg-slate-100 text-slate-600 text-[8px] font-bold rounded uppercase border border-slate-200">Processed</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-700 font-medium">
                                        {{ formatDate(req.start_date) }} - {{ formatDate(req.end_date) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-slate-500 line-clamp-1 max-w-xs">{{ req.reason }}</div>
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

        <!-- Modal -->
        <Modal :show="showModal" @close="showModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Leave Request' : 'File Leave Request' }}</h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submit" class="p-6 space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Leave Type</label>
                    <select v-model="form.leave_type_id" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        <option value="" disabled>Select Type</option>
                        <option v-for="type in filteredLeaveTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Start Date</label>
                        <input v-model="form.start_date" type="date" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">End Date</label>
                        <input v-model="form.end_date" type="date" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-bold">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Reason</label>
                    <textarea v-model="form.reason" rows="3" required placeholder="State your reason..." class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"></textarea>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-50">
                    <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors mr-3">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-8 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        {{ isEditing ? 'Update Filing' : 'Submit Filing' }}
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
