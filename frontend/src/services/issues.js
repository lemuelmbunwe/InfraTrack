import { apiBaseUrl, AuthRequestError, getAccessToken } from './auth'

async function issueRequest(path, { method = 'GET', body } = {}) {
  const headers = {
    Accept: 'application/json',
  }
  const token = getAccessToken()
  const isMultipart = body instanceof FormData

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  if (body && !isMultipart) {
    headers['Content-Type'] = 'application/json'
  }

  const response = await fetch(`${apiBaseUrl}/v1/issues${path}`, {
    method,
    headers,
    body: body ? (isMultipart ? body : JSON.stringify(body)) : undefined,
  })
  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new AuthRequestError(payload.message || 'Unable to load issue data.', response.status, payload)
  }

  return payload
}

export function generateYaoundeSampleCoordinates() {
  const latitude = 3.848
  const longitude = 11.502
  const offset = 0.02

  return {
    latitude: (latitude + (Math.random() - 0.5) * offset).toFixed(6),
    longitude: (longitude + (Math.random() - 0.5) * offset).toFixed(6),
  }
}

export async function fetchIssues() {
  const payload = await issueRequest('')

  return payload.issues
}

export async function fetchIssue(issueId) {
  const payload = await issueRequest(`/${encodeURIComponent(issueId)}`)

  return payload.issue
}

export async function createIssue(formData) {
  const payload = await issueRequest('', { method: 'POST', body: formData })

  return payload.issue
}

export async function updateIssue(issueId, changes) {
  const isMultipart = changes instanceof FormData

  if (isMultipart) {
    changes.append('_method', 'PATCH')
  }

  const payload = await issueRequest(`/${encodeURIComponent(issueId)}`, {
    method: isMultipart ? 'POST' : 'PATCH',
    body: changes,
  })

  return payload.issue
}

export async function fetchIssuePhoto(photoUrl) {
  const headers = { Accept: 'image/*' }
  const token = getAccessToken()

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(photoUrl, { headers })

  if (!response.ok) {
    throw new AuthRequestError('Unable to load issue photo.', response.status)
  }

  return URL.createObjectURL(await response.blob())
}
