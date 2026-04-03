import { defineStore } from 'pinia'
import APIServices from '@/services/APIServices.ts'
import { storeAccessToken } from '@/stores/accessTokenStorage.ts'
import { computed, ref } from 'vue'

export const useLoginStore = defineStore('login', () => {
  const user = ref(null)
  const userName = computed(() =>
    user.value ? `${user.value.firstname} ${user.value.lastname}` : '',
  )

  async function register(user) {
    return await APIServices.post('register', user)
  }

  async function sendMail(email) {
    return await APIServices.post('getNewCode', email)
  }

  async function login(alt, userInfo) {
    let data
    if (alt === 'mail') {
      data = await APIServices.post('login/mail', userInfo)
    } else {
      data = await APIServices.post('login/totp', userInfo)
    }
    let token = data.data.access_token
    storeAccessToken(token)
  }

  return { user, userName, register, sendMail, login } //, resend, sendMail, logout}
})
