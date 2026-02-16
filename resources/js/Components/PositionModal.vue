<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useToast } from '@/Composables/useToast';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: Boolean,
    position: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close', 'success']);

const { showSuccess, showError } = useToast();
const isEditing = ref(false);

const form = useForm({
    name: '',
    rank: 'RankAndFile',
    description: '',
    has_late_policy: true,
});

// Input Handlers
const handleAlphaUpperInput = (field, e) => {
    const val = e.target.value.replace(/[^a-zA-Z\s]/g, '').replace(/\p{Extended_Pictographic}/gu, '').toUpperCase();
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
        if (props.position) {
            isEditing.value = true;
            form.name = props.position.name;
            form.rank = props.position.rank;
            form.description = props.position.description;
            form.has_late_policy = !!props.position.has_late_policy;
        } else {
            isEditing.value = false;
            form.reset();
        }
        form.clearErrors();
    }
});

const submit = () => {
    const url = isEditing.value ? route('positions.update', props.position.id) : route('positions.store');
    const method = isEditing.value ? 'put' : 'post';

    form[method](url, {
        onSuccess: (page) => {
            showSuccess(`Position ${isEditing.value ? 'updated' : 'created'} successfully.`);
            // Extract the newly created/updated position from flash or response if possible, 
            // but usually Inertia just reloads the props.
            // We pass page.props.options.positions or similar if needed.
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
                <h3 class="text-xl font-bold text-slate-900">{{ isEditing ? 'Edit Position' : 'New Position' }}</h3>
                <p class="text-sm text-slate-500">{{ isEditing ? 'Update position details.' : 'Add a new job position.' }}</p>
            </div>
            <button @click="emit('close')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <XMarkIcon class="w-6 h-6" />
            </button>
        </div>
        
        <form @submit.prevent="submit" class="p-8">
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Position Name</label>
                    <input 
                        :value="form.name" 
                        @input="handleAlphaUpperInput('name', $event)" 
                        type="text" 
                        required 
                        class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-900 font-semibold" 
                        placeholder="e.g. SOFTWARE ENGINEER"
                    >
                </div>
                 <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Rank</label>
                    <select v-model="form.rank" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-900">
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
                    {{ isEditing ? 'Save Changes' : 'Create Position' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
