<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { 
    UserIcon, 
    BuildingOfficeIcon, 
    BriefcaseIcon, 
    ArrowRightCircleIcon,
    ExclamationCircleIcon 
} from '@heroicons/vue/24/outline';
import axios from 'axios';

const query = ref('');
const results = ref([]);
const isOpen = ref(false);
const isLoading = ref(false);
const searchInput = ref(null);
const selectedIndex = ref(-1);

const icons = {
    UserIcon,
    BuildingOfficeIcon,
    BriefcaseIcon,
    ArrowRightCircleIcon
};

// Simple debounce implementation
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

const performSearch = debounce(async (searchQuery) => {
    if (searchQuery.length < 2) {
        results.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get(route('global.search'), {
            params: { query: searchQuery }
        });
        results.value = response.data;
    } catch (error) {
        console.error('Search failed:', error);
        results.value = [];
    } finally {
        isLoading.value = false;
    }
}, 300);

watch(query, (newQuery) => {
    if (newQuery) {
        isOpen.value = true;
        performSearch(newQuery);
    } else {
        isOpen.value = false;
        results.value = [];
    }
    selectedIndex.value = -1;
});

const handleKeydown = (e) => {
    if (!isOpen.value) return;

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            selectedIndex.value = (selectedIndex.value + 1) % results.value.length;
            break;
        case 'ArrowUp':
            e.preventDefault();
            selectedIndex.value = selectedIndex.value <= 0 
                ? results.value.length - 1 
                : selectedIndex.value - 1;
            break;
        case 'Enter':
            e.preventDefault();
            if (selectedIndex.value >= 0 && results.value[selectedIndex.value]) {
                selectResult(results.value[selectedIndex.value]);
            }
            break;
        case 'Escape':
            isOpen.value = false;
            break;
    }
};

const selectResult = (result) => {
    isOpen.value = false;
    query.value = '';
    router.visit(result.url);
};

const closeSearch = (e) => {
    // Delay closing to allow click events on results to propagate
    setTimeout(() => {
        isOpen.value = false;
    }, 200);
};

// Global Keyboard Shortcut (Ctrl+K)
const handleGlobalKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchInput.value?.focus();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <div class="relative w-full h-full max-w-lg">
        <div class="relative w-full h-full text-slate-400 focus-within:text-slate-600">
            <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none">
                <MagnifyingGlassIcon class="h-5 w-5" aria-hidden="true" />
            </div>
            <input 
                ref="searchInput"
                v-model="query"
                @focus="isOpen = !!query"
                @blur="closeSearch"
                @keydown="handleKeydown"
                name="search" 
                id="search" 
                class="block w-full h-full pl-10 pr-3 py-2 border-transparent text-slate-900 placeholder-slate-400 focus:outline-none focus:placeholder-slate-500 focus:ring-0 focus:border-transparent sm:text-sm bg-transparent" 
                placeholder="Global Search (Ctrl+K)" 
                type="search" 
                autocomplete="off"
            >
            <div v-if="isLoading" class="absolute inset-y-0 right-0 flex items-center pr-3">
                <svg class="animate-spin h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <!-- Dropdown Results -->
        <div 
            v-if="isOpen && (results.length > 0 || query.length >= 2)"
            class="absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-xl border border-slate-100 max-h-96 overflow-y-auto z-50 py-2"
        >
            <div v-if="results.length === 0 && !isLoading" class="px-4 py-3 text-sm text-slate-500 text-center flex flex-col items-center">
                <ExclamationCircleIcon class="w-6 h-6 mb-1 text-slate-300" />
                No results found for "{{ query }}"
            </div>

            <div v-else>
                <div v-for="(result, index) in results" :key="index">
                    <!-- Group Header (Simplified for now, results might be mixed or sorted by backend) -->
                    <div 
                        v-if="index === 0 || results[index-1].group !== result.group"
                        class="px-3 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50"
                    >
                        {{ result.group }}
                    </div>

                    <button
                        @click="selectResult(result)"
                        @mouseenter="selectedIndex = index"
                        class="w-full text-left px-4 py-2 flex items-center space-x-3 hover:bg-slate-50 transition-colors"
                        :class="{'bg-blue-50': selectedIndex === index}"
                    >
                        <component 
                            :is="icons[result.icon] || ArrowRightCircleIcon" 
                            class="w-5 h-5 text-slate-400"
                            :class="{'text-blue-500': selectedIndex === index}"
                        />
                        <div>
                            <div class="text-sm font-medium text-slate-700" :class="{'text-blue-700': selectedIndex === index}">{{ result.title }}</div>
                            <div class="text-xs text-slate-500">{{ result.subtitle }}</div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
