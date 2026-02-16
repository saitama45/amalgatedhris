<script setup>
import { Head, useForm, router, Link } from '@inertiajs/vue3';
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
    PlusIcon, 
    TrashIcon,
    PencilSquareIcon,
    XMarkIcon,
    CheckCircleIcon,
    XCircleIcon,
    UserIcon,
    CalendarIcon,
    InformationCircleIcon,
    Cog6ToothIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    requests: Object,
    leaveTypes: Array,
    employees: Array,
    filters: Object
});

const { hasPermission } = usePermission();
const { showSuccess, showError } = useToast();
const { confirm, showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();

const pagination = usePagination(props.requests, 'leave-requests.index');

watch(() => props.requests, (newData) => {
    pagination.updateData(newData);
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    employee_id: '',
    leave_type_id: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
    reason: '',
});

// Autocomplete State
const employeeSearch = ref('');
const showEmployeeDropdown = ref(false);
const filteredEmployees = ref([]);
const isSelecting = ref(false);

const searchEmployees = () => {
    if (!employeeSearch.value) {
        filteredEmployees.value = props.employees.slice(0, 50);
        return;
    }
    
    const term = employeeSearch.value.toLowerCase();
    filteredEmployees.value = props.employees.filter(emp => 
        emp.name.toLowerCase().includes(term)
    ).slice(0, 50);
};

watch(employeeSearch, () => {
    if (!isSelecting.value) {
        form.employee_id = '';
    }
    searchEmployees();
    isSelecting.value = false;
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
    editingId.value = null;
    form.reset();
    employeeSearch.value = '';
    filteredEmployees.value = props.employees.slice(0, 50);
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

    isSelecting.value = true;
    employeeSearch.value = req.employee?.user?.name || '';
    
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('leave-requests.update', editingId.value), {
            onSuccess: () => {
                showModal.value = false;
            }
        });
    } else {
        form.post(route('leave-requests.store'), {
            onSuccess: () => {
                showModal.value = false;
            }
        });
    }
};

const approve = async (id) => {
    const isConfirmed = await confirm({
        title: 'Approve Leave',
        message: 'Are you sure you want to approve this leave request?',
        confirmButtonText: 'Approve'
    });

    if (isConfirmed) {
        router.put(route('leave-requests.approve', id), {}, {
            onError: () => showError('Failed to approve request.')
        });
    }
};

const reject = async (id) => {
    const isConfirmed = await confirm({
        title: 'Reject Leave',
        message: 'Are you sure you want to reject this leave request?',
        confirmButtonText: 'Reject'
    });

    if (isConfirmed) {
        router.put(route('leave-requests.reject', id), {}, {
            onError: () => showError('Failed to reject request.')
        });
    }
};

const deleteRequest = async (id) => {
    const isConfirmed = await confirm({
        title: 'Delete Leave Request',
        message: 'Are you sure you want to delete this request?',
        confirmButtonText: 'Delete'
    });

    if (isConfirmed) {
        router.delete(route('leave-requests.destroy', id), {
            onError: () => showError('Failed to delete request.')
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
    <Head title="Leave Management - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Leave Management</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage employee leave requests and balances.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Leave Requests"
                        subtitle="History of employee filings"
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
                            <div class="flex gap-2">
                                <Link 
                                    v-if="$page.props.auth.permissions.includes('payroll.settings')"
                                    :href="route('leave-types.index')" 
                                    class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-200 transition-all font-bold text-sm flex items-center border border-slate-200"
                                >
                                    <Cog6ToothIcon class="w-4 h-4 mr-2" /> Policy Setup
                                </Link>
                                <button v-if="hasPermission('leave_requests.create')" @click="openCreateModal" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20">
                                    <PlusIcon class="w-4 h-4 mr-2" /> File Leave
                                </button>
                            </div>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Leave Type</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Period</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="req in data" :key="req.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 border border-slate-200">
                                            <UserIcon class="w-4 h-4" />
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-bold text-slate-900">{{ req.employee?.user?.name }}</div>
                                            <div class="text-[10px] text-slate-500 uppercase font-semibold">ID: {{ req.employee?.employee_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg border border-blue-100">
                                        {{ req.leave_type?.name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-700 font-medium">
                                        {{ formatDate(req.start_date) }} - {{ formatDate(req.end_date) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 italic truncate max-w-xs">{{ req.reason }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border', statusClass(req.status)]">
                                        {{ req.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        <button v-if="hasPermission('leave_requests.approve') && req.status === 'Pending'" @click="approve(req.id)" class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Approve">
                                            <CheckCircleIcon class="w-5 h-5" />
                                        </button>
                                        <button v-if="hasPermission('leave_requests.reject') && req.status === 'Pending'" @click="reject(req.id)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Reject">
                                            <XCircleIcon class="w-5 h-5" />
                                        </button>
                                        <button v-if="req.status === 'Pending' && hasPermission('leave_requests.edit')" @click="openEditModal(req)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Edit">
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button v-if="req.status === 'Pending' && hasPermission('leave_requests.delete')" @click="deleteRequest(req.id)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-all" title="Delete">
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

        <!-- Modal -->
        <Modal :show="showModal" @close="showModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Leave Request' : 'File Leave Request' }}</h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submit" class="p-6 space-y-5">
                <div v-if="$page.props.auth.permissions.includes('leave_requests.approve') || $page.props.auth.permissions.includes('leave_requests.view')" class="relative">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Employee</label>
                    <input 
                        type="text" 
                        v-model="employeeSearch"
                        @focus="showEmployeeDropdown = true; searchEmployees()"
                        @blur="handleEmployeeSearchBlur"
                        placeholder="Type to search employee..."
                        class="w-full rounded-xl border-slate-200 text-sm bg-slate-50 px-4 py-2.5 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                        required
                        :disabled="isEditing"
                    >
                    <!-- Dropdown -->
                    <div v-if="showEmployeeDropdown && filteredEmployees.length > 0 && !isEditing" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto">
                        <ul class="py-1">
                            <li 
                                v-for="emp in filteredEmployees" 
                                :key="emp.id"
                                @mousedown.prevent="selectEmployee(emp)"
                                class="px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer flex items-center"
                            >
                                <UserIcon class="w-4 h-4 mr-2 text-slate-400" />
                                {{ emp.name }}
                            </li>
                        </ul>
                    </div>
                    <div v-else-if="showEmployeeDropdown && filteredEmployees.length === 0 && !isEditing" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl p-4 text-sm text-slate-500 text-center">
                        No employees found matching your search.
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Leave Type</label>
                    <select v-model="form.leave_type_id" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        <option value="" disabled>Select Type</option>
                        <option v-for="type in leaveTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
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
