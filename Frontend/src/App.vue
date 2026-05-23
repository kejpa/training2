<script setup>
import {onMounted} from 'vue'
import {RouterLink, RouterView, useRouter} from 'vue-router'
import ToastContainer from '@/components/ToastContainer.vue'
import {useLoginStore} from "@/stores/loginStore.js";
import {useRegisterSW} from 'virtual:pwa-register/vue'
import edit from '@/assets/icons/edit.svg'
import activity from '@/assets/icons/activity.png'
import info from '@/assets/icons/info.png'
import list from '@/assets/icons/list.png'
import chart from '@/assets/icons/chart.png'

const router = useRouter()
const loginStore = useLoginStore()

const {needRefresh, updateServiceWorker} = useRegisterSW()

onMounted(() => {
  // Återställ session om token finns i localStorage
  loginStore.restoreSession()
})
</script>

<template>
  <header>
    <nav class="header">
      <img :src="edit" alt="Mata in" @click="router.push('/')"/>
      <img :src="activity" alt="Aktiviteter" @click="router.push('/activities')"/>
      <img :src="list" alt="Lista" @click="router.push('/sessionslist')"/>
      <img :src="chart" alt="Graf" @click="router.push('/statistics')"/>
      <img :src="info" alt="Om" @click="router.push('/about')"/>
    </nav>
    <ToastContainer/>
    <div v-if="needRefresh" class="update-banner">
      <span>En ny version finns tillgänglig.</span>
      <button @click="updateServiceWorker()">Uppdatera nu</button>
      <button @click="needRefresh = false">Senare</button>
    </div>
    <h1>
      <img alt="Dagbok" class="logo" src="@/assets/icons/notebook.png"/>
      Träningsdagbok
    </h1>

    <div class="wrapper">
      <nav>
        <RouterLink to="/">Mata in pass</RouterLink>
        <RouterLink to="/sessionslist">Lista</RouterLink>
        <RouterLink to="/statistics">Statistik</RouterLink>
        <RouterLink to="/activities">Aktiviteter</RouterLink>
        <RouterLink to="/about">Om...</RouterLink>
      </nav>
    </div>
  </header>
  <main>
    <RouterView/>
  </main>
  <footer>
    &copy; Kjell Hansen 2026{{
      new Date().getFullYear() > 2026 ? '-' + new Date().getFullYear() : ''
    }}
  </footer>
</template>

<style scoped>
header {
  line-height: 1.5;
}

header h1 {
  text-align: center;
}

.logo {
  display: inline-block;
  width: 7vw;
}

.wrapper nav {
  display: none;
}

nav.header {
  grid-area: header;
  background-color: #000080;
  display: flex;
  flex-direction: row;
  padding: 1vh;
  justify-content: space-evenly;
}

main {
  grid-area: main;
  margin: 0 2vw;
  overflow: auto;
  padding-left: 1em;
}

.update-banner {
  position: fixed;
  bottom: 1rem;
  left: 50%;
  transform: translateX(-50%);
  background: #1a1a2e;
  border: 1px solid #41b883;
  border-radius: 8px;
  padding: 0.75rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  z-index: 9999;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
  white-space: nowrap;
}

.update-banner button {
  padding: 0.3rem 0.8rem;
  border-radius: 4px;
  border: none;
  cursor: pointer;
  font-size: 0.9rem;
}

.update-banner button:first-of-type {
  background: #41b883;
  color: white;
}

.update-banner button:last-of-type {
  background: transparent;
  border: 1px solid #666;
  color: inherit;
}

footer {
  grid-area: footer;
  text-align: center;
  padding-top: 10px;
  background-color: var(--color-background);
  border-top: 3px double var(--color-border);
}

nav.header img {
  height: 8vh;
}

@media (min-width: 650px) {
  .wrapper nav {
    display: initial;
  }

  nav.header {
    display: none;
  }

  header {
    display: flex;
    place-items: center;
    padding-right: calc(var(--section-gap) / 2);
    flex-direction: column;
  }

  .logo {
    margin: 0 2rem 0 0;
    max-height: 15vh;
  }

  nav {
    font-size: 1.5rem;

    padding: 1rem 0;
    width: 100%;
    text-align: center;
    margin-top: 0.5rem;
  }

  nav a {
    color: var(--vt-c-indigo);
  }

  nav a.router-link-exact-active:hover {
    background-color: transparent;
  }

  nav a {
    padding: 0 1rem;
  }
}
</style>
