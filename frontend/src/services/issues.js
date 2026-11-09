import { apiFetch, apiRequest } from './apiClient'

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
  const payload = await apiRequest('/v1/issues', {
    fallbackMessage: 'Unable to load issue data.',
  })

  return payload.issues
}

export async function fetchIssue(issueId) {
  const payload = await apiRequest(`/v1/issues/${encodeURIComponent(issueId)}`, {
    fallbackMessage: 'Unable to load issue data.',
  })

  return payload.issue
}

export async function createIssue(formData) {
  const payload = await apiRequest('/v1/issues', {
    method: 'POST',
    body: formData,
    fallbackMessage: 'Unable to submit this report.',
  })

  return payload.issue
}

export async function updateIssue(issueId, changes) {
  const isMultipart = typeof FormData !== 'undefined' && changes instanceof FormData

  if (isMultipart) {
    changes.append('_method', 'PATCH')
  }

  const payload = await apiRequest(`/v1/issues/${encodeURIComponent(issueId)}`, {
    method: isMultipart ? 'POST' : 'PATCH',
    body: changes,
    fallbackMessage: 'Unable to save issue changes.',
  })

  return payload.issue
}

export async function fetchIssuePhoto(photoUrl) {
  const response = await apiFetch(photoUrl, {
    headers: { Accept: 'image/*' },
    fallbackMessage: 'Unable to load issue photo.',
  })

  return URL.createObjectURL(await response.blob())
}

export async function fetchContractors() {
  const payload = await apiRequest('/v1/users/contractors', {
    fallbackMessage: 'Unable to load contractors.',
  })

  return payload.contractors
}

export async function assignIssue(issueId, contractorId) {
  const payload = await apiRequest(`/v1/issues/${encodeURIComponent(issueId)}/assignment`, {
    method: 'PATCH',
    body: { contractor_id: contractorId },
    fallbackMessage: 'Unable to assign this issue.',
  })

  return payload.issue
}

export async function updateContractorStatus(issueId, status) {
  const payload = await apiRequest(`/v1/issues/${encodeURIComponent(issueId)}/status`, {
    method: 'PATCH',
    body: { status },
    fallbackMessage: 'Unable to update issue status.',
  })

  return payload.issue
}
