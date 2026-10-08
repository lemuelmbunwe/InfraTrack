export const apiBaseUrl = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '')
const tokenStorageKey = 'infratrack_access_token'

export class AuthRequestError extends Error {
  constructor(message, status, payload = {}) {
    super(message)
    this.name = 'AuthRequestError'
    this.status = status
    this.payload = payload
  }
}

export function getAccessToken() {
  return sessionStorage.getItem(tokenStorageKey)
}

export function clearAccessToken() {
  sessionStorage.removeItem(tokenStorageKey)
}

export async function signIn(email, password) {
  const response = await fetch(`${apiBaseUrl}/v1/auth/login`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  })
  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new AuthRequestError(payload.message || 'Unable to sign in.', response.status, payload)
  }

  if (!payload.access_token || !payload.user) {
    throw new Error('The sign-in response was incomplete.')
  }

  sessionStorage.setItem(tokenStorageKey, payload.access_token)

  return payload.user
}

export async function fetchCurrentUser() {
  const token = getAccessToken()

  if (!token) {
    return null
  }

  const response = await fetch(`${apiBaseUrl}/v1/auth/me`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  })
  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new AuthRequestError(payload.message || 'Unable to restore session.', response.status, payload)
  }

  return payload.user
}

export async function createUser(userData) {
  const token = getAccessToken()

  const response = await fetch(`${apiBaseUrl}/v1/users`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(userData),
  })
  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new AuthRequestError(payload.message || 'Unable to create account.', response.status, payload)
  }

  return payload.user
}