<script setup>
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { 
    PlusIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    EyeIcon,
    AdjustmentsHorizontalIcon,
    MagnifyingGlassIcon
} from '@heroicons/vue/24/outline';
import { usePermission } from '@/Composables/usePermission';
import { useConfirm } from '@/Composables/useConfirm';

const props = defineProps({
    deductions: Object,
    employees: Array,
    deductionTypes: Array,
    filters: Object,
});

const { hasPermission } = usePermission();
const { confirm } = useConfirm();

// Search & Filter
const search = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || '');

watch([search, typeFilter], () => {
    router.get(
        route('deductions.index'),
        { search: search.value, type: typeFilter.value },
        { preserveState: true, preserveScroll: true, replace: true }
    );
});

// --- DEDUCTION MODAL ---
const showDeductionModal = ref(false);
const editingDeduction = ref(null);

// Employee Autocomplete State
const employeeSearch = ref('');
const showEmployeeDropdown = ref(false);

const filteredEmployees = computed(() => {
    if (!employeeSearch.value) return [];
    const query = employeeSearch.value.toLowerCase();
    return props.employees.filter(emp => 
        emp.name.toLowerCase().includes(query)
    ).slice(0, 10); // Limit results
});

const selectEmployee = (emp) => {
    deductionForm.employee_id = emp.id;
    employeeSearch.value = emp.name;
    showEmployeeDropdown.value = false;
};

const deductionForm = useForm({
    employee_id: '',
    deduction_type_id: '',
    calculation_type: 'fixed_amount',
    amount: '',
    frequency: 'semimonthly',
    schedule: 'both',
    total_amount: '',
    remaining_balance: '',
    terms: '', // Helper for UI
    effective_date: new Date().toISOString().split('T')[0],
    // end_date removed from UI, defaults to null
    status: 'active',
});

// Auto-calculation Logic
watch(() => deductionForm.terms, (newTerms) => {
    if (newTerms > 0 && deductionForm.total_amount > 0) {
        // Calculate Amount based on Terms
        const calculated = deductionForm.total_amount / newTerms;
        deductionForm.amount = parseFloat(calculated.toFixed(2));
    }
});

watch(() => deductionForm.amount, (newAmount) => {
    // Optional: Reverse calculate terms if amount is typed manually?
    // This can be tricky with rounding, so we prioritize the Terms -> Amount flow
    // or just display estimated terms.
    if (newAmount > 0 && deductionForm.total_amount > 0) {
        // Just an estimate for display if needed, but we won't force-update 'terms' 
        // to avoid circular loops while typing.
    }
});

const openDeductionModal = (deduction = null) => {
    editingDeduction.value = deduction;
    showEmployeeDropdown.value = false;
    
    if (deduction) {
        deductionForm.employee_id = deduction.employee_id;
        deductionForm.deduction_type_id = deduction.deduction_type_id;
        deductionForm.calculation_type = deduction.calculation_type;
        deductionForm.amount = deduction.amount;
        deductionForm.frequency = deduction.frequency;
        deductionForm.schedule = deduction.schedule;
        deductionForm.total_amount = deduction.total_amount;
        deductionForm.remaining_balance = deduction.remaining_balance;
        deductionForm.effective_date = deduction.effective_date ? deduction.effective_date.split('T')[0] : '';
        // Calculate initial terms for display
        if (deduction.total_amount > 0 && deduction.amount > 0) {
            deductionForm.terms = Math.round(deduction.total_amount / deduction.amount);
        } else {
            deductionForm.terms = '';
        }
        
        deductionForm.status = deduction.status;
        
        // Pre-fill employee search
        const emp = props.employees.find(e => e.id == deduction.employee_id);
        // employees prop is just {id, name} mapped from controller, so we use .name directly
        employeeSearch.value = emp ? emp.name : (deduction.employee?.user?.name || 'Unknown Employee');
    } else {
        deductionForm.reset();
        deductionForm.effective_date = new Date().toISOString().split('T')[0];
        employeeSearch.value = '';
    }
    showDeductionModal.value = true;
};

const closeDeductionModal = () => {
    showDeductionModal.value = false;
    deductionForm.reset();
    editingDeduction.value = null;
    employeeSearch.value = '';
};

const submitDeduction = () => {
    if (editingDeduction.value) {
        deductionForm.put(route('deductions.update', editingDeduction.value.id), {
            onSuccess: () => closeDeductionModal(),
        });
    } else {
        deductionForm.post(route('deductions.store'), {
            onSuccess: () => closeDeductionModal(),
        });
    }
};

const deleteDeduction = async (deduction) => {
    const confirmed = await confirm({
        title: 'Delete Deduction',
        message: `Are you sure you want to stop/delete this deduction for ${deduction.employee?.user?.name || 'this employee'}?`
    });

    if (confirmed) {
        router.delete(route('deductions.destroy', deduction.id));
    }
};

// --- TYPE MODAL ---
const showTypeModal = ref(false);
const showManageTypesModal = ref(false); 
const editingType = ref(null);

const typeForm = useForm({
    name: '',
    description: '',
    is_active: true,
});

const openTypeModal = (type = null) => {
    editingType.value = type;
    if (type) {
        typeForm.name = type.name;
        typeForm.description = type.description || '';
        typeForm.is_active = !!type.is_active;
    } else {
        typeForm.reset();
        typeForm.is_active = true;
    }
    showTypeModal.value = true;
};

const closeTypeModal = () => {
    showTypeModal.value = false;
    typeForm.reset();
    editingType.value = null;
};

const submitType = () => {
    if (editingType.value) {
        typeForm.put(route('deduction-types.update', editingType.value.id), {
            onSuccess: () => {
                closeTypeModal();
            },
        });
    } else {
        typeForm.post(route('deduction-types.store'), {
            onSuccess: () => {
                closeTypeModal();
            },
        });
    }
};

const deleteType = async (type) => {
    const confirmed = await confirm({
        title: 'Delete Deduction Type',
        message: 'Are you sure? This might affect existing deductions associated with this type.'
    });

    if (confirmed) {
        router.delete(route('deduction-types.destroy', type.id));
    }
};

const openManageTypes = () => {
    showManageTypesModal.value = true;
};

// Helpers
const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Other Deductions" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Other Deductions</h2>
                    <div class="flex gap-2">
                        <SecondaryButton 
                            v-if="hasPermission('deductions.create')"
                            @click="openManageTypes"
                        >
                            <AdjustmentsHorizontalIcon class="w-4 h-4 mr-2" />
                            Manage Types
                        </SecondaryButton>
                        <PrimaryButton 
                            v-if="hasPermission('deductions.create')"
                            @click="openDeductionModal()"
                        >
                            <PlusIcon class="w-4 h-4 mr-2" />
                            Add Deduction
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Filters & Content -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        
                        <!-- Search & Filter Bar -->
                        <div class="flex flex-col sm:flex-row gap-4 mb-6">
                            <div class="relative flex-1">
                                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                <TextInput
                                    v-model="search"
                                    placeholder="Search employee..."
                                    class="w-full pl-10"
                                />
                            </div>
                            <div class="w-full sm:w-64">
                                <select 
                                    v-model="typeFilter"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full"
                                >
                                    <option value="">All Types</option>
                                    <option v-for="t in deductionTypes" :key="t.id" :value="t.id">
                                        {{ t.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount / Schedule</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="deductions.data.length === 0">
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            No deductions found.
                                        </td>
                                    </tr>
                                    <tr v-for="d in deductions.data" :key="d.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">{{ d.employee?.user?.name }}</div>
                                            <div class="text-xs text-gray-500">{{ d.employee?.employee_code }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ d.deduction_type?.name }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="font-semibold text-gray-700">
                                                {{ formatCurrency(d.amount) }}
                                            </div>
                                            <div class="text-xs">
                                                {{ d.frequency === 'semimonthly' ? 'Twice a month' : 'Once a month' }}
                                                <span class="text-gray-400">({{ d.schedule === 'both' ? '15th & 30th' : (d.schedule === 'first_half' ? '15th' : '30th') }})</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div v-if="d.total_amount > 0">
                                                <div class="font-medium text-gray-900">{{ formatCurrency(d.remaining_balance) }}</div>
                                                <div class="text-xs text-gray-500">of {{ formatCurrency(d.total_amount) }}</div>
                                            </div>
                                            <div v-else class="text-gray-400">-</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span 
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="{
                                                    'bg-green-100 text-green-800': d.status === 'active',
                                                    'bg-gray-100 text-gray-800': d.status === 'completed',
                                                    'bg-red-100 text-red-800': d.status === 'cancelled'
                                                }"
                                            >
                                                {{ d.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link 
                                                v-if="hasPermission('deductions.view')"
                                                :href="route('deductions.show', d.id)" 
                                                class="text-blue-600 hover:text-blue-900 mr-3 inline-block"
                                                title="View Details"
                                            >
                                                <EyeIcon class="w-5 h-5" />
                                            </Link>
                                            <button 
                                                v-if="hasPermission('deductions.edit') && !(d.total_amount > 0 && d.remaining_balance < d.total_amount)"
                                                @click="openDeductionModal(d)" 
                                                class="text-indigo-600 hover:text-indigo-900 mr-3 inline-block"
                                                title="Edit Assignment"
                                            >
                                                <PencilSquareIcon class="w-5 h-5" />
                                            </button>
                                            <button 
                                                v-if="hasPermission('deductions.delete')"
                                                @click="deleteDeduction(d)" 
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4 flex justify-end" v-if="deductions.links.length > 3">
                            <div class="flex gap-1">
                                <template v-for="(link, k) in deductions.links" :key="k">
                                    <div
                                        v-if="link.url === null"
                                        class="px-4 py-2 text-sm text-gray-500 border rounded-md"
                                        v-html="link.label"
                                    />
                                    <Link
                                        v-else
                                        class="px-4 py-2 text-sm border rounded-md transition-colors"
                                        :class="{ 'bg-indigo-50 border-indigo-500 text-indigo-700': link.active, 'bg-white hover:bg-gray-50 text-gray-700': !link.active }"
                                        :href="link.url"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- MANAGE TYPES MODAL -->
        <Modal :show="showManageTypesModal" @close="showManageTypesModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Manage Deduction Types</h3>
                    <PrimaryButton @click="openTypeModal()">New Type</PrimaryButton>
                </div>
                
                <div class="overflow-y-auto max-h-[60vh] border rounded-md">
                     <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="type in deductionTypes" :key="type.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ type.name }}
                                    <div class="text-xs text-gray-500 font-normal">{{ type.description }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span :class="type.is_active ? 'text-green-600' : 'text-gray-400'">{{ type.is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    <button @click="openTypeModal(type)" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</button>
                                    <button @click="deleteType(type)" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                             <tr v-if="deductionTypes.length === 0">
                                <td colspan="3" class="px-4 py-3 text-center text-sm text-gray-500">No types defined.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showManageTypesModal = false">Close</SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- CREATE/EDIT TYPE MODAL -->
        <Modal :show="showTypeModal" maxWidth="md" @close="closeTypeModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ editingType ? 'Edit Type' : 'Create Deduction Type' }}</h3>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Name" />
                        <TextInput v-model="typeForm.name" class="w-full mt-1" />
                        <InputError :message="typeForm.errors.name" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Description" />
                        <TextInput v-model="typeForm.description" class="w-full mt-1" />
                        <InputError :message="typeForm.errors.description" class="mt-2" />
                    </div>
                    <div class="flex items-center">
                         <input 
                            type="checkbox" 
                            id="is_active" 
                            v-model="typeForm.is_active"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        >
                        <label for="is_active" class="ml-2 text-sm text-gray-600">Active</label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeTypeModal">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitType" :disabled="typeForm.processing">Save</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- ASSIGN DEDUCTION MODAL -->
        <Modal :show="showDeductionModal" @close="closeDeductionModal">
             <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ editingDeduction ? 'Edit Deduction Assignment' : 'Assign New Deduction' }}</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Employee Autocomplete -->
                    <div class="col-span-2 relative">
                        <InputLabel value="Employee" />
                        <div class="relative">
                            <TextInput
                                v-model="employeeSearch"
                                @focus="showEmployeeDropdown = true"
                                @input="showEmployeeDropdown = true"
                                placeholder="Search employee by name..."
                                class="w-full mt-1"
                                :disabled="!!editingDeduction"
                            />
                            <div v-if="showEmployeeDropdown && filteredEmployees.length > 0" 
                                 class="absolute z-10 w-full bg-white border border-gray-200 rounded-md shadow-lg mt-1 max-h-48 overflow-y-auto">
                                <div 
                                    v-for="emp in filteredEmployees" 
                                    :key="emp.id"
                                    @click="selectEmployee(emp)"
                                    class="px-4 py-2 hover:bg-indigo-50 cursor-pointer text-sm text-gray-700"
                                >
                                    {{ emp.name }}
                                </div>
                            </div>
                            <!-- Validation hidden input -->
                             <input type="hidden" :value="deductionForm.employee_id">
                        </div>
                        <InputError :message="deductionForm.errors.employee_id" class="mt-2" />
                        <p v-if="editingDeduction" class="text-xs font-semibold text-blue-600 mt-1">
                                Selected: {{ employeeSearch }}
                            </p>
                    </div>

                    <!-- Type -->
                    <div class="col-span-2 md:col-span-1">
                        <InputLabel value="Deduction Type" />
                        <select 
                            v-model="deductionForm.deduction_type_id" 
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            :disabled="!!editingDeduction"
                        >
                            <option value="" disabled>Select Type</option>
                            <option v-for="t in deductionTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                         <InputError :message="deductionForm.errors.deduction_type_id" class="mt-2" />
                    </div>

                    <!-- Amount -->
                    <div class="col-span-2 md:col-span-1">
                         <InputLabel value="Amount per Deduction (PHP)" />
                        <TextInput type="number" step="0.01" v-model="deductionForm.amount" class="w-full mt-1" />
                        <InputError :message="deductionForm.errors.amount" class="mt-2" />
                    </div>

                     <!-- Frequency -->
                    <div class="col-span-2 md:col-span-1">
                        <InputLabel value="Frequency" />
                        <select 
                            v-model="deductionForm.frequency" 
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        >
                            <option value="semimonthly">Twice a Month (Semimonthly)</option>
                            <option value="once_a_month">Once a Month</option>
                        </select>
                        <InputError :message="deductionForm.errors.frequency" class="mt-2" />
                    </div>

                    <!-- Schedule -->
                     <div class="col-span-2 md:col-span-1">
                        <InputLabel value="Schedule" />
                        <select 
                            v-model="deductionForm.schedule" 
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        >
                            <option value="both" v-if="deductionForm.frequency === 'semimonthly'">Both (15th & 30th)</option>
                            <option value="first_half">First Half (15th)</option>
                            <option value="second_half">Second Half (30th)</option>
                        </select>
                        <div class="text-xs text-gray-500 mt-1 space-y-1">
                            <p v-if="deductionForm.schedule === 'first_half'">• Deducted ONLY on the <strong>15th</strong> payroll.</p>
                            <p v-if="deductionForm.schedule === 'second_half'">• Deducted ONLY on the <strong>30th</strong> payroll.</p>
                            <p v-if="deductionForm.schedule === 'both'">• Deducted on <strong>BOTH</strong> payrolls (e.g. 500 on 15th, 500 on 30th).</p>
                        </div>
                         <InputError :message="deductionForm.errors.schedule" class="mt-2" />
                    </div>

                    <!-- Total Amount (Loan) -->
                    <div class="col-span-2 md:col-span-1">
                         <InputLabel value="Total Amount (Optional)" />
                         <TextInput type="number" step="0.01" v-model="deductionForm.total_amount" class="w-full mt-1" placeholder="For loans/limited deductions" />
                         <p class="text-xs text-gray-500 mt-1">Leave empty for continuous deductions</p>
                         <InputError :message="deductionForm.errors.total_amount" class="mt-2" />
                    </div>

                    <!-- Terms / Installments (Helper) -->
                    <div class="col-span-2 md:col-span-1" v-if="deductionForm.total_amount > 0">
                         <InputLabel value="Terms (Installments)" />
                         <TextInput type="number" step="1" v-model="deductionForm.terms" class="w-full mt-1" placeholder="e.g. 12" />
                         <p class="text-xs text-gray-500 mt-1">Auto-calculates the deduction amount.</p>
                    </div>

                    <!-- Remaining Balance (Edit Only) -->
                    <div class="col-span-2 md:col-span-1" v-if="editingDeduction && deductionForm.total_amount > 0">
                         <InputLabel value="Current Balance" />
                         <TextInput type="number" step="0.01" v-model="deductionForm.remaining_balance" class="w-full mt-1" />
                         <p class="text-xs text-gray-500 mt-1">Adjust if necessary.</p>
                         <InputError :message="deductionForm.errors.remaining_balance" class="mt-2" />
                    </div>
                    
                    <!-- Dates -->
                     <div class="col-span-2 md:col-span-1">
                         <InputLabel value="Effective Start Date" />
                         <TextInput type="date" v-model="deductionForm.effective_date" class="w-full mt-1" />
                         <InputError :message="deductionForm.errors.effective_date" class="mt-2" />
                    </div>

                    <!-- Status -->
                     <div class="col-span-2 md:col-span-1" v-if="editingDeduction">
                        <InputLabel value="Status" />
                        <select 
                            v-model="deductionForm.status" 
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        >
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                         <InputError :message="deductionForm.errors.status" class="mt-2" />
                    </div>

                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeductionModal">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitDeduction" :disabled="deductionForm.processing">Save Assignment</PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
