<script setup>
import IssueList from '../components/IssueList.vue'
import { useAuthStore } from '../stores/auth'
import WorkspaceShell from '../components/WorkspaceShell.vue'

const auth = useAuthStore()
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    eyebrow="Field inspector"
    title="Your reports"
  >
    <div class="workspace-action-row">
      <RouterLink class="primary-action" :to="{ name: 'issue-create' }">
        Report pothole <span aria-hidden="true">+</span>
      </RouterLink>
    </div>
    <IssueList role="inspector" />
  </WorkspaceShell>
</template>