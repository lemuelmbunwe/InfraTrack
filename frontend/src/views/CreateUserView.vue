<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import CreateUserForm from '../components/CreateUserForm.vue'
import WorkspaceShell from '../components/WorkspaceShell.vue'
import { AuthRequestError } from '../services/auth'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const isSubmitting = ref(false)
const errorMessage = ref('')
const errors = ref({})
const createdUser = ref(null)

async function createUser(payload) {
  isSubmitting.value = true
  errorMessage.value = ''
  errors.value = {}

  try {
    createdUser.value = await auth.createUser(payload)
  } catch (error) {
    if (error instanceof AuthRequestError && error.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(error.payload.errors || {}).map(([field, messages]) => [
          field,
          Array.isArray(messages) ? messages : [messages],
        ]),
      )
    } else if (error instanceof AuthRequestError && error.status === 403) {
      errorMessage.value = 'Your account is not allowed to create users.'
    } else if (error instanceof AuthRequestError && error.status === 401) {
      errorMessage.value = 'Your sign-in has expired. Sign in again to continue.'
    } else {
      errorMessage.value = 'Unable to create this account right now.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function returnToAdmin() {
  router.push({ name: 'admin-home' })
}
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    eyebrow="User management"
    title="Create account"
  >
    <section v-if="createdUser" class="creation-success" role="status">
      <p class="eyebrow">Account created</p>
      <h2>{{ createdUser.name }}</h2>
      <p>{{ createdUser.email }} · {{ createdUser.role }}</p>
      <button class="secondary-action" type="button" @click="returnToAdmin">Back to admin</button>
    </section>

    <CreateUserForm
      v-else
      :errors="errors"
      :error-message="errorMessage"
      :is-submitting="isSubmitting"
      @submit="createUser"
    />
  </WorkspaceShell>
</template>