<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
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
    BriefcaseIcon // Added
} from '@heroicons/vue/24/outline';

const props = defineProps({
    applicants: Object,
    filters: Object,
    options: Object, // Added
});

const showModal = ref(false);
const showHireModal = ref(false); // Added
const isEditing = ref(false);
const editingApplicant = ref(null);
const hiringApplicant = ref(null); // Added

const { confirm } = useConfirm();
const { post, put, destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

const pagination = usePagination(props.applicants, 'applicants.index');

onMounted(() => {
    pagination.updateData(props.applicants);
});

watch(() => props.applicants, (newApplicants) => {
    pagination.updateData(newApplicants);
}, { deep: true });

// ... existing form and functions ...

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
    hireForm.post(route('applicants.hire', hiringApplicant.value.id), {
        onSuccess: () => {
            showHireModal.value = false;
            showSuccess('Applicant hired and converted to Employee');
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
    form.phone = applicant.phone;
    form.status = applicant.status;
    form.exam_score = applicant.exam_score;
    form.interviewer_notes = applicant.interviewer_notes;
    form.resume = null; 
    form.clearErrors();
    showModal.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('applicants.update', editingApplicant.value.id), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Applicant updated successfully');
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
                showSuccess('Applicant added successfully');
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
            onSuccess: () => showSuccess('Applicant deleted successfully'),
            onError: (errors) => showError('Failed to delete applicant')
        });
    }
};

const statusColors = {
    pool: 'bg-slate-100 text-slate-700 border-slate-200',
    exam: 'bg-blue-50 text-blue-700 border-blue-100',
    interview: 'bg-indigo-50 text-indigo-700 border-indigo-100',
    passed: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    failed: 'bg-rose-50 text-rose-700 border-rose-100',
    hired: 'bg-purple-50 text-purple-700 border-purple-100',
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
                                        {{ applicant.status }}
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
                            <input v-model="form.first_name" @input="form.first_name = $event.target.value.toUpperCase()" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Middle Name</label>
                            <input v-model="form.middle_name" @input="form.middle_name = $event.target.value.toUpperCase()" type="text" placeholder="(Optional)" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Last Name</label>
                            <input v-model="form.last_name" @input="form.last_name = $event.target.value.toUpperCase()" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Email Address</label>
                            <input v-model="form.email" type="email" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Phone Number</label>
                            <input v-model="form.phone" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
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
                                    <option value="hired">Hired</option>
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
                            <input v-model="hireForm.basic_rate" type="number" step="0.01" required placeholder="0.00" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Allowance</label>
                            <input v-model="hireForm.allowance" type="number" step="0.01" placeholder="0.00" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
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
