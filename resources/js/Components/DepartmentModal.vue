<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useToast } from '@/Composables/useToast';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: Boolean,
    department: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close', 'success']);

const { showSuccess, showError } = useToast();
const isEditing = ref(false);

const form = useForm({
    name: '',
    department_code: '',
    oms_code: '',
    description: '',
});

// Input Handlers
const handleAlphaUpperInput = (field, e) => {
    const val = e.target.value.replace(/[^a-zA-Z\s]/g, '').replace(/\p{Extended_Pictographic}/gu, '').toUpperCase();
    form[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const handleCodeInput = (field, e) => {
    const val = e.target.value.replace(/[^a-zA-Z0-9-]/g, '').toUpperCase();
    form[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const handleNoEmojiInput = (field, e) => {
    const val = e.target.value.replace(/\p{Extended_Pictographic}/gu, '');
    form[field] = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.department) {
            isEditing.value = true;
            form.name = props.department.name;
            form.department_code = props.department.department_code;
            form.oms_code = props.department.oms_code;
            form.description = props.department.description;
        } else {
            isEditing.value = false;
            form.reset();
        }
        form.clearErrors();
    }
});

const submit = () => {
    const url = isEditing.value ? route('departments.update', props.department.id) : route('departments.store');
    const method = isEditing.value ? 'put' : 'post';

    form[method](url, {
        onSuccess: (page) => {
            showSuccess(`Department ${isEditing.value ? 'updated' : 'created'} successfully.`);
            emit('success', page.props);
            emit('close');
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'Validation error';
            showError(errorMessage);
        }
    });
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="lg">
        <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <div>
                <h3 class="text-xl font-bold text-slate-900">{{ isEditing ? 'Edit Department' : 'New Department' }}</h3>
                <p class="text-sm text-slate-500">{{ isEditing ? 'Update department details.' : 'Add a new department.' }}</p>
            </div>
            <button @click="emit('close')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <XMarkIcon class="w-6 h-6" />
            </button>
        </div>
        
        <form @submit.prevent="submit" class="p-8">
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Department Name</label>
                    <input 
                        :value="form.name" 
                        @input="handleAlphaUpperInput('name', $event)" 
                        type="text" 
                        required 
                        class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-900 font-semibold" 
                        placeholder="e.g. INFORMATION TECHNOLOGY"
                    >
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Dept Code</label>
                        <input 
                            :value="form.department_code" 
                            @input="handleCodeInput('department_code', $event)" 
                            type="text" 
                            class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-900 font-mono" 
                            placeholder="IT-01"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">OMS Code</label>
                        <input 
                            :value="form.oms_code" 
                            @input="handleCodeInput('oms_code', $event)" 
                            type="text" 
                            class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-900 font-mono" 
                            placeholder="OMS-123"
                        >
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                    <textarea 
                        :value="form.description" 
                        @input="handleNoEmojiInput('description', $event)" 
                        rows="3" 
                        class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-900" 
                        placeholder="Optional description..."
                    ></textarea>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                <button type="button" @click="emit('close')" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all flex items-center">
                    <span v-if="form.processing" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
                    {{ isEditing ? 'Save Changes' : 'Create Department' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
