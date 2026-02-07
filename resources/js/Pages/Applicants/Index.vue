<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useErrorHandler } from '@/Composables/useErrorHandler';
import { useToast } from '@/Composables/useToast';
import { usePagination } from '@/Composables/usePagination';
import { usePermission } from '@/Composables/usePermission';
import { 
    UserPlusIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    DocumentTextIcon,
    PhoneIcon,
    EnvelopeIcon,
    BriefcaseIcon,
    FolderIcon,
    ArrowUpTrayIcon,
    CheckCircleIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    applicants: Object,
    filters: Object,
    options: Object, 
});

const showModal = ref(false);
const showHireModal = ref(false);
const showDocsModal = ref(false);
const isEditing = ref(false);
const editingApplicant = ref(null);
const hiringApplicant = ref(null);
const managingApplicant = ref(null);
const applicantDocs = ref([]);
const stagedFiles = ref({});
const uploadingDocId = ref(null);

const { confirm } = useConfirm();
const { post, put, destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

const pagination = usePagination(props.applicants, 'applicants.index');

// Reactive Input Validation & Formatting
const handleNameInput = (field, e) => {
    // Allow only letters and spaces, then convert to uppercase
    const val = e.target.value.replace(/[^a-zA-Z\s]/g, '').toUpperCase();
    form[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const handleEmailInput = (e) => {
    // Disallow emojis and non-standard characters in email
    const val = e.target.value.replace(/[^a-zA-Z0-9@._-]/g, '');
    form.email = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const isEmailValid = computed(() => {
    if (!form.email) return true;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(form.email);
});

onMounted(() => {
    pagination.updateData(props.applicants);
});

watch(() => props.applicants, (newApplicants) => {
    pagination.updateData(newApplicants);
}, { deep: true });

const hireForm = useForm({
    company_id: '',
    department_id: '',
    position_id: '',
    start_date: new Date().toISOString().split('T')[0],
    basic_rate: '',
    allowance: 0,
});

const openHireModal = (applicant) => {
    hiringApplicant.value = applicant;
    hireForm.reset();
    showHireModal.value = true;
};

const submitHire = () => {
    // Ensure values are positive before submission
    if (hireForm.basic_rate <= 0) {
        showError('Basic Rate must be greater than 0.');
        return;
    }
    if (hireForm.allowance < 0) {
        hireForm.allowance = 0;
    }

    hireForm.post(route('applicants.hire', hiringApplicant.value.id), {
        onSuccess: () => {
            showHireModal.value = false;
        },
        onError: (errors) => {
             const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error';
             showError(errorMessage);
        }
    });
};

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    phone: '',
    status: 'pool',
    resume: null,
    exam_score: '',
    interviewer_notes: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    editingApplicant.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (applicant) => {
    isEditing.value = true;
    editingApplicant.value = applicant;
    form.first_name = applicant.first_name;
    form.middle_name = applicant.middle_name;
    form.last_name = applicant.last_name;
    form.email = applicant.email;
    form.phone = formatPhoneNumber(applicant.phone);
    form.status = applicant.status;
    form.exam_score = applicant.exam_score;
    form.interviewer_notes = applicant.interviewer_notes;
    form.resume = null; 
    form.clearErrors();
    showModal.value = true;
};

const formatPhoneNumber = (value) => {
    if (!value) return '';
    let clean = value.replace(/\D/g, '');
    if (clean.length > 11) clean = clean.slice(0, 11);
    
    let formatted = '';
    if (clean.length > 0) {
        formatted = clean.substring(0, 4);
        if (clean.length > 4) {
            formatted += ' ' + clean.substring(4, 7);
            if (clean.length > 7) {
                formatted += ' ' + clean.substring(7, 11);
            }
        }
    }
    return formatted;
};

const handlePhoneInput = (e) => {
    const formatted = formatPhoneNumber(e.target.value);
    form.phone = formatted;
    // Force DOM sync to remove any invalid characters that escaped the keydown check
    if (e.target.value !== formatted) {
        e.target.value = formatted;
    }
};

const onlyNumbers = (e) => {
    // Allow navigation and deletion keys
    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
    if (allowedKeys.includes(e.key)) return;

    // Block anything that isn't a number
    if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault();
    }
};

const submitForm = () => {
    if (!isEmailValid.value) {
        showError('Please provide a valid email address.');
        return;
    }

    if (isEditing.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('applicants.update', editingApplicant.value.id), {
            onSuccess: () => {
                showModal.value = false;
            },
            onError: (errors) => {
                 const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error';
                 showError(errorMessage);
            }
        });
    } else {
        form.post(route('applicants.store'), {
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

const deleteApplicant = async (applicant) => {
    const confirmed = await confirm({
        title: 'Delete Applicant',
        message: `Are you sure you want to delete ${applicant.first_name} ${applicant.last_name}?`
    });
    
    if (confirmed) {
        destroy(route('applicants.destroy', applicant.id), {
            onError: (errors) => showError('Failed to delete applicant')
        });
    }
};

// --- Document Management Functions ---

const fetchApplicantDocs = (applicantId) => {
    axios.get(route('applicants.documents.list', applicantId))
        .then(response => {
            applicantDocs.value = response.data;
        })
        .catch(() => showError('Failed to load documents'));
};

const openDocsModal = (applicant) => {
    managingApplicant.value = applicant;
    applicantDocs.value = [];
    stagedFiles.value = {};
    uploadingDocId.value = null;
    showDocsModal.value = true;
    fetchApplicantDocs(applicant.id);
};

const triggerFileInput = (docTypeId) => {
    document.getElementById(`file-input-${docTypeId}`).click();
};

const handleFileSelect = (event, docTypeId) => {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
        showError('File is too large. Maximum size is 10MB.');
        event.target.value = '';
        return;
    }

    stagedFiles.value[docTypeId] = file;
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

    axios.post(route('applicants.upload-document', managingApplicant.value.id), formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    }).then(() => {
        showSuccess('Document uploaded successfully');
        delete stagedFiles.value[docTypeId];
        fetchApplicantDocs(managingApplicant.value.id);
    }).catch(err => {
        showError(err.response?.data?.message || 'Upload failed');
    }).finally(() => {
        uploadingDocId.value = null;
    });
};

const getDocStatus = (docTypeId) => {
    return applicantDocs.value.find(d => d.document_type_id == docTypeId);
};

const statusColors = {
    pool: 'bg-slate-100 text-slate-700 border-slate-200',
    exam: 'bg-blue-50 text-blue-700 border-blue-100',
    interview: 'bg-indigo-50 text-indigo-700 border-indigo-100',
    passed: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    failed: 'bg-rose-50 text-rose-700 border-rose-100',
    hired: 'bg-purple-50 text-purple-700 border-purple-100',
    backed_out: 'bg-gray-50 text-gray-600 border-gray-200',
    backed_out: 'bg-slate-100 text-slate-500 border-slate-200',
};
</script>

<template>
    <Head title="Applicants - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Applicant Pooling</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage recruitment pipeline and candidates.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Candidate List"
                        subtitle="Active applicants in the pipeline"
                        search-placeholder="Search name or email..."
                        empty-message="No applicants found."
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
                                v-if="hasPermission('applicants.create')"
                                @click="openCreateModal"
                                class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                            >
                                <UserPlusIcon class="w-5 h-5" />
                                <span>New Applicant</span>
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Candidate</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Contact</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Resume</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="applicant in data" :key="applicant.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-slate-900">
                                        {{ applicant.first_name }} 
                                        {{ applicant.middle_name ? applicant.middle_name + ' ' : '' }}
                                        {{ applicant.last_name }}
                                    </div>
                                    <div class="text-xs text-slate-500">Added: {{ new Date(applicant.created_at).toLocaleDateString() }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col space-y-1">
                                        <div class="flex items-center text-sm text-slate-600">
                                            <EnvelopeIcon class="w-4 h-4 mr-2 text-slate-400" />
                                            {{ applicant.email }}
                                        </div>
                                        <div class="flex items-center text-sm text-slate-600">
                                            <PhoneIcon class="w-4 h-4 mr-2 text-slate-400" />
                                            {{ applicant.phone }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['inline-flex px-2.5 py-1 text-xs font-bold rounded-lg border capitalize', statusColors[applicant.status] || 'bg-slate-100 text-slate-500']">
                                        {{ applicant.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a v-if="applicant.resume_path" :href="'/' + applicant.resume_path" target="_blank" class="text-blue-600 hover:text-blue-800 flex items-center text-sm font-medium">
                                        <DocumentTextIcon class="w-4 h-4 mr-1" /> View CV
                                    </a>
                                    <span v-else class="text-slate-400 text-xs italic">No resume</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                     <div class="flex justify-end space-x-1">
                                        <button
                                            v-if="hasPermission('applicants.manage_requirements')"
                                            @click="openDocsModal(applicant)"
                                            class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all"
                                            title="Manage Requirements"
                                        >
                                            <FolderIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            v-if="hasPermission('applicants.hire') && applicant.status === 'passed'" 
                                            @click="openHireModal(applicant)"
                                            class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all"
                                            title="Hire / Convert to Employee"
                                        >
                                            <BriefcaseIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            v-if="hasPermission('applicants.edit')" 
                                            @click="openEditModal(applicant)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit Applicant"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            v-if="hasPermission('applicants.delete')"
                                            @click="deleteApplicant(applicant)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Delete Applicant"
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
             
             <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-xl font-bold text-slate-900">{{ isEditing ? 'Update Applicant Profile' : 'New Applicant' }}</h3>
                    <p class="text-sm text-slate-500">{{ isEditing ? 'Manage candidate details and status.' : 'Add a new candidate to the pool.' }}</p>
                </div>
                
                <form @submit.prevent="submitForm" class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">First Name</label>
                            <input 
                                :value="form.first_name" 
                                @input="handleNameInput('first_name', $event)" 
                                type="text" 
                                required 
                                placeholder="JUAN"
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all uppercase"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Middle Name</label>
                            <input 
                                :value="form.middle_name" 
                                @input="handleNameInput('middle_name', $event)" 
                                type="text" 
                                placeholder="(Optional)" 
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all uppercase"
                            >
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Last Name</label>
                            <input 
                                :value="form.last_name" 
                                @input="handleNameInput('last_name', $event)" 
                                type="text" 
                                required 
                                placeholder="DELA CRUZ"
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all uppercase"
                            >
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Email Address</label>
                            <input 
                                :value="form.email" 
                                @input="handleEmailInput"
                                type="email" 
                                required 
                                placeholder="juan@example.com"
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                                :class="{'border-rose-500 ring-rose-500/20': !isEmailValid && form.email}"
                            >
                            <p v-if="!isEmailValid && form.email" class="text-[10px] text-rose-600 mt-1 font-bold">Please enter a valid email format.</p>
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Phone Number</label>
                            <input 
                                :value="form.phone" 
                                @input="handlePhoneInput"
                                @keydown="onlyNumbers"
                                type="text" 
                                required 
                                placeholder="09XX XXX XXXX"
                                maxlength="13"
                                inputmode="numeric"
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                            >
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-700 mb-1">Resume / CV (PDF, DOC)</label>
                        <input type="file" @input="form.resume = $event.target.files[0]" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all">
                    </div>
                    
                    <div v-if="isEditing" class="border-t border-slate-100 pt-6 mt-6">
                         <h4 class="font-bold text-slate-800 mb-4">Recruitment Progress</h4>
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                             <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Current Status</label>
                                <select v-model="form.status" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                    <option value="pool">Pool</option>
                                    <option value="exam">For Exam</option>
                                    <option value="interview">For Interview</option>
                                    <option value="passed">Passed</option>
                                    <option value="failed">Failed</option>
                                    <option value="backed_out">Back Out</option>
                                </select>
                            </div>
                             <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Exam Score (%)</label>
                                <input v-model="form.exam_score" type="number" min="0" max="100" step="0.01" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            </div>
                         </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Interviewer Notes</label>
                            <textarea v-model="form.interviewer_notes" rows="3" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                            {{ isEditing ? 'Save Changes' : 'Add Candidate' }}
                        </button>
                    </div>
                </form>
             </div>
        </div>

        <!-- Documents Modal -->
        <div v-if="showDocsModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
             <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showDocsModal = false"></div>
             
             <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full relative flex flex-col max-h-[90vh] animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center flex-shrink-0 rounded-t-2xl">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Applicant Requirements</h3>
                        <p class="text-sm text-slate-500">Manage documents for <strong>{{ managingApplicant?.first_name }} {{ managingApplicant?.last_name }}</strong></p>
                    </div>
                    <button @click="showDocsModal = false" class="text-slate-400 hover:text-slate-500 transition-colors p-1 rounded-lg hover:bg-slate-100">
                        <span class="sr-only">Close</span>
                        <XMarkIcon class="h-6 w-6" />
                    </button>
                </div>
                
                <div class="flex-1 overflow-y-auto custom-scrollbar p-0">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-10">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Requirement</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="docType in options?.document_types" :key="docType.id" class="hover:bg-slate-50 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="getDocStatus(docType.id)" class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 border border-emerald-200">
                                        <CheckCircleIcon class="w-5 h-5" />
                                    </div>
                                    <div v-else class="w-8 h-8 border-2 border-slate-200 rounded-full mx-auto bg-slate-50 border-dashed"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-normal">
                                    <div class="flex flex-col">
                                        <div class="flex items-center mb-1">
                                            <span class="text-sm font-bold text-slate-800">{{ docType.name }}</span>
                                            <span v-if="docType.is_required" class="ml-2 text-[10px] text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-100 font-bold uppercase tracking-wide">Required</span>
                                        </div>

                                        <!-- Staged File Info -->
                                        <div v-if="stagedFiles[docType.id]" class="text-xs text-blue-600 font-semibold bg-blue-50 px-2 py-1.5 rounded-md border border-blue-100 flex items-center w-fit animate-in fade-in zoom-in duration-200">
                                            <DocumentTextIcon class="w-4 h-4 mr-1.5" />
                                            <span class="truncate max-w-[200px]">{{ stagedFiles[docType.id].name }}</span>
                                        </div>

                                        <!-- Uploaded File Info -->
                                        <div v-else-if="getDocStatus(docType.id)" class="flex items-center space-x-2 text-xs text-slate-500">
                                            <span class="bg-slate-100 px-2 py-0.5 rounded text-slate-600 font-mono uppercase">
                                                {{ getDocStatus(docType.id).file_path.split('.').pop() }}
                                            </span>
                                            <span>• Uploaded {{ new Date(getDocStatus(docType.id).created_at).toLocaleDateString() }}</span>
                                        </div>
                                        
                                        <div v-else class="text-xs text-slate-400 italic">Not uploaded yet.</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <input 
                                        type="file" 
                                        :id="`file-input-${docType.id}`" 
                                        class="hidden" 
                                        @change="(e) => handleFileSelect(e, docType.id)"
                                    >
                                    
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

                                    <div v-else class="flex justify-end items-center space-x-2">
                                        <a v-if="getDocStatus(docType.id)" :href="'/' + getDocStatus(docType.id).file_path" target="_blank" class="text-slate-500 hover:text-blue-600 text-xs font-bold flex items-center px-2 py-1.5 rounded hover:bg-blue-50 transition-all">
                                            <DocumentTextIcon class="w-4 h-4 mr-1.5" /> View
                                        </a>
                                        
                                        <button @click="triggerFileInput(docType.id)" class="flex items-center ml-auto text-slate-600 hover:text-blue-600 font-bold text-xs bg-white border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 hover:border-blue-200 hover:shadow-sm transition-all">
                                            <span v-if="getDocStatus(docType.id)">Replace</span>
                                            <span v-else>Select File</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
             </div>
        </div>

        <!-- Hire Modal -->
        <div v-if="showHireModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
             <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="showHireModal = false"></div>
             
             <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-xl font-bold text-slate-900">Onboarding & Hiring</h3>
                    <p class="text-sm text-slate-500">Convert <strong>{{ hiringApplicant?.first_name }} {{ hiringApplicant?.last_name }}</strong> to an employee.</p>
                </div>
                
                <form @submit.prevent="submitHire" class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Company</label>
                            <select v-model="hireForm.company_id" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                <option value="" disabled>Select Company</option>
                                <option v-for="company in options?.companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Start Date</label>
                            <input v-model="hireForm.start_date" type="date" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Department</label>
                            <select v-model="hireForm.department_id" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                <option value="" disabled>Select Department</option>
                                <option v-for="dept in options?.departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Position</label>
                            <select v-model="hireForm.position_id" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                <option value="" disabled>Select Position</option>
                                <option v-for="pos in options?.positions" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Basic Rate</label>
                            <input 
                                v-model="hireForm.basic_rate" 
                                type="number" 
                                step="0.01" 
                                min="0.01"
                                @keypress="(e) => { if(e.key === '-') e.preventDefault(); }"
                                required 
                                placeholder="0.00" 
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Allowance</label>
                            <input 
                                v-model="hireForm.allowance" 
                                type="number" 
                                step="0.01" 
                                min="0"
                                @keypress="(e) => { if(e.key === '-') e.preventDefault(); }"
                                placeholder="0.00" 
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                            >
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                        <button type="button" @click="showHireModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        <button type="submit" :disabled="hireForm.processing" class="px-6 py-2.5 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 disabled:opacity-50 transition-all">
                            Confirm Hiring
                        </button>
                    </div>
                </form>
             </div>
        </div>
    </AppLayout>
</template>
