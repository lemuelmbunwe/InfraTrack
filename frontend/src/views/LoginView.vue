<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthShell from '../components/AuthShell.vue'
import LoginForm from '../components/LoginForm.vue'
import { AuthRequestError } from '../services/auth'
import { homeRouteName } from '../router/roles'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const isSubmitting = ref(false)
const errorMessage = ref(auth.sessionError.value)

function messageForError(error) {
  if (error instanceof AuthRequestError) {
    if (error.status === 401) {
      return 'Email or password is incorrect.'
    }

    if (error.status === 403) {
      return 'This account is inactive.'
    }

    if (error.status === 422) {
      return Object.values(error.payload.errors || {}).flat()[0] || 'Enter a valid email and password.'
    }

    if (error.status === 429) {
      return 'Too many attempts. Please try again shortly.'
    }
  }

  return 'Unable to reach InfraTrack right now.'
}

async function submitCredentials({ email, password }) {
  isSubmitting.value = true
  errorMessage.value = ''

  try {
    await auth.signIn(email, password)
    const redirect = route.query.redirect
    const destination = typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
      ? redirect
      : { name: homeRouteName(auth.currentUser.value.role) }

    await router.replace(destination)
  } catch (error) {
    errorMessage.value = messageForError(error)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <AuthShell>
    <LoginForm
      :error-message="errorMessage"
      :is-submitting="isSubmitting"
      @submit="submitCredentials"
    />
  </AuthShell>
</template>