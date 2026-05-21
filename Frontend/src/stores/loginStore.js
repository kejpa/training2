import {defineStore} from 'pinia'
import APIServices from '@/services/APIServices.ts'
import {deleteAccessToken, getAccessToken, storeAccessToken} from '@/stores/accessTokenStorage.ts'
import {computed, ref} from 'vue'
import router from '@/router'

export const useLoginStore = defineStore('login', () => {
  const accessToken = ref(null)
  const user = ref(null)
  const userName = computed(() =>
    user.value ? `${user.value.firstname} ${user.value.lastname}` : '',
  )
  const isAuthenticated = computed(() => !!accessToken.value)

  async function register(user) {
    return await APIServices.post('register', user)
  }

  async function sendMail(email) {
    return await APIServices.post('getNewCode', email)
  }

  async function login(userInfo) {
    try {
      let data = await APIServices.post('login/mail', userInfo)

      // Lagra token och user data
      accessToken.value = data.data.access_token
      user.value = data.data.user
      storeAccessToken(accessToken.value)

      return {success: true}
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || 'Inloggning misslyckades'
      }
    }
  }
  function logout() {
    // Rensa state
    accessToken.value = null
    user.value = null
    deleteAccessToken()

    // Anropa logout endpoint
    APIServices.post('logout').catch(() => {})

    // Redirect till login
    router.push({ name: 'login' })
  }
  function restoreSession() {
    const token = getAccessToken()
    if (token) {
      accessToken.value = token
      fetchUser()
    }
  }

  async function fetchUser() {
    try {
      const data = await APIServices.get('refresh')
      user.value = data.user
    } catch (error) {
      // Refreshtoken är ogiltig eller saknas
      logout()
    }
  }

  return {user, userName,isAuthenticated, register, sendMail, login, logout, restoreSession}
})
