import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastsStore = defineStore('toasts', () => {
  const toasts = ref([])
  function addToast(type, message) {
    let mess
    if (Array.isArray(message)) {
      mess = message.join('\n')
    } else if (typeof message === 'object') {
      mess = JSON.stringify(message)
    } else {
      mess = message
    }
    let now = new Date()
    let toast = { time: now.getTime(), type, message: mess }
    toast.toastTimer = setTimeout(() => {
      this.remove(toast)
    }, 5000)
    this.toasts.push(toast)
  }
  function remove(toast) {
    this.toasts.map((itm) => {
      if (itm.time === toast.time) {
        clearTimeout(toast.toastTimer)
      }
    })
    this.toasts = this.toasts.filter((itm) => {
      return itm.time !== toast.time
    })
  }
  function resetTimer(toast) {
    this.toasts.map((itm) => {
      if (itm.time === toast.time) {
        toast.toastTimer = setTimeout(() => {
          this.remove(toast)
        }, 5000)
      }
    })
  }
  function stopTimer(toast) {
    this.toasts.map((itm) => {
      if (itm.time === toast.time) {
        clearTimeout(toast.toastTimer)
      }
    })
  }

  return {
    toasts,
    addToast,
    resetTimer,
    stopTimer,
  }
})
