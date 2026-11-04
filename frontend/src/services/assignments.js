import { apiBaseUrl, AuthRequestError, getAccessToken } from './auth'

async function assignmentRequest(path, { method = 'GET', body } = {}) {
  const token = getAccessToken()
  const response = await fetch(`${apiBaseUrl}/v1/assignments${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  })
  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new AuthRequestError(payload.message || 'Unable to load assignment data.', response.status, payload)
  }

  return payload
}

export async function fetchAssignments() {
  const payload = await assignmentRequest('')
  return payload.assignments
}

export async function fetchAssignment(assignmentId) {
  const payload = await assignmentRequest(`/${encodeURIComponent(assignmentId)}`)
  return payload.assignment
}

export async function createAssignment(data) {
  const payload = await assignmentRequest('', { method: 'POST', body: data })
  return payload.assignment
}

export async function updateAssignment(assignmentId, changes) {
  const payload = await assignmentRequest(`/${encodeURIComponent(assignmentId)}`, {
    method: 'PATCH',
    body: changes,
  })
  return payload.assignment
}
