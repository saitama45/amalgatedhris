<template>
    <Head title="Roles & Permissions - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Access Control</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage system security roles and functional permissions.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Data Table Container -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Security Roles"
                        subtitle="Defined organizational access levels"
                        search-placeholder="Search by role name..."
                        empty-message="No roles defined. Create a new role to begin."
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
                                v-if="hasPermission('roles.create')"
                                @click="openCreateModal" 
                                class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                            >
                                <ShieldCheckIcon class="w-5 h-5" />
                                <span>Define New Role</span>
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Role Designation</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Default Landing</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Permission Scope</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="role in data" :key="role.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold border border-slate-200">
                                            <ShieldCheckIcon class="w-5 h-5" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-bold text-slate-900">{{ role.name }}</div>
                                            <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">System Identifier: {{ role.name.toLowerCase().replace(' ', '_') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        {{ props.config.landing_pages[role.landing_page] || role.landing_page }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button 
                                        @click="viewPermissions(role)" 
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 hover:bg-blue-50 hover:text-blue-700 border border-slate-200 hover:border-blue-100 transition-all"
                                    >
                                        <EyeIcon class="w-3.5 h-3.5 mr-1.5" />
                                        {{ role.permissions.length }} Capability Keys
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-1">
                                        <button 
                                            v-if="hasPermission('roles.edit')"
                                            @click="editRole(role)" 
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Modify Access Scope"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            v-if="hasPermission('roles.delete')"
                                            @click="deleteRole(role)" 
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Delete Role"
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

        <!-- Permissions Viewer Modal -->
        <div v-if="showPermissionsModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="closePermissionsModal"></div>
            
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-xl font-bold text-slate-900">Permission Manifest</h3>
                    <p class="text-sm text-slate-500">Active capabilities for role: <strong class="text-slate-800">{{ selectedRole?.name }}</strong></p>
                </div>
                
                <div class="p-8 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="flex flex-wrap gap-2">
                        <span v-for="permission in (selectedRole?.permissions ? filterPermissions(selectedRole.permissions) : [])" :key="permission.id" 
                              class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-100">
                            {{ permission.name }}
                        </span>
                        <div v-if="!selectedRole?.permissions.length" class="text-slate-400 italic text-sm py-4">
                            No permissions assigned to this role.
                        </div>
                    </div>
                </div>
                
                <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button @click="closePermissionsModal" 
                            class="px-6 py-2.5 text-slate-600 font-bold bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                        Close Manifest
                    </button>
                </div>
            </div>
        </div>

        <!-- Create/Edit Role Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="closeModal"></div>
            
            <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full relative overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-xl font-bold text-slate-900">
                        {{ isEditing ? 'Modify Security Role' : 'Define Security Role' }}
                    </h3>
                    <p class="text-sm text-slate-500">Configure role name and granular access permissions.</p>
                </div>
                
                <form @submit.prevent="submitForm">
                    <div class="p-8 max-h-[70vh] overflow-y-auto custom-scrollbar">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Role Designation (Display Name)</label>
                                <input :value="form.name" @input="handleAlphaUpperInput(form, 'name', $event)" type="text" required placeholder="Ex. ACCOUNTING MANAGER"
                                    class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-semibold uppercase">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Default Landing Page (After Login)</label>
                                <select v-model="form.landing_page" required
                                    class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-semibold">
                                    <option v-for="(label, routeName) in props.config.landing_pages" :key="routeName" :value="routeName">
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-8">
                            <label class="block text-sm font-bold text-slate-700 mb-2">Associated Companies</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-48 overflow-y-auto custom-scrollbar border border-slate-200 rounded-xl p-3 bg-slate-50">
                                <label v-for="company in companies" :key="company.id" class="flex items-center p-2 rounded-lg hover:bg-white transition-colors cursor-pointer group">
                                    <input type="checkbox" :value="company.id" v-model="form.company_ids" 
                                           class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 transition-all">
                                    <span class="ml-3 text-sm font-medium text-slate-600 group-hover:text-slate-900">{{ company.name }}</span>
                                </label>
                                <div v-if="companies.length === 0" class="col-span-2 text-center text-sm text-slate-400 py-2">
                                    No companies available.
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">Users with this role will be associated with selected companies.</p>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <label class="block text-sm font-bold text-slate-700 flex items-center">
                                    <LockClosedIcon class="w-4 h-4 mr-2 text-slate-400" />
                                    Module Management
                                </label>
                                <label class="flex items-center cursor-pointer group">
                                    <input type="checkbox" 
                                           :checked="isAllSelected()"
                                           :indeterminate="isAllIndeterminate()"
                                           @change="toggleAllPermissions()"
                                           class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 transition-all">
                                    <span class="ml-2 text-xs font-bold text-slate-500 group-hover:text-blue-600 transition-colors">Select All Permissions</span>
                                </label>
                            </div>
                            
                            <div class="space-y-8">
                                <div v-for="(categories, groupName) in groupedPermissions" :key="groupName">
                                    <div v-if="Object.keys(categories).length > 0">
                                        <h3 class="font-bold text-lg text-slate-900 mb-4 border-b border-slate-200 pb-2">{{ groupName }}</h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div v-for="(perms, category) in categories" :key="category" class="bg-slate-50/50 border border-slate-100 rounded-2xl p-5">
                                                <div class="flex items-center justify-between mb-4">
                                                    <h4 class="font-bold text-slate-800 capitalize flex items-center text-sm">
                                                        <div class="w-1.5 h-4 bg-blue-500 rounded-full mr-2"></div>
                                                        {{ props.config.module_labels[category] || category.replace('_', ' ') }}
                                                    </h4>
                                                    <label class="flex items-center cursor-pointer group">
                                                        <input type="checkbox" 
                                                               :checked="isCategorySelected(category, perms)"
                                                               :indeterminate="isCategoryIndeterminate(category, perms)"
                                                               @change="toggleCategoryPermissions(category, perms)"
                                                               class="w-3.5 h-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 transition-all">
                                                        <span class="ml-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider group-hover:text-indigo-600 transition-colors">Check All</span>
                                                    </label>
                                                </div>
                                                <div class="space-y-2.5">
                                                    <label v-for="permission in sortPermissions(perms)" :key="permission.id" 
                                                           class="flex items-center group cursor-pointer p-2 hover:bg-white rounded-lg transition-colors border border-transparent hover:border-slate-100">
                                                        <input type="checkbox" :value="permission.name" v-model="form.permissions"
                                                               class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 transition-all">
                                                        <span class="ml-3 text-sm font-medium text-slate-600 group-hover:text-slate-900 transition-colors">
                                                            {{ formatPermissionName(permission.name) }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end space-x-3">
                        <button type="button" @click="closeModal" 
                                class="px-6 py-2.5 text-slate-600 font-bold bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                            Discard
                        </button>
                        <button type="submit" 
                                class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 transition-all">
                            {{ isEditing ? 'Update Role Definition' : 'Commit Role Definition' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from '@/Components/DataTable.vue'
import { useToast } from '@/Composables/useToast'
import { useConfirm } from '@/Composables/useConfirm'
import { useErrorHandler } from '@/Composables/useErrorHandler'
import { usePagination } from '@/Composables/usePagination'
import { usePermission } from '@/Composables/usePermission'
import { useConfidential } from '@/Composables/useConfidential'
import { 
    ShieldCheckIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    EyeIcon,
    LockClosedIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    roles: Object,
    permissions: Object,
    companies: Array,
    config: Object,
})

const { showSuccess, showError } = useToast()
const { confirm } = useConfirm()
const { post, put, destroy } = useErrorHandler()
const pagination = usePagination(props.roles, 'roles.index')
const { hasPermission } = usePermission();
const { canViewSalary, canManagePayroll } = useConfidential();
const page = usePage();

// Restricted Permissions Configuration
const salaryRestrictedPerms = ['applicants.view_salary', 'employees.view_salary'];
const payrollRestrictedPerms = [
    'payroll.view',
    'payroll.create',
    'payroll.approve',
    'payroll.revert',
    'payroll.edit_payslip',
    'payroll.delete',
    'payroll.manage_loans',
    'payroll.settings'
];

// Helper to filter out restricted permissions for unauthorized users
const filterPermissions = (perms) => {
    return perms.filter(p => {
        // Salary perms
        if (salaryRestrictedPerms.includes(p.name)) {
            return canViewSalary.value;
        }
        // Payroll perms
        if (payrollRestrictedPerms.includes(p.name)) {
            return canManagePayroll.value;
        }
        return true;
    });
};

// Input Handlers
const handleAlphaUpperInput = (formObj, field, e) => {
    // Allow only letters and spaces, remove emojis, then convert to uppercase
    const val = e.target.value.replace(/[^a-zA-Z\s]/g, '').replace(/\p{Extended_Pictographic}/gu, '').toUpperCase();
    formObj[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const showModal = ref(false)
const showPermissionsModal = ref(false)
const isEditing = ref(false)
const currentRole = ref(null)
const selectedRole = ref(null)

const form = reactive({
    name: '',
    landing_page: 'dashboard',
    permissions: [],
    company_ids: [],
})

const groupedPermissions = computed(() => {
    if (!props.permissions || !props.config?.sidebar_structure) return {};
    
    const result = {};
    
    // Iterate over our defined structure
    Object.entries(props.config.sidebar_structure).forEach(([groupName, categories]) => {
        result[groupName] = {};
        
        categories.forEach(category => {
            if (props.permissions[category]) {
                const filtered = filterPermissions(props.permissions[category]);
                if (filtered.length > 0) {
                    result[groupName][category] = filtered;
                }
            }
        });
    });
    
    return result;
});

onMounted(() => {
    pagination.updateData(props.roles)
})

watch(() => props.roles, (newRoles) => {
    pagination.updateData(newRoles);
}, { deep: true });

const viewPermissions = (role) => {
    selectedRole.value = role
    showPermissionsModal.value = true
}

const closePermissionsModal = () => {
    showPermissionsModal.value = false
    selectedRole.value = null
}

const openCreateModal = () => {
    isEditing.value = false
    currentRole.value = null
    form.name = ''
    form.landing_page = 'dashboard'
    form.permissions = []
    
    // Default select "Community Creators Inc." if it exists
    const defaultCompany = props.companies.find(c => c.name.toLowerCase().includes('community creators inc'));
    form.company_ids = defaultCompany ? [defaultCompany.id] : []
    
    showModal.value = true
}

const toggleCategoryPermissions = (category, perms) => {
    const permNames = perms.map(p => p.name);
    const allSelected = isCategorySelected(category, perms);
    
    if (allSelected) {
        // Remove all permissions of this category
        form.permissions = form.permissions.filter(p => !permNames.includes(p));
    } else {
        // Add all permissions of this category (avoid duplicates)
        const otherPermissions = form.permissions.filter(p => !permNames.includes(p));
        form.permissions = [...otherPermissions, ...permNames];
    }
};

const isCategorySelected = (category, perms) => {
    if (!perms || perms.length === 0) return false;
    const permNames = perms.map(p => p.name);
    return permNames.every(name => form.permissions.includes(name));
};

const isCategoryIndeterminate = (category, perms) => {
    if (!perms || perms.length === 0) return false;
    const permNames = perms.map(p => p.name);
    const selectedInCat = permNames.filter(name => form.permissions.includes(name));
    return selectedInCat.length > 0 && selectedInCat.length < permNames.length;
};

const toggleAllPermissions = () => {
    const allPerms = Object.values(props.permissions).flat().map(p => p.name);
    const visiblePermNames = allPerms.filter(name => {
        if (salaryRestrictedPerms.includes(name)) return canViewSalary.value;
        if (payrollRestrictedPerms.includes(name)) return canManagePayroll.value;
        return true;
    });

    if (isAllSelected()) {
        // Only remove visible permissions to avoid accidentally stripping restricted ones if they exist
        form.permissions = form.permissions.filter(p => !visiblePermNames.includes(p));
    } else {
        // Add all visible permissions (avoid duplicates)
        const currentRestricted = form.permissions.filter(p => !visiblePermNames.includes(p));
        form.permissions = [...new Set([...currentRestricted, ...visiblePermNames])];
    }
};

const isAllSelected = () => {
    const allPerms = Object.values(props.permissions).flat().map(p => p.name);
    const visiblePermNames = allPerms.filter(name => {
        if (salaryRestrictedPerms.includes(name)) return canViewSalary.value;
        if (payrollRestrictedPerms.includes(name)) return canManagePayroll.value;
        return true;
    });
        
    return visiblePermNames.length > 0 && visiblePermNames.every(p => form.permissions.includes(p));
};

const isAllIndeterminate = () => {
    const allPerms = Object.values(props.permissions).flat().map(p => p.name);
    const visiblePermNames = allPerms.filter(name => {
        if (salaryRestrictedPerms.includes(name)) return canViewSalary.value;
        if (payrollRestrictedPerms.includes(name)) return canManagePayroll.value;
        return true;
    });
        
    const selectedVisible = visiblePermNames.filter(name => form.permissions.includes(name));
    return selectedVisible.length > 0 && selectedVisible.length < visiblePermNames.length;
};

const editRole = (role) => {
    isEditing.value = true
    currentRole.value = role
    form.name = role.name
    form.landing_page = role.landing_page || 'dashboard'
    form.permissions = role.permissions.map(p => p.name)
    form.company_ids = role.companies ? role.companies.map(c => c.id) : []
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    form.name = ''
    form.landing_page = 'dashboard'
    form.permissions = []
    form.company_ids = []
}

const submitForm = () => {
    const url = isEditing.value ? `/roles/${currentRole.value.id}` : '/roles'
    const method = isEditing.value ? 'put' : 'post'
    
    const requestMethod = method === 'put' ? put : post
    
    requestMethod(url, form, {
        onSuccess: () => {
            closeModal()
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error'
            showError(errorMessage)
        }
    })
}

const deleteRole = async (role) => {
    const confirmed = await confirm({
        title: 'Revoke Security Role',
        message: `Are you sure you want to delete the "${role.name}" role? This will impact all users currently assigned to it.`
    })
    
    if (confirmed) {
        destroy(`/roles/${role.id}`, {
            onSuccess: () => {},
            onError: (errors) => {
                const errorMessage = Object.values(errors).flat().join(', ') || 'Dependencies exist for this role'
                showError(errorMessage)
            }
        })
    }
}

const sortPermissions = (permissions) => {
    const order = [
        'view', 
        'create', 
        'edit', 
        'update', 
        'delete',
        'view_all', 
        'view_sensitive', 
        'view_salary',
        'create_salary',
        'edit_salary',
        'delete_salary',
        'view_documents',
        'edit_documents',
        'file_leave',
        'file_ot',
        'approve', 
        'reject',
        'hire', 
        'process', 
        'manage', 
        'manage_loans',
        'config', 
        'settings',
        'kiosk'
    ];
    
    return permissions.sort((a, b) => {
        const aAction = a.name.split('.')[1];
        const bAction = b.name.split('.')[1];
        const aIndex = order.indexOf(aAction);
        const bIndex = order.indexOf(bAction);
        
        if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;
        if (aIndex !== -1) return -1; // If only 'a' is in order, 'a' comes first
        if (bIndex !== -1) return 1;  // If only 'b' is in order, 'b' comes first
        
        return aAction.localeCompare(bAction);
    });
}

const formatPermissionName = (name) => {
    return props.config?.permission_descriptions[name] || name;
}
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f8fafc;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
