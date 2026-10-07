<script setup>
import { useAuthStore } from '../stores/auth'
import WorkspaceShell from '../components/WorkspaceShell.vue'

const auth = useAuthStore()
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    eyebrow="Contractor workspace"
    title="Assigned work"
  >
    <p class="workspace-empty-state">No work is assigned to you.</p>
  </WorkspaceShell>
</template>