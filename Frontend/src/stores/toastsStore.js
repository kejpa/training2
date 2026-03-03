import {defineStore} from 'pinia'
import {ref} from 'vue'

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
    let toast = {time: now.getTime(), type, message: mess}
    toast.toastTimer = setTimeout(() => {
      remove(toast)
    }, 5000)
    toasts.value.push(toast)
  }

  function remove(toast) {
    toasts.value.map((itm) => {
      if (itm.time === toast.time) {
        clearTimeout(toast.toastTimer)
      }
    })
    toasts.value = toasts.value.filter((itm) => {
      return itm.time !== toast.time
    })
  }

  function stopTimer(toast) {
    const found = toasts.value.find((itm) => itm.time === toast.time)
    if (found) clearTimeout(found.toastTimer)
  }

  function resetTimer(toast) {
    const found = toasts.value.find((itm) => itm.time === toast.time)
    if (found) {
      found.toastTimer = setTimeout(() => remove(found), 5000)
    }
  }

  return {
    toasts,
    addToast,
    remove,
    resetTimer,
    stopTimer,
  }
})
