<script setup>
import { ref } from 'vue'
import { useLoginStore } from '@/stores/loginStore.js'
import { useToastsStore } from '@/stores/toastsStore.js'

const user = ref({ email: '', code: '' })
const loginStore = useLoginStore()
const { login, resend } = useLoginStore()
const enterCode = ref(false)
const loginAlternative = ref('mail')

function nextState() {
  if (loginAlternative.value === 'mail') {
    let payload = {}
    payload.email = user.value.email
    loginStore
      .sendMail(payload)
      .then(() => {
        enterCode.value = true
      })
      .catch((e) => {
        useToastsStore().addToast('error', e.data.error)
      })
  } else {
    enterCode.value = true
  }
}
</script>
<template>
  <div>
    <h1>Logga in</h1>
    <label>
      <span>Användare:</span>
      <input type="email" v-model="user.email" required :disabled="enterCode" />
    </label>
    <label v-if="!enterCode">
      <input type="radio" v-model="loginAlternative" value="auth" />Logga in med authenticator
    </label>
    <label v-if="!enterCode">
      <input type="radio" v-model="loginAlternative" value="mail" />Skicka inloggningskod via mail
    </label>
    <button v-if="!enterCode" @click="nextState">Nästa</button>
    <template v-if="enterCode">
      <label>
        <span>Loginkod:</span>
        <input type="text" v-model="user.code" pattern="[0-9]{6}" size="7" required />
      </label>
      <button @click="login(loginAlternative, user)">Logga in</button>
    </template>
    <button v-if="!enterCode" @click="resend(user)">Skicka qr-kod</button>
    <br />
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
