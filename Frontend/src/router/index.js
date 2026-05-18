import { createRouter, createWebHistory } from 'vue-router'
import {useLoginStore} from "@/stores/loginStore.js";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('../views/SessionsView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/activities',
      name: 'activities',
      component: () => import('../views/ActivitiesView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/sessions/:id?',
      name: 'sessions',
      component: () => import('../views/SessionsView.vue'),
      props: true,
      meta: { requiresAuth: true }
    },
    {
      path: '/sessionslist',
      name: 'sessionslist',
      component: () => import('../views/SessionsList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/statistics',
      name: 'statistics',
      component: () => import('../views/StatisticsView.vue'),
      meta: { requiresAuth: true }
    },
  // Publika rutter
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
      meta: { requiresAuth: false }
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../components/RegisterForm.vue'),
      meta: { requiresAuth: false }
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../components/LoginForm.vue'),
      meta: { requiresAuth: false }
    },
    // Catch-all route (404)
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { requiresAuth: false }
    }
  ],
})

// Navigation Guard
router.beforeEach((to, from, next) => {
  const loginStore = useLoginStore()
  const requiresAuth = to.meta.requiresAuth !== false // Default: true
  const isAuthenticated = loginStore.isAuthenticated

  // Om route kräver auth och användaren inte är inloggad
  if (requiresAuth && !isAuthenticated) {
    next({
      name: 'login',
      query: { redirect: to.fullPath } // Spara destination för redirect efter login
    })
  }
  // Om användaren är inloggad och försöker nå login/register
  else if (!requiresAuth && isAuthenticated && (to.name === 'login' || to.name === 'register')) {
    next({ name: 'home' })
  }
  // Annars fortsätt normalt
  else {
    next()
  }
})

export default router
