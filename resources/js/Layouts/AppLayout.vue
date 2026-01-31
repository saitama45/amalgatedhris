<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import Sidebar from '@/Components/Sidebar.vue';
import Toast from '@/Components/Toast.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useToast } from '@/Composables/useToast.js';
import { useConfirm } from '@/Composables/useConfirm.js';
import { usePermission } from '@/Composables/usePermission.js';
import { 
    BellIcon, 
    MagnifyingGlassIcon,
    Bars3Icon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    fluid: {
        type: Boolean,
        default: false
    }
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

// Initialize sidebar state from localStorage or default to false (expanded)
const getStoredSidebarState = () => {
    if (typeof window !== 'undefined') {
        return localStorage.getItem('sidebarCollapsed') === 'true';
    }
    return false;
};

const sidebarCollapsed = ref(getStoredSidebarState());
const mobileMenuOpen = ref(false);
const userMenuOpen = ref(false);
const userMenuRef = ref(null);
const { success, error, warning, info } = useToast();
const { showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();
const { hasPermission } = usePermission();

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

// Watch for flash messages
watch(() => page.props.flash, (flash) => {
    if (flash?.success) success(flash.success);
    if (flash?.error) error(flash.error);
    if (flash?.warning) warning(flash.warning);
    if (flash?.info) info(flash.info);
}, { deep: true });

// Watch for changes and save to localStorage
watch(sidebarCollapsed, (newValue) => {
    if (typeof window !== 'undefined') {
        localStorage.setItem('sidebarCollapsed', newValue);
    }
});

const logout = () => {
    router.post(route('logout'));
};

const handleClickOutside = (event) => {
    if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
        userMenuOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex">
        <!-- Mobile Sidebar Drawer -->
        <div v-if="mobileMenuOpen" class="relative z-50 md:hidden" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" @click="mobileMenuOpen = false"></div>

            <div class="fixed inset-0 flex">
                <div class="relative flex w-full max-w-xs flex-1">
                    <!-- Mobile Sidebar Instance -->
                    <Sidebar 
                        :is-collapsed="false" 
                        @toggle="mobileMenuOpen = false"
                        class="flex h-full w-full"
                    />
                </div>
            </div>
        </div>

        <!-- Desktop Sidebar -->
        <Sidebar 
            :is-collapsed="sidebarCollapsed" 
            @toggle="toggleSidebar"
            class="hidden md:flex flex-shrink-0 z-30"
        />

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Top Navigation Bar -->
            <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-sm z-20">
                
                <!-- Left Section: Mobile Toggle & Search -->
                <div class="flex flex-1 items-center gap-4">
                    <!-- Mobile Menu Button -->
                    <button 
                        @click="mobileMenuOpen = true" 
                        type="button" 
                        class="md:hidden -ml-2 p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-md focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                    >
                        <span class="sr-only">Open sidebar</span>
                        <Bars3Icon class="h-6 w-6" aria-hidden="true" />
                    </button>

                    <!-- Search Bar -->
                    <div class="flex-1 flex max-w-lg">
                        <div class="relative w-full text-slate-400 focus-within:text-slate-600">
                            <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none">
                                <MagnifyingGlassIcon class="h-5 w-5" aria-hidden="true" />
                            </div>
                            <input 
                                name="search" 
                                id="search" 
                                class="block w-full h-full pl-10 pr-3 py-2 border-transparent text-slate-900 placeholder-slate-400 focus:outline-none focus:placeholder-slate-500 focus:ring-0 focus:border-transparent sm:text-sm" 
                                placeholder="Global Search (Ctrl+K)" 
                                type="search" 
                                autocomplete="off"
                            >
                        </div>
                    </div>
                </div>

                <!-- Right Header Controls -->
                <div class="ml-4 flex items-center md:ml-6 space-x-4">
                    <!-- Notifications -->
                    <button class="bg-white p-1 rounded-full text-slate-400 hover:text-slate-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 relative">
                        <span class="sr-only">View notifications</span>
                        <BellIcon class="h-6 w-6" aria-hidden="true" />
                        <span class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                    </button>

                    <!-- Profile Dropdown -->
                    <div class="relative" ref="userMenuRef">
                        <button 
                            @click="userMenuOpen = !userMenuOpen"
                            class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 lg:p-2 lg:rounded-md lg:hover:bg-slate-50 transition-colors" 
                            id="user-menu-button" 
                            aria-expanded="false" 
                            aria-haspopup="true"
                        >
                            <span class="sr-only">Open user menu</span>
                            <div v-if="user.profile_photo" class="h-8 w-8 rounded-full overflow-hidden border border-slate-200">
                                <img :src="'/storage/' + user.profile_photo" class="h-full w-full object-cover" :alt="user.name">
                            </div>
                            <div v-else class="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center border border-blue-200">
                                <span class="text-sm font-bold text-blue-700">{{ user.name?.charAt(0) || 'U' }}</span>
                            </div>
                            <span class="hidden ml-3 text-slate-700 text-sm font-medium lg:block">{{ user.name }}</span>
                            <svg class="hidden ml-1 h-5 w-5 text-slate-400 lg:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div 
                            v-show="userMenuOpen" 
                            class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50 animate-in fade-in zoom-in duration-100" 
                            role="menu" 
                            aria-orientation="vertical" 
                            aria-labelledby="user-menu-button" 
                            tabindex="-1"
                        >
                            <div class="px-4 py-3 border-b border-slate-100">
                                <p class="text-sm text-slate-500">Signed in as</p>
                                <p class="text-sm font-medium text-slate-900 truncate">{{ user.email }}</p>
                            </div>
                            
                            <Link :href="route('profile.edit')" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem" tabindex="-1">Your Profile</Link>
                            <Link href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem" tabindex="-1">Settings</Link>
                            
                            <button @click="logout" class="w-full text-left block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 font-medium border-t border-slate-100" role="menuitem" tabindex="-1">
                                Sign out
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto bg-slate-50 focus:outline-none custom-scrollbar">
                <div class="py-6">
                     <!-- Page Header (Slot) -->
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
                        <slot name="header"></slot>
                    </div>
                    
                    <!-- Content (Slot) -->
                    <div :class="[fluid ? 'w-full px-0' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8']">
                         <slot />
                    </div>
                </div>
            </main>
        </div>
        
        <!-- Toast Notifications -->
        <Toast />
        
        <!-- Confirm Modal -->
        <ConfirmModal 
            :show="showConfirmModal" 
            :title="confirmTitle" 
            :message="confirmMessage" 
            @confirm="handleConfirm" 
            @cancel="handleCancel" 
        />
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}
</style>