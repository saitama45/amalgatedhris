<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { usePermission } from '@/Composables/usePermission.js';
import { useToast } from '@/Composables/useToast.js';
import { useConfirm } from '@/Composables/useConfirm.js';
import { 
    PlusIcon, 
    TrashIcon,
    PencilSquareIcon,
    BanknotesIcon,
    XMarkIcon,
    CheckCircleIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    rates: Array,
    can: Object
});

const { hasPermission } = usePermission();
const { showSuccess, showError } = useToast();
const { confirm, showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    key: '',
    name: '',
    rate: '',
    description: '',
    is_active: true
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (rate) => {
    isEditing.value = true;
    editingId.value = rate.id;
    form.key = rate.key;
    form.name = rate.name;
    form.rate = rate.rate;
    form.description = rate.description;
    form.is_active = rate.is_active;
    showModal.value = true;
};

const submit = () => {
    if (parseFloat(form.rate) <= 0) {
        showError('Multiplier value must be greater than 0.');
        return;
    }

    if (isEditing.value) {
        form.put(route('overtime-rates.update', editingId.value), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Rate updated successfully.');
            },
            onError: () => showError('Failed to update rate.')
        });
    } else {
        form.post(route('overtime-rates.store'), {
            onSuccess: () => {
                showModal.value = false;
                showSuccess('Rate created successfully.');
            },
            onError: () => showError('Failed to create rate.')
        });
    }
};

const deleteRate = async (id) => {
    const isConfirmed = await confirm({
        title: 'Delete OT Rate',
        message: 'Are you sure you want to delete this multiplier? Logic using this key might break.'
    });

    if (isConfirmed) {
        router.delete(route('overtime-rates.destroy', id), {
            onSuccess: () => showSuccess('Rate deleted.'),
            onError: () => showError('Failed to delete rate.')
        });
    }
};
</script>

<template>
    <Head title="Overtime Multipliers - HRIS" />

    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Overtime Multipliers</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage global OT rates for payroll computation.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Multiplier Table"
                        subtitle="Active rates for computation"
                        :data="rates"
                        :show-search="false"
                        :show-pagination="false"
                    >
                        <template #actions>
                            <button v-if="can.manage" @click="openCreateModal" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all font-bold text-sm flex items-center shadow-lg shadow-blue-600/20">
                                <PlusIcon class="w-4 h-4 mr-2" /> Add Multiplier
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Name / Key</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Multiplier</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Description</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>

                        <template #body="{ data }">
                            <tr v-for="rate in data" :key="rate.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-slate-900">{{ rate.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono tracking-tighter uppercase font-semibold">KEY: {{ rate.key }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-lg font-bold text-blue-600 font-mono">x{{ parseFloat(rate.rate).toFixed(2) }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-normal">
                                    <p class="text-xs text-slate-500 max-w-xs">{{ rate.description || '-' }}</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="rate.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'" class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider">
                                        {{ rate.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-1">
                                        <button v-if="can.manage" @click="openEditModal(rate)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Edit">
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button v-if="can.manage" @click="deleteRate(rate.id)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Delete">
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
                <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Multiplier' : 'Add New Multiplier' }}</h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submit" class="p-6 space-y-5">
                <div v-if="!isEditing" class="bg-amber-50 border border-amber-100 rounded-xl p-4 flex gap-3">
                    <InformationCircleIcon class="w-6 h-6 text-amber-600 shrink-0" />
                    <p class="text-xs text-amber-700">The <strong>Key</strong> is used in the computation logic. Do not change existing keys unless you update the backend logic.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Rate Name</label>
                    <input v-model="form.name" type="text" required placeholder="e.g. Regular Holiday OT" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">System Key</label>
                    <input v-model="form.key" type="text" required :disabled="isEditing" placeholder="e.g. regular_ot" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono uppercase text-xs">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Multiplier Value (Rate)</label>
                    <input v-model="form.rate" type="number" step="0.01" min="0.01" @keypress="(e) => { if(e.key === '-') e.preventDefault(); }" required placeholder="1.25" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono text-lg font-bold text-blue-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Description</label>
                    <textarea v-model="form.description" rows="3" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm"></textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.is_active" id="is_active" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <label for="is_active" class="text-sm font-bold text-slate-700">Active (Used in computation)</label>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-50">
                    <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors mr-3">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-8 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        {{ isEditing ? 'Update Multiplier' : 'Save Multiplier' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Confirm Modal -->
        <ConfirmModal 
            :show="showConfirmModal" 
            :title="confirmTitle" 
            :message="confirmMessage" 
            @confirm="handleConfirm" 
            @cancel="handleCancel" 
        />

    </AppLayout>
</template>
