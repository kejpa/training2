<script setup>
import {RouterLink, RouterView} from 'vue-router'
import ToastContainer from '@/components/ToastContainer.vue'
</script>

<template>
  <ToastContainer/>
  <header>
    <h1><img alt="Vue logo" class="logo" src="@/assets/icons/notebook.png"/>
      Träningsdagbok
    </h1>

    <div class="wrapper">
      <nav>
        <RouterLink to="/">Mata in pass</RouterLink>
        <RouterLink to="/activities">Aktiviteter</RouterLink>
        <RouterLink to="/sessionslist">Lista</RouterLink>
        <RouterLink to="/about">Om...</RouterLink>
      </nav>
    </div>
  </header>

  <RouterView/>
</template>

<style scoped>
header {
  line-height: 1.5;
  max-height: 100vh;
}

.logo {
  display: inline-block;
  margin: 0 auto 2rem;
  width: 100px;
}

nav {
  width: 100%;
  font-size: 12px;
  text-align: center;
  margin-top: 2rem;
}

nav a.router-link-exact-active {
  color: var(--color-text);
}

nav a.router-link-exact-active:hover {
  background-color: transparent;
}

nav a {
  padding: 0 1rem;
}

nav a:first-of-type {
  border: 0;
}

@media (min-width: 1024px) {
  header {
    display: flex;
    place-items: center;
    padding-right: calc(var(--section-gap) / 2);
    flex-direction: column;
  }

  .logo {
    margin: 0 2rem 0 0;
  }

  header .wrapper {
    width: 50%;
  }

  nav {
    margin-left: -1rem;
    font-size: 1rem;

    padding: 1rem 0;
    margin-top: 1rem;
  }
}
</style>
