import {
  apiBaseUrl,
  apiRequest,
  ApiError,
  clearAccessToken,
  getAccessToken,
  setAccessToken,
} from './apiClient'

export { apiBaseUrl, ApiError, clearAccessToken, getAccessToken }

export async function signIn(email, password) {
  const payload = await apiRequest('/v1/auth/login', {
    method: 'POST',
    body: { email, password },
    authenticated: false,
    fallbackMessage: 'Unable to sign in.',
  })

  if (!payload.access_token || !payload.user) {
    throw new ApiError('The sign-in response was incomplete.', 200, payload)
  }

  setAccessToken(payload.access_token)

  return payload.user
}

export async function fetchCurrentUser() {
  if (!getAccessToken()) {
    return null
  }

  const payload = await apiRequest('/v1/auth/me', {
    fallbackMessage: 'Unable to restore session.',
  })

  return payload.user
}

export async function createUser(userData) {
  const payload = await apiRequest('/v1/users', {
    method: 'POST',
    body: userData,
    fallbackMessage: 'Unable to create account.',
  })

  return payload.user
}
