<script setup>
import {APP_VERSION} from '@/version'

</script>
<template>
  <div class="about">
    <p class="tagline">Din personliga träningsdagbok</p>
    <p class="description">
      Träningsdagboken är en enkel och säker app för att hålla koll på dina träningspass.
      Skapa egna aktiviteter, logga dina pass och följ din utveckling över tid.
    </p>
    <p class="description">
      Appen är byggd med fokus på säkerhet, användarvänlighet och prestanda.
      All data lagras säkert och endast du har tillgång till dina träningsuppgifter.
    </p>
    <div class="version-badge">
      <p>v{{ APP_VERSION }}</p>
     </div>

  </div>
</template>

<style>

@media (min-width: 650px) {
  .about {
    display: flex;
    flex-direction: column;
    width: 900px;
    margin: 0 auto;
  }
}
</style>
