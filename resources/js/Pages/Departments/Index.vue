<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useErrorHandler } from '@/Composables/useErrorHandler';
import { useToast } from '@/Composables/useToast';
import { usePagination } from '@/Composables/usePagination';
import { 
    PlusIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    BuildingOfficeIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    departments: Object,
    filters: Object,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingDepartment = ref(null);

const { confirm } = useConfirm();
const { destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();

const pagination = usePagination(props.departments, 'departments.index');

onMounted(() => {
    pagination.updateData(props.departments);
});

watch(() => props.departments, (newDepartments) => {
    pagination.updateData(newDepartments);
}, { deep: true });

const form = useForm({
    name: '',
    department_code: '',
    oms_code: '',
    description: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    editingDepartment.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (department) => {
    isEditing.value = true;
    editingDepartment.value = department;
    form.name = department.name;
    form.department_code = department.department_code;
    form.oms_code = department.oms_code;
    form.description = department.description;
    form.clearErrors();
    showModal.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('departments.update', editingDepartment.value.id), {
            onSuccess: () => {
                showModal.value = false;
            },
            onError: (errors) => {
                 const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error';
                 showError(errorMessage);
            }
        });
    } else {
        form.post(route('departments.store'), {
            onSuccess: () => {
                showModal.value = false;
            },
            onError: (errors) => {
                 const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error';
                 showError(errorMessage);
            }
        });
    }
};

const deleteDepartment = async (department) => {
    const confirmed = await confirm({
        title: 'Delete Department',
        message: `Are you sure you want to delete ${department.name}?`
    });
    
    if (confirmed) {
        destroy(route('departments.destroy', department.id), {
            onError: (errors) => showError('Failed to delete department')
        });
    }
};
</script>

<template>
    <Head title="Departments - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Departments</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage company departments and organization units.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Department List"
                        subtitle="Active departments in the organization"
                        search-placeholder="Search department name..."
                        empty-message="No departments found."
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
                                @click="openCreateModal"
                                class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                            >
                                <PlusIcon class="w-5 h-5" />
                                <span>New Department</span>
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Name</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Code</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">OMS Code</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Description</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="department in data" :key="department.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 mr-4">
                                            <BuildingOfficeIcon class="w-5 h-5" />
                                        </div>
                                        <div class="text-sm font-bold text-slate-900">
                                            {{ department.name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-mono bg-slate-100 px-2 py-1 rounded text-slate-600">{{ department.department_code || '-' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-mono bg-slate-100 px-2 py-1 rounded text-slate-600">{{ department.oms_code || '-' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-normal">
                                    <div class="text-sm text-slate-600 max-w-md">{{ department.description || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                     <div class="flex justify-end space-x-1">
                                        <button
                                            @click="openEditModal(department)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit Department"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            @click="deleteDepartment(department)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Delete Department"
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
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
             <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>
             
             <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-xl font-bold text-slate-900">{{ isEditing ? 'Edit Department' : 'New Department' }}</h3>
                    <p class="text-sm text-slate-500">{{ isEditing ? 'Update department details.' : 'Add a new department.' }}</p>
                </div>
                
                <form @submit.prevent="submitForm" class="p-8">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Department Name</label>
                            <input v-model="form.name" @input="form.name = $event.target.value.toUpperCase()" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="e.g. HUMAN RESOURCES">
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Department Code</label>
                                <input v-model="form.department_code" @input="form.department_code = $event.target.value.toUpperCase()" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="e.g. HR">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">OMS Code</label>
                                <input v-model="form.oms_code" @input="form.oms_code = $event.target.value.toUpperCase()" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="e.g. 1001">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                            <textarea v-model="form.description" rows="3" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="Optional description..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                            {{ isEditing ? 'Save Changes' : 'Create Department' }}
                        </button>
                    </div>
                </form>
             </div>
        </div>
    </AppLayout>
</template>
