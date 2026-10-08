import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import AdminHomeView from '../views/AdminHomeView.vue'
import InspectorHomeView from '../views/InspectorHomeView.vue'
import ContractorHomeView from '../views/ContractorHomeView.vue'
import CreateUserView from '../views/CreateUserView.vue'
import IssueCreateView from '../views/IssueCreateView.vue'
import IssueDetailView from '../views/IssueDetailView.vue'
import { homeRouteName } from './roles'

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
      name: 'home',
      redirect: { name: 'login' },
    },
    {
      path: '/admin',
      name: 'admin-home',
      component: AdminHomeView,
      meta: { requiresAuth: true, role: 'admin', title: 'Admin' },
    },
    {
      path: '/inspector',
      name: 'inspector-home',
      component: InspectorHomeView,
      meta: { requiresAuth: true, role: 'inspector', title: 'Inspector' },
    },
    {
      path: '/contractor',
      name: 'contractor-home',
      component: ContractorHomeView,
      meta: { requiresAuth: true, role: 'contractor', title: 'Contractor' },
    },
    {
      path: '/inspector/issues/new',
      name: 'issue-create',
      component: IssueCreateView,
      meta: { requiresAuth: true, role: 'inspector', title: 'Report issue' },
    },
    {
      path: '/inspector/issues/:issueId',
      name: 'inspector-issue-detail',
      component: IssueDetailView,
      meta: { requiresAuth: true, role: 'inspector', title: 'Issue details' },
    },
    {
      path: '/admin/issues/:issueId',
      name: 'admin-issue-detail',
      component: IssueDetailView,
      meta: { requiresAuth: true, role: 'admin', title: 'Issue details' },
    },
    {
      path: '/admin/users/new',
      name: 'user-create',
      component: CreateUserView,
      meta: { requiresAuth: true, role: 'admin', superAdminOnly: true, title: 'Create user' },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'home' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const isAuthenticated = await auth.restoreSession()
  const user = auth.currentUser.value

  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && isAuthenticated) {
    return { name: homeRouteName(user.role) }
  }

  if (to.meta.role && to.meta.role !== user?.role) {
    return { name: homeRouteName(user?.role) }
  }

  if (to.meta.superAdminOnly && !user?.super) {
    return { name: homeRouteName(user?.role) }
  }
})

router.afterEach((to) => {
  document.title = `${to.meta.title || 'InfraTrack'} | InfraTrack`
})

export default router