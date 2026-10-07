<script setup>
import { useAuthStore } from '../stores/auth'
import WorkspaceShell from '../components/WorkspaceShell.vue'

const auth = useAuthStore()
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    eyebrow="Admin workspace"
    title="Admin overview"
  >
    <div v-if="auth.currentUser.value?.super" class="workspace-action-row">
      <RouterLink class="primary-action" :to="{ name: 'user-create' }">
        Create user <span aria-hidden="true">+</span>
      </RouterLink>
    </div>
    <p v-else class="workspace-empty-state">No operational items to review.</p>
  </WorkspaceShell>
</template>