<script setup>
import {onMounted, ref} from "vue";
import {storeToRefs} from "pinia";
import {useSessionsStore} from "@/stores/sessionsStore.js";
import {useActivitiesStore} from "@/stores/activitiesStore.js";
import {useStatisticsStore} from "@/stores/statisticsStore.js";
import ActivitiesCountChart from "@/components/ActivitiesCountChart.vue";
import ActivityDistanceChart from "@/components/ActivityDistanceChart.vue";

const monthCount = ref(3)

const sessionsStore = useSessionsStore()
const activitiesStore = useActivitiesStore()
const {activities} = storeToRefs(activitiesStore)

const statisticsStore = useStatisticsStore()
const {months} = storeToRefs(statisticsStore)

const openActivities = ref({})

function toggle(id) {
  openActivities.value[id] = !openActivities.value[id]
  localStorage.setItem('openActivities', JSON.stringify(openActivities.value))
}

onMounted(async () => {
  await Promise.all([ activitiesStore.getAll(), sessionsStore.getAll()])
  monthCount.value = localStorage.getItem('monthCount') ?? 3
  const openActivities = localStorage.getItem('openActivities') ?? []
})

function storeMonthCount() {
  localStorage.setItem('monthCount', monthCount.value)
}
</script>

<template>
  <div>
    Antal månader: {{ monthCount }} <br>
    <input type="range" min="1" max="12" value="1" v-model="monthCount"/>
  </div>
  <ActivitiesCountChart :month-count="monthCount" @change="storeMonthCount" />
  <div v-for="activity in activities" :key="activity.id">
    <h2 @click="toggle(activity.id)" style="cursor: pointer">
      {{ activity.name }}
      <span>{{ openActivities[activity.id] === false ? '˅' : '>' }}</span>
    </h2>
    <div>
      <ActivityDistanceChart :month-count="monthCount" :activity-id="activity.id"/>
    </div>
  </div>
</template>

<style scoped>

</style>
