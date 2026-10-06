<script setup>
import { ref } from 'vue'

defineProps({
  errorMessage: {
    type: String,
    default: '',
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit'])
const email = ref('')
const password = ref('')
const showPassword = ref(false)

function submit() {
  emit('submit', { email: email.value, password: password.value })
}
</script>

<template>
  <form class="login-form" @submit.prevent="submit">
    <div class="form-heading">
      <p class="eyebrow">Account access</p>
      <h1>Sign in</h1>
    </div>

    <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

    <label for="email">Email address</label>
    <input
      id="email"
      v-model="email"
      type="email"
      name="email"
      autocomplete="username"
      required
      maxlength="255"
      placeholder="name@council.gov"
      :disabled="isSubmitting"
    />

    <label for="password">Password</label>
    <input
      id="password"
      v-model="password"
      :type="showPassword ? 'text' : 'password'"
      name="password"
      autocomplete="current-password"
      required
      :disabled="isSubmitting"
    />

    <label class="show-password" for="show-password">
      <input id="show-password" v-model="showPassword" type="checkbox" :disabled="isSubmitting" />
      <span>Show password</span>
    </label>

    <button type="submit" :disabled="isSubmitting">
      {{ isSubmitting ? 'Signing in...' : 'Sign in' }}
    </button>
  </form>
</template>