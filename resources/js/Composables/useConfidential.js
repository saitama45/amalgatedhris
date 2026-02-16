import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useConfidential() {
    const page = usePage();

    const canViewSalary = computed(() => !!page.props.auth.confidential?.can_view_salary);
    const canManagePayroll = computed(() => !!page.props.auth.confidential?.can_manage_payroll);
    
    // Specifically for gmcloud45@gmail.com to manage the confidential list
    const isPrimaryAdmin = computed(() => page.props.auth.user?.email === 'gmcloud45@gmail.com');

    return {
        canViewSalary,
        canManagePayroll,
        isPrimaryAdmin
    };
}
