import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import SessionView from '../views/SessionView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true, title: 'Sign in' },
    },
    {
      path: '/',
      name: 'session',
      component: SessionView,
      meta: { requiresAuth: true, title: 'Signed in' },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'session' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const isAuthenticated = await auth.restoreSession()

  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && isAuthenticated) {
    return { name: 'session' }
  }
})

router.afterEach((to) => {
  document.title = `${to.meta.title || 'InfraTrack'} | InfraTrack`
})

export default router