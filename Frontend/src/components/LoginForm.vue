<script setup>
import {ref} from 'vue'
import {useLoginStore} from '@/stores/loginStore.js'
import {useToastsStore} from '@/stores/toastsStore.js'
import router from "@/router/index.js";

const user = ref({email: '', code: ''})
const loginStore = useLoginStore()
const {login, resend} = useLoginStore()
const enterCode = ref(false)

function nextState() {
  let payload = {}
  payload.email = user.value.email
  loginStore
    .sendMail(payload)
    .then(() => {
      enterCode.value = true
      useToastsStore().addToast('error', e.data.error)
    })
    .catch((e) => {
      useToastsStore().addToast('error', e.data.error)
    })
}

async function handleLogin() {
  error.value = ''

  const result = await loginStore.login( {
    email: email.value,
    code: code.value
  })

  if (result.success) {
    // Redirect till ursprunglig destination eller home
    const redirect = route.query.redirect || '/'
    router.push(redirect)
  } else {
    error.value = result.error
  }
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
    <template v-if="enterCode">
      <label>
        <span>Loginkod:</span>
        <input type="text" v-model="user.code" pattern="[0-9]{6}" size="7" required/>
      </label>
      <button @click="handleLogin()">Logga in</button>
    </template>
    <br/>
    <RouterLink to="/register">Ny användare</RouterLink>
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
