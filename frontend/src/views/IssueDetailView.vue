<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import WorkspaceShell from '../components/WorkspaceShell.vue'
import { AuthRequestError } from '../services/auth'
import { fetchIssue, fetchIssuePhoto, updateIssue } from '../services/issues'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const issue = ref(null)
const photoSource = ref('')
const replacementPhoto = ref(null)
const isLoading = ref(true)
const isSaving = ref(false)
const errorMessage = ref('')
const errors = ref({})
const isAdmin = computed(() => auth.currentUser.value?.role === 'admin')
const canEdit = computed(() => isAdmin.value || issue.value?.status !== 'assigned')
const form = reactive({
  latitude: '',
  longitude: '',
  address: '',
  address_text: '',
  description: '',
  severity: 'medium',
  status: 'reported',
})

function fillForm(issueData) {
  form.latitude = issueData.latitude
  form.longitude = issueData.longitude
  form.address = issueData.address || ''
  form.address_text = issueData.address_text || ''
  form.description = issueData.description || ''
  form.severity = issueData.severity
  form.status = issueData.status
}

async function loadIssue() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    issue.value = await fetchIssue(route.params.issueId)
    fillForm(issue.value)
    photoSource.value = await fetchIssuePhoto(issue.value.photo_url)
  } catch (error) {
    errorMessage.value = error instanceof AuthRequestError && error.status === 404
      ? 'This issue is unavailable.'
      : 'Unable to load issue details right now.'
  } finally {
    isLoading.value = false
  }
}

function selectPhoto(event) {
  replacementPhoto.value = event.target.files?.[0] || null
}

async function saveChanges() {
  if (!issue.value) {
    return
  }

  isSaving.value = true
  errorMessage.value = ''
  errors.value = {}

  const changes = isAdmin.value
    ? { status: form.status, severity: form.severity, description: form.description }
    : new FormData()

  if (!isAdmin.value) {
    changes.append('latitude', form.latitude)
    changes.append('longitude', form.longitude)
    changes.append('address', form.address)
    changes.append('address_text', form.address_text)
    changes.append('description', form.description)
    changes.append('severity', form.severity)
    if (replacementPhoto.value) {
      changes.append('photo', replacementPhoto.value)
    }
  }

  try {
    await updateIssue(issue.value.id, changes)
    await router.replace({
      name: isAdmin.value ? 'admin-home' : 'inspector-home',
      query: { notice: 'updated' },
    })
  } catch (error) {
    if (error instanceof AuthRequestError && error.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(error.payload.errors || {}).map(([field, messages]) => [
          field,
          Array.isArray(messages) ? messages : [messages],
        ]),
      )
    } else if (error instanceof AuthRequestError && error.status === 403) {
      errorMessage.value = 'You are not allowed to edit this issue.'
    } else {
      errorMessage.value = 'Unable to save issue changes right now.'
    }
  } finally {
    isSaving.value = false
  }
}

function returnToList() {
  router.push({ name: isAdmin.value ? 'admin-home' : 'inspector-home' })
}

onMounted(loadIssue)

onBeforeUnmount(() => {
  if (photoSource.value) {
    URL.revokeObjectURL(photoSource.value)
  }
})
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    :eyebrow="isAdmin ? 'Admin review' : 'Field inspector'"
    title="Issue details"
  >
    <p v-if="isLoading" class="issue-empty-state" role="status">Loading issue...</p>
    <p v-else-if="errorMessage && !issue" class="error-message" role="alert">{{ errorMessage }}</p>

    <template v-else-if="issue">
      <div class="issue-detail-toolbar">
        <button class="quiet-action" type="button" @click="returnToList">← Back to reports</button>
        <div class="issue-detail-tags">
          <span class="status-label">{{ issue.status.replace('_', ' ') }}</span>
          <span class="severity-label" :class="`severity-${issue.severity}`">{{ issue.severity }}</span>
        </div>
      </div>

      <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

      <div class="issue-detail-grid">
        <section class="issue-photo-panel">
          <img v-if="photoSource" :src="photoSource" alt="Reported pothole" />
          <p v-else class="issue-photo-loading">Loading photo...</p>
          <p class="issue-photo-caption">Report #{{ issue.id }} · {{ new Date(issue.reported_at).toLocaleString() }}</p>
        </section>

        <form class="issue-detail-form" @submit.prevent="saveChanges">
          <section class="issue-form-section">
            <p class="eyebrow">Reported by</p>
            <h2>{{ issue.reporter?.name }}</h2>
            <p class="detail-coordinate-line">{{ Number(issue.latitude).toFixed(6) }}, {{ Number(issue.longitude).toFixed(6) }}</p>
          </section>

          <section class="issue-form-section">
            <div class="issue-form-section-heading">
              <p class="eyebrow">Location</p>
            </div>
            <div class="form-field">
              <label for="detail-address">Geocoded address <span>Optional</span></label>
              <input id="detail-address" v-model="form.address" :disabled="!canEdit || isAdmin" maxlength="1000" />
              <p v-if="errors.address" class="field-error">{{ errors.address[0] }}</p>
            </div>
            <div class="form-field">
              <label for="detail-address-text">Text address <span>Optional</span></label>
              <input id="detail-address-text" v-model="form.address_text" :disabled="!canEdit || isAdmin" maxlength="1000" />
              <p v-if="errors.address_text" class="field-error">{{ errors.address_text[0] }}</p>
            </div>
            <template v-if="!isAdmin">
              <div class="coordinate-grid">
                <div class="form-field">
                  <label for="detail-latitude">Latitude</label>
                  <input id="detail-latitude" v-model="form.latitude" type="number" min="-90" max="90" step="any" :disabled="!canEdit" />
                  <p v-if="errors.latitude" class="field-error">{{ errors.latitude[0] }}</p>
                </div>
                <div class="form-field">
                  <label for="detail-longitude">Longitude</label>
                  <input id="detail-longitude" v-model="form.longitude" type="number" min="-180" max="180" step="any" :disabled="!canEdit" />
                  <p v-if="errors.longitude" class="field-error">{{ errors.longitude[0] }}</p>
                </div>
              </div>
              <div class="form-field">
                <label for="replacement-photo">Replace photo <span>Optional</span></label>
                <input id="replacement-photo" type="file" accept="image/jpeg,image/png,image/webp" :disabled="!canEdit" @change="selectPhoto" />
                <p v-if="errors.photo" class="field-error">{{ errors.photo[0] }}</p>
              </div>
            </template>
          </section>

          <section class="issue-form-section">
            <div v-if="isAdmin" class="form-field">
              <label for="detail-status">Status</label>
              <select id="detail-status" v-model="form.status">
                <option value="reported">Reported</option>
                <option value="assigned">Assigned</option>
                <option value="in_progress">In progress</option>
                <option value="resolved">Resolved</option>
              </select>
              <p v-if="errors.status" class="field-error">{{ errors.status[0] }}</p>
            </div>
            <fieldset class="severity-options">
              <legend>Severity</legend>
              <label v-for="level in ['low', 'medium', 'high']" :key="level" class="severity-option">
                <input v-model="form.severity" type="radio" name="detail-severity" :value="level" :disabled="!canEdit" />
                <span :class="`severity-${level}`">{{ level }}</span>
              </label>
            </fieldset>
            <p v-if="errors.severity" class="field-error">{{ errors.severity[0] }}</p>
            <div class="form-field">
              <label for="detail-description">Description <span>Optional</span></label>
              <textarea id="detail-description" v-model="form.description" rows="4" maxlength="5000" :disabled="!canEdit"></textarea>
              <p v-if="errors.description" class="field-error">{{ errors.description[0] }}</p>
            </div>
          </section>

          <div v-if="canEdit" class="issue-form-actions">
            <button class="primary-action" type="submit" :disabled="isSaving">
              {{ isSaving ? 'Saving...' : 'Save changes' }}
            </button>
          </div>
          <p v-else class="issue-empty-state">This issue can no longer be edited by its Inspector.</p>
        </form>
      </div>

      <section v-if="issue.status_history?.length" class="status-history">
        <p class="eyebrow">Status history</p>
        <ol>
          <li v-for="(entry, index) in issue.status_history" :key="`${entry.changed_at}-${index}`">
            <span>{{ entry.new_status.replace('_', ' ') }}</span>
            <time :datetime="entry.changed_at">{{ new Date(entry.changed_at).toLocaleString() }}</time>
          </li>
        </ol>
      </section>
    </template>
  </WorkspaceShell>
</template>
