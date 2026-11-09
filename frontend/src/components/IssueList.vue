<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../services/apiClient'
import { fetchIssuePage } from '../services/issues'

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
const selectedStatus = ref('')
const pagination = ref({ current_page: 1, per_page: 15, total: 0, last_page: 1 })
const summary = ref({ total: 0, reported: 0, assigned: 0, in_progress: 0, resolved: 0 })
const isLoading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const statuses = [
  { value: '', label: 'All' },
  { value: 'reported', label: 'Reported' },
  { value: 'assigned', label: 'Assigned' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'resolved', label: 'Resolved' },
]

async function loadIssues(page = 1) {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const result = await fetchIssuePage({ status: selectedStatus.value, page })
    issues.value = result.issues
    pagination.value = result.pagination
    summary.value = result.summary
    emit('loaded', result.summary)
  } catch (error) {
    issues.value = []
    if (error instanceof ApiError && error.status === 403) {
      errorMessage.value = 'You are not allowed to view these issues.'
    } else {
      errorMessage.value = 'Unable to load issues right now.'
    }
  } finally {
    isLoading.value = false
  }
}

function selectStatus(status) {
  if (selectedStatus.value === status) return
  selectedStatus.value = status
  loadIssues(1)
}

function statusCount(status) {
  if (!status) return summary.value.total
  return summary.value[status]
}

function detailRoute(issue) {
  if (props.role === 'admin') {
    return { name: 'admin-issue-detail', params: { issueId: issue.id } }
  }
  if (props.role === 'contractor') {
    return { name: 'contractor-issue-detail', params: { issueId: issue.id } }
  }
  return { name: 'inspector-issue-detail', params: { issueId: issue.id } }
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
  <section
    class="issue-queue"
    :aria-label="role === 'admin' ? 'Issues' : (role === 'contractor' ? 'Assigned work queue' : 'Your reports')"
  >
    <header class="issue-queue-header">
      <div>
        <p class="eyebrow">{{ role === 'admin' ? 'Incoming reports' : (role === 'contractor' ? 'Work queue' : 'Report history') }}</p>
        <h2>{{ role === 'admin' ? 'Issues' : (role === 'contractor' ? 'Assigned to you' : 'Your reports') }}</h2>
      </div>
      <button class="quiet-action" type="button" :disabled="isLoading" @click="loadIssues">
        {{ isLoading ? 'Loading...' : 'Refresh' }}
      </button>
    </header>

    <p v-if="successMessage" class="success-message" role="status">{{ successMessage }}</p>
    <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="issue-empty-state" role="status">Loading reports...</p>

    <template v-else>
      <nav class="issue-status-filters" aria-label="Filter issues by status">
        <button
          v-for="status in statuses"
          :key="status.value || 'all'"
          type="button"
          :aria-pressed="selectedStatus === status.value"
          @click="selectStatus(status.value)"
        >
          {{ status.label }} <span>{{ statusCount(status.value) }}</span>
        </button>
      </nav>

      <p v-if="issues.length === 0" class="issue-empty-state">
        {{ selectedStatus ? `No ${statuses.find((status) => status.value === selectedStatus)?.label.toLowerCase()} issues.` : (role === 'admin' ? 'No issues have been reported.' : (role === 'contractor' ? 'No assigned work.' : 'No reports yet.')) }}
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

      <footer class="issue-pagination" aria-label="Issue list pagination">
        <span>
          Showing {{ pagination.total === 0 ? 0 : ((pagination.current_page - 1) * pagination.per_page) + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }}
        </span>
        <div>
          <button type="button" :disabled="isLoading || pagination.current_page <= 1" @click="loadIssues(pagination.current_page - 1)">Previous</button>
          <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button type="button" :disabled="isLoading || pagination.current_page >= pagination.last_page" @click="loadIssues(pagination.current_page + 1)">Next</button>
        </div>
      </footer>
    </template>
  </section>
</template>
