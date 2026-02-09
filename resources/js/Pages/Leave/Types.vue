<script setup>
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useToast } from '@/Composables/useToast.js';
import { useConfirm } from '@/Composables/useConfirm.js';
import { 
    PlusIcon, 
    TrashIcon,
    PencilSquareIcon,
    XMarkIcon,
    CheckCircleIcon,
    InformationCircleIcon,
    ArrowLeftIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    types: Array
});

const { showSuccess, showError } = useToast();
const { confirm, showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    days_per_year: 0,
    is_convertible: false,
    is_cumulative: false,
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (type) => {
    isEditing.value = true;
    editingId.value = type.id;
    form.name = type.name;
    form.days_per_year = type.days_per_year;
    form.is_convertible = !!type.is_convertible;
    form.is_cumulative = !!type.is_cumulative;
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('leave-types.update', editingId.value), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Leave type updated.');
            }
        });
    } else {
        form.post(route('leave-types.store'), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Leave type created.');
            }
        });
    }
};

const deleteType = async (id) => {
    const isConfirmed = await confirm({
        title: 'Delete Leave Type',
        message: 'Are you sure? This may affect existing leave records.'
    });

    if (isConfirmed) {
        router.delete(route('leave-types.destroy', id), {
            onSuccess: () => showSuccess('Leave type deleted.')
        });
    }
};
</script>

<template>
    <Head title="Leave Setup - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Leave Policy Setup</h2>
                    <p class="text-sm text-slate-500 mt-1">Configure leave types and annual allocations.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Back Button -->
                <div class="mb-6">
                    <Link :href="route('leave-requests.index')" class="inline-flex items-center text-sm font-bold text-slate-500 hover:text-blue-600 transition-colors group">
                        <ArrowLeftIcon class="w-4 h-4 mr-2 group-hover:-translate-x-1 transition-transform" />
                        Back to Leave Management
                    </Link>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Available Leave Types"
                        subtitle="Rules for each leave category"
                        :data="types"
                        :show-search="false"
                        :show-pagination="false"
                    >
                        <template #actions>
                            <button @click="openCreateModal" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20">
                                <PlusIcon class="w-4 h-4 mr-2" /> New Type
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Leave Name</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Days / Year</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Convertible</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Cumulative</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="type in data" :key="type.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-slate-900">{{ type.name }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-lg font-bold text-blue-600 font-mono">{{ type.days_per_year }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <CheckCircleIcon v-if="!!type.is_convertible" class="w-5 h-5 text-emerald-500 mx-auto" />
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <CheckCircleIcon v-if="!!type.is_cumulative" class="w-5 h-5 text-indigo-500 mx-auto" />
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        <button @click="openEditModal(type)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Edit">
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button @click="deleteType(type.id)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Delete">
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

        <!-- Modal -->
        <Modal :show="showModal" @close="showModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Leave Type' : 'Add New Leave Type' }}</h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submit" class="p-6 space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type Name</label>
                    <input v-model="form.name" type="text" required placeholder="e.g. Sick Leave" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Entitlement (Days per Year)</label>
                    <input v-model="form.days_per_year" type="number" required min="0" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono text-lg font-bold">
                </div>

                <div class="flex flex-col gap-3 pt-2">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" v-model="form.is_convertible" id="is_convertible" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <label for="is_convertible" class="text-sm font-bold text-slate-700">Convertible to Cash (Year-end)</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" v-model="form.is_cumulative" id="is_cumulative" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <label for="is_cumulative" class="text-sm font-bold text-slate-700">Cumulative (Carries over to next year)</label>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-50">
                    <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors mr-3">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-8 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        {{ isEditing ? 'Update Type' : 'Save Type' }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmModal 
            :show="showConfirmModal" 
            :title="confirmTitle" 
            :message="confirmMessage" 
            @confirm="handleConfirm" 
            @cancel="handleCancel" 
        />

    </AppLayout>
</template>
