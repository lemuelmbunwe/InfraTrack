import { computed, ref } from 'vue'
import {
  AuthRequestError,
  clearAccessToken,
  createUser as requestCreateUser,
  fetchCurrentUser,
  getAccessToken,
  signIn as requestSignIn,
} from '../services/auth'

const currentUser = ref(null)
const sessionError = ref('')
let restoreSessionPromise = null

export function useAuthStore() {
  async function restoreSession() {
    if (currentUser.value) {
      return true
    }

    if (!getAccessToken()) {
      return false
    }

    if (!restoreSessionPromise) {
      restoreSessionPromise = (async () => {
        sessionError.value = ''

        try {
          currentUser.value = await fetchCurrentUser()
          return currentUser.value !== null
        } catch (error) {
          if (error instanceof AuthRequestError && error.status === 401) {
            clearAccessToken()
            sessionError.value = 'Your sign-in has expired. Please sign in again.'
          } else if (error instanceof AuthRequestError && error.status === 403) {
            clearAccessToken()
            sessionError.value = 'This account is inactive.'
          } else {
            sessionError.value = 'Unable to verify your sign-in right now.'
          }

          return false
        } finally {
          restoreSessionPromise = null
        }
      })()
    }

    return restoreSessionPromise
  }

  async function signIn(email, password) {
    currentUser.value = await requestSignIn(email, password)
    sessionError.value = ''

    return currentUser.value
  }

  async function createUser(userData) {
    return requestCreateUser(userData)
  }

  return {
    currentUser,
    isAuthenticated: computed(() => currentUser.value !== null),
    sessionError,
    restoreSession,
    signIn,
    createUser,
  }
}