import {defineStore} from 'pinia'
import APIServices from '@/services/APIServices.ts'

export const useLoginStore = defineStore('login', () => {
  async function register(user) {
    return await APIServices.post('register', user)
  }

  async function sendMail(email) {
    return await APIServices.post('getNewCode', email)
  }

  async function login(alt, userInfo) {
    if (alt === 'mail') {
      let data = await APIServices.post('login/mail', userInfo)
    } else {
      let data = await APIServices.post('login/totp', userInfo)
    }
  }

  return {register, sendMail, login} //, resend, sendMail, logout}
})
