<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import WorkspaceShell from '../components/WorkspaceShell.vue'
import AssignmentList from '../components/AssignmentList.vue'

const auth = useAuthStore()
const workload = ref(null)

function updateWorkload(assignments) {
  const issues = assignments.flatMap((assignment) => assignment.issues)
  workload.value = {
    total: issues.length,
    active: issues.filter((issue) => issue.status !== 'resolved').length,
    resolved: issues.filter((issue) => issue.status === 'resolved').length,
  }
}
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    eyebrow="Contractor workspace"
    title="Workboard"
  >
    <div class="contractor-dashboard">
      <section class="contractor-work-summary" aria-label="Workload summary">
        <div>
          <p class="eyebrow">Current workload</p>
          <h2>Assigned work</h2>
          <p v-if="workload" class="contractor-workload-breakdown">
            <span>{{ workload.active }} active</span>
            <span aria-hidden="true"> · </span>
            <span>{{ workload.resolved }} completed</span>
          </p>
        </div>
        <span class="contractor-status-mark" :aria-label="`${workload?.active ?? 0} active assignments`">
          {{ workload ? workload.active : '—' }}
        </span>
      </section>

      <section class="contractor-work-queue">
        <AssignmentList role="contractor" @loaded="updateWorkload" />
      </section>
    </div>
  </WorkspaceShell>
</template>
