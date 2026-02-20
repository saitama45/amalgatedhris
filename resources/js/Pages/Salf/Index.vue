<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { 
    PlusIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    EyeIcon,
    DocumentArrowDownIcon,
    ClipboardDocumentCheckIcon
} from '@heroicons/vue/24/outline';
import { usePermission } from '@/Composables/usePermission';

const props = defineProps({
    forms: Object,
    filters: Object,
    isPortal: Boolean,
});

const { confirm } = useConfirm();
const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();

const getRoute = (name, params = {}) => {
    if (props.isPortal) {
        if (name === 'salf.index') return route('portal.salf', params);
        params.portal = 1;
    }
    return route(name, params);
};

const deleteForm = async (form) => {
    const confirmed = await confirm({
        title: 'Delete SALF',
        message: `Are you sure you want to delete this SALF for ${form.employee.first_name} ${form.employee.last_name}?`
    });
    
    if (confirmed) {
        router.delete(route('salf.destroy', form.id), {
            onSuccess: () => showSuccess('SALF deleted successfully'),
            onError: () => showError('Failed to delete SALF')
        });
    }
};

const getStatusColor = (status) => {
    switch (status) {
        case 'approved': return 'bg-green-100 text-green-700 border-green-200';
        case 'submitted': return 'bg-blue-100 text-blue-700 border-blue-200';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <Head title="Strategic Action Layout Form (SALF) - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">{{ isPortal ? 'My SALF' : 'Employee SALF' }}</h2>
                    <p class="text-sm text-slate-500 mt-1">Strategic Action Layout Form - Monitoring goals and target deadlines.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="SALF Records"
                        subtitle="Manage strategic action plans"
                        search-placeholder="Search employee name..."
                        empty-message="No SALF records found."
                        :search="filters.search"
                        :data="forms.data"
                        :current-page="forms.current_page"
                        :last-page="forms.last_page"
                        :per-page="forms.per_page"
                        @update:search="(val) => router.get(getRoute('salf.index'), { search: val }, { preserveState: true })"
                        @go-to-page="(page) => router.get(getRoute('salf.index'), { page, search: filters.search })"
                    >
                         <template #actions>
                            <Link
                                v-if="hasPermission('salf.create') || hasPermission('portal.salf')"
                                :href="getRoute('salf.create')"
                                class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-5 py-2.5 rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 flex items-center space-x-2 text-sm font-semibold shadow-lg shadow-blue-600/20"
                            >
                                <PlusIcon class="w-5 h-5" />
                                <span>Create New SALF</span>
                            </Link>
                        </template>

                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Period</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Department/Company</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="form in data" :key="form.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 mr-4 font-bold">
                                            {{ form.employee?.first_name?.[0] || '?' }}{{ form.employee?.last_name?.[0] || '?' }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-slate-900">
                                                {{ form.employee?.first_name }} {{ form.employee?.last_name }}
                                            </div>
                                            <div class="text-xs text-slate-500 font-medium">
                                                {{ form.employee?.position?.name || 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 font-medium">
                                    {{ form.period_covered }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-700 font-semibold">{{ form.department?.name || form.employee?.department?.name || '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ form.company?.name || form.employee?.company?.name || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider border', getStatusColor(form.status)]">
                                        {{ form.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                     <div class="flex justify-end space-x-1">
                                        <Link
                                            :href="getRoute('salf.show', { salf: form.id })"
                                            class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                                            title="View SALF"
                                        >
                                            <EyeIcon class="w-5 h-5" />
                                        </Link>
                                        <Link
                                            v-if="hasPermission('salf.edit') || hasPermission('portal.salf')"
                                            :href="getRoute('salf.edit', { salf: form.id })"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit SALF"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </Link>
                                        <a
                                            :href="route('salf.export-pdf', form.id)"
                                            class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all"
                                            title="Export PDF"
                                            target="_blank"
                                        >
                                            <DocumentArrowDownIcon class="w-5 h-5" />
                                        </a>
                                        <button
                                            v-if="hasPermission('salf.delete') || (hasPermission('portal.salf') && form.status === 'draft')"
                                            @click="deleteForm(form)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Delete SALF"
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
    </AppLayout>
</template>
