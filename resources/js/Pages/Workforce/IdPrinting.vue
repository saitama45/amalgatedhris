<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { usePagination } from '@/Composables/usePagination';
import { useToast } from '@/Composables/useToast';
import { usePermission } from '@/Composables/usePermission';
import { 
    IdentificationIcon, 
    PlusIcon, 
    TrashIcon, 
    PrinterIcon,
    ArrowUpTrayIcon,
    XMarkIcon,
    UserCircleIcon,
    PhotoIcon,
    MagnifyingGlassIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    templates: Array,
    employees: Object,
    filters: Object,
});

const { hasPermission } = usePermission();
const { showSuccess, showError } = useToast();

const filterForm = ref({
    search: props.filters.search || '',
});

const pagination = usePagination(props.employees, 'id_printing.index', () => ({
    ...filterForm.value
}));

const applyFilters = () => {
    pagination.performSearch(route('id_printing.index'), filterForm.value);
};

watch(() => props.employees, (newData) => {
    pagination.updateData(newData);
});

// Template Upload Form
const showUploadModal = ref(false);
const uploadForm = useForm({
    name: '',
    front_image: null,
    back_image: null,
});

const submitTemplate = () => {
    uploadForm.post(route('id_printing.templates.store'), {
        onSuccess: () => {
            showUploadModal.value = false;
            uploadForm.reset();
            showSuccess('Template uploaded successfully.');
        }
    });
};

const deleteTemplate = (id) => {
    if (confirm('Are you sure you want to delete this template?')) {
        router.delete(route('id_printing.templates.destroy', id), {
            onSuccess: () => showSuccess('Template deleted.')
        });
    }
};

// Selection Logic
const selectedEmployees = ref([]);
const selectedTemplate = ref(null);
const pdfForm = ref(null);

const toggleEmployeeSelection = (employeeId) => {
    const index = selectedEmployees.value.indexOf(employeeId);
    if (index === -1) {
        selectedEmployees.value.push(employeeId);
    } else {
        selectedEmployees.value.splice(index, 1);
    }
};

const selectAll = (event) => {
    if (event.target.checked) {
        selectedEmployees.value = props.employees.data.map(e => e.id);
    } else {
        selectedEmployees.value = [];
    }
};

watch(() => props.templates, (newTemplates) => {
    if (newTemplates.length > 0 && !selectedTemplate.value) {
        selectedTemplate.value = newTemplates[0].id;
    }
}, { immediate: true });

const generateIDs = () => {
    if (selectedEmployees.value.length === 0) {
        showError('Please select at least one employee.');
        return;
    }
    if (!selectedTemplate.value) {
        showError('Please select an ID template.');
        return;
    }

    // Use the hidden form in the template for reliable submission
    if (pdfForm.value) {
        // Ensure CSRF is set
        const tokenInput = pdfForm.value.querySelector('input[name="_token"]');
        if (tokenInput) {
            tokenInput.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
        
        // Form is reactive to selectedEmployees and selectedTemplate
        // We just need to trigger submit
        pdfForm.value.submit();
    } else {
        // Fallback: Create form manually if ref fails
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('id_printing.generate');
        form.target = '_blank';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrf) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = csrf;
            form.appendChild(input);
        }

        selectedEmployees.value.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'employee_ids[]';
            input.value = id;
            form.appendChild(input);
        });

        const tempInput = document.createElement('input');
        tempInput.type = 'hidden';
        tempInput.name = 'template_id';
        tempInput.value = selectedTemplate.value;
        form.appendChild(tempInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
};
</script>

<template>
    <Head title="ID Layout & Printing - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">ID Layout & Printing</h2>
                    <p class="text-sm text-slate-500 mt-1">Design and print employee identification cards.</p>
                </div>
                <div v-if="hasPermission('id_printing.manage_templates')" class="mt-4 md:mt-0">
                    <button @click="showUploadModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20">
                        <PlusIcon class="w-4 h-4 mr-2" /> Upload Template
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Left: Template Selection -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                            <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <PhotoIcon class="w-5 h-5 text-blue-500" />
                                1. Select Template
                            </h3>
                            
                            <div v-if="templates.length > 0" class="space-y-3">
                                <div v-for="temp in templates" :key="temp.id" 
                                    @click="selectedTemplate = temp.id"
                                    :class="[
                                        'relative p-4 rounded-xl border-2 cursor-pointer transition-all group',
                                        selectedTemplate === temp.id ? 'border-blue-500 bg-blue-50' : 'border-slate-100 hover:border-slate-200 bg-slate-50'
                                    ]"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-bold text-sm" :class="selectedTemplate === temp.id ? 'text-blue-700' : 'text-slate-700'">{{ temp.name }}</p>
                                            <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest font-black">Standard 86x54mm</p>
                                        </div>
                                        <button v-if="hasPermission('id_printing.manage_templates')" @click.stop="deleteTemplate(temp.id)" class="p-1 text-slate-300 hover:text-red-500 transition-colors">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                    
                                    <div class="mt-3 flex gap-2 overflow-hidden">
                                        <div class="w-1/2 aspect-[1.58/1] rounded bg-white border border-slate-200 overflow-hidden relative">
                                            <img :src="'/storage/' + temp.front_image_path" class="w-full h-full object-cover">
                                            <div class="absolute top-1 left-1 bg-black/50 text-white text-[8px] px-1 rounded">FRONT</div>
                                        </div>
                                        <div v-if="temp.back_image_path" class="w-1/2 aspect-[1.58/1] rounded bg-white border border-slate-200 overflow-hidden relative">
                                            <img :src="'/storage/' + temp.back_image_path" class="w-full h-full object-cover">
                                            <div class="absolute top-1 left-1 bg-black/50 text-white text-[8px] px-1 rounded">BACK</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12 border-2 border-dashed border-slate-200 rounded-2xl">
                                <IdentificationIcon class="w-12 h-12 text-slate-200 mx-auto mb-2" />
                                <p class="text-xs text-slate-400">No templates found.<br>Upload one to get started.</p>
                            </div>
                        </div>

                        <div class="bg-blue-600 rounded-2xl shadow-xl shadow-blue-600/20 p-6 text-white overflow-hidden relative group">
                            <div class="relative z-10">
                                <h3 class="font-black text-lg uppercase tracking-tight">Print Queue</h3>
                                <p class="text-blue-100 text-xs mt-1">{{ selectedEmployees.length }} Employees Selected</p>
                                
                                <button @click="generateIDs" 
                                    class="w-full mt-6 bg-white text-blue-600 font-black py-3 rounded-xl hover:bg-blue-50 transition-all flex items-center justify-center gap-2 shadow-lg active:scale-95 disabled:opacity-50 disabled:grayscale"
                                    :disabled="selectedEmployees.length === 0 || !selectedTemplate"
                                >
                                    <PrinterIcon class="w-5 h-5" />
                                    GENERATE PDF
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Form for PDF Generation -->
                    <form ref="pdfForm" :action="route('id_printing.generate')" method="POST" target="_blank" class="hidden">
                        <input type="hidden" name="_token" value="">
                        <input v-for="id in selectedEmployees" :key="id" type="hidden" name="employee_ids[]" :value="id">
                        <input type="hidden" name="template_id" :value="selectedTemplate">
                    </form>

                    <!-- Right: Employee Selection -->
                    <div class="lg:col-span-2">
                        <!-- Search Bar -->
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-6 flex gap-4">
                            <div class="relative flex-1">
                                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                                <input 
                                    v-model="filterForm.search" 
                                    @keyup.enter="applyFilters"
                                    type="text" 
                                    placeholder="Search name or code..." 
                                    class="w-full pl-10 rounded-xl border-slate-200 text-sm focus:ring-blue-500"
                                >
                            </div>
                            <button @click="applyFilters" class="bg-slate-800 text-white px-6 py-2 rounded-xl font-bold text-sm hover:bg-slate-700 transition-all">
                                Search
                            </button>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                            <DataTable
                                title="2. Select Employees"
                                subtitle="Choose people to generate IDs for"
                                :show-search="false"
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
                                <template #header>
                                    <tr class="bg-slate-50">
                                        <th class="p-4 text-center border-b border-slate-100 w-12">
                                            <input type="checkbox" @change="selectAll" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Department</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                    </tr>
                                </template>

                                <template #body="{ data }">
                                    <tr v-for="emp in data" :key="emp.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0 cursor-pointer" @click="toggleEmployeeSelection(emp.id)">
                                        <td class="p-4 text-center">
                                            <input type="checkbox" :checked="selectedEmployees.includes(emp.id)" @change="toggleEmployeeSelection(emp.id)" @click.stop class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-8 w-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 border border-slate-200 overflow-hidden">
                                                    <img v-if="emp.profile_photo" :src="'/storage/' + emp.profile_photo" class="w-full h-full object-cover">
                                                    <UserCircleIcon v-else class="w-6 h-6" />
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-bold text-slate-900">{{ emp.user?.name }}</div>
                                                    <div class="text-[10px] text-slate-500 uppercase font-black tracking-tighter">{{ emp.employee_code }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-xs font-bold text-slate-600">{{ emp.active_employment_record?.department?.name || '-' }}</div>
                                            <div class="text-[10px] text-slate-400">{{ emp.active_employment_record?.position?.name || '-' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span v-if="emp.active_employment_record?.is_active" class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase rounded border border-emerald-100">Active</span>
                                            <span v-else class="px-2 py-0.5 bg-slate-50 text-slate-400 text-[10px] font-black uppercase rounded border border-slate-100">Inactive</span>
                                        </td>
                                    </tr>
                                </template>
                            </DataTable>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <Modal :show="showUploadModal" @close="showUploadModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">Add ID Template</h3>
                <button @click="showUploadModal = false"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
            </div>
            
            <form @submit.prevent="submitTemplate" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Template Name</label>
                    <input v-model="uploadForm.name" type="text" required placeholder="e.g. Regular Employee ID 2026" class="w-full rounded-xl border-slate-200 text-sm focus:ring-blue-500">
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Front Image (PNG/JPG)</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-200 border-dashed rounded-xl hover:border-blue-400 transition-colors bg-slate-50">
                            <div class="space-y-1 text-center">
                                <PhotoIcon class="mx-auto h-10 w-10 text-slate-300" />
                                <div class="flex text-sm text-slate-600">
                                    <label class="relative cursor-pointer bg-white rounded-md font-bold text-blue-600 hover:text-blue-500 px-2">
                                        <span>Upload front</span>
                                        <input type="file" @input="uploadForm.front_image = $event.target.files[0]" class="sr-only" required accept="image/*">
                                    </label>
                                </div>
                                <p class="text-[10px] text-slate-400">{{ uploadForm.front_image ? uploadForm.front_image.name : 'Max 2MB' }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Back Image (Optional)</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-200 border-dashed rounded-xl hover:border-blue-400 transition-colors bg-slate-50">
                            <div class="space-y-1 text-center">
                                <PhotoIcon class="mx-auto h-10 w-10 text-slate-300" />
                                <div class="flex text-sm text-slate-600">
                                    <label class="relative cursor-pointer bg-white rounded-md font-bold text-blue-600 hover:text-blue-500 px-2">
                                        <span>Upload back</span>
                                        <input type="file" @input="uploadForm.back_image = $event.target.files[0]" class="sr-only" accept="image/*">
                                    </label>
                                </div>
                                <p class="text-[10px] text-slate-400">{{ uploadForm.back_image ? uploadForm.back_image.name : 'Max 2MB' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" :disabled="uploadForm.processing" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold hover:bg-blue-700 transition-all flex items-center">
                        <span v-if="uploadForm.processing" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
                        Save Template
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
