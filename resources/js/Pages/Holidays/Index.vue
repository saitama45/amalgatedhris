<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useErrorHandler } from '@/Composables/useErrorHandler';
import { useToast } from '@/Composables/useToast';
import { usePagination } from '@/Composables/usePagination';
import { usePermission } from '@/Composables/usePermission';
import { 
    PlusIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    CalendarDaysIcon,
    XMarkIcon,
    ArrowPathIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    holidays: Object,
    filters: Object,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingHoliday = ref(null);

const { confirm } = useConfirm();
const { destroy } = useErrorHandler();
const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

const pagination = usePagination(props.holidays, 'holidays.index');

onMounted(() => {
    pagination.updateData(props.holidays);
});

watch(() => props.holidays, (newHolidays) => {
    pagination.updateData(newHolidays);
}, { deep: true });

const form = useForm({
    name: '',
    date: '',
    type: 'Regular',
    is_recurring: true,
    description: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    editingHoliday.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (holiday) => {
    isEditing.value = true;
    editingHoliday.value = holiday;
    form.name = holiday.name;
    form.date = holiday.date ? holiday.date.split('T')[0] : '';
    form.type = holiday.type;
    form.is_recurring = Boolean(holiday.is_recurring);
    form.description = holiday.description;
    form.clearErrors();
    showModal.value = true;
};

const submitForm = () => {
    // Date Validation: Prevent past dates using local time comparison
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const [y, m, d] = form.date.split('-').map(Number);
    const selectedDate = new Date(y, m - 1, d);

    if (selectedDate < today) {
        showError('Holiday date cannot be in the past.');
        return;
    }

    if (isEditing.value) {
        form.put(route('holidays.update', editingHoliday.value.id), {
            onSuccess: () => {
                showModal.value = false;
            },
            onError: () => showError('Failed to update holiday')
        });
    } else {
        form.post(route('holidays.store'), {
            onSuccess: () => {
                showModal.value = false;
            },
            onError: () => showError('Failed to add holiday')
        });
    }
};

const isSyncing = ref(false);
const syncHolidays = async () => {
    const year = new Date().getFullYear();
    const confirmed = await confirm({
        title: 'Sync National Holidays',
        message: `This will fetch official Philippines holidays for ${year}. New ones will be added, existing ones will be skipped. Proceed?`,
        confirmButtonText: 'Sync Now'
    });

    if (!confirmed) return;

    isSyncing.value = true;
    router.post(route('holidays.sync'), { year }, {
        onFinish: () => {
            isSyncing.value = false;
        },
        onError: () => showError('Failed to sync holidays.')
    });
};

const deleteHoliday = async (holiday) => {
    const confirmed = await confirm({
        title: 'Delete Holiday',
        message: `Are you sure you want to delete "${holiday.name}"?`
    });
    
    if (confirmed) {
        destroy(route('holidays.destroy', holiday.id), {
            onSuccess: () => showSuccess('Holiday deleted successfully'),
            onError: () => showError('Failed to delete holiday')
        });
    }
};

const typeColors = {
    'Regular': 'bg-purple-50 text-purple-700 border-purple-100',
    'Special Non-Working': 'bg-amber-50 text-amber-700 border-amber-100',
    'Special Working': 'bg-blue-50 text-blue-700 border-blue-100',
    'Local/Declared': 'bg-emerald-50 text-emerald-700 border-emerald-100',
};

const minDate = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD format
</script>

<template>
    <Head title="Holidays - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Holiday Management</h2>
                    <p class="text-sm text-slate-500 mt-1">Configure national and local holidays for payroll computation.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Holiday Calendar"
                        subtitle="Upcoming and past holidays"
                        search-placeholder="Search holiday name..."
                        empty-message="No holidays found."
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
                            <div class="flex gap-2">
                                <button
                                    v-if="hasPermission('holidays.create')"
                                    @click="syncHolidays"
                                    :disabled="isSyncing"
                                    class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2.5 rounded-xl transition-all font-bold text-sm flex items-center shadow-sm disabled:opacity-50"
                                >
                                    <ArrowPathIcon :class="['w-4 h-4 mr-2', isSyncing ? 'animate-spin' : '']" />
                                    {{ isSyncing ? 'Syncing...' : 'Sync National Holidays' }}
                                </button>
                                <button
                                    v-if="hasPermission('holidays.create')"
                                    @click="openCreateModal"
                                    class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                                >
                                    <PlusIcon class="w-5 h-5" />
                                    <span>New Holiday</span>
                                </button>
                            </div>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Holiday Name</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Type</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="holiday in data" :key="holiday.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 mr-4 font-bold text-xs">
                                            {{ new Date(holiday.date).getDate() }}
                                        </div>
                                        <div class="text-sm font-bold text-slate-900">
                                            {{ new Date(holiday.date).toLocaleDateString('en-US', { month: 'long', year: 'numeric', weekday: 'short' }) }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-slate-900">{{ holiday.name }}</div>
                                    <div class="text-xs text-slate-500">{{ holiday.description || (holiday.is_recurring ? 'Recurring Annual' : 'One-time') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['inline-flex px-2.5 py-1 text-xs font-bold rounded-lg border', typeColors[holiday.type] || 'bg-slate-100 text-slate-500']">
                                        {{ holiday.type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                     <div class="flex justify-end space-x-1">
                                        <button
                                            v-if="hasPermission('holidays.edit')"
                                            @click="openEditModal(holiday)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            v-if="hasPermission('holidays.delete')"
                                            @click="deleteHoliday(holiday)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Delete"
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
        <Modal :show="showModal" @close="showModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Holiday' : 'New Holiday' }}</h3>
                </div>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submitForm" class="p-6">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Holiday Name</label>
                        <input v-model="form.name" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="e.g. Quezon City Day">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Date</label>
                        <input v-model="form.date" type="date" :min="minDate" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Holiday Type</label>
                        <select v-model="form.type" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            <option value="Regular">Regular Holiday (100% + 100%)</option>
                            <option value="Special Non-Working">Special Non-Working (No work, No pay / 30%)</option>
                            <option value="Special Working">Special Working (Work with regular pay)</option>
                            <option value="Local/Declared">Local / Declared (Mayor/President)</option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-2">
                        <input v-model="form.is_recurring" id="is_recurring" type="checkbox" class="w-5 h-5 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500 focus:ring-2 transition-all cursor-pointer">
                        <label for="is_recurring" class="text-sm font-bold text-slate-700 cursor-pointer">Recurring Annual Holiday</label>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                        <textarea v-model="form.description" rows="2" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                    <button type="button" @click="showModal = false" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        {{ isEditing ? 'Save Changes' : 'Add Holiday' }}
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
