<script setup>
import { ref } from 'vue'
import IssueList from '../components/IssueList.vue'
import { useAuthStore } from '../stores/auth'
import WorkspaceShell from '../components/WorkspaceShell.vue'

const auth = useAuthStore()
const issueMetrics = ref(null)

function updateIssueMetrics(issues) {
  issueMetrics.value = {
    total: issues.length,
    reported: issues.filter((issue) => issue.status === 'reported').length,
    inProgress: issues.filter((issue) => issue.status === 'in_progress').length,
    highSeverity: issues.filter((issue) => issue.severity === 'high').length,
  }
}
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    eyebrow="Admin workspace"
    title="Admin overview"
  >
    <section class="dashboard-metrics" aria-label="Issue overview">
      <div class="dashboard-metric">
        <span class="dashboard-metric-label">Total reports</span>
        <strong>{{ issueMetrics?.total ?? '—' }}</strong>
      </div>
      <div class="dashboard-metric">
        <span class="dashboard-metric-label">Reported</span>
        <strong>{{ issueMetrics?.reported ?? '—' }}</strong>
      </div>
      <div class="dashboard-metric">
        <span class="dashboard-metric-label">In progress</span>
        <strong>{{ issueMetrics?.inProgress ?? '—' }}</strong>
      </div>
      <div class="dashboard-metric dashboard-metric-alert">
        <span class="dashboard-metric-label">High severity</span>
        <strong>{{ issueMetrics?.highSeverity ?? '—' }}</strong>
      </div>
    </section>

    <div v-if="auth.currentUser.value?.super" class="workspace-action-row">
      <RouterLink class="primary-action" :to="{ name: 'user-create' }">
        Create user <span aria-hidden="true">+</span>
      </RouterLink>
    </div>
    <IssueList role="admin" @loaded="updateIssueMetrics" />
  </WorkspaceShell>
</template>