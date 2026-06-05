<script setup>
import {nextTick, ref} from 'vue'
import {useLoginStore} from '@/stores/loginStore.js'
import {useToastsStore} from '@/stores/toastsStore.js'
import router from "@/router/index.js";
import {useRoute} from "vue-router";

const route = useRoute()
const user = ref({email: '', code: ''})
const loginStore = useLoginStore()
const {login, resend} = useLoginStore()
const enterCode = ref(false)

function nextState() {
  let payload = {}
  payload.email = user.value.email
  let data = loginStore.sendMail(payload)
  if (data.success) {
    enterCode.value = true
    alert("enterCode=" + enterCode.value)
  } else {
    useToastsStore().addToast('error', e.data.error)
  }
}

async function handleLogin() {
  const result = await loginStore.login({
    email: user.value.email,
    code: user.value.code
  })

  if (result.success) {
    // Vänta på att store state har uppdaterats
    await nextTick()

    // Redirect till ursprunglig destination eller home
    const redirect = route.query.redirect || '/'
    // Dubbel-check att vi är inloggade
    if (loginStore.isAuthenticated) {
      router.replace(redirect)
    }
  } else {
    useToastsStore().addToast('error', result.error)
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
    <button v-if="!enterCode" @click="nextState()">Nästa</button>
    <div v-if="enterCode">
      <label>
        <span>Loginkod:</span>
        <input type="text" v-model="user.code" pattern="[0-9]{6}" size="7" required/>
      </label>
      <button @click="handleLogin()">Logga in</button>
    </div>
    <RouterLink to="/register">Ny användare</RouterLink>
    <input type="checkbox" v-model="enterCode">
    <br/>
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
