<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Autocomplete from '@/Components/Autocomplete.vue';
import { usePermission } from '@/Composables/usePermission';
import { 
    ChevronLeftIcon, 
    PlusIcon, 
    TrashIcon,
    Bars3BottomLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    salf: Object,
    employees: Array,
    isPortal: Boolean,
});

const { hasPermission } = usePermission();
const canManageSalf = hasPermission('salf.edit');

const getBackRoute = () => {
    return props.isPortal ? route('portal.salf') : route('salf.index');
};

const employeeOptions = computed(() => {
    return props.employees.map(emp => ({
        id: emp.id,
        fullName: `${emp.first_name} ${emp.last_name}`
    }));
});

const form = useForm({
    employee_id: props.salf.employee_id,
    department_id: props.salf.department_id,
    company_id: props.salf.company_id,
    period_covered: props.salf.period_covered,
    approved_by: props.salf.approved_by,
    status: props.salf.status,
    items: props.salf.items.map(item => ({
        id: item.id,
        is_header: item.is_header,
        section: item.section || '',
        area_of_concern: item.area_of_concern,
        action_plan: item.action_plan,
        support_group: item.support_group || '',
        target_date: item.target_date ? item.target_date.split('T')[0] : '',
        actual_value: item.actual_value,
        target_value: item.target_value,
        remarks: item.remarks || ''
    }))
});

watch(() => form.employee_id, (newVal) => {
    const employee = props.employees.find(e => e.id == newVal);
    if (employee) {
        // Handle both direct and nested relationships from different possible eager loads
        form.department_id = employee.active_employment_record?.department_id || employee.department_id;
        form.company_id = employee.active_employment_record?.company_id || employee.company_id;
    }
});

const addItem = (isHeader = false) => {
    form.items.push({ 
        is_header: isHeader, 
        section: '', 
        area_of_concern: '', 
        action_plan: '', 
        support_group: '', 
        target_date: '', 
        actual_value: isHeader ? null : 0, 
        target_value: isHeader ? null : 100, 
        remarks: '' 
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    form.put(route('salf.update', props.salf.id));
};
</script>

<template>
    <Head title="Edit SALF - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="getBackRoute()" class="p-2 bg-white rounded-xl shadow-sm border border-slate-100 text-slate-400 hover:text-blue-600 transition-all">
                    <ChevronLeftIcon class="w-6 h-6" />
                </Link>
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Edit Strategic Action Layout Form</h2>
                    <p class="text-sm text-slate-500 mt-1">Modify your strategic goals and progress.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <!-- Header Info -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 mb-6 overflow-hidden relative">
                        <div class="relative grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                            <div v-if="!isPortal">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Employee</label>
                                <Autocomplete 
                                    v-model="form.employee_id"
                                    :options="employeeOptions"
                                    label-key="fullName"
                                    value-key="id"
                                    placeholder="Search employee..."
                                    :disabled="!canManageSalf"
                                />
                                <div v-if="form.errors.employee_id" class="text-red-500 text-xs mt-1">{{ form.errors.employee_id }}</div>
                            </div>
                            <div v-else>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Employee</label>
                                <div class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700">
                                    {{ salf.employee.first_name }} {{ salf.employee.last_name }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Period Covered</label>
                                <input v-model="form.period_covered" type="text" class="w-full bg-slate-50 border-slate-200 rounded-xl focus:ring-blue-600 focus:border-blue-600 transition-all text-sm font-semibold" />
                                <div v-if="form.errors.period_covered" class="text-red-500 text-xs mt-1">{{ form.errors.period_covered }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Approved By</label>
                                <input v-model="form.approved_by" type="text" class="w-full bg-slate-50 border-slate-200 rounded-xl focus:ring-blue-600 focus:border-blue-600 transition-all text-sm font-semibold" />
                                <div v-if="form.errors.approved_by" class="text-red-500 text-xs mt-1">{{ form.errors.approved_by }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Status</label>
                                <select v-model="form.status" class="w-full bg-slate-50 border-slate-200 rounded-xl focus:ring-blue-600 focus:border-blue-600 transition-all text-sm font-semibold">
                                    <option value="draft">Draft</option>
                                    <option value="submitted">Submitted</option>
                                    <option value="approved">Approved</option>
                                </select>
                                <div v-if="form.errors.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
                        <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-blue-100 rounded-lg text-blue-600">
                                    <Bars3BottomLeftIcon class="w-5 h-5" />
                                </div>
                                <h3 class="font-bold text-slate-800">Action Plan Items</h3>
                            </div>
                            <div class="flex items-center space-x-4">
                                <button 
                                    type="button" 
                                    @click="addItem(true)"
                                    class="flex items-center space-x-2 text-sm font-bold text-indigo-600 hover:text-indigo-700 transition-colors"
                                >
                                    <SparklesIcon class="w-5 h-5" />
                                    <span>Add Topic/Header</span>
                                </button>
                                <button 
                                    type="button" 
                                    @click="addItem(false)"
                                    class="flex items-center space-x-2 text-sm font-bold text-blue-600 hover:text-blue-700 transition-colors"
                                >
                                    <PlusIcon class="w-5 h-5" />
                                    <span>Add New Item</span>
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left bg-slate-50 border-b border-slate-100">
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-40">Type/Section</th>
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-64">Area of Concern</th>
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-72">Action Plan</th>
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-32">Support</th>
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-40">Target Date</th>
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-24">Actual</th>
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-24">Target</th>
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-48">Remarks</th>
                                        <th class="px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-widest w-16 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in form.items" :key="index" 
                                        :class="['border-b border-slate-50 transition-colors', item.is_header ? 'bg-slate-100/50' : 'hover:bg-slate-50/30']"
                                    >
                                        <td class="p-2">
                                            <div class="flex items-center space-x-2">
                                                <div :class="['w-1 h-6 rounded-full', item.is_header ? 'bg-indigo-500' : 'bg-transparent']"></div>
                                                <input v-model="item.section" type="text" :placeholder="item.is_header ? 'TOPIC NAME' : 'Section'" 
                                                    :class="['w-full text-xs border-slate-100 rounded-lg focus:ring-blue-600 bg-transparent font-bold', item.is_header ? 'uppercase text-indigo-700' : 'font-medium']" 
                                                />
                                            </div>
                                        </td>
                                        
                                        <template v-if="!item.is_header">
                                            <td class="p-2">
                                                <textarea v-model="item.area_of_concern" rows="2" class="w-full text-xs border-slate-100 rounded-lg focus:ring-blue-600 bg-transparent font-medium"></textarea>
                                            </td>
                                            <td class="p-2">
                                                <textarea v-model="item.action_plan" rows="2" class="w-full text-xs border-slate-100 rounded-lg focus:ring-blue-600 bg-transparent font-medium"></textarea>
                                            </td>
                                            <td class="p-2">
                                                <input v-model="item.support_group" type="text" class="w-full text-xs border-slate-100 rounded-lg focus:ring-blue-600 bg-transparent font-medium" />
                                            </td>
                                            <td class="p-2">
                                                <input v-model="item.target_date" type="date" class="w-full text-xs border-slate-100 rounded-lg focus:ring-blue-600 bg-transparent font-medium" />
                                            </td>
                                            <td class="p-2">
                                                <input v-model="item.actual_value" type="number" step="0.01" class="w-full text-xs border-slate-100 rounded-lg focus:ring-blue-600 bg-transparent font-bold text-emerald-600" />
                                            </td>
                                            <td class="p-2">
                                                <input v-model="item.target_value" type="number" step="0.01" class="w-full text-xs border-slate-100 rounded-lg focus:ring-blue-600 bg-transparent font-bold text-blue-600" />
                                            </td>
                                            <td class="p-2">
                                                <input v-model="item.remarks" type="text" class="w-full text-xs border-slate-100 rounded-lg focus:ring-blue-600 bg-transparent font-medium" />
                                            </td>
                                        </template>
                                        <template v-else>
                                            <td colspan="7" class="p-2">
                                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-center py-2">Section Header / Topic Row</div>
                                            </td>
                                        </template>

                                        <td class="p-2 text-center">
                                            <button @click="removeItem(index)" type="button" class="p-2 text-slate-300 hover:text-red-600 transition-colors" title="Remove row">
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4">
                         <Link :href="getBackRoute()" class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all text-sm">
                            Cancel
                        </Link>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-8 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-bold shadow-lg shadow-blue-600/20 hover:from-blue-700 hover:to-indigo-700 transition-all text-sm disabled:opacity-50"
                        >
                            <span v-if="form.processing">Updating...</span>
                            <span v-else>Update SALF</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
