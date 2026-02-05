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
    DocumentDuplicateIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    documentTypes: Object,
    filters: Object,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingType = ref(null);

const { confirm } = useConfirm();
const { destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

const pagination = usePagination(props.documentTypes, 'document-types.index');

// Input Handlers
const handleAlphaInput = (formObj, field, e) => {
    // Allow only letters and spaces, remove emojis
    const val = e.target.value.replace(/[^a-zA-Z\s]/g, '').replace(/\p{Extended_Pictographic}/gu, '');
    formObj[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

onMounted(() => {
    pagination.updateData(props.documentTypes);
});

watch(() => props.documentTypes, (newTypes) => {
    pagination.updateData(newTypes);
}, { deep: true });

const form = useForm({
    name: '',
    is_required: false,
});

const openCreateModal = () => {
    isEditing.value = false;
    editingType.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (docType) => {
    isEditing.value = true;
    editingType.value = docType;
    form.name = docType.name;
    form.is_required = Boolean(docType.is_required);
    form.clearErrors();
    showModal.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('document-types.update', editingType.value.id), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Document Type updated successfully');
            },
            onError: (errors) => {
                 const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error';
                 showError(errorMessage);
            }
        });
    } else {
        form.post(route('document-types.store'), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Document Type added successfully');
            },
            onError: (errors) => {
                 const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error';
                 showError(errorMessage);
            }
        });
    }
};

const deleteType = async (docType) => {
    const confirmed = await confirm({
        title: 'Delete Document Type',
        message: `Are you sure you want to delete "${docType.name}"? This might affect existing employee records.`
    });
    
    if (confirmed) {
        destroy(route('document-types.destroy', docType.id), {
            onSuccess: () => showSuccess('Document Type deleted successfully'),
            onError: (errors) => showError('Failed to delete document type')
        });
    }
};
</script>

<template>
    <Head title="Document Types - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Document Requirements</h2>
                    <p class="text-sm text-slate-500 mt-1">Configure mandatory and optional documents for 201 files.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Document Checklist Items"
                        subtitle="Manage checklist requirements"
                        search-placeholder="Search document name..."
                        empty-message="No document types found."
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
                                v-if="hasPermission('document_types.create')"
                                @click="openCreateModal"
                                class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                            >
                                <PlusIcon class="w-5 h-5" />
                                <span>New Requirement</span>
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Name</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="docType in data" :key="docType.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 mr-4">
                                            <DocumentDuplicateIcon class="w-5 h-5" />
                                        </div>
                                        <div class="text-sm font-bold text-slate-900">
                                            {{ docType.name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                     <span v-if="docType.is_required" class="inline-flex px-2.5 py-1 text-xs font-bold rounded-lg bg-red-100 text-red-700 border border-red-200 uppercase tracking-wide">
                                        Required
                                    </span>
                                    <span v-else class="inline-flex px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 text-slate-600 border border-slate-200 uppercase tracking-wide">
                                        Optional
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                     <div class="flex justify-end space-x-1">
                                        <button
                                            v-if="hasPermission('document_types.edit')"
                                            @click="openEditModal(docType)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            v-if="hasPermission('document_types.delete')"
                                            @click="deleteType(docType)"
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
                    <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Document Type' : 'New Requirement' }}</h3>
                    <p class="text-sm text-slate-500">Define a document checklist item.</p>
                </div>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submitForm" class="p-6">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Document Name</label>
                        <input :value="form.name" @input="handleAlphaInput(form, 'name', $event)" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="e.g. Police Clearance">
                    </div>
                    <div class="flex items-center">
                         <input v-model="form.is_required" id="is_required" type="checkbox" class="w-5 h-5 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500 focus:ring-2 transition-all cursor-pointer">
                         <label for="is_required" class="ml-2 block text-sm font-bold text-slate-700 cursor-pointer">
                            Mark as Required
                            <span class="block text-xs text-slate-500 font-normal">This document is mandatory for all employees.</span>
                         </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                    <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        {{ isEditing ? 'Save Changes' : 'Add Requirement' }}
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
