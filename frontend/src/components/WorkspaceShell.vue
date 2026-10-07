<script setup>
defineProps({
  user: {
    type: Object,
    required: true,
  },
  eyebrow: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
})
</script>

<template>
  <main class="workspace-frame">
    <header class="workspace-header">
      <RouterLink class="workspace-brand" :to="{ name: 'home' }" aria-label="InfraTrack home">
        <span class="workspace-brand-mark" aria-hidden="true">I</span>
        <span class="workspace-brand-name">Infra<span>Track</span></span>
      </RouterLink>

      <div class="workspace-user">
        <span class="workspace-user-name">{{ user.name }}</span>
        <span class="workspace-user-role">{{ user.role }}</span>
      </div>
    </header>

    <section class="workspace-content">
      <p class="eyebrow">{{ eyebrow }}</p>
      <h1>{{ title }}</h1>
      <slot />
    </section>

    <footer class="workspace-footer">
      <span>InfraTrack</span>
      <span>Municipal infrastructure management</span>
    </footer>
  </main>
</template>