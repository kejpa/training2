<script setup>
import { APP_VERSION } from '@/version'
import ActivityDistanceChart from "@/components/ActivityDistanceChart.vue";
</script>
<template>
  <div class="about">
    <h1>This is an about page</h1>
    <p>Version: {{ APP_VERSION }}</p>
  </div>
  <div>
    <ActivityDistanceChart month-count="5" activity-id="0fa5f32c-70b0-4a03-9058-51e470f39a2d"/>
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
