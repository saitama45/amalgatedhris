<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { ChevronUpDownIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: [String, Number],
    options: {
        type: Array,
        default: () => [],
    },
    labelKey: {
        type: String,
        default: 'name',
    },
    valueKey: {
        type: String,
        default: 'id',
    },
    placeholder: {
        type: String,
        default: 'Search and select...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const search = ref('');
const containerRef = ref(null);
const searchInput = ref(null);

const filteredOptions = computed(() => {
    if (!search.value) return props.options;
    const query = search.value.toLowerCase();
    return props.options.filter(opt => {
        const label = typeof opt === 'object' ? opt[props.labelKey] : opt;
        return String(label).toLowerCase().includes(query);
    });
});

const selectedOption = computed(() => {
    return props.options.find(opt => {
        const val = typeof opt === 'object' ? opt[props.valueKey] : opt;
        return val == props.modelValue;
    });
});

const toggle = async () => {
    if (!props.disabled) {
        isOpen.value = !isOpen.value;
        if (isOpen.value) {
            search.value = '';
            await nextTick();
            searchInput.value?.focus();
        }
    }
};

const close = () => {
    isOpen.value = false;
};

const select = (option) => {
    const val = typeof option === 'object' ? option[props.valueKey] : option;
    emit('update:modelValue', val);
    close();
};

const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

// Sync search text with selected option when opening
watch(isOpen, (newVal) => {
    if (newVal && selectedOption.value) {
        // Optional: pre-fill search with selected value? 
        // Better to leave empty for full list or pre-fill for editing.
        // Let's keep it empty for now as per common autocomplete patterns.
    }
});
</script>

<template>
    <div ref="containerRef" class="relative">
        <div
            @click="toggle"
            :class="[
                'relative w-full cursor-default overflow-hidden rounded-xl bg-slate-50 border border-slate-200 text-left focus:outline-none focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500 transition-all sm:text-sm',
                disabled ? 'opacity-50 cursor-not-allowed' : ''
            ]"
        >
            <div class="flex items-center px-4 py-2.5">
                <span v-if="selectedOption && !isOpen" class="block truncate text-slate-900 font-medium">
                    {{ typeof selectedOption === 'object' ? selectedOption[labelKey] : selectedOption }}
                </span>
                <span v-else-if="!isOpen" class="block truncate text-slate-400">
                    {{ placeholder }}
                </span>
                <input
                    v-if="isOpen"
                    ref="searchInput"
                    v-model="search"
                    class="w-full border-none p-0 focus:ring-0 bg-transparent text-slate-900 placeholder-slate-400"
                    :placeholder="placeholder"
                    @click.stop
                />
            </div>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                <ChevronUpDownIcon class="h-4 w-4 text-slate-400" aria-hidden="true" />
            </span>
        </div>

        <transition
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
            >
                <div v-if="filteredOptions.length === 0 && search !== ''" class="relative cursor-default select-none py-2 px-4 text-slate-700">
                    Nothing found.
                </div>

                <div
                    v-for="option in filteredOptions"
                    :key="typeof option === 'object' ? option[valueKey] : option"
                    class="relative cursor-default select-none py-2 pl-10 pr-4 hover:bg-blue-50 transition-colors"
                    :class="{ 'bg-blue-50 text-blue-900': (typeof option === 'object' ? option[valueKey] : option) == modelValue, 'text-slate-900': (typeof option === 'object' ? option[valueKey] : option) != modelValue }"
                    @click="select(option)"
                >
                    <span class="block truncate" :class="{ 'font-bold': (typeof option === 'object' ? option[valueKey] : option) == modelValue, 'font-normal': (typeof option === 'object' ? option[valueKey] : option) != modelValue }">
                        {{ typeof option === 'object' ? option[labelKey] : option }}
                    </span>
                    <span
                        v-if="(typeof option === 'object' ? option[valueKey] : option) == modelValue"
                        class="absolute inset-y-0 left-0 flex items-center pl-3 text-blue-600"
                    >
                        <CheckIcon class="h-4 w-4 stroke-[3]" aria-hidden="true" />
                    </span>
                </div>
            </div>
        </transition>
    </div>
</template>
