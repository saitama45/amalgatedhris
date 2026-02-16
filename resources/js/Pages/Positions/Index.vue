<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import PositionModal from '@/Components/PositionModal.vue';
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
const editingPosition = ref(null);

const { confirm } = useConfirm();
const { destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();

const pagination = usePagination(props.positions, 'positions.index');

onMounted(() => {
    pagination.updateData(props.positions);
});

watch(() => props.positions, (newPositions) => {
    pagination.updateData(newPositions);
}, { deep: true });

const openCreateModal = () => {
    editingPosition.value = null;
    showModal.value = true;
};

const openEditModal = (position) => {
    editingPosition.value = position;
    showModal.value = true;
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
        
        <PositionModal 
            :show="showModal" 
            :position="editingPosition"
            @close="showModal = false"
        />
    </AppLayout>
</template>
