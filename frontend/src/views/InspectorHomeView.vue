<script setup>
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
    <p class="workspace-empty-state">No reports yet.</p>
  </WorkspaceShell>
</template>