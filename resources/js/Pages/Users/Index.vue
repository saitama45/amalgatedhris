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
    KeyIcon, 
    TrashIcon,
    EnvelopeIcon,
    BuildingOfficeIcon,
    UserCircleIcon,
    CheckCircleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    users: Object,
    roles: Array,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showPasswordModal = ref(false);
const editingUser = ref(null);
const resetPasswordUser = ref(null);
const { confirm } = useConfirm();
const { post, put, destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

const pagination = usePagination(props.users, 'users.index');

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
});

const editForm = useForm({
    name: '',
    email: '',
    role: '',
    department: '',
    position: '',
});

const passwordForm = useForm({
    password: '',
});

const createUser = () => {
    post(route('users.store'), createForm.data(), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            showSuccess('User created successfully')
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred'
            showError(errorMessage)
        }
    });
};

const editUser = (user) => {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.roles[0]?.name || '';
    editForm.department = user.department || '';
    editForm.position = user.position || '';
    showEditModal.value = true;
};

const updateUser = () => {
    put(route('users.update', editingUser.value.id), editForm.data(), {
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
            editingUser.value = null;
            showSuccess('User updated successfully')
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred'
            showError(errorMessage)
        }
    });
};

const deleteUser = async (user) => {
    const confirmed = await confirm({
        title: 'Delete User',
        message: `Are you sure you want to delete "${user.name}"? This will permanently remove their access to HRIS.`
    })
    
    if (confirmed) {
        destroy(route('users.destroy', user.id), {
            onSuccess: () => showSuccess('User deleted successfully'),
            onError: (errors) => {
                const errorMessage = Object.values(errors).flat().join(', ') || 'Cannot delete user'
                showError(errorMessage)
            }
        });
    }
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
            passwordForm.reset();
            resetPasswordUser.value = null;
            showSuccess('Password reset successfully')
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
                                @click="showCreateModal = true"
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
                                    {{ user.department || 'General' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        <CheckCircleIcon class="w-3.5 h-3.5 mr-1" />
                                        Authorized
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
                                        <button
                                            v-if="hasPermission('users.delete')"
                                            @click="deleteUser(user)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Revoke Access"
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
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Full Name</label>
                            <div class="relative">
                                <UserCircleIcon class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                                <input v-model="createForm.name" type="text" required placeholder="Ex. Juan Dela Cruz" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Email Address</label>
                            <div class="relative">
                                <EnvelopeIcon class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                                <input v-model="createForm.email" type="email" required placeholder="corporate@cci.com" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
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
                                <input v-model="createForm.password" type="password" required placeholder="••••••••" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Department</label>
                             <div class="relative">
                                <BuildingOfficeIcon class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                                <input v-model="createForm.department" type="text" placeholder="Ex. Accounting" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            </div>
                        </div>

                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Position</label>
                            <input v-model="createForm.position" type="text" placeholder="Ex. Senior Manager" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
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
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Full Name</label>
                            <input v-model="editForm.name" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Email Address</label>
                            <input v-model="editForm.email" type="email" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Security Role</label>
                            <select v-model="editForm.role" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Department</label>
                            <input v-model="editForm.department" type="text" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
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