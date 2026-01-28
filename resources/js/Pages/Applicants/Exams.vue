<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { usePagination } from '@/Composables/usePagination';

const props = defineProps({
    applicants: Object,
});

const pagination = usePagination(props.applicants, 'applicants.exams');

onMounted(() => {
    pagination.updateData(props.applicants);
});

watch(() => props.applicants, (newApplicants) => {
    pagination.updateData(newApplicants);
}, { deep: true });

const getScoreColor = (score) => {
    if (!score) return 'text-slate-500';
    if (score >= 90) return 'text-emerald-600 font-bold';
    if (score >= 75) return 'text-blue-600 font-bold';
    return 'text-rose-600 font-bold';
};
</script>

<template>
    <Head title="Exam Results - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Exam Results</h2>
                    <p class="text-sm text-slate-500 mt-1">Review applicant assessment scores.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Assessment Scores"
                        subtitle="Applicants with recorded exam results"
                        search-placeholder="Search candidate..."
                        empty-message="No exam records found."
                        :search="pagination.search.value"
                        :data="pagination.data.value"
                        :current-page="pagination.currentPage.value"
                        :last-page="pagination.lastPage.value"
                        :per-page="pagination.perPage.value"
                        :showing-text="pagination.showingText.value"
                        :is-loading="pagination.isLoading.value"
                        @update:search="pagination.search.value = $event"
                        @go-to-page="pagination.goToPage"
                        @change-per-page="pagination.changePerPage"
                    >
                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Candidate</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Score</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Date Added</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="applicant in data" :key="applicant.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-slate-900">{{ applicant.first_name }} {{ applicant.last_name }}</div>
                                    <div class="text-xs text-slate-500">{{ applicant.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2.5 py-1 text-xs font-bold rounded-lg border bg-slate-100 text-slate-500 border-slate-200 capitalize">
                                        {{ applicant.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="applicant.exam_score !== null" :class="['text-lg', getScoreColor(applicant.exam_score)]">
                                        {{ applicant.exam_score }}%
                                    </div>
                                    <span v-else class="text-xs text-slate-400 italic">Pending</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                    {{ new Date(applicant.created_at).toLocaleDateString() }}
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
