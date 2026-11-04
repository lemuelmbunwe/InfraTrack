<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import WorkspaceShell from '../components/WorkspaceShell.vue'
import { AuthRequestError } from '../services/auth'
import { createAssignment } from '../services/assignments'
import { fetchContractors, fetchIssues } from '../services/issues'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const title = ref('')
const instructions = ref('')
const contractorId = ref('')
const selectedIssueIds = ref([])
const issues = ref([])
const contractors = ref([])
const isLoading = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const errors = ref({})

const eligibleIssues = computed(() => issues.value.filter((issue) => (
  issue.status === 'reported' && !issue.assigned_contractor
)))

function fieldError(field) {
  const messages = errors.value[field]
    || Object.entries(errors.value).find(([key]) => key.startsWith(`${field}.`))?.[1]
  return messages?.[0] || ''
}

async function loadOptions() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [allIssues, activeContractors] = await Promise.all([fetchIssues(), fetchContractors()])
    issues.value = allIssues
    contractors.value = activeContractors
  } catch {
    errorMessage.value = 'Unable to load reported issues and contractors.'
  } finally {
    isLoading.value = false
  }
}

async function submitAssignment() {
  errorMessage.value = ''
  errors.value = {}

  if (selectedIssueIds.value.length === 0) {
    errors.value.issue_ids = ['Select at least one reported issue.']
    return
  }

  isSubmitting.value = true

  try {
    const assignment = await createAssignment({
      title: title.value,
      instructions: instructions.value.trim() || null,
      contractor_id: Number(contractorId.value),
      issue_ids: selectedIssueIds.value,
    })

    await router.replace({ name: 'admin-assignment-detail', params: { assignmentId: assignment.id } })
  } catch (error) {
    if (error instanceof AuthRequestError && error.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(error.payload.errors || {}).map(([field, messages]) => [
          field,
          Array.isArray(messages) ? messages : [messages],
        ]),
      )
      errorMessage.value = 'Review the highlighted fields and selected issues.'
    } else if (error instanceof AuthRequestError && error.status === 403) {
      errorMessage.value = 'You are not allowed to create assignments.'
    } else {
      errorMessage.value = 'Unable to create this assignment right now.'
    }
  } finally {
    isSubmitting.value = false
  }
}

onMounted(loadOptions)
</script>

<template>
  <WorkspaceShell :user="auth.currentUser.value" eyebrow="Workforce dispatch" title="Create assignment">
    <div class="assignment-page-toolbar">
      <RouterLink class="quiet-link" :to="{ name: 'admin-home' }">← Back to admin</RouterLink>
    </div>

    <form class="assignment-form" @submit.prevent="submitAssignment">
      <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

      <section class="assignment-form-section">
        <div class="issue-form-section-heading">
          <p class="eyebrow">01 / Work package</p>
          <h2>Assignment details</h2>
        </div>
        <div class="form-field">
          <label for="assignment-title">Assignment title</label>
          <input id="assignment-title" v-model="title" maxlength="255" required :aria-invalid="Boolean(fieldError('title'))" />
          <p v-if="fieldError('title')" class="field-error">{{ fieldError('title') }}</p>
        </div>
        <div class="form-field">
          <label for="assignment-instructions">Instructions <span>Optional</span></label>
          <textarea id="assignment-instructions" v-model="instructions" rows="4" />
          <p v-if="fieldError('instructions')" class="field-error">{{ fieldError('instructions') }}</p>
        </div>
      </section>

      <section class="assignment-form-section">
        <div class="issue-form-section-heading">
          <p class="eyebrow">02 / Contractor</p>
          <h2>Initial responsible contractor</h2>
        </div>
        <div class="form-field">
          <label for="assignment-contractor">Contractor</label>
          <select id="assignment-contractor" v-model="contractorId" required :disabled="isLoading || contractors.length === 0">
            <option value="">{{ isLoading ? 'Loading contractors...' : 'Select an active contractor' }}</option>
            <option v-for="contractor in contractors" :key="contractor.id" :value="contractor.id">
              {{ contractor.name }}
            </option>
          </select>
          <p v-if="fieldError('contractor_id')" class="field-error">{{ fieldError('contractor_id') }}</p>
        </div>
        <p class="form-help">All selected issues will initially be assigned to this contractor. Issues can be reassigned individually later.</p>
      </section>

      <section class="assignment-form-section">
        <div class="issue-form-section-heading">
          <p class="eyebrow">03 / Issues</p>
          <h2>Select reported issues</h2>
          <p class="form-help">Only unassigned issues in the reported status can be included.</p>
        </div>
        <p v-if="isLoading" class="workspace-empty-state" role="status">Loading eligible issues...</p>
        <p v-else-if="eligibleIssues.length === 0" class="workspace-empty-state">There are no unassigned reported issues available.</p>
        <ul v-else class="assignment-issue-picker">
          <li v-for="issue in eligibleIssues" :key="issue.id">
            <label class="assignment-issue-option">
              <input v-model="selectedIssueIds" type="checkbox" :value="issue.id" />
              <span class="assignment-issue-number">#{{ issue.id }}</span>
              <span class="assignment-issue-copy">
                <strong>{{ issue.address_text || issue.address || 'Location not provided' }}</strong>
                <small>{{ issue.severity }} severity · {{ issue.reporter?.name || 'Inspector' }}</small>
              </span>
              <span class="status-label">{{ issue.status.replace('_', ' ') }}</span>
            </label>
          </li>
        </ul>
        <p v-if="fieldError('issue_ids')" class="field-error">{{ fieldError('issue_ids') }}</p>
      </section>

      <div class="issue-form-actions">
        <RouterLink class="secondary-action" :to="{ name: 'admin-home' }">Cancel</RouterLink>
        <button class="primary-action" type="submit" :disabled="isSubmitting || isLoading || eligibleIssues.length === 0 || contractors.length === 0">
          {{ isSubmitting ? 'Creating assignment...' : `Create assignment${selectedIssueIds.length ? ` · ${selectedIssueIds.length} issues` : ''}` }}
        </button>
      </div>
    </form>
  </WorkspaceShell>
</template>
