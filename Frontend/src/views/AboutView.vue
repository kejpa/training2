<script setup>
import { APP_VERSION } from '@/version'

</script>
<template>
  <div class="about">
    <h1>This is an about page</h1>
    <p>Version: {{APP_VERSION}}</p>
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
