<script setup>
import { onMounted, ref } from 'vue'

const message = ref('Loading...')

onMounted(async () => {
  const response = await fetch(`${import.meta.env.VITE_API_URL}/test`)
  const data = await response.json()

  message.value = data.message
})
</script>

<template>
  <h1>{{ message }}</h1>
</template>