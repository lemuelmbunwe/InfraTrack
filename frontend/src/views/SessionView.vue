<script setup>
import AuthShell from '../components/AuthShell.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>

<template>
  <AuthShell>
    <section class="signed-in-state">
      <p class="eyebrow"><span class="status-dot"></span> Session active</p>
      <h1>Signed in</h1>
      <p class="user-name">{{ auth.currentUser.value?.name }}</p>
      <p class="user-email">{{ auth.currentUser.value?.email }}</p>
    </section>
  </AuthShell>
</template>