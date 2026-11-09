export const apiBaseUrl = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '')

const tokenStorageKey = 'infratrack_access_token'

export class ApiError extends Error {
  constructor(message, status, payload = {}, cause = undefined) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.payload = payload
    const validationErrors = payload && typeof payload.errors === 'object' && payload.errors !== null
      ? payload.errors
      : {}
    this.errors = Object.fromEntries(Object.entries(validationErrors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages : [messages],
    ]))
    this.code = typeof payload?.code === 'string' ? payload.code : null

    if (cause !== undefined) {
      this.cause = cause
    }
  }
}

export function getAccessToken() {
  return sessionStorage.getItem(tokenStorageKey)
}

export function setAccessToken(token) {
  sessionStorage.setItem(tokenStorageKey, token)
}

export function clearAccessToken() {
  sessionStorage.removeItem(tokenStorageKey)
}

async function errorPayload(response) {
  const contentType = response.headers.get('content-type') || ''

  if (!contentType.includes('application/json')) {
    return {}
  }

  return response.json().catch(() => ({}))
}

async function performRequest(path, {
  method = 'GET',
  body,
  headers = {},
  authenticated = true,
  fallbackMessage = 'The request could not be completed.',
} = {}) {
  const requestHeaders = {
    Accept: 'application/json',
    ...headers,
  }
  const token = authenticated ? getAccessToken() : null
  const isMultipart = typeof FormData !== 'undefined' && body instanceof FormData
  const hasBody = body !== undefined && body !== null

  if (token) {
    requestHeaders.Authorization = `Bearer ${token}`
  }

  const isBlob = typeof Blob !== 'undefined' && body instanceof Blob

  if (hasBody && !isMultipart && typeof body !== 'string' && !isBlob) {
    requestHeaders['Content-Type'] ??= 'application/json'
    body = JSON.stringify(body)
  }

  let response

  try {
    response = await fetch(path.startsWith('http') ? path : `${apiBaseUrl}${path}`, {
      method,
      headers: requestHeaders,
      body: hasBody ? body : undefined,
    })
  } catch (cause) {
    throw new ApiError(fallbackMessage, 0, {}, cause)
  }

  if (!response.ok) {
    const payload = await errorPayload(response)
    const message = typeof payload.message === 'string' ? payload.message : fallbackMessage

    throw new ApiError(message, response.status, payload)
  }

  return response
}

export async function apiRequest(path, options = {}) {
  const response = await performRequest(path, options)
  const responseBody = await response.text()

  if (!responseBody) {
    return {}
  }

  try {
    return JSON.parse(responseBody)
  } catch (cause) {
    throw new ApiError('The server returned an invalid JSON response.', response.status, {}, cause)
  }
}

export function apiFetch(path, options = {}) {
  return performRequest(path, options)
}
