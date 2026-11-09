import { apiRequest } from './apiClient'

export async function fetchAssignments() {
  const payload = await apiRequest('/v1/assignments', {
    fallbackMessage: 'Unable to load assignment data.',
  })

  return payload.assignments
}

export async function fetchAssignment(assignmentId) {
  const payload = await apiRequest(`/v1/assignments/${encodeURIComponent(assignmentId)}`, {
    fallbackMessage: 'Unable to load assignment data.',
  })

  return payload.assignment
}

export async function createAssignment(data) {
  const payload = await apiRequest('/v1/assignments', {
    method: 'POST',
    body: data,
    fallbackMessage: 'Unable to create assignment.',
  })

  return payload.assignment
}

export async function updateAssignment(assignmentId, changes) {
  const payload = await apiRequest(`/v1/assignments/${encodeURIComponent(assignmentId)}`, {
    method: 'PATCH',
    body: changes,
    fallbackMessage: 'Unable to save assignment details.',
  })

  return payload.assignment
}
