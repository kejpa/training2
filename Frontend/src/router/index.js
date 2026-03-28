import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/activities',
      name: 'activities',
      component: () => import('../views/ActivitiesView.vue'),
    },
    {
      path: '/sessions/:id?',
      name: 'sessions',
      component: () => import('../views/SessionsView.vue'),
      props: true
    },
    {
      path: '/sessionslist',
      name: 'sessionslist',
      component: () => import('../views/SessionsList.vue'),
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../components/RegisterForm.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../components/LoginForm.vue'),
    },
  ],
})

export default router
