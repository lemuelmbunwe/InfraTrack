<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AuthRequestError } from '../services/auth'
import { fetchIssues } from '../services/issues'

const props = defineProps({
  role: {
    type: String,
    required: true,
  },
})
const emit = defineEmits(['loaded'])
const route = useRoute()
const router = useRouter()

const issues = ref([])
const isLoading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

async function loadIssues() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    issues.value = await fetchIssues()
    emit('loaded', issues.value)
  } catch (error) {
    issues.value = []
    if (error instanceof AuthRequestError && error.status === 403) {
      errorMessage.value = 'You are not allowed to view these issues.'
    } else {
      errorMessage.value = 'Unable to load issues right now.'
    }
  } finally {
    isLoading.value = false
  }
}

function detailRoute(issue) {
  return {
    name: props.role === 'admin' ? 'admin-issue-detail' : 'inspector-issue-detail',
    params: { issueId: issue.id },
  }
}

function formatDate(value) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(() => {
  if (route.query.notice === 'created') {
    successMessage.value = 'Issue report submitted successfully.'
  } else if (route.query.notice === 'updated') {
    successMessage.value = 'Issue details saved successfully.'
  }

  if (route.query.notice) {
    const query = { ...route.query }
    delete query.notice
    void router.replace({ name: route.name, params: route.params, query })
  }

  loadIssues()
})
</script>

<template>
  <section class="issue-queue" :aria-label="role === 'admin' ? 'Reported issues' : 'Your reports'">
    <header class="issue-queue-header">
      <div>
        <p class="eyebrow">{{ role === 'admin' ? 'Incoming reports' : 'Report history' }}</p>
        <h2>{{ role === 'admin' ? 'Reported issues' : 'Your reports' }}</h2>
      </div>
      <button class="quiet-action" type="button" :disabled="isLoading" @click="loadIssues">
        {{ isLoading ? 'Loading...' : 'Refresh' }}
      </button>
    </header>

    <p v-if="successMessage" class="success-message" role="status">{{ successMessage }}</p>
    <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="issue-empty-state" role="status">Loading reports...</p>
    <p v-else-if="issues.length === 0" class="issue-empty-state">
      {{ role === 'admin' ? 'No issues have been reported.' : 'No reports yet.' }}
    </p>

    <ul v-else class="issue-list">
      <li v-for="issue in issues" :key="issue.id">
        <RouterLink class="issue-list-item" :to="detailRoute(issue)">
          <span class="issue-list-id">#{{ issue.id }}</span>
          <span class="issue-list-main">
            <strong>{{ issue.address_text || issue.address || 'Location not provided' }}</strong>
            <small>{{ issue.reporter?.name || 'Inspector' }} · {{ formatDate(issue.reported_at) }}</small>
          </span>
          <span class="issue-list-meta">
            <span class="severity-label" :class="`severity-${issue.severity}`">{{ issue.severity }}</span>
            <span class="status-label">{{ issue.status.replace('_', ' ') }}</span>
          </span>
          <span class="issue-list-arrow" aria-hidden="true">→</span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
