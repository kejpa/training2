<script setup>
import { ref } from 'vue'
import { useLoginStore } from '@/stores/loginStore.js'
import router from '@/router/index.js'
import { useToastsStore } from '@/stores/toastsStore.js'

const { addToast } = useToastsStore()
const user = ref({ email: '', otp: '' })
const { register } = useLoginStore()

function registreraAnvandare() {
  try {
    let info = register(user.value)
    addToast('info', info)
    router.push('/login')
    return
  } catch (e) {
    console.log(e)
    addToast('error', 'Kunde inte skapa användare')
  }
}
</script>
<template>
  <div>
    <h1>Registrera användare</h1>
    <label>
      <span>Förnamn:</span>
      <input type="text" v-model="user.firstname" required />
    </label>
    <label>
      <span>Efternamn:</span>
      <input type="text" v-model="user.lastname" required />
    </label>
    <label>
      <span>Epost:</span>
      <input type="email" v-model="user.email" required />
    </label>
    <button @click="registreraAnvandare">Registrera!</button>
    <br />
    <RouterLink to="/login">Logga in</RouterLink>
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
