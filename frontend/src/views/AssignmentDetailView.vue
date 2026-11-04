<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import WorkspaceShell from '../components/WorkspaceShell.vue'
import { AuthRequestError } from '../services/auth'
import { fetchAssignment, updateAssignment } from '../services/assignments'
import { fetchContractors, assignIssue } from '../services/issues'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const assignment = ref(null)
const contractors = ref([])
const isLoading = ref(true)
const isSaving = ref(false)
const isReassigning = ref(false)
const isEditingDetails = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const errors = ref({})
const editing = reactive({ title: '', instructions: '' })
const activeIssueId = ref(null)
const selectedContractorId = ref('')

const isAdmin = computed(() => auth.currentUser.value?.role === 'admin')
const isContractor = computed(() => auth.currentUser.value?.role === 'contractor')

async function loadAssignment() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const data = await fetchAssignment(route.params.assignmentId)
    assignment.value = data
    editing.title = data.title
    editing.instructions = data.instructions || ''
    if (isAdmin.value) {
      try {
        contractors.value = await fetchContractors()
      } catch {
        contractors.value = []
        errorMessage.value = 'Assignment loaded, but the contractor list is unavailable.'
      }
    }
  } catch (error) {
    assignment.value = null
    errorMessage.value = error instanceof AuthRequestError && error.status === 404
      ? 'This assignment is unavailable.'
      : 'Unable to load assignment details right now.'
  } finally {
    isLoading.value = false
  }
}

function canOpenIssue(issue) {
  return isAdmin.value || issue.assigned_contractor?.id === auth.currentUser.value?.id
}

function issueDetailRoute(issue) {
  return {
    name: isAdmin.value ? 'admin-issue-detail' : 'contractor-issue-detail',
    params: { issueId: issue.id },
  }
}

function startReassignment(issue) {
  activeIssueId.value = issue.id
  selectedContractorId.value = ''
  errors.value = {}
  errorMessage.value = ''
}

function cancelReassignment() {
  activeIssueId.value = null
  selectedContractorId.value = ''
  errors.value = {}
}

function fieldError(field) {
  return errors.value[field]?.[0] || ''
}

function startEditingDetails() {
  editing.title = assignment.value.title
  editing.instructions = assignment.value.instructions || ''
  errors.value = {}
  errorMessage.value = ''
  successMessage.value = ''
  isEditingDetails.value = true
}

function cancelEditingDetails() {
  editing.title = assignment.value.title
  editing.instructions = assignment.value.instructions || ''
  errors.value = {}
  errorMessage.value = ''
  isEditingDetails.value = false
}

async function saveDetails() {
  isSaving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  errors.value = {}

  try {
    assignment.value = await updateAssignment(assignment.value.id, {
      title: editing.title,
      instructions: editing.instructions.trim() || null,
    })
    isEditingDetails.value = false
    successMessage.value = 'Assignment details saved.'
  } catch (error) {
    if (error instanceof AuthRequestError && error.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(error.payload.errors || {}).map(([field, messages]) => [
          field,
          Array.isArray(messages) ? messages : [messages],
        ]),
      )
      errorMessage.value = 'Review the highlighted assignment details.'
    } else if (error instanceof AuthRequestError && error.status === 403) {
      errorMessage.value = 'You are not allowed to edit this assignment.'
    } else {
      errorMessage.value = 'Unable to save assignment details right now.'
    }
  } finally {
    isSaving.value = false
  }
}

async function reassignIssue(issue) {
  if (!selectedContractorId.value || issue.status !== 'assigned') {
    return
  }

  isReassigning.value = true
  errorMessage.value = ''
  successMessage.value = ''
  errors.value = {}

  try {
    await assignIssue(issue.id, Number(selectedContractorId.value))
    await loadAssignment()
    activeIssueId.value = null
    selectedContractorId.value = ''
    successMessage.value = `Issue #${issue.id} was reassigned.`
  } catch (error) {
    if (error instanceof AuthRequestError && error.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(error.payload.errors || {}).map(([field, messages]) => [
          field,
          Array.isArray(messages) ? messages : [messages],
        ]),
      )
      errorMessage.value = 'The issue could not be reassigned. Check its status and contractor.'
    } else {
      errorMessage.value = 'Unable to reassign this issue right now.'
    }
  } finally {
    isReassigning.value = false
  }
}

function backToHome() {
  router.push({ name: isAdmin.value ? 'admin-home' : 'contractor-home' })
}

function formatStatus(status) {
  return status.replaceAll('_', ' ')
}

onMounted(loadAssignment)
</script>

<template>
  <WorkspaceShell
    :user="auth.currentUser.value"
    :eyebrow="isAdmin ? 'Work package management' : 'Your work package'"
    :title="assignment?.title || 'Assignment details'"
  >
    <div class="assignment-page-toolbar">
      <button class="quiet-action" type="button" @click="backToHome">← Back to workboard</button>
      <button v-if="isAdmin && !isEditingDetails" class="quiet-action" type="button" @click="startEditingDetails">
        Edit assignment
      </button>
    </div>

    <p v-if="isLoading" class="workspace-empty-state" role="status">Loading assignment...</p>
    <p v-else-if="errorMessage && !assignment" class="error-message" role="alert">{{ errorMessage }}</p>

    <template v-else-if="assignment">
      <p v-if="successMessage" class="success-message" role="status">{{ successMessage }}</p>
      <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

      <form v-if="isAdmin && isEditingDetails" class="assignment-edit-form" @submit.prevent="saveDetails">
        <p class="eyebrow">Assignment details</p>
        <div class="form-field">
          <label for="assignment-edit-title">Title</label>
          <input id="assignment-edit-title" v-model="editing.title" maxlength="255" required :aria-invalid="Boolean(fieldError('title'))" />
          <p v-if="fieldError('title')" class="field-error">{{ fieldError('title') }}</p>
        </div>
        <div class="form-field">
          <label for="assignment-edit-instructions">Instructions <span>Optional</span></label>
          <textarea id="assignment-edit-instructions" v-model="editing.instructions" rows="3" />
          <p v-if="fieldError('instructions')" class="field-error">{{ fieldError('instructions') }}</p>
        </div>
        <div class="assignment-edit-actions">
          <button class="quiet-action" type="button" :disabled="isSaving" @click="cancelEditingDetails">Cancel</button>
          <button class="primary-action" type="submit" :disabled="isSaving || !editing.title.trim()">
            {{ isSaving ? 'Saving...' : 'Save details' }}
          </button>
        </div>
      </form>

      <section class="assignment-overview-card">
        <div class="assignment-overview-section">
          <p class="eyebrow">Initial contractor</p>
          <strong>{{ assignment.initial_contractor?.name || 'Not recorded' }}</strong>
        </div>
        <div class="assignment-overview-section">
          <p class="eyebrow">Currently involved</p>
          <span v-if="assignment.contractors.length" class="assignment-contractor-list">
            <span v-for="contractor in assignment.contractors" :key="contractor.id" class="assignment-contractor-chip">
              {{ contractor.name }}
            </span>
          </span>
          <span v-else class="muted-copy">No contractors currently have issues in this assignment.</span>
        </div>
      </section>

      <section class="assignment-roster" aria-label="Issues in this assignment">
        <header class="assignment-roster-header">
          <div>
            <p class="eyebrow">Work allocation</p>
            <h2>Issues in this assignment <span>{{ assignment.issues.length }}</span></h2>
          </div>
        </header>

        <p v-if="assignment.issues.length === 0" class="workspace-empty-state">
          This assignment has no currently allocated issues.
        </p>

        <ul v-else class="assignment-roster-list">
          <li v-for="issue in assignment.issues" :key="issue.id" class="assignment-roster-item">
            <div class="assignment-roster-summary">
              <RouterLink v-if="canOpenIssue(issue)" class="assignment-roster-id assignment-roster-link" :to="issueDetailRoute(issue)">
                Issue #{{ issue.id }}
              </RouterLink>
              <span v-else class="assignment-roster-id">Issue #{{ issue.id }}</span>
              <span class="status-label">{{ formatStatus(issue.status) }}</span>
              <span class="assignment-roster-responsibility">
                <span class="assignment-roster-contractor">{{ issue.assigned_contractor?.name || 'Unassigned' }}</span>
                <button
                  v-if="isAdmin && issue.status === 'assigned'"
                  class="quiet-action assignment-reassign-button"
                  type="button"
                  :disabled="isReassigning"
                  @click="activeIssueId === issue.id ? cancelReassignment() : startReassignment(issue)"
                >
                  {{ activeIssueId === issue.id ? 'Cancel' : 'Reassign' }}
                </button>
              </span>
            </div>

            <div v-if="isAdmin && activeIssueId === issue.id" class="assignment-reassign-controls">
              <div class="form-field">
                <label :for="`contractor-${issue.id}`">Reassign issue #{{ issue.id }}</label>
                <select :id="`contractor-${issue.id}`" v-model="selectedContractorId" :disabled="isReassigning">
                  <option value="">Select contractor</option>
                  <option
                    v-for="contractor in contractors"
                    :key="contractor.id"
                    :value="contractor.id"
                    :disabled="contractor.id === issue.assigned_contractor?.id"
                  >
                    {{ contractor.name }}{{ contractor.id === issue.assigned_contractor?.id ? ' (current)' : '' }}
                  </option>
                </select>
                <p v-if="fieldError('contractor_id')" class="field-error">{{ fieldError('contractor_id') }}</p>
              </div>
              <button class="primary-action" type="button" :disabled="!selectedContractorId || isReassigning" @click="reassignIssue(issue)">
                {{ isReassigning ? 'Reassigning...' : 'Confirm reassignment' }}
              </button>
            </div>
          </li>
        </ul>
      </section>
    </template>
  </WorkspaceShell>
</template>
