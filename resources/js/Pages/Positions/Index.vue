<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useErrorHandler } from '@/Composables/useErrorHandler';
import { useToast } from '@/Composables/useToast';
import { usePagination } from '@/Composables/usePagination';
import { 
    PlusIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    BriefcaseIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    positions: Object,
    filters: Object,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingPosition = ref(null);

const { confirm } = useConfirm();
const { destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();

const pagination = usePagination(props.positions, 'positions.index');

// Input Handlers
const handleAlphaUpperInput = (formObj, field, e) => {
    // Allow only letters and spaces, remove emojis, then convert to uppercase
    const val = e.target.value.replace(/[^a-zA-Z\s]/g, '').replace(/\p{Extended_Pictographic}/gu, '').toUpperCase();
    formObj[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const handleNoEmojiInput = (formObj, field, e) => {
    // Remove emojis
    const val = e.target.value.replace(/\p{Extended_Pictographic}/gu, '');
    formObj[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

onMounted(() => {
    pagination.updateData(props.positions);
});

watch(() => props.positions, (newPositions) => {
    pagination.updateData(newPositions);
}, { deep: true });

const form = useForm({
    name: '',
    rank: 'RankAndFile',
    description: '',
    has_late_policy: true,
});

const openCreateModal = () => {
    isEditing.value = false;
    editingPosition.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (position) => {
    isEditing.value = true;
    editingPosition.value = position;
    form.name = position.name;
    form.rank = position.rank;
    form.description = position.description;
    form.has_late_policy = !!position.has_late_policy;
    form.clearErrors();
    showModal.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('positions.update', editingPosition.value.id), {
            onSuccess: () => {
                showModal.value = false;
            },
            onError: (errors) => {
                 const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error';
                 showError(errorMessage);
            }
        });
    } else {
        form.post(route('positions.store'), {
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

const deletePosition = async (position) => {
    const confirmed = await confirm({
        title: 'Delete Position',
        message: `Are you sure you want to delete ${position.name}?`
    });
    
    if (confirmed) {
        destroy(route('positions.destroy', position.id), {
            onError: (errors) => showError('Failed to delete position')
        });
    }
};

const rankColors = {
    RankAndFile: 'bg-slate-100 text-slate-700',
    Supervisor: 'bg-blue-50 text-blue-700',
    Manager: 'bg-indigo-50 text-indigo-700',
    Executive: 'bg-purple-50 text-purple-700',
};
</script>

<template>
    <Head title="Positions - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Positions</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage job titles and ranks.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Position List"
                        subtitle="Available job positions"
                        search-placeholder="Search position name..."
                        empty-message="No positions found."
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
                                @click="openCreateModal"
                                class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                            >
                                <PlusIcon class="w-5 h-5" />
                                <span>New Position</span>
                            </button>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Name</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Rank</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Late Policy</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Description</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="position in data" :key="position.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-600 mr-4">
                                            <BriefcaseIcon class="w-5 h-5" />
                                        </div>
                                        <div class="text-sm font-bold text-slate-900">
                                            {{ position.name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                     <span :class="['inline-flex px-2.5 py-1 text-xs font-bold rounded-lg capitalize', rankColors[position.rank] || 'bg-slate-100 text-slate-500']">
                                        {{ position.rank }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span v-if="position.has_late_policy" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">
                                        Enabled
                                    </span>
                                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800">
                                        Disabled
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-normal">
                                    <div class="text-sm text-slate-600 max-w-md">{{ position.description || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                     <div class="flex justify-end space-x-1">
                                        <button
                                            @click="openEditModal(position)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit Position"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            @click="deletePosition(position)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Delete Position"
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
             
             <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-xl font-bold text-slate-900">{{ isEditing ? 'Edit Position' : 'New Position' }}</h3>
                    <p class="text-sm text-slate-500">{{ isEditing ? 'Update position details.' : 'Add a new job position.' }}</p>
                </div>
                
                <form @submit.prevent="submitForm" class="p-8">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Position Name</label>
                            <input :value="form.name" @input="handleAlphaUpperInput(form, 'name', $event)" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="e.g. SOFTWARE ENGINEER">
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Rank</label>
                            <select v-model="form.rank" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                <option value="RankAndFile">Rank & File</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Manager">Manager</option>
                                <option value="Executive">Executive</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-200">
                            <div>
                                <label class="block text-sm font-bold text-slate-700">Late Policy</label>
                                <p class="text-xs text-slate-500">Enable late and undertime deductions for this position.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.has_late_policy" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                            <textarea :value="form.description" @input="handleNoEmojiInput(form, 'description', $event)" rows="3" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="Optional description..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                            {{ isEditing ? 'Save Changes' : 'Create Position' }}
                        </button>
                    </div>
                </form>
             </div>
        </div>
    </AppLayout>
</template>
