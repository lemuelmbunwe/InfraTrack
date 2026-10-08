<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import WorkspaceShell from '../components/WorkspaceShell.vue'
import { AuthRequestError } from '../services/auth'
import { createIssue, generateYaoundeSampleCoordinates } from '../services/issues'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const coordinates = ref(generateYaoundeSampleCoordinates())
const addressText = ref('')
const description = ref('')
const severity = ref('medium')
const photo = ref(null)
const photoPreview = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')
const errors = ref({})

function regenerateCoordinates() {
  coordinates.value = generateYaoundeSampleCoordinates()
}

function selectPhoto(event) {
  if (photoPreview.value) {
    URL.revokeObjectURL(photoPreview.value)
  }

  photo.value = event.target.files?.[0] || null
  photoPreview.value = photo.value ? URL.createObjectURL(photo.value) : ''
  delete errors.value.photo
}

async function submitReport() {
  isSubmitting.value = true
  errorMessage.value = ''
  errors.value = {}

  const formData = new FormData()
  formData.append('photo', photo.value)
  formData.append('latitude', coordinates.value.latitude)
  formData.append('longitude', coordinates.value.longitude)
  formData.append('severity', severity.value)
  formData.append('address_text', addressText.value)
  formData.append('description', description.value)

  try {
    await createIssue(formData)
    await router.replace({ name: 'inspector-home', query: { notice: 'created' } })
  } catch (error) {
    if (error instanceof AuthRequestError && error.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(error.payload.errors || {}).map(([field, messages]) => [
          field,
          Array.isArray(messages) ? messages : [messages],
        ]),
      )
    } else {
      errorMessage.value = 'Unable to submit this report right now.'
    }
  } finally {
    isSubmitting.value = false
  }
}

onBeforeUnmount(() => {
  if (photoPreview.value) {
    URL.revokeObjectURL(photoPreview.value)
  }
})
</script>

<template>
  <WorkspaceShell :user="auth.currentUser.value" eyebrow="Field inspector" title="Report a pothole">
    <form class="issue-form" @submit.prevent="submitReport">
      <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

      <section class="issue-form-section">
        <div class="issue-form-section-heading">
          <p class="eyebrow">01 / Photograph</p>
          <h2>Issue evidence</h2>
        </div>
        <label class="photo-picker" for="issue-photo">
          <img v-if="photoPreview" :src="photoPreview" alt="Selected pothole preview" />
          <span v-else class="photo-placeholder" aria-hidden="true">+</span>
          <span>{{ photo ? photo.name : 'Choose a pothole photo' }}</span>
          <small>JPEG, PNG, or WebP · up to 10 MB</small>
        </label>
        <input
          id="issue-photo"
          class="visually-hidden"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          required
          @change="selectPhoto"
        />
        <p v-if="errors.photo" class="field-error">{{ errors.photo[0] }}</p>
      </section>

      <section class="issue-form-section">
        <div class="issue-form-section-heading">
          <p class="eyebrow">02 / Location</p>
          <h2>Report location</h2>
        </div>
        <p class="sample-location-note">Sample coordinates near Yaounde. Adjust them to the report location.</p>
        <div class="coordinate-grid">
          <div class="form-field">
            <label for="issue-latitude">Latitude</label>
            <input
              id="issue-latitude"
              v-model="coordinates.latitude"
              type="number"
              min="-90"
              max="90"
              step="any"
              required
              :aria-invalid="Boolean(errors.latitude)"
            />
            <p v-if="errors.latitude" class="field-error">{{ errors.latitude[0] }}</p>
          </div>
          <div class="form-field">
            <label for="issue-longitude">Longitude</label>
            <input
              id="issue-longitude"
              v-model="coordinates.longitude"
              type="number"
              min="-180"
              max="180"
              step="any"
              required
              :aria-invalid="Boolean(errors.longitude)"
            />
            <p v-if="errors.longitude" class="field-error">{{ errors.longitude[0] }}</p>
          </div>
        </div>
        <button class="quiet-action coordinate-action" type="button" @click="regenerateCoordinates">
          Generate new sample coordinates
        </button>
        <div class="form-field issue-address-field">
          <label for="issue-address-text">Text address <span>Optional</span></label>
          <input id="issue-address-text" v-model="addressText" maxlength="1000" placeholder="Street, neighborhood, or nearby landmark" />
          <p v-if="errors.address_text" class="field-error">{{ errors.address_text[0] }}</p>
        </div>
      </section>

      <section class="issue-form-section">
        <div class="issue-form-section-heading">
          <p class="eyebrow">03 / Assessment</p>
          <h2>Initial severity</h2>
        </div>
        <fieldset class="severity-options">
          <legend>Choose severity</legend>
          <label v-for="level in ['low', 'medium', 'high']" :key="level" class="severity-option">
            <input v-model="severity" type="radio" name="severity" :value="level" />
            <span :class="`severity-${level}`">{{ level }}</span>
          </label>
        </fieldset>
        <p v-if="errors.severity" class="field-error">{{ errors.severity[0] }}</p>
        <div class="form-field issue-address-field">
          <label for="issue-description">Description <span>Optional</span></label>
          <textarea id="issue-description" v-model="description" rows="4" maxlength="5000" placeholder="Add useful details about the pothole"></textarea>
          <p v-if="errors.description" class="field-error">{{ errors.description[0] }}</p>
        </div>
      </section>

      <div class="issue-form-actions">
        <RouterLink class="secondary-action" :to="{ name: 'inspector-home' }">Cancel</RouterLink>
        <button class="primary-action" type="submit" :disabled="isSubmitting || !photo">
          {{ isSubmitting ? 'Submitting report...' : 'Submit report' }}
        </button>
      </div>
    </form>
  </WorkspaceShell>
</template>
