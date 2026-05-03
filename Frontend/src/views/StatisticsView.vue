<script setup>
import {computed, onMounted, ref} from "vue";
import {useSessionsStore} from "@/stores/sessionsStore.js";
import {storeToRefs} from "pinia";
import {useActivitiesStore} from "@/stores/activitiesStore.js";
import ActivitiesCountChart from "@/components/ActivitiesCountChart.vue";

const monthCount = ref(1)
const sessionsStore = useSessionsStore()
const {sessions} = storeToRefs(sessionsStore)
const activitiesStore = useActivitiesStore()
const {activities} = storeToRefs(activitiesStore)

const months = computed(() => {
  if (sessions.value.length === 0) return []
  let tmp = []
  let firstDate = sessions.value.reduce((min, item) => {
    return new Date(item.date) < new Date(min) ? item.date : min
  }, sessions.value[0].date)
  for (let y = new Date(firstDate).getFullYear(); y <= new Date().getFullYear(); y++) {
    for (let m = 1; m < 13; m++) {
      let mnth = `${y}-${m.toString().padStart(2, '0')}`
      if (mnth >= firstDate.substring(0, 7) && mnth <= new Date().toISOString().substring(0, 7)) {
        tmp.push(mnth)
      }
    }
  }
  return tmp
})

const monthlyStats = computed(() => {
  let tmp = []
  for (const month of months.value) {
    let act = []
    for (const activity of activities.value) {
      let count = sessions.value.filter(s => s.date.substring(0, 7) === month && s.activityid === activity.id).length
      let duration, distance, rpe
      if (activity.log_duration) {
        duration = sessions.value.filter(s => s.date.substring(0, 7) === month && s.activityid === activity.id).reduce((sum, item) => {
          let [h, m] = item.duration.split(':')
          return sum + (parseInt(h) * 60 + parseInt(m))
        }, 0)
        duration = Math.round(duration / 60, 0) + ":" + (duration % 60).toString().padStart(2, '0')
      }
      if (activity.log_distance) {
        distance = sessions.value.filter(s => s.date.substring(0, 7) === month && s.activityid === activity.id).reduce((sum, item) => {
          return sum + item.distance
        }, 0)
      }
      rpe = {
        "lätt": sessions.value.filter(s => s.date.substring(0, 7) === month && s.activityid === activity.id && s.rpe <= 4).length,
        "medel": sessions.value.filter(s => s.date.substring(0, 7) === month && s.activityid === activity.id && s.rpe > 4 && s.rpe < 8).length,
        "hög": sessions.value.filter(s => s.date.substring(0, 7) === month && s.activityid === activity.id && s.rpe >= 8).length
      }
      act.push({activity: activity.name, count, distance, duration, rpe})
    }
    tmp.push({month, "activities": act})
  }
  return tmp
})

const openActivities = ref({})

function toggle(id) {
  openActivities.value[id] = !openActivities.value[id]
  localStorage.setItem('openActivities', JSON.stringify(openActivities.value))
}

onMounted(async () => {
  await activitiesStore.getAll()
  await sessionsStore.getAll()
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
  <ActivitiesCountChart :month-count="monthCount" @change="storeMonthCount"/>
  <div v-for="activity in activities" :key="activity.id">
    <h2 @click="toggle(activity.id)" style="cursor: pointer">
      {{ activity.name }}
      <span>{{ openActivities[activity.id] === false ? '˅' : '>' }}</span>
    </h2>
    <div v-show="openActivities[activity.id]===false">
      Diagram
    </div>
  </div>
  <p v-for="o in monthlyStats" :key="o">{{ o }}</p>
</template>

<style scoped>

</style>
