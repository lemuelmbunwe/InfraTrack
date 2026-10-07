<script setup>
import { ref, watch } from 'vue'

defineProps({
  errors: {
    type: Object,
    default: () => ({}),
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
  errorMessage: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['submit'])
const name = ref('')
const email = ref('')
const role = ref('inspector')
const superAdmin = ref(false)
const password = ref('')
const passwordConfirmation = ref('')

watch(role, (selectedRole) => {
  if (selectedRole !== 'admin') {
    superAdmin.value = false
  }
})

function submit() {
  emit('submit', {
    name: name.value,
    email: email.value,
    role: role.value,
    super: role.value === 'admin' && superAdmin.value,
    password: password.value,
    password_confirmation: passwordConfirmation.value,
  })
}
</script>

<template>
  <form class="account-form" @submit.prevent="submit">
    <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

    <div class="form-grid">
      <div class="form-field form-field-wide">
        <label for="account-name">Full name</label>
        <input
          id="account-name"
          v-model="name"
          autocomplete="name"
          maxlength="255"
          required
          :disabled="isSubmitting"
          :aria-invalid="Boolean(errors.name)"
        />
        <p v-if="errors.name" class="field-error">{{ errors.name[0] }}</p>
      </div>

      <div class="form-field form-field-wide">
        <label for="account-email">Email address</label>
        <input
          id="account-email"
          v-model="email"
          type="email"
          autocomplete="email"
          maxlength="255"
          required
          :disabled="isSubmitting"
          :aria-invalid="Boolean(errors.email)"
        />
        <p v-if="errors.email" class="field-error">{{ errors.email[0] }}</p>
      </div>

      <div class="form-field">
        <label for="account-role">Role</label>
        <select id="account-role" v-model="role" required :disabled="isSubmitting">
          <option value="admin">Admin</option>
          <option value="inspector">Inspector</option>
          <option value="contractor">Contractor</option>
        </select>
        <p v-if="errors.role" class="field-error">{{ errors.role[0] }}</p>
      </div>

      <label v-if="role === 'admin'" class="super-toggle">
        <input v-model="superAdmin" type="checkbox" :disabled="isSubmitting" />
        <span>Grant super-admin access</span>
        <small>Super-admins can create and promote user accounts.</small>
      </label>

      <div class="form-field">
        <label for="account-password">Initial password</label>
        <input
          id="account-password"
          v-model="password"
          type="password"
          autocomplete="new-password"
          minlength="8"
          required
          :disabled="isSubmitting"
          :aria-invalid="Boolean(errors.password)"
        />
        <p v-if="errors.password" class="field-error">{{ errors.password[0] }}</p>
      </div>

      <div class="form-field">
        <label for="account-password-confirmation">Confirm password</label>
        <input
          id="account-password-confirmation"
          v-model="passwordConfirmation"
          type="password"
          autocomplete="new-password"
          minlength="8"
          required
          :disabled="isSubmitting"
        />
      </div>
    </div>

    <button class="primary-action" type="submit" :disabled="isSubmitting">
      {{ isSubmitting ? 'Creating account...' : 'Create account' }}
    </button>
  </form>
</template>