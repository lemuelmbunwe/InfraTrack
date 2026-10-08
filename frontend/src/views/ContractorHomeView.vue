<script setup>
import { useAuthStore } from '../stores/auth'
import WorkspaceShell from '../components/WorkspaceShell.vue'

const auth = useAuthStore()
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    eyebrow="Contractor workspace"
    title="Workboard"
  >
    <div class="contractor-dashboard">
      <section class="contractor-work-summary">
        <div>
          <p class="eyebrow">Current workload</p>
          <h2>Assignments</h2>
        </div>
        <span class="contractor-status-mark" aria-hidden="true">—</span>
      </section>

      <section class="contractor-work-queue">
        <header class="dashboard-section-header">
          <div>
            <p class="eyebrow">Work queue</p>
            <h2>Assigned to you</h2>
          </div>
        </header>
        <div class="contractor-empty-state">
          <span class="contractor-empty-rule" aria-hidden="true"></span>
          <div>
            <strong>No assigned work</strong>
            <p>Your current assignments will appear here.</p>
          </div>
        </div>
      </section>
    </div>
  </WorkspaceShell>
</template>