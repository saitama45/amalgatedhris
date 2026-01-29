<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { usePagination } from '@/Composables/usePagination';
import { useToast } from '@/Composables/useToast';
import { usePermission } from '@/Composables/usePermission';
import { 
    UserIcon, 
    IdentificationIcon, 
    BuildingOffice2Icon,
    PencilSquareIcon,
    DocumentDuplicateIcon,
    UserMinusIcon,
    XMarkIcon,
    CheckCircleIcon,
    ArrowUpTrayIcon,
    EyeIcon,
    MagnifyingGlassPlusIcon,
    MagnifyingGlassMinusIcon,
    ArrowPathIcon,
    BanknotesIcon,
    TrashIcon,
    PlusIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    employees: Object,
    filters: Object,
    options: Object, // Contains document_types
});

const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();
const pagination = usePagination(props.employees, 'employees.index');

onMounted(() => {
    pagination.updateData(props.employees);
});

watch(() => props.employees, (newEmployees) => {
    pagination.updateData(newEmployees);
    
    // Update selected employee reference for modal if open
    if (salaryEmployee.value) {
        const updated = newEmployees.data.find(e => e.id === salaryEmployee.value.id);
        if (updated) {
            salaryEmployee.value = updated;
        }
    }
}, { deep: true });

// --- Salary History State & Logic ---
const showSalaryModal = ref(false);
const salaryEmployee = ref(null);
const salaryHistory = ref([]);
const isLoadingSalary = ref(false);
const isEditingSalary = ref(false);
const editingSalaryItem = ref(null);

const canShowSalaryForm = computed(() => {
    if (isEditingSalary.value) {
        return hasPermission('employees.edit_salary');
    }
    return hasPermission('employees.create_salary');
});

const salaryForm = useForm({
    basic_rate: '',
    allowance: 0,
    position_id: '',
    company_id: '',
    effective_date: new Date().toISOString().split('T')[0],
});

const openSalaryModal = (employee) => {
    salaryEmployee.value = employee;
    resetSalaryForm();
    
    // Set current position and company as default
    if (employee.active_employment_record) {
        salaryForm.position_id = employee.active_employment_record.position_id;
        salaryForm.company_id = employee.active_employment_record.company_id;
    }
    
    showSalaryModal.value = true;
    fetchSalaryHistory(employee.id);
};

const resetSalaryForm = () => {
    isEditingSalary.value = false;
    editingSalaryItem.value = null;
    salaryForm.reset();
    salaryForm.clearErrors();
};

const editSalaryItem = (item) => {
    isEditingSalary.value = true;
    editingSalaryItem.value = item;
    
    salaryForm.basic_rate = item.basic_rate;
    salaryForm.allowance = item.allowance;
    // For editing existing history, we typically don't change the position linked to that record directly here easily without complex logic, 
    // so we might disable position edit or just show it. 
    // But user asked to edit salary rate specifically.
    // The position is tied to the employment_record, not just the salary_history row directly (it's a relationship).
    // So for this "Edit Rate" feature, we will focus on rate and date.
    
    // We'll set the position_id to the one in the record for display/consistency, 
    // but we might disable changing it if it implies a different employment record logic.
    // Let's allow simple rate updates.
    salaryForm.position_id = item.employment_record?.position_id; 
    salaryForm.company_id = item.employment_record?.company_id;
    
    // Backend now returns YYYY-MM-DD, so we can use it directly
    salaryForm.effective_date = item.effective_date;
};

const fetchSalaryHistory = (employeeId) => {
    isLoadingSalary.value = true;
    axios.get(route('employees.salary.index', employeeId))
        .then(response => {
            // New structure: { history: [], current_record: {} }
            const historyData = response.data.history;
            salaryHistory.value = historyData;

            // Update the local salaryEmployee's active record to reflect any backend repairs/changes
            if (response.data.current_record && salaryEmployee.value) {
                salaryEmployee.value.active_employment_record = response.data.current_record;
            }

            // Set current rates in form as default for new record if NOT editing
            if (!isEditingSalary.value && historyData.length > 0) {
                salaryForm.basic_rate = historyData[0].basic_rate;
                salaryForm.allowance = historyData[0].allowance;
            }
        })
        .finally(() => isLoadingSalary.value = false);
};

const submitSalary = () => {
    if (isEditingSalary.value && editingSalaryItem.value) {
        // Update existing record
        salaryForm.put(route('salary-history.update', editingSalaryItem.value.id), {
            onSuccess: () => {
                showSuccess('Salary record updated successfully');
                resetSalaryForm();
                fetchSalaryHistory(salaryEmployee.value.id);
            },
            onError: (errors) => {
                // If it's a flash error (business logic like payroll check), it might not be in 'errors' object 
                // depending on how useErrorHandler handles it. 
                // But standard Inertia errors bag works.
                // However, we returned ->with('error', msg). We need to check page props for that or standard error bag.
                // Usually controller validation errors land here. Business logic errors via 'with' land in page.props.flash.
                // Let's assume standard error toast helper handles flash.
                showError('Failed to update salary record.');
            }
        });
    } else {
        // Add new record
        salaryForm.post(route('employees.salary.store', salaryEmployee.value.id), {
            onSuccess: () => {
                showSuccess('Salary rate added successfully');
                fetchSalaryHistory(salaryEmployee.value.id);
                // Keep form populated with new latest for convenience, or reset?
                // Resetting to "Add Mode" is safer.
                resetSalaryForm();
                // But re-populate position
                if (salaryEmployee.value?.active_employment_record) {
                    salaryForm.position_id = salaryEmployee.value.active_employment_record.position_id;
                }
            },
            onError: () => showError('Failed to add salary rate.')
        });
    }
};

const deleteSalaryItem = (item) => {
    if (!confirm('Are you sure you want to delete this salary record? This action cannot be undone.')) return;

    router.delete(route('salary-history.destroy', item.id), {
        onSuccess: () => {
            showSuccess('Salary record deleted successfully');
            fetchSalaryHistory(salaryEmployee.value.id);
            if (editingSalaryItem.value?.id === item.id) {
                resetSalaryForm();
            }
        },
        onError: () => showError('Failed to delete salary record.')
    });
};

// --- File Preview State & Logic ---
const showPreviewModal = ref(false);
const previewDoc = ref(null);
const previewUrl = ref('');
const previewType = ref('');

// Zoom & Pan State
const scale = ref(1);
const translateX = ref(0);
const translateY = ref(0);
const isDragging = ref(false);
const startX = ref(0);
const startY = ref(0);

const openPreview = (doc) => {
    if (!doc) return;
    previewDoc.value = doc;
    previewUrl.value = `/${doc.file_path}`;
    
    // Reset Zoom/Pan
    scale.value = 1;
    translateX.value = 0;
    translateY.value = 0;

    const extension = doc.file_path.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
        previewType.value = 'image';
    } else if (extension === 'pdf') {
        previewType.value = 'pdf';
    } else {
        previewType.value = 'other'; 
    }
    
    showPreviewModal.value = true;
};

const closePreview = () => {
    showPreviewModal.value = false;
    previewDoc.value = null;
    previewUrl.value = '';
    previewType.value = '';
};

// Image Controls
const zoomIn = () => {
    scale.value += 0.15;
};

const zoomOut = () => {
    if (scale.value > 0.2) scale.value -= 0.15;
};

const resetZoom = () => {
    scale.value = 1;
    translateX.value = 0;
    translateY.value = 0;
};

const onWheel = (e) => {
    if (previewType.value !== 'image') return;
    e.preventDefault();
    if (e.deltaY < 0) zoomIn();
    else zoomOut();
};

const startDrag = (e) => {
    if (previewType.value !== 'image' || scale.value <= 1) return; // Only drag if zoomed in or just generally allow
    isDragging.value = true;
    startX.value = e.clientX - translateX.value;
    startY.value = e.clientY - translateY.value;
};

const onDrag = (e) => {
    if (!isDragging.value) return;
    e.preventDefault();
    translateX.value = e.clientX - startX.value;
    translateY.value = e.clientY - startY.value;
};

const stopDrag = () => {
    isDragging.value = false;
};

// --- Edit Profile State & Logic ---
const showEditModal = ref(false);
const editingEmployee = ref(null);
const editForm = useForm({
    civil_status: '',
    gender: '',
    birthday: '',
    address: '',
    emergency_contact: '',
    emergency_contact_relationship: '',
    emergency_contact_number: '',
    sss_no: '',
    philhealth_no: '',
    pagibig_no: '',
    tin_no: '',
    employment_status: '', // Added
});

const openEditModal = (employee) => {
    editingEmployee.value = employee;
    editForm.civil_status = employee.civil_status || '';
    editForm.gender = employee.gender || '';
    editForm.birthday = employee.birthday ? employee.birthday.split('T')[0] : '';
    editForm.address = employee.address || '';
    editForm.emergency_contact = employee.emergency_contact || '';
    editForm.emergency_contact_relationship = employee.emergency_contact_relationship || '';
    editForm.emergency_contact_number = employee.emergency_contact_number || '';
    editForm.sss_no = employee.sss_no || '';
    editForm.philhealth_no = employee.philhealth_no || '';
    editForm.pagibig_no = employee.pagibig_no || '';
    editForm.tin_no = employee.tin_no || '';
    editForm.employment_status = employee.active_employment_record?.employment_status || ''; // Added
    editForm.clearErrors();
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('employees.update', editingEmployee.value.id), {
        onSuccess: () => {
            showEditModal.value = false;
            showSuccess('Profile updated successfully');
        },
        onError: () => showError('Failed to update profile. Please check inputs.')
    });
};

// --- Document Checklist Logic ---
const showDocsModal = ref(false);
const docsEmployeeId = ref(null);
const fileInputs = ref({}); // Store references to file inputs
const stagedFiles = ref({}); // Store selected but not uploaded files: { docTypeId: File }
const uploadingDocId = ref(null); // Track which doc is currently uploading
const localDocuments = ref([]); // Local copy of documents for the modal
const isLoadingDocs = ref(false); // Loading state for fetching documents

const activeDocsEmployee = computed(() => {
    if (!docsEmployeeId.value || !props.employees.data) return null;
    return props.employees.data.find(e => e.id === docsEmployeeId.value);
});

const fetchDocuments = (employeeId) => {
    isLoadingDocs.value = true;
    axios.get(route('employees.documents.list', employeeId))
        .then(response => {
            localDocuments.value = response.data;
        })
        .catch(error => {
            console.error('Failed to fetch documents', error);
            showError('Could not load current documents.');
        })
        .finally(() => {
            isLoadingDocs.value = false;
        });
};

const openDocsModal = (employee) => {
    docsEmployeeId.value = employee.id;
    stagedFiles.value = {}; 
    uploadingDocId.value = null;
    localDocuments.value = []; // Clear previous
    showDocsModal.value = true;
    
    // Fetch fresh data independently of props
    fetchDocuments(employee.id);
};

const triggerFileInput = (docTypeId) => {
    const input = fileInputs.value[docTypeId];
    if (input) input.click();
};

const handleFileSelect = (event, docTypeId) => {
    const file = event.target.files[0];
    if (!file) return;

    // 50MB Validation (50 * 1024 * 1024 bytes)
    const maxSize = 50 * 1024 * 1024;
    if (file.size > maxSize) {
        showError('File is too large. Maximum allowed size is 50MB.');
        event.target.value = ''; // Reset input
        return;
    }
    
    // Stage the file
    stagedFiles.value[docTypeId] = file;
    
    // Reset input value so selecting the same file triggers change again if needed
    event.target.value = ''; 
};

const cancelStage = (docTypeId) => {
    delete stagedFiles.value[docTypeId];
};

const saveDocument = (docTypeId) => {
    const file = stagedFiles.value[docTypeId];
    if (!file) return;

    uploadingDocId.value = docTypeId;

    const formData = new FormData();
    formData.append('document_type_id', docTypeId);
    formData.append('file', file);

    router.post(route('employees.upload-document', docsEmployeeId.value), formData, {
        onSuccess: () => {
            showSuccess('Document uploaded successfully');
            delete stagedFiles.value[docTypeId]; 
            // Fetch fresh documents immediately
            fetchDocuments(docsEmployeeId.value);
        },
        onError: (errors) => {
             const errorMessage = Object.values(errors).flat().join(', ') || 'Failed to upload document.';
             showError(errorMessage);
        },
        onFinish: () => {
            uploadingDocId.value = null;
        },
        forceFormData: true,
        preserveScroll: true,
        preserveState: true, // Keep modal state intact
    });
};

const getEmployeeDoc = (docTypeId) => {
    if (!localDocuments.value) return null;
    // Ensure strict type comparison safety, although usually IDs are numbers
    return localDocuments.value.find(d => d.document_type_id == docTypeId);
};

// --- Resignation State & Logic ---
const showResignModal = ref(false);
const resigningEmployee = ref(null);
const resignForm = useForm({
    end_date: '',
    reason: '',
});

const openResignModal = (employee) => {
    resigningEmployee.value = employee;
    resignForm.reset();
    resignForm.clearErrors();
    showResignModal.value = true;
};

const submitResign = () => {
    if (!confirm('Are you sure you want to mark this employee as Resigned? This action usually cannot be undone easily.')) return;
    
    resignForm.put(route('employees.resign', resigningEmployee.value.id), {
        onSuccess: () => {
            showResignModal.value = false;
            showSuccess('Employee status updated to Resigned');
        },
        onError: () => showError('Failed to update status.')
    });
};
</script>

<template>
    <Head title="Employee Directory - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Employee Directory</h2>
                    <p class="text-sm text-slate-500 mt-1">Official list of active employees and their designations.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Workforce 201"
                        subtitle="Complete employee roster"
                        search-placeholder="Search name, code, or email..."
                        empty-message="No employees found."
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
                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">ID & Contact</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Position</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Department</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="employee in data" :key="employee.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 border border-slate-200">
                                            <UserIcon class="w-5 h-5" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-bold text-slate-900">{{ employee.user?.name || 'N/A' }}</div>
                                            <div class="text-xs text-slate-500">Joined: {{ new Date(employee.created_at).toLocaleDateString() }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col space-y-1">
                                        <div class="flex items-center text-sm text-slate-600 font-mono">
                                            <IdentificationIcon class="w-4 h-4 mr-2 text-slate-400" />
                                            {{ employee.employee_code }}
                                        </div>
                                        <div class="text-xs text-slate-500 ml-6">{{ employee.user?.email }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-slate-700">
                                        {{ employee.active_employment_record?.position?.name || 'No Position' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center text-sm text-slate-600">
                                        <BuildingOffice2Icon class="w-4 h-4 mr-2 text-slate-400" />
                                        {{ employee.active_employment_record?.department?.name || 'Unassigned' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-1">
                                        <button 
                                            v-if="hasPermission('employees.edit')"
                                            @click="openEditModal(employee)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit Profile"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            v-if="hasPermission('employees.edit_documents')"
                                            @click="openDocsModal(employee)"
                                            class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all"
                                            title="Manage Documents"
                                        >
                                            <DocumentDuplicateIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            v-if="hasPermission('employees.view_salary')"
                                            @click="openSalaryModal(employee)"
                                            class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all"
                                            title="Salary History"
                                        >
                                            <BanknotesIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            v-if="employee.active_employment_record?.is_active && hasPermission('employees.edit')"
                                            @click="openResignModal(employee)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Mark as Resigned"
                                        >
                                            <UserMinusIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Edit Profile Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Edit Employee Profile</h3>
                    <p class="text-sm text-slate-500">Update personal information for {{ editingEmployee?.user?.name }}</p>
                </div>
                <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submitEdit" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Civil Status</label>
                        <select v-model="editForm.civil_status" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            <option value="" disabled>Select Status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Gender</label>
                        <select v-model="editForm.gender" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            <option value="" disabled>Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Employment Status</label>
                        <select v-model="editForm.employment_status" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-bold text-blue-600">
                            <option value="" disabled>Select Status</option>
                            <option value="Probationary">Probationary</option>
                            <option value="Regular">Regular</option>
                            <option value="Consultant">Consultant</option>
                            <option value="Project-Based">Project-Based</option>
                            <option value="Casual">Casual</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Birthday</label>
                        <input v-model="editForm.birthday" type="date" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>
                     <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">TIN</label>
                        <input v-model="editForm.tin_no" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>
                </div>

                 <div class="mb-6">
                    <label class="block text-sm font-bold text-slate-700 mb-1">Complete Address</label>
                    <textarea v-model="editForm.address" rows="2" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"></textarea>
                </div>

                <div class="border-t border-slate-100 pt-6 mb-6">
                    <h4 class="font-bold text-slate-800 mb-4">Emergency Contact</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Contact Person</label>
                            <input v-model="editForm.emergency_contact" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                         <div class="md:col-span-1">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Relationship</label>
                            <input v-model="editForm.emergency_contact_relationship" type="text" placeholder="e.g. Spouse, Parent" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Contact Number</label>
                            <input v-model="editForm.emergency_contact_number" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6 mt-6">
                    <h4 class="font-bold text-slate-800 mb-4">Government Numbers</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">SSS No.</label>
                            <input v-model="editForm.sss_no" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">PhilHealth No.</label>
                            <input v-model="editForm.philhealth_no" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Pag-IBIG No.</label>
                            <input v-model="editForm.pagibig_no" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                    <button type="button" @click="showEditModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        Save Changes
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Documents Modal -->
        <Modal :show="showDocsModal" @close="showDocsModal = false" maxWidth="3xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                 <div>
                    <h3 class="text-lg font-bold text-slate-900">Requirements Checklist</h3>
                    <p class="text-sm text-slate-500">Manage 201 file documents for {{ activeDocsEmployee?.user?.name }}</p>
                </div>
                <button @click="showDocsModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <!-- Loading State -->
                <div v-if="isLoadingDocs" class="flex justify-center items-center py-12">
                     <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <div v-else class="overflow-hidden bg-white border border-slate-200 rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-10">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Requirement</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-for="docType in options?.document_types" :key="docType.id" class="hover:bg-slate-50 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap align-top w-12">
                                    <div v-if="getEmployeeDoc(docType.id)" class="text-emerald-500 bg-emerald-50 rounded-full p-1 w-fit">
                                        <CheckCircleIcon class="w-5 h-5" />
                                    </div>
                                    <div v-else class="w-7 h-7 border-2 border-slate-200 rounded-full mx-auto bg-slate-50"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-normal align-top">
                                    <div class="flex flex-col">
                                        <div class="flex items-center mb-1">
                                            <span class="text-sm font-bold text-slate-800">{{ docType.name }}</span>
                                            <span v-if="docType.is_required" class="ml-2 px-2 py-0.5 text-[10px] font-bold bg-rose-50 text-rose-600 rounded-full border border-rose-100 uppercase tracking-wide">Required</span>
                                        </div>
                                        
                                        <!-- Staged File Info -->
                                        <div v-if="stagedFiles[docType.id]" class="text-xs text-blue-600 font-semibold bg-blue-50 px-2 py-1.5 rounded-md border border-blue-100 flex items-center w-fit animate-in fade-in zoom-in duration-200">
                                            <DocumentDuplicateIcon class="w-4 h-4 mr-1.5" />
                                            <span class="truncate max-w-[200px]">{{ stagedFiles[docType.id].name }}</span>
                                        </div>

                                        <!-- Uploaded File Info -->
                                        <div v-else-if="getEmployeeDoc(docType.id)" class="flex items-center space-x-2 text-xs text-slate-500">
                                            <span class="bg-slate-100 px-2 py-0.5 rounded text-slate-600 font-mono">
                                                {{ getEmployeeDoc(docType.id).file_path.split('.').pop().toUpperCase() }}
                                            </span>
                                            <span>• Uploaded {{ new Date(getEmployeeDoc(docType.id).created_at).toLocaleDateString() }}</span>
                                        </div>
                                        
                                        <div v-else class="text-xs text-slate-400 italic">
                                            Not uploaded yet.
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium align-middle">
                                    <!-- Saving State -->
                                    <div v-if="uploadingDocId === docType.id" class="flex items-center justify-end text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-xs font-bold">Uploading...</span>
                                    </div>

                                    <!-- Action Buttons: Save / Cancel -->
                                    <div v-else-if="stagedFiles[docType.id]" class="flex items-center justify-end space-x-2">
                                        <button @click="cancelStage(docType.id)" class="text-slate-500 hover:text-slate-700 text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                                            Cancel
                                        </button>
                                        <button @click="saveDocument(docType.id)" class="bg-blue-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-all shadow-sm shadow-blue-500/30 flex items-center">
                                            <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
                                            Save File
                                        </button>
                                    </div>

                                    <!-- Action Buttons: Preview / Replace -->
                                    <div v-else-if="getEmployeeDoc(docType.id)" class="flex items-center justify-end space-x-3">
                                        <button 
                                            @click="triggerFileInput(docType.id)" 
                                            class="text-slate-400 hover:text-blue-600 text-xs font-semibold underline decoration-slate-300 hover:decoration-blue-400 underline-offset-2 transition-all"
                                        >
                                            Replace
                                        </button>
                                        <button 
                                            @click="openPreview(getEmployeeDoc(docType.id))" 
                                            class="flex items-center text-slate-700 hover:text-blue-600 font-bold text-xs bg-white border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 hover:border-blue-200 transition-all shadow-sm group-hover:shadow-md"
                                        >
                                            <EyeIcon class="w-4 h-4 mr-1.5" />
                                            Preview
                                        </button>
                                    </div>

                                    <!-- Action Button: Select File -->
                                    <div v-else>
                                        <button @click="triggerFileInput(docType.id)" class="flex items-center ml-auto text-slate-600 hover:text-blue-600 font-bold text-xs bg-slate-50 px-3 py-1.5 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition-all border border-slate-200 hover:border-blue-200 shadow-sm">
                                            <ArrowUpTrayIcon class="w-4 h-4 mr-1.5" />
                                            Select File
                                        </button>
                                    </div>

                                    <!-- Hidden File Input -->
                                    <input 
                                        type="file" 
                                        :ref="el => { if (el) fileInputs[docType.id] = el }" 
                                        class="hidden" 
                                        @change="handleFileSelect($event, docType.id)"
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>

        <!-- Resignation Modal -->
         <Modal :show="showResignModal" @close="showResignModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-red-100 bg-red-50 flex justify-between items-center">
                 <div>
                    <h3 class="text-lg font-bold text-red-800">Process Resignation</h3>
                </div>
                <button @click="showResignModal = false" class="text-red-400 hover:text-red-600 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submitResign" class="p-6">
                <p class="text-sm text-slate-600 mb-6">You are marking <strong>{{ resigningEmployee?.user?.name }}</strong> as resigned. This will deactivate their current employment record.</p>
                
                <div class="space-y-4">
                     <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Last Day of Work (Effectivity)</label>
                        <input v-model="resignForm.end_date" type="date" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                    </div>
                     <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Reason for Leaving</label>
                        <textarea v-model="resignForm.reason" rows="3" required placeholder="State reason..." class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 mt-6">
                    <button type="button" @click="showResignModal = false" class="px-4 py-2 text-slate-600 font-bold bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="resignForm.processing" class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 shadow-lg shadow-red-600/20 disabled:opacity-50 transition-all">
                        Confirm Resignation
                    </button>
                </div>
            </form>
         </Modal>

        <!-- Salary History Modal -->
        <Modal :show="showSalaryModal" @close="showSalaryModal = false" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Salary & Compensation History</h3>
                    <p class="text-sm text-slate-500">Manage rates for {{ salaryEmployee?.user?.name }}</p>
                </div>
                <button @click="showSalaryModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <!-- Current Status Summary -->
                <div class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Current Assignment</p>
                        <div class="text-sm font-bold text-slate-900">{{ salaryEmployee?.active_employment_record?.position?.name || 'No Position' }}</div>
                        <div class="text-xs text-slate-500">{{ salaryEmployee?.active_employment_record?.department?.name }}</div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Current Total Rate</p>
                        <div class="text-lg font-mono font-bold text-emerald-600">
                            {{ salaryHistory.length > 0 ? (parseFloat(salaryHistory[0].basic_rate) + parseFloat(salaryHistory[0].allowance)).toLocaleString(undefined, {minimumFractionDigits: 2}) : '0.00' }}
                        </div>
                    </div>
                </div>

                <!-- Add New Entry Form -->
                <form v-if="canShowSalaryForm" @submit.prevent="submitSalary" class="mb-8 bg-white p-6 rounded-2xl border-2 border-emerald-100 shadow-sm transition-all" :class="{'ring-2 ring-amber-200 border-amber-300': isEditingSalary}">
                    <h4 class="font-bold mb-4 text-sm flex items-center justify-between" :class="isEditingSalary ? 'text-amber-800' : 'text-slate-900'">
                        <div class="flex items-center">
                            <div class="p-1.5 rounded-lg mr-3" :class="isEditingSalary ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'">
                                <component :is="isEditingSalary ? PencilSquareIcon : PlusIcon" class="w-4 h-4" />
                            </div>
                            {{ isEditingSalary ? 'Edit Salary Record' : 'Add New Rate / Job Movement' }}
                        </div>
                        <button v-if="isEditingSalary" @click.prevent="resetSalaryForm" class="text-xs text-slate-400 hover:text-slate-600 underline">Cancel Edit</button>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="md:col-span-2">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Company (Entity)</label>
                                    <select v-model="salaryForm.company_id" required class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 bg-slate-50 font-bold">
                                        <option value="" disabled>Select Company</option>
                                        <option v-for="co in options?.companies" :key="co.id" :value="co.id">{{ co.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">New Position (Assignment)</label>
                                    <select v-model="salaryForm.position_id" required class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 bg-slate-50 font-bold">
                                        <option value="" disabled>Select Position</option>
                                        <option v-for="pos in options?.positions" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <p v-if="isEditingSalary" class="text-[10px] text-amber-600 mt-1">Changing Position or Company here updates the linked historical record.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Basic Monthly Rate</label>
                            <input v-model="salaryForm.basic_rate" type="number" step="0.01" required placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Allowance</label>
                            <input v-model="salaryForm.allowance" type="number" step="0.01" placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Effectivity Date</label>
                            <input v-model="salaryForm.effective_date" type="date" required class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button v-if="isEditingSalary" type="button" @click="resetSalaryForm" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="salaryForm.processing" class="text-white text-xs font-bold px-8 py-2.5 rounded-xl transition-all shadow-lg" :class="isEditingSalary ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-500/20' : 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/20'">
                            {{ isEditingSalary ? 'Update Record' : 'Add to History' }}
                        </button>
                    </div>
                </form>

                <!-- History List -->
                <div v-if="isLoadingSalary" class="flex justify-center py-8">
                    <svg class="animate-spin h-8 w-8 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div v-else class="overflow-hidden border border-slate-100 rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-[10px] font-bold text-slate-500 uppercase">Effectivity</th>
                                <th class="px-4 py-2 text-left text-[10px] font-bold text-slate-500 uppercase">Company / Position</th>
                                <th class="px-4 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Basic Rate</th>
                                <th class="px-4 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Allowance</th>
                                <th class="px-4 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Total</th>
                                <th class="px-4 py-2 text-right text-[10px] font-bold text-slate-500 uppercase w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-50">
                            <tr v-for="item in salaryHistory" :key="item.id" class="hover:bg-slate-50 group">
                                <td class="px-4 py-3 text-sm font-bold text-slate-700">
                                    {{ item.effective_date.substring(0, 10) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    <div class="font-bold text-slate-800">{{ item.employment_record?.company?.name || 'Unknown Company' }}</div>
                                    <div class="text-xs">{{ item.employment_record?.position?.name || 'Unknown Position' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-mono">
                                    {{ parseFloat(item.basic_rate).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-mono text-slate-500">
                                    {{ parseFloat(item.allowance).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-emerald-600 font-mono">
                                    {{ (parseFloat(item.basic_rate) + parseFloat(item.allowance)).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button 
                                        v-if="hasPermission('employees.edit_salary')"
                                        @click="editSalaryItem(item)" 
                                        class="text-blue-600 hover:text-blue-800 p-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 transition-all border border-blue-200"
                                        title="Edit Record"
                                    >
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="hasPermission('employees.delete_salary')"
                                        @click="deleteSalaryItem(item)" 
                                        class="ml-1 text-red-600 hover:text-red-800 p-1.5 rounded-lg bg-red-50 hover:bg-red-100 transition-all border border-red-200"
                                        title="Delete Record"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="salaryHistory.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400 text-sm italic">No salary history recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>

        <!-- Preview Modal -->
        <Modal :show="showPreviewModal" @close="closePreview" maxWidth="full" zIndexClass="z-[60]">
            <div class="relative bg-slate-900 rounded-lg overflow-hidden h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700 z-10 shrink-0">
                    <h3 class="text-white font-bold text-sm truncate flex items-center">
                        <DocumentDuplicateIcon class="w-4 h-4 mr-2 text-slate-400" />
                        File Preview
                    </h3>
                    
                    <!-- Zoom Controls (Visible only for Images) -->
                    <div v-if="previewType === 'image'" class="flex items-center space-x-1 bg-slate-700 rounded-lg p-1 mx-4">
                        <button @click="zoomOut" class="p-1.5 text-slate-300 hover:text-white hover:bg-slate-600 rounded transition-colors" title="Zoom Out">
                            <MagnifyingGlassMinusIcon class="w-4 h-4" />
                        </button>
                        <span class="text-xs text-slate-400 font-mono w-12 text-center">{{ Math.round(scale * 100) }}%</span>
                        <button @click="zoomIn" class="p-1.5 text-slate-300 hover:text-white hover:bg-slate-600 rounded transition-colors" title="Zoom In">
                            <MagnifyingGlassPlusIcon class="w-4 h-4" />
                        </button>
                        <div class="w-px h-4 bg-slate-600 mx-1"></div>
                        <button @click="resetZoom" class="p-1.5 text-slate-300 hover:text-white hover:bg-slate-600 rounded transition-colors" title="Reset">
                            <ArrowPathIcon class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="flex items-center space-x-2">
                        <a 
                            v-if="previewDoc" 
                            :href="previewUrl" 
                            download 
                            class="text-slate-400 hover:text-white text-xs font-bold px-3 py-1.5 rounded hover:bg-slate-700 transition-colors"
                        >
                            Download
                        </a>
                        <button @click="closePreview" class="text-slate-400 hover:text-white p-1 rounded hover:bg-slate-700 transition-colors">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="flex-1 overflow-hidden flex items-center justify-center bg-slate-900/95 relative p-4 select-none">
                    <div v-if="!previewDoc" class="text-slate-500">Loading...</div>
                    
                    <!-- Image Preview -->
                    <div 
                        v-else-if="previewType === 'image'"
                        class="w-full h-full flex items-center justify-center overflow-hidden cursor-move"
                        @wheel="onWheel"
                        @mousedown="startDrag"
                        @mousemove="onDrag"
                        @mouseup="stopDrag"
                        @mouseleave="stopDrag"
                    >
                        <img 
                            :src="previewUrl" 
                            class="max-w-none transition-transform duration-75 ease-out origin-center rounded shadow-2xl" 
                            :style="{ 
                                transform: `translate(${translateX}px, ${translateY}px) scale(${scale})`,
                                cursor: isDragging ? 'grabbing' : 'grab'
                            }"
                            draggable="false"
                            alt="Document Preview"
                        />
                    </div>
                    
                    <!-- PDF Preview -->
                    <iframe 
                        v-else-if="previewType === 'pdf'" 
                        :src="previewUrl" 
                        class="w-full h-full rounded bg-white"
                        frameborder="0"
                    ></iframe>
                    
                    <!-- Fallback -->
                    <div v-else class="text-center">
                        <div class="bg-slate-800 p-6 rounded-2xl inline-block mb-4">
                            <DocumentDuplicateIcon class="w-16 h-16 text-slate-600 mx-auto" />
                        </div>
                        <p class="text-slate-300 font-bold text-lg mb-2">Preview not available</p>
                        <p class="text-slate-500 text-sm mb-6">This file type cannot be displayed directly.</p>
                        <a 
                            :href="previewUrl" 
                            target="_blank" 
                            class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-blue-700 transition-colors"
                        >
                            Download File
                        </a>
                    </div>
                </div>
            </div>
        </Modal>

    </AppLayout>
</template>
