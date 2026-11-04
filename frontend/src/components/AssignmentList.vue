<script setup>
import { onMounted, ref } from 'vue'
import { fetchAssignments } from '../services/assignments'
import { AuthRequestError } from '../services/auth'

const props = defineProps({
  role: {
    type: String,
    required: true,
  },
})
const emit = defineEmits(['loaded'])

const assignments = ref([])
const isLoading = ref(true)
const errorMessage = ref('')

async function loadAssignments() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    assignments.value = await fetchAssignments()
    emit('loaded', assignments.value)
  } catch (error) {
    assignments.value = []
    errorMessage.value = error instanceof AuthRequestError && error.status === 403
      ? 'You are not allowed to view assignments.'
      : 'Unable to load assignments right now.'
  } finally {
    isLoading.value = false
  }
}

function detailRoute(assignment) {
  return {
    name: props.role === 'admin' ? 'admin-assignment-detail' : 'contractor-assignment-detail',
    params: { assignmentId: assignment.id },
  }
}

onMounted(loadAssignments)
</script>

<template>
  <section class="assignment-list-section" aria-label="Maintenance assignments">
    <header class="assignment-list-header">
      <div>
        <p class="eyebrow">{{ role === 'admin' ? 'Work packages' : 'Your work packages' }}</p>
        <h2>{{ role === 'admin' ? 'Assignments' : 'Maintenance assignments' }}</h2>
      </div>
      <button class="quiet-action" type="button" :disabled="isLoading" @click="loadAssignments">
        {{ isLoading ? 'Loading...' : 'Refresh' }}
      </button>
    </header>

    <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="workspace-empty-state" role="status">Loading assignments...</p>
    <p v-else-if="assignments.length === 0" class="workspace-empty-state">
      {{ role === 'admin' ? 'No maintenance assignments yet.' : 'No assignments currently involve you.' }}
    </p>

    <ul v-else class="assignment-list">
      <li v-for="assignment in assignments" :key="assignment.id">
        <RouterLink class="assignment-list-card" :to="detailRoute(assignment)">
          <span class="assignment-list-main">
            <span class="assignment-list-title-row">
              <strong>{{ assignment.title }}</strong>
              <span class="assignment-state" :class="assignment.issues.length ? 'assignment-state-active' : 'assignment-state-empty'">
                {{ assignment.issues.length ? 'Active' : 'No current issues' }}
              </span>
            </span>
            <small>
              {{ assignment.issues.length }} {{ assignment.issues.length === 1 ? 'issue' : 'issues' }}
              · {{ assignment.contractors.length }} {{ assignment.contractors.length === 1 ? 'contractor' : 'contractors' }}
            </small>
            <span v-if="assignment.instructions" class="assignment-list-instructions">{{ assignment.instructions }}</span>
          </span>
          <span class="assignment-list-arrow" aria-hidden="true">→</span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
