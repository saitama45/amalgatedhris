<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    KeyIcon, 
    TrashIcon,
    EnvelopeIcon,
    BuildingOfficeIcon,
    UserCircleIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    users: Object,
    roles: Array,
    employees: Array,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showPasswordModal = ref(false);
const editingUser = ref(null);
const resetPasswordUser = ref(null);
const { confirm } = useConfirm();
const { post, put } = useErrorHandler();
const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

const pagination = usePagination(props.users, 'users.index');

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
        const targetForm = showCreateModal.value ? createForm : (showEditModal.value ? editForm : null);
        if (targetForm) {
            targetForm.name = '';
            targetForm.email = '';
            targetForm.department = '';
            targetForm.position = '';
        }
    }
    searchEmployees();
    isSelecting.value = false;
});

const selectEmployee = (employee) => {
    isSelecting.value = true;
    const targetForm = showCreateModal.value ? createForm : (showEditModal.value ? editForm : null);
    if (targetForm) {
        targetForm.name = employee.name;
        targetForm.email = employee.email || '';
        targetForm.department = employee.department_name || '';
        targetForm.position = employee.position_name || '';
        targetForm.applicant_id = employee.applicant_id || null;
        targetForm.employee_id = employee.employee_id || null;
    }
    employeeSearch.value = employee.name;
    showEmployeeDropdown.value = false;
};

const handleEmployeeSearchBlur = () => {
    setTimeout(() => {
        showEmployeeDropdown.value = false;
    }, 200);
};

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    employeeSearch.value = '';
    filteredEmployees.value = props.employees.slice(0, 50);
    showCreateModal.value = true;
};

// Reactive Input Validation & Formatting
const handleAlphaUpperInput = (formObj, field, e) => {
    // Allow only letters and spaces, then convert to uppercase
    const val = e.target.value.replace(/[^a-zA-Z\s]/g, '').toUpperCase();
    formObj[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const handleEmailInput = (formObj, e) => {
    // Disallow emojis and non-standard characters in email
    const val = e.target.value.replace(/\p{Extended_Pictographic}/gu, '').replace(/[^a-zA-Z0-9@._-]/g, '');
    formObj.email = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const handleEmojiBlocking = (val) => {
    return val.replace(/\p{Extended_Pictographic}/gu, '');
};

const handleEmployeeSearchInput = (e) => {
    const val = handleEmojiBlocking(e.target.value).toUpperCase();
    employeeSearch.value = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const handlePasswordInput = (formObj, e) => {
    const val = handleEmojiBlocking(e.target.value);
    formObj.password = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const validateEmail = (email) => {
    if (!email) return true;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

const isCreateEmailValid = computed(() => validateEmail(createForm.email));
const isEditEmailValid = computed(() => validateEmail(editForm.email));

onMounted(() => {
    pagination.updateData(props.users);
});

watch(() => props.users, (newUsers) => {
    pagination.updateData(newUsers);
}, { deep: true });

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
    department: '',
    position: '',
    applicant_id: null,
    employee_id: null,
    access_end_date: '',
});

const editForm = useForm({
    name: '',
    email: '',
    role: '',
    department: '',
    position: '',
    employee_id: null,
    access_end_date: '',
});

const passwordForm = useForm({
    password: '',
});

const createUser = () => {
    if (!isCreateEmailValid.value) {
        showError('Please provide a valid email address.');
        return;
    }
    post(route('users.store'), createForm.data(), {
        onSuccess: () => {
            showCreateModal.value = false;
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred'
            showError(errorMessage)
        }
    });
};

const editUser = (user) => {
    editingUser.value = user;
    isSelecting.value = true;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.roles[0]?.name || '';
    editForm.department = user.department || '';
    editForm.position = user.position || '';
    editForm.employee_id = user.employee?.id || null;
    editForm.access_end_date = user.access_end_date ? user.access_end_date.substring(0, 10) : '';
    employeeSearch.value = user.name;
    showEditModal.value = true;
};

const updateUser = () => {
    if (!isEditEmailValid.value) {
        showError('Please provide a valid email address.');
        return;
    }
    put(route('users.update', editingUser.value.id), editForm.data(), {
        onSuccess: () => {
            showEditModal.value = false;
            editingUser.value = null;
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred'
            showError(errorMessage)
        }
    });
};

const resetPassword = (user) => {
    resetPasswordUser.value = user;
    passwordForm.password = 'password123';
    showPasswordModal.value = true;
};

const updatePassword = () => {
    put(route('users.reset-password', resetPasswordUser.value.id), passwordForm.data(), {
        onSuccess: () => {
            showPasswordModal.value = false;
            resetPasswordUser.value = null;
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred'
            showError(errorMessage)
        }
    });
};
</script>

<template>
    <Head title="User Management - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">User Management</h2>
                    <p class="text-sm text-slate-500 mt-1">Configure system access and personnel roles.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Data Table Container -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Personnel Directory"
                        subtitle="List of active corporate accounts"
                        search-placeholder="Search by name, email, or department..."
                        empty-message="No records found in the directory."
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
                                v-if="hasPermission('users.create')"
                                @click="openCreateModal"
                                class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                            >
                                <UserPlusIcon class="w-5 h-5" />
                                <span>Register Personnel</span>
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Personnel</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Security Role</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Department</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Position</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Access Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="user in data" :key="user.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold border border-slate-200">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-bold text-slate-900">{{ user.name }}</div>
                                            <div class="text-xs text-slate-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        {{ user.roles[0]?.name || 'Restricted' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 font-medium">
                                    {{ user.department }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 font-medium">
                                    {{ user.position }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="user.access_end_date" class="flex flex-col">
                                        <span 
                                            :class="[
                                                'inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-lg border mb-1',
                                                new Date(user.access_end_date) <= new Date() 
                                                    ? 'bg-rose-50 text-rose-700 border-rose-100' 
                                                    : 'bg-amber-50 text-amber-700 border-amber-100'
                                            ]"
                                        >
                                            <ClockIcon class="w-3.5 h-3.5 mr-1" />
                                            {{ new Date(user.access_end_date) <= new Date() ? 'Access Expired' : 'Ends ' + new Date(user.access_end_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) }}
                                        </span>
                                    </div>
                                    <span v-else class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        <CheckCircleIcon class="w-3.5 h-3.5 mr-1" />
                                        Permanent
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-1">
                                        <button
                                            v-if="hasPermission('users.edit')"
                                            @click="editUser(user)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Modify Profile"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            v-if="hasPermission('users.edit')"
                                            @click="resetPassword(user)"
                                            class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all"
                                            title="Reset Security Key"
                                        >
                                            <KeyIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Modals with Redesigned Look -->
        <!-- Create User Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="showCreateModal = false"></div>
            
            <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-xl font-bold text-slate-900">Register New Personnel</h3>
                    <p class="text-sm text-slate-500">Create a new corporate account for the HRIS system.</p>
                </div>
                
                <form @submit.prevent="createUser" class="p-8 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2 relative">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Search Employee</label>
                            <div class="relative">
                                <UserCircleIcon class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                                <input 
                                    type="text" 
                                    :value="employeeSearch"
                                    @input="handleEmployeeSearchInput"
                                    @focus="showEmployeeDropdown = true; searchEmployees()"
                                    @blur="handleEmployeeSearchBlur"
                                    required 
                                    placeholder="Type name to search existing employee..." 
                                    class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all uppercase"
                                >
                            </div>
                            <!-- Dropdown -->
                            <div v-if="showEmployeeDropdown && filteredEmployees.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto">
                                <ul class="py-1">
                                    <li 
                                        v-for="emp in filteredEmployees" 
                                        :key="emp.employee_id"
                                        @mousedown.prevent="selectEmployee(emp)"
                                        class="px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer flex items-center"
                                    >
                                        <UserCircleIcon class="w-4 h-4 mr-2 text-slate-400" />
                                        {{ emp.name }}
                                    </li>
                                </ul>
                            </div>
                            <div v-else-if="showEmployeeDropdown && filteredEmployees.length === 0" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl p-4 text-sm text-slate-500 text-center">
                                No employees found.
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Full Name (Selected)</label>
                            <input 
                                :value="createForm.name" 
                                readonly
                                type="text" 
                                required 
                                class="block w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl transition-all uppercase font-bold text-slate-600 cursor-not-allowed"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Email Address (Read-only)</label>
                            <div class="relative">
                                <EnvelopeIcon class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                                <input 
                                    :value="createForm.email" 
                                    readonly
                                    type="email" 
                                    required 
                                    placeholder="corporate@cci.com" 
                                    class="block w-full pl-10 pr-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-not-allowed font-medium text-slate-600"
                                >
                            </div>
                        </div>

                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Security Role</label>
                            <select v-model="createForm.role" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                <option value="">Assign Role</option>
                                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                            </select>
                        </div>

                         <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Security Password</label>
                            <div class="relative">
                                <KeyIcon class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                                <input 
                                    :value="createForm.password" 
                                    @input="handlePasswordInput(createForm, $event)"
                                    type="password" 
                                    required 
                                    placeholder="••••••••" 
                                    class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                                >
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-rose-600 mb-1 flex items-center gap-1">
                                <ClockIcon class="w-4 h-4" /> Access End Date (Optional)
                            </label>
                            <input 
                                v-model="createForm.access_end_date"
                                type="date" 
                                class="block w-full px-4 py-2.5 bg-rose-50/30 border border-rose-100 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all font-bold text-rose-700"
                            >
                            <p class="text-[10px] text-slate-400 mt-1">If set, user will be blocked starting from this date.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Department (Read-only)</label>
                             <div class="relative">
                                <BuildingOfficeIcon class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                                <input 
                                    :value="createForm.department" 
                                    readonly
                                    type="text" 
                                    class="block w-full pl-10 pr-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl transition-all uppercase font-semibold text-slate-600 cursor-not-allowed"
                                >
                            </div>
                        </div>

                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Position (Read-only)</label>
                            <input 
                                :value="createForm.position" 
                                readonly
                                type="text" 
                                class="block w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl transition-all uppercase font-semibold text-slate-600 cursor-not-allowed"
                            >
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100">
                        <button type="button" @click="showCreateModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        <button type="submit" :disabled="createForm.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">Create Account</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showEditModal = false"></div>
            
            <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-xl font-bold text-slate-900">Modify Personnel Profile</h3>
                    <p class="text-sm text-slate-500">Update organizational details for the selected user.</p>
                </div>
                
                <form @submit.prevent="updateUser" class="p-8 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2 relative">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Search/Change Employee</label>
                            <div class="relative">
                                <UserCircleIcon class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                                <input 
                                    type="text" 
                                    :value="employeeSearch"
                                    @input="handleEmployeeSearchInput"
                                    @focus="showEmployeeDropdown = true; searchEmployees()"
                                    @blur="handleEmployeeSearchBlur"
                                    required 
                                    placeholder="Type name to change employee..." 
                                    class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all uppercase"
                                >
                            </div>
                            <!-- Dropdown -->
                            <div v-if="showEmployeeDropdown && filteredEmployees.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto">
                                <ul class="py-1">
                                    <li 
                                        v-for="emp in filteredEmployees" 
                                        :key="emp.employee_id"
                                        @mousedown.prevent="selectEmployee(emp)"
                                        class="px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer flex items-center"
                                    >
                                        <UserCircleIcon class="w-4 h-4 mr-2 text-slate-400" />
                                        {{ emp.name }}
                                    </li>
                                </ul>
                            </div>
                            <div v-else-if="showEmployeeDropdown && filteredEmployees.length === 0" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl p-4 text-sm text-slate-500 text-center">
                                No employees found.
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Full Name (Selected)</label>
                            <input 
                                :value="editForm.name" 
                                readonly
                                type="text" 
                                required 
                                class="block w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl transition-all uppercase font-bold text-slate-600 cursor-not-allowed"
                            >
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Email Address (Read-only)</label>
                            <input 
                                :value="editForm.email" 
                                readonly
                                type="email" 
                                required 
                                class="block w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-not-allowed font-medium text-slate-600"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Security Role</label>
                            <select v-model="editForm.role" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-rose-600 mb-1 flex items-center gap-1">
                                <ClockIcon class="w-4 h-4" /> Access End Date (Optional)
                            </label>
                            <input 
                                v-model="editForm.access_end_date"
                                type="date" 
                                class="block w-full px-4 py-2.5 bg-rose-50/30 border border-rose-100 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all font-bold text-rose-700"
                            >
                            <p class="text-[10px] text-slate-400 mt-1">If set, user will be blocked starting from this date.</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Department (Read-only)</label>
                            <input 
                                :value="editForm.department" 
                                readonly
                                type="text" 
                                class="block w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl transition-all uppercase font-semibold text-slate-600 cursor-not-allowed"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Position (Read-only)</label>
                            <input 
                                :value="editForm.position" 
                                readonly
                                type="text" 
                                class="block w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl transition-all uppercase font-semibold text-slate-600 cursor-not-allowed"
                            >
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100">
                        <button type="button" @click="showEditModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Reset Modal -->
        <div v-if="showPasswordModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showPasswordModal = false"></div>
            
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 bg-amber-50/50">
                    <h3 class="text-xl font-bold text-slate-900 flex items-center">
                        <KeyIcon class="w-6 h-6 mr-2 text-amber-600" />
                        Reset Security Key
                    </h3>
                    <p class="text-sm text-slate-500 mt-1">Issue a new temporary password for <strong>{{ resetPasswordUser?.name }}</strong>.</p>
                </div>
                
                <form @submit.prevent="updatePassword" class="p-8 space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">New Security Password</label>
                        <input v-model="passwordForm.password" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all font-mono">
                        <p class="text-[10px] text-slate-400 mt-2 uppercase tracking-widest font-bold">Recommended: password123 (Temporary)</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100">
                        <button type="button" @click="showPasswordModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        <button type="submit" :disabled="passwordForm.processing" class="px-6 py-2.5 bg-amber-600 text-white font-bold rounded-xl hover:bg-amber-700 shadow-lg shadow-amber-600/20 disabled:opacity-50 transition-all">Rotate Key</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>