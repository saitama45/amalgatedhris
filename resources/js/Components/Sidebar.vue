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
    ChevronRightIcon
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

const toggleSidebar = () => {
    emit('toggle');
};

// Menu State
const menuState = ref({
    recruitment: false,
    workforce: false,
    timekeeping: false,
    compensation: false,
    portal: false,
    system: false
});

const toggleMenu = (key) => {
    menuState.value[key] = !menuState.value[key];
};

const checkActiveRoutes = () => {
    const currentUrl = page.url;

    // Recruitment
    if (route().current('applicants.*')) menuState.value.recruitment = true;
    
    // Workforce
    if (route().current('employees.*')) menuState.value.workforce = true;

    // Timekeeping
    if (route().current('dtr.*') || route().current('shifts.*') || route().current('schedules.*') || route().current('holidays.*') || route().current('attendance.kiosk')) menuState.value.timekeeping = true;

    // Compensation
    if (route().current('contributions.*') || route().current('deductions.*')) menuState.value.compensation = true;

    // Workforce vs System (Users)
    if (route().current('users.*')) {
        if (currentUrl.includes('view=system')) {
            menuState.value.system = true;
        } else {
            // Default fallback
            menuState.value.system = true;
        }
    }

    // System
    if (route().current('companies.*') || route().current('roles.*') || route().current('departments.*') || route().current('positions.*') || route().current('document-types.*')) menuState.value.system = true;
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
                <div v-if="!isCollapsed" class="flex items-center gap-3 overflow-hidden">
                    <div class="bg-gradient-to-br from-teal-500 to-indigo-600 p-1.5 rounded-lg shadow-lg shadow-teal-900/50">
                        <ApplicationLogo class="w-5 h-5 text-white" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-white tracking-wide">Amalgated</span>
                        <span class="text-[10px] text-teal-400 font-semibold tracking-wider uppercase">Enterprise HR</span>
                    </div>
                </div>
                 <div v-else class="mx-auto">
                    <div class="bg-gradient-to-br from-teal-500 to-indigo-600 p-2 rounded-lg">
                        <ApplicationLogo class="w-5 h-5 text-white" />
                    </div>
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
                <!-- Dashboard -->
                <Link
                    v-if="hasPermission('dashboard.view')"
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
                    <HomeIcon
                        :class="[
                            'w-5 h-5 flex-shrink-0 transition-colors',
                            route().current('dashboard') ? 'text-teal-400' : 'text-slate-500 group-hover:text-teal-400',
                            isCollapsed ? 'mx-auto' : 'mr-3'
                        ]"
                    />
                    <span v-if="!isCollapsed" class="font-medium text-sm">Dashboard</span>
                </Link>

                <!-- MODULE: RECRUITMENT -->
                <template v-if="hasAnyPermission(['recruitment.view', 'recruitment.create'])">
                    <!-- Main Menu Item -->
                    <div 
                        v-if="!isCollapsed"
                        @click="toggleMenu('recruitment')"
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white cursor-pointer transition-all duration-200 mt-2"
                    >
                        <div class="flex items-center">
                            <BriefcaseIcon class="w-5 h-5 mr-3 flex-shrink-0" />
                            <span class="font-bold text-xs uppercase tracking-wider">Recruitment</span>
                        </div>
                        <component :is="menuState.recruitment ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" />
                    </div>
                    <!-- Collapsed Divider -->
                    <div v-else class="my-4 border-t border-[#1E293B]"></div>

                    <!-- Submenu Items -->
                    <div v-show="(menuState.recruitment && !isCollapsed) || isCollapsed" :class="{'ml-4 border-l border-[#1E293B] pl-2 space-y-1': !isCollapsed}">
                        <Link
                            v-if="hasPermission('applicants.view')"
                            :href="route('applicants.index')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('applicants.index')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Applicants')"
                            @mouseleave="handleMouseLeave"
                        >
                            <UserPlusIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Applicants</span>
                        </Link>

                        <Link
                            v-if="hasPermission('exams.view')"
                            :href="route('applicants.exams')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('applicants.exams')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Exam Results')"
                            @mouseleave="handleMouseLeave"
                        >
                            <ClipboardDocumentCheckIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Exam Results</span>
                        </Link>
                    </div>
                </template>

                <!-- MODULE: WORKFORCE -->
                <template v-if="hasAnyPermission(['employees.view', 'employees.edit'])">
                    <div 
                        v-if="!isCollapsed"
                        @click="toggleMenu('workforce')"
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white cursor-pointer transition-all duration-200 mt-2"
                    >
                        <div class="flex items-center">
                            <UsersIcon class="w-5 h-5 mr-3 flex-shrink-0" />
                            <span class="font-bold text-xs uppercase tracking-wider">Workforce</span>
                        </div>
                        <component :is="menuState.workforce ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" />
                    </div>
                    <div v-else class="my-4 border-t border-[#1E293B]"></div>

                    <div v-show="(menuState.workforce && !isCollapsed) || isCollapsed" :class="{'ml-4 border-l border-[#1E293B] pl-2 space-y-1': !isCollapsed}">
                        <Link
                            v-if="hasPermission('employees.view')"
                            :href="route('employees.index')" 
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('employees.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                             @mouseenter="handleMouseEnter($event, 'Employee Directory')"
                            @mouseleave="handleMouseLeave"
                        >
                            <UserGroupIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Employees (201)</span>
                        </Link>
                    </div>
                </template>

                 <!-- MODULE: TIMEKEEPING -->
                <template v-if="hasAnyPermission(['dtr.view', 'shifts.view', 'attendance.kiosk'])">
                     <div 
                        v-if="!isCollapsed"
                        @click="toggleMenu('timekeeping')"
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white cursor-pointer transition-all duration-200 mt-2"
                    >
                        <div class="flex items-center">
                            <ClockIcon class="w-5 h-5 mr-3 flex-shrink-0" />
                            <span class="font-bold text-xs uppercase tracking-wider">Time & Attendance</span>
                        </div>
                        <component :is="menuState.timekeeping ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" />
                    </div>
                    <div v-else class="my-4 border-t border-[#1E293B]"></div>

                    <div v-show="(menuState.timekeeping && !isCollapsed) || isCollapsed" :class="{'ml-4 border-l border-[#1E293B] pl-2 space-y-1': !isCollapsed}">
                        <Link
                            v-if="hasPermission('attendance.kiosk')"
                            :href="route('attendance.kiosk')"
                             :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('attendance.kiosk')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Attendance Kiosk')"
                            @mouseleave="handleMouseLeave"
                        >
                            <ComputerDesktopIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Attendance Kiosk</span>
                        </Link>
                        <Link
                            v-if="hasPermission('dtr.view')"
                            :href="route('dtr.index')"
                             :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('dtr.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Daily Time Records')"
                            @mouseleave="handleMouseLeave"
                        >
                            <ClockIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">DTR Logs</span>
                        </Link>
                        <Link
                            v-if="hasPermission('shifts.view')"
                            :href="route('shifts.index')"
                             class="flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white transition-all duration-200 group relative"
                            @mouseenter="handleMouseEnter($event, 'Shift Templates')"
                            @mouseleave="handleMouseLeave"
                        >
                            <CalendarDaysIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Shift Templates</span>
                        </Link>
                         <Link
                            v-if="hasPermission('schedules.manage')"
                            :href="route('schedules.index')"
                             class="flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white transition-all duration-200 group relative"
                            @mouseenter="handleMouseEnter($event, 'Shift Assignment')"
                            @mouseleave="handleMouseLeave"
                        >
                            <ClipboardDocumentCheckIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Shift Assignment</span>
                        </Link>
                         <Link
                            v-if="hasPermission('holidays.view')"
                            :href="route('holidays.index')"
                             class="flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white transition-all duration-200 group relative"
                            @mouseenter="handleMouseEnter($event, 'Holiday Calendar')"
                            @mouseleave="handleMouseLeave"
                        >
                            <CalendarDaysIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Holiday Calendar</span>
                        </Link>
                    </div>
                </template>

                <!-- MODULE: PAYROLL -->
                <template v-if="hasAnyPermission(['payroll.view', 'payroll.process'])">
                     <div 
                        v-if="!isCollapsed"
                        @click="toggleMenu('compensation')"
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white cursor-pointer transition-all duration-200 mt-2"
                    >
                        <div class="flex items-center">
                            <BanknotesIcon class="w-5 h-5 mr-3 flex-shrink-0" />
                            <span class="font-bold text-xs uppercase tracking-wider">Compensation</span>
                        </div>
                        <component :is="menuState.compensation ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" />
                    </div>
                    <div v-else class="my-4 border-t border-[#1E293B]"></div>

                    <div v-show="(menuState.compensation && !isCollapsed) || isCollapsed" :class="{'ml-4 border-l border-[#1E293B] pl-2 space-y-1': !isCollapsed}">
                        <Link
                            :href="route('contributions.index')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('contributions.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Contribution Tables')"
                            @mouseleave="handleMouseLeave"
                        >
                            <TableCellsIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Contribution Tables</span>
                        </Link>

                        <Link
                            v-if="hasPermission('deductions.view')"
                            :href="route('deductions.index')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('deductions.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Other Deductions')"
                            @mouseleave="handleMouseLeave"
                        >
                            <CreditCardIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Other Deductions</span>
                        </Link>

                        <Link
                            v-if="hasPermission('payroll.view')"
                            href="#"
                             class="flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white transition-all duration-200 group relative"
                            @mouseenter="handleMouseEnter($event, 'Payroll Processing')"
                            @mouseleave="handleMouseLeave"
                        >
                            <BanknotesIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Payroll</span>
                        </Link>
                    </div>
                </template>
                
                 <!-- MODULE: EMPLOYEE SELF SERVICE (PORTAL) -->
                 <template v-if="hasPermission('portal.view')">
                     <div 
                        v-if="!isCollapsed"
                        @click="toggleMenu('portal')"
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white cursor-pointer transition-all duration-200 mt-2"
                    >
                        <div class="flex items-center">
                            <ComputerDesktopIcon class="w-5 h-5 mr-3 flex-shrink-0" />
                            <span class="font-bold text-xs uppercase tracking-wider">My Portal</span>
                        </div>
                        <component :is="menuState.portal ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" />
                    </div>
                    <div v-else class="my-4 border-t border-[#1E293B]"></div>

                    <div v-show="(menuState.portal && !isCollapsed) || isCollapsed" :class="{'ml-4 border-l border-[#1E293B] pl-2 space-y-1': !isCollapsed}">
                         <Link
                            v-if="hasPermission('portal.file_leave')"
                            href="#"
                             class="flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white transition-all duration-200 group relative"
                            @mouseenter="handleMouseEnter($event, 'File Leave')"
                            @mouseleave="handleMouseLeave"
                        >
                            <DocumentDuplicateIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">File Leave / OT</span>
                        </Link>
                    </div>
                 </template>


                <!-- MODULE: ADMIN -->
                <template v-if="hasAnyPermission(['roles.view', 'companies.view', 'users.view'])">
                     <div 
                        v-if="!isCollapsed"
                        @click="toggleMenu('system')"
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg text-slate-400 hover:bg-[#161F32] hover:text-white cursor-pointer transition-all duration-200 mt-2"
                    >
                        <div class="flex items-center">
                            <Cog6ToothIcon class="w-5 h-5 mr-3 flex-shrink-0" />
                            <span class="font-bold text-xs uppercase tracking-wider">System</span>
                        </div>
                        <component :is="menuState.system ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" />
                    </div>
                    <div v-else class="my-4 border-t border-[#1E293B]"></div>

                    <div v-show="(menuState.system && !isCollapsed) || isCollapsed" :class="{'ml-4 border-l border-[#1E293B] pl-2 space-y-1': !isCollapsed}">
                        <!-- Users -->
                        <Link
                            v-if="hasPermission('users.view')"
                            :href="route('users.index', { view: 'system' })"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('users.*') && page.url.includes('view=system')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Users')"
                            @mouseleave="handleMouseLeave"
                        >
                            <UserGroupIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Users</span>
                        </Link>

                        <!-- Companies -->
                        <Link
                            v-if="hasPermission('companies.view')"
                            :href="route('companies.index')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('companies.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Companies')"
                            @mouseleave="handleMouseLeave"
                        >
                            <BuildingOfficeIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Companies</span>
                        </Link>

                        <!-- Departments -->
                        <Link
                            v-if="hasPermission('departments.view')"
                            :href="route('departments.index')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('departments.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Departments')"
                            @mouseleave="handleMouseLeave"
                        >
                            <BuildingOfficeIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Departments</span>
                        </Link>

                        <!-- Positions -->
                        <Link
                            v-if="hasPermission('positions.view')"
                            :href="route('positions.index')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('positions.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Positions')"
                            @mouseleave="handleMouseLeave"
                        >
                            <BriefcaseIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Positions</span>
                        </Link>

                        <!-- Document Requirements -->
                        <Link
                            v-if="hasPermission('document_types.view')"
                            :href="route('document-types.index')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                route().current('document-types.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Document Types')"
                            @mouseleave="handleMouseLeave"
                        >
                            <DocumentDuplicateIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Doc Requirements</span>
                        </Link>

                        <!-- Roles -->
                        <Link
                            v-if="hasPermission('roles.view')"
                            :href="route('roles.index')"
                            :class="[
                                'flex items-center px-3 py-2 rounded-lg transition-all duration-200 group relative',
                                 route().current('roles.*')
                                    ? 'text-teal-400 bg-slate-800/50'
                                    : 'text-slate-400 hover:bg-[#161F32] hover:text-white'
                            ]"
                            @mouseenter="handleMouseEnter($event, 'Roles & Permissions')"
                            @mouseleave="handleMouseLeave"
                        >
                            <ShieldCheckIcon :class="['w-5 h-5 flex-shrink-0 transition-colors', isCollapsed ? 'mx-auto' : 'mr-3']" />
                            <span v-if="!isCollapsed" class="font-medium text-sm">Roles & Permissions</span>
                        </Link>
                    </div>
                </template>



            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-[#1E293B] bg-[#0B1120] z-20">
                <div class="flex items-center group relative p-2 rounded-xl hover:bg-[#161F32] transition-colors cursor-pointer">
                    <div 
                        class="relative"
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

                    
                    <Link 
                        v-if="!isCollapsed"
                        :href="route('logout')" 
                        method="post" 
                        as="button" 
                        class="ml-2 p-1.5 text-slate-500 hover:text-rose-400 hover:bg-[#1E293B] rounded-lg transition-colors"
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