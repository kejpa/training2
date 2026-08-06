<script setup>
import {nextTick, ref} from 'vue'
import {useLoginStore} from '@/stores/loginStore.js'
import {useToastsStore} from '@/stores/toastsStore.js'
import router from "@/router/index.js"
import {useRoute} from "vue-router"

const route = useRoute()
const user = ref({email: '', code: ''})
const loginStore = useLoginStore()
const toastsStore = useToastsStore()
const enterCode = ref(false)
const loading = ref(false)
const error = ref('')

async function nextState() {
  console.log('nextState clicked')

  // Validera epost
  if (!user.value.email) {
    error.value = 'Du måste ange en epostadress'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const response = await loginStore.sendMail({email: user.value.email})

    console.log('sendMail response:', response)

    // Kontrollera responsstrukturen
    if (response?.statusCode === 200 || response?.status === 200) {
      console.log('Email sent successfully, showing code input')
      enterCode.value = true
      toastsStore.addToast('success', 'Loginkod skickad till din epostadress')
    } else {
      const errorMsg = response?.data?.error || response?.error || 'Något gick fel'
      error.value = errorMsg
      toastsStore.addToast('error', errorMsg)
    }
  } catch (err) {
    console.error('Error in nextState:', err)
    error.value = err?.data?.error || 'Något gick fel'
    toastsStore.addToast('error', error.value)
  } finally {
    loading.value = false
  }
}

async function handleLogin() {
  console.log('handleLogin clicked')

  if (!user.value.code) {
    error.value = 'Du måste ange en loginkod'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const result = await loginStore.login({
      email: user.value.email,
      code: user.value.code
    })

    console.log('login result:', result)

    if (result?.success === true) {
      // Vänta på att store state har uppdaterats
      await nextTick()

      // Redirect till ursprunglig destination eller home
      const redirect = route.query.redirect || '/'

      // Dubbel-check att vi är inloggade
      if (loginStore.isAuthenticated) {
      toastsStore.addToast('success', 'Inloggning lyckades - Omdirigerar!')
        // Kontrollera att vi inte redan är där
        if (router.currentRoute.value.path !== redirect) {
          console.log('Redirecting to:', redirect)
          await router.push(redirect)
        } else {
          console.log('Already at destination, reloading page')
          window.location.reload()
        }
      }
    } else {
      const errorMsg = result?.error || 'Inloggning misslyckades'
      error.value = errorMsg
      toastsStore.addToast('error', errorMsg)
    }
  } catch (err) {
    console.error('Error in handleLogin:', err)
    error.value = err?.data?.error || 'Något gick fel'
    toastsStore.addToast('error', error.value)
  } finally {
    loading.value = false
  }
}

// Håll alla states synkade
function reset() {
  user.value = {email: '', code: ''}
  enterCode.value = false
  error.value = ''
  loading.value = false
}
</script>
<template>
  <div>
    <h1>Logga in</h1>
    <label>
      <span>Användare:</span>
      <input type="email" v-model="user.email" required :disabled="enterCode"/>
    </label>
    <button v-if="!enterCode" @click="nextState">Nästa</button>
    <div v-if="enterCode">
      <label>
        <span>Loginkod:</span>
        <input type="text" v-model="user.code" pattern="[0-9]{6}" size="7" required/>
      </label>
      <button @click="handleLogin">Logga in</button>
    </div>
    <p>
      <RouterLink to="/register">Ny användare</RouterLink>
    </p>
  </div>
</template>
<style scoped>
div {
  width: fit-content;
  margin: auto;
}

button,
input,
label {
  font-size: large;
}

label {
  display: block;
  margin-top: 0.3em;
}

label span {
  display: inline-block;
  width: 6em;
  text-align: right;
  padding-right: 5px;
}

button {
  margin: 1em 0.5em;
}
</style>
