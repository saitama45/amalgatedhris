<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useErrorHandler } from '@/Composables/useErrorHandler';
import { useToast } from '@/Composables/useToast';
import { usePagination } from '@/Composables/usePagination';
import { usePermission } from '@/Composables/usePermission';
import { 
    PlusIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    ClockIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    shifts: Object,
    filters: Object,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingShift = ref(null);

const { confirm } = useConfirm();
const { destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

const pagination = usePagination(props.shifts, 'shifts.index');

onMounted(() => {
    pagination.updateData(props.shifts);
});

watch(() => props.shifts, (newShifts) => {
    pagination.updateData(newShifts);
}, { deep: true });

const form = useForm({
    name: '',
    start_time: '',
    end_time: '',
    break_minutes: 60,
    description: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    editingShift.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (shift) => {
    isEditing.value = true;
    editingShift.value = shift;
    form.name = shift.name;
    // Ensure time format is HH:MM
    form.start_time = shift.start_time ? shift.start_time.substring(0, 5) : '';
    form.end_time = shift.end_time ? shift.end_time.substring(0, 5) : '';
    form.break_minutes = shift.break_minutes;
    form.description = shift.description;
    form.clearErrors();
    showModal.value = true;
};

const submitForm = () => {
    // If the project doesn't support overnight shifts in this specific template, 
    // we block start >= end.
    if (form.start_time && form.end_time && form.start_time >= form.end_time) {
        showError('End Time must be later than Start Time. Overnight shifts are not supported in this template.');
        return;
    }

    if (isEditing.value) {
        form.put(route('shifts.update', editingShift.value.id), {
            onSuccess: () => {
                showModal.value = false;
            },
            onError: () => showError('Failed to update shift')
        });
    } else {
        form.post(route('shifts.store'), {
            onSuccess: () => {
                showModal.value = false;
            },
            onError: () => showError('Failed to create shift')
        });
    }
};

const deleteShift = async (shift) => {
    const confirmed = await confirm({
        title: 'Delete Shift Template',
        message: `Are you sure you want to delete "${shift.name}"?`
    });
    
    if (confirmed) {
        destroy(route('shifts.destroy', shift.id), {
            onError: () => showError('Failed to delete shift')
        });
    }
};
</script>

<template>
    <Head title="Shift Templates - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Shift Templates</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage standard work schedules.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Shift List"
                        subtitle="Available shift patterns"
                        search-placeholder="Search shift name..."
                        empty-message="No shifts found."
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
                            <button
                                v-if="hasPermission('shifts.create')"
                                @click="openCreateModal"
                                class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                            >
                                <PlusIcon class="w-5 h-5" />
                                <span>New Shift</span>
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Shift Name</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Schedule</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Break</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="shift in data" :key="shift.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 mr-4">
                                            <ClockIcon class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-slate-900">{{ shift.name }}</div>
                                            <div class="text-xs text-slate-500">{{ shift.description || 'No description' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-mono bg-slate-100 px-2 py-1 rounded text-slate-700 border border-slate-200">
                                        {{ shift.start_time?.substring(0,5) }} - {{ shift.end_time?.substring(0,5) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-slate-600">{{ shift.break_minutes }} mins</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                     <div class="flex justify-end space-x-1">
                                        <button
                                            v-if="hasPermission('shifts.edit')"
                                            @click="openEditModal(shift)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            v-if="hasPermission('shifts.delete')"
                                            @click="deleteShift(shift)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Delete"
                                        >
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
        
        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Shift Template' : 'New Shift Template' }}</h3>
                </div>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submitForm" class="p-6">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Shift Name</label>
                        <input v-model="form.name" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="e.g. Regular Day">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Start Time</label>
                            <input v-model="form.start_time" type="time" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">End Time</label>
                            <input v-model="form.end_time" type="time" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Break Duration (Minutes)</label>
                        <input v-model="form.break_minutes" type="number" min="0" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                        <textarea v-model="form.description" rows="2" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                    <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        {{ isEditing ? 'Save Changes' : 'Create Template' }}
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
