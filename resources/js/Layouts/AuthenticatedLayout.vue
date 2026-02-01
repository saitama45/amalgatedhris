<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Sidebar.vue';
import Toast from '@/Components/Toast.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useToast } from '@/Composables/useToast.js';
import { useConfirm } from '@/Composables/useConfirm.js';

const isSidebarCollapsed = ref(false);
const page = usePage();
const { success, error, warning, info } = useToast();
const { showConfirmModal, confirmTitle, confirmMessage, handleConfirm, handleCancel } = useConfirm();

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
};

// Watch for flash messages
watch(() => page.props.flash, (flash) => {
    if (flash?.success) success(flash.success);
    if (flash?.error) error(flash.error);
    if (flash?.warning) warning(flash.warning);
    if (flash?.info) info(flash.info);
}, { deep: true });
</script>

<template>
    <div class="flex h-screen bg-gray-100 overflow-hidden">
        <!-- Sidebar -->
        <Sidebar 
            :isCollapsed="isSidebarCollapsed" 
            @toggle="toggleSidebar"
        />

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden transition-all duration-300">
            <!-- Header (Optional, if you want a top bar for title or mobile toggle) -->
            <header class="bg-white shadow-sm z-10" v-if="$slots.header">
                <div class="mx-auto px-4 py-4 sm:px-6 lg:px-8 flex justify-between items-center">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4 sm:p-6 lg:p-8">
                <slot />
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

