<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    HomeIcon,
    Bars3Icon,
    XMarkIcon,
    UserGroupIcon,
    ShieldCheckIcon,
    BuildingOfficeIcon,
    UserPlusIcon,
    ClockIcon,
    CalendarDaysIcon,
    BanknotesIcon,
    DocumentDuplicateIcon,
    ClipboardDocumentCheckIcon,
    BriefcaseIcon,
    UsersIcon,
    Cog6ToothIcon,
    ComputerDesktopIcon,
    TableCellsIcon,
    CreditCardIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    CameraIcon,
    MapPinIcon
} from '@heroicons/vue/24/outline';
import { usePermission } from '@/Composables/usePermission';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
    isCollapsed: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle']);

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const { hasPermission, hasAnyPermission } = usePermission();

// Dynamic Menu Structure from Config
const sidebarConfig = computed(() => page.props.config?.sidebar_structure || {});
const moduleLabels = computed(() => page.props.config?.module_labels || {});

// Helper to determine if a main group should be visible
const canShowGroup = (groupCategories) => {
    return groupCategories.some(category => {
        const userPermissions = page.props.auth?.permissions || [];
        // Show group if user has the base permission OR any sub-permission
        return userPermissions.some(p => p === category || p.startsWith(category + '.'));
    });
};

// Helper to check permission for a specific category link
const hasModuleAccess = (category) => {
    // Check for 'category.view' OR just 'category' (for standalone permissions)
    return hasPermission(category + '.view') || hasPermission(category);
};

// Helper to get actual route name from category key
const getRouteName = (category) => {
    const customMappings = {
        'dashboard': 'dashboard',
        'attendance.kiosk': 'attendance.kiosk',
        'exams': 'applicants.exams',
        'government_deductions': 'contributions.index',
        'overtime_rates': 'overtime-rates.index',
        'document_types': 'document-types.index',
        'leave_requests': 'leave-requests.index',
        'government_remittances': 'government-remittances.index',
        'portal.dashboard': 'portal.dashboard',
        'portal.leaves': 'portal.leaves',
        'portal.overtime': 'portal.overtime',
        'portal.attendance': 'portal.attendance',
        'portal.ob-attendance': 'portal.ob-attendance',
        'portal.payslips': 'portal.payslips',
        'portal.deductions': 'portal.deductions',
    };

    return customMappings[category] || (category + '.index');
};

const toggleSidebar = () => {
    emit('toggle');
};

// Mapping slugs to Heroicons
const iconMap = {
    dashboard: HomeIcon,
    applicants: UserPlusIcon,
    exams: ClipboardDocumentCheckIcon,
    employees: UserGroupIcon,
    'attendance.kiosk': ComputerDesktopIcon,
    dtr: ClockIcon,
    shifts: CalendarDaysIcon,
    schedules: ClipboardDocumentCheckIcon,
    holidays: CalendarDaysIcon,
    overtime: ClockIcon,
    overtime_rates: TableCellsIcon,
    leave_requests: DocumentDuplicateIcon,
    payroll: BanknotesIcon,
    government_deductions: TableCellsIcon,
    deductions: CreditCardIcon,
    'portal.dashboard': HomeIcon,
    'portal.leaves': DocumentDuplicateIcon,
    'portal.overtime': ClockIcon,
    'portal.attendance': ClockIcon,
    'portal.ob-attendance': CameraIcon,
    'portal.payslips': BanknotesIcon,
    'portal.deductions': CreditCardIcon,
    users: UserGroupIcon,
    companies: BuildingOfficeIcon,
    departments: BuildingOfficeIcon,
    positions: BriefcaseIcon,
    document_types: DocumentDuplicateIcon,
    roles: ShieldCheckIcon,
    government_remittances: TableCellsIcon
};

// Mapping group names to their main icons
const groupIconMap = {
    'Recruitment': BriefcaseIcon,
    'Workforce': UsersIcon,
    'Time & Attendance': ClockIcon,
    'Compensation': BanknotesIcon,
    'Reports': DocumentDuplicateIcon,
    'My Portal': ComputerDesktopIcon,
    'System Administration': Cog6ToothIcon,
    'Overview': HomeIcon
};

// Menu State (Keyed by Group Name)
const menuState = ref({});

const toggleMenu = (key) => {
    menuState.value[key] = !menuState.value[key];
};

const isRouteActive = (category) => {
    // Special Case: Applicants and Exams share the same 'applicants' prefix
    if (category === 'applicants') {
        return route().current('applicants.*') && !route().current('applicants.exams*');
    }
    if (category === 'exams') {
        return route().current('applicants.exams*');
    }

    const routeName = getRouteName(category);
    // Get the base prefix (e.g., 'leave-requests' from 'leave-requests.index')
    const routeParts = routeName.split('.');
    const routePrefix = routeParts[0];
    
    // Check for exact matches or children of the specific module
    if (route().current(category) || route().current(category + '.*') || route().current(routeName + '.*')) {
        return true;
    }

    // Handle prefixed routes (like leave-requests.*) but skip the generic 'portal' prefix 
    // to avoid highlighting every portal module at once.
    if (routePrefix !== 'portal' && route().current(routePrefix + '.*')) {
        return true;
    }
    
    return (category === 'leave_requests' && route().current('leave-types.*')) ||
           (category === 'government_deductions' && route().current('contributions.*')) || 
           (category === 'attendance.kiosk' && route().current('attendance.kiosk'));
};

const checkActiveRoutes = () => {
    // Automatically open menus containing active routes
    Object.entries(sidebarConfig.value).forEach(([groupName, categories]) => {
        const isActive = categories.some(cat => isRouteActive(cat));
        if (isActive) menuState.value[groupName] = true;
    });
};

onMounted(() => {
    checkActiveRoutes();
});

// Tooltip State
const tooltipLabel = ref('');
const tooltipStyle = ref({ top: '0px', left: '0px' });
const showTooltip = ref(false);

const handleMouseEnter = (event, label) => {
    if (!props.isCollapsed) return;
    
    const rect = event.currentTarget.getBoundingClientRect();
    tooltipLabel.value = label;
    tooltipStyle.value = {
        top: `${rect.top + (rect.height / 2) - 16}px`, 
        left: `${rect.right + 12}px`
    };
    showTooltip.value = true;
};

const handleMouseLeave = () => {
    showTooltip.value = false;
};
</script>

<template>
    <div class="flex h-screen sticky top-0 font-sans">
        <!-- Sidebar -->
        <div
            :class="[
                'bg-[#0B1120] text-slate-300 transition-all duration-300 ease-[cubic-bezier(0.25,0.8,0.25,1)] flex flex-col border-r border-[#1E293B]',
                isCollapsed ? 'w-20' : 'w-72'
            ]"
        >
            <!-- Sidebar Header -->
            <div class="h-16 flex items-center justify-between px-5 border-b border-[#1E293B] bg-[#0B1120] z-20">
                <Link :href="route($page.props.auth.landing_page)" v-if="!isCollapsed" class="flex items-center gap-3 overflow-hidden group/logo">
                    <div class="bg-gradient-to-br from-teal-500 to-indigo-600 p-1.5 rounded-lg shadow-lg shadow-teal-900/50 group-hover/logo:scale-110 transition-transform duration-200">
                        <ApplicationLogo class="w-5 h-5 text-white" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-white tracking-wide group-hover/logo:text-teal-400 transition-colors">Amalgated</span>
                        <span class="text-[10px] text-teal-400 font-semibold tracking-wider uppercase">Enterprise HR</span>
                    </div>
                </Link>
                 <div v-else class="mx-auto">
                    <Link :href="route($page.props.auth.landing_page)" class="bg-gradient-to-br from-teal-500 to-indigo-600 p-2 rounded-lg block hover:scale-110 transition-transform duration-200">
                        <ApplicationLogo class="w-5 h-5 text-white" />
                    </Link>
                </div>

                <button
                    v-if="!isCollapsed"
                    @click="toggleSidebar"
                    class="p-1.5 rounded-md text-slate-500 hover:text-white hover:bg-[#1E293B] transition-colors"
                >
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>
            
             <!-- Collapsed Toggle (when collapsed) -->
             <div v-if="isCollapsed" class="flex justify-center py-4 border-b border-[#1E293B] z-20 bg-[#0B1120]">
                 <button
                    @click="toggleSidebar"
                    class="p-1.5 rounded-md text-slate-500 hover:text-white hover:bg-[#1E293B] transition-colors"
                >
                    <Bars3Icon class="w-5 h-5" />
                </button>
             </div>


            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-6 px-3 space-y-1 custom-scrollbar z-10">
                <div v-for="(categories, groupName) in sidebarConfig" :key="groupName">
                    <!-- Dashboard Special Case (If in its own group) -->
                    <template v-if="groupName === 'Overview' && hasPermission('dashboard.view')">
                        <Link
                            :href="route('dashboard')"
                            :class="[
                                'flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 group relative mb-2',
                                route().current('dashboard')
                                    ? 'bg-gradient-to-r from-[#161F32] to-transparent border-l-2 border-teal-500 text-teal-400'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white border-l-2 border-transparent'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Dashboard')"
                            @mouseleave="handleMouseLeave"
                        >
                            <HomeIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', route().current('dashboard') ? 'text-teal-400' : 'text-slate-500 group-hover:text-teal-400', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Dashboard</span>
                        </Link>
                    </template>

                    <!-- Dynamic Groups -->
                    <template v-else-if="canShowGroup(categories)">
                        <!-- Main Menu Item -->
                        <div 
                            v-if="!isCollapsed"
                            @click="toggleMenu(groupName)"
                            class="flex items-center justify-between px-3 py-2.5 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white cursor-pointer transition-all duration-200 mt-2"
                        >
                            <div class="flex items-center">
                                <component :is="groupIconMap[groupName] || Cog6ToothIcon" class="w-5 h-5 mr-3 flex-shrink-0" />
                                <span class="font-bold text-xs uppercase tracking-wider">{{ groupName }}</span>
                            </div>
                            <component :is="menuState[groupName] ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" />
                        </div>
                        <!-- Collapsed Divider -->
                        <div v-else class="my-4 border-t border-[#1E293B]"></div>

                        <!-- Submenu Items -->
                        <div v-show="(menuState[groupName] && !isCollapsed) || isCollapsed" :class="{'ml-4 border-l border-[#1E293B] pl-2 space-y-1': !isCollapsed}">
                            <div v-for="category in categories" :key="category">
                                <Link
                                    v-if="hasModuleAccess(category)"
                                    :href="route().has(getRouteName(category)) ? route(getRouteName(category)) : '#'"
                                    :class="[
                                        'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                        isRouteActive(category)
                                            ? 'text-teal-400 bg-slate-800/50'
                                            : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                                    ]"
                                    @mouseenter="handleMouseEnter($event, moduleLabels[category] || category)"
                                    @mouseleave="handleMouseLeave"
                                >
                                    <component :is="iconMap[category] || ShieldCheckIcon" :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                                    <span v-if="!isCollapsed" class="font-medium text-sm">{{ moduleLabels[category] || category }}</span>
                                </Link>
                            </div>
                        </div>
                    </template>
                </div>
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-[#1E293B] bg-[#0B1120] z-20">
                <div class="flex items-center group relative p-2 rounded-xl hover:bg-[#161F32] transition-colors cursor-pointer">
                    <Link :href="route('profile.edit')" class="flex items-center flex-1 min-w-0">
                        <div 
                            class="relative flex-shrink-0"
                            @mouseenter="handleMouseEnter($event, user.name)"
                            @mouseleave="handleMouseLeave"
                        >
                            <div v-if="user.profile_photo" class="w-10 h-10 rounded-full overflow-hidden border-2 border-[#1E293B] ring-2 ring-transparent group-hover:ring-teal-500/30 transition-all">
                                <img :src="'/storage/' + user.profile_photo" class="h-full w-full object-cover" :alt="user.name">
                            </div>
                            <div v-else class="w-10 h-10 bg-[#1E293B] rounded-full flex items-center justify-center border-2 border-[#1E293B] group-hover:bg-[#253248] transition-colors">
                                <span class="text-sm font-bold text-teal-400">
                                    {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                                </span>
                            </div>
                            <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-teal-500 border-2 border-[#0B1120] rounded-full"></div>
                        </div>
                        
                        <div v-if="!isCollapsed" class="ml-3 flex-1 overflow-hidden">
                            <p class="text-sm font-medium text-slate-200 truncate group-hover:text-white transition-colors">{{ user.name || 'User' }}</p>
                            <p class="text-xs text-slate-500 truncate group-hover:text-slate-400 transition-colors">{{ user.email || 'user@example.com' }}</p>
                        </div>
                    </Link>

                    
                    <Link 
                        v-if="!isCollapsed"
                        :href="route('logout')" 
                        method="post" 
                        as="button" 
                        class="ml-2 p-1.5 text-slate-500 hover:text-rose-400 hover:bg-[#1E293B] rounded-lg transition-colors flex-shrink-0"
                        title="Logout"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Floating Tooltip Portal -->
        <Teleport to="body">
            <div 
                v-if="showTooltip && isCollapsed" 
                class="fixed z-[100] px-3 py-2 bg-[#1E293B] text-white text-xs font-semibold rounded-md shadow-xl border border-slate-700 pointer-events-none animate-in fade-in zoom-in duration-75"
                :style="tooltipStyle"
            >
                {{ tooltipLabel }}
                <div class="absolute top-1/2 -left-1 -translate-y-1/2 w-2 h-2 bg-[#1E293B] border-l border-b border-slate-700 rotate-45"></div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1E293B;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #334155;
}
</style>