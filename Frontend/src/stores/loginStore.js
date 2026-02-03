import { defineStore } from 'pinia'
import APIServices from '@/services/APIServices.ts'

export const useLoginStore = defineStore('login', () => {
  async function register(user) {
    return await APIServices.post('/register', user)
  }

  return { register } //, login, resend, sendMail, logout}
})
