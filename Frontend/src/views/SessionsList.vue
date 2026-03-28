<script setup>
import {useSessionsStore} from "@/stores/sessionsStore.js";
import {onMounted} from "vue";
import {storeToRefs} from "pinia";
import {useActivitiesStore} from "@/stores/activitiesStore.js";

const sessionsStore = useSessionsStore()
const activitiesStore = useActivitiesStore()
const {sessions} = storeToRefs(sessionsStore)
const {activities} = storeToRefs(activitiesStore)

onMounted(async () => {
  await activitiesStore.getAll()
  await sessionsStore.getAll()
})
</script>

<template>
  <ul class="header">
    <li>Aktivitet</li>
    <li>Datum</li>
    <li>Distans</li>
    <li>Tid</li>
    <li>Beskrivning</li>
    <li>rpe</li>
  </ul>
  <ul v-for="session in sessions" :key="session.id" @click="$router.push(`/sessions/${session.id}`)">
    <li>{{ `${activities.find(itm => itm.id === session.activityid)?.emoji} ${activities.find(itm => itm.id === session.activityid)?.name}` }}</li>
    <li>{{ session.date }}</li>
    <li>{{ session.distance ? `${session.distance} ${activities.find(itm => itm.id === session.activityid)?.distance_unit} ` : '' }}</li>
    <li>{{ session.duration ? `${session.duration.substring(0,5)}` : '' }}</li>
    <li>{{ session.description }}</li>
    <li>{{ session.rpe }}</li>
  </ul>
</template>

<style scoped>
ul {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr;
  gap: 10px;
  list-style: none;
  padding: 0;
  background-color: #888;
  cursor: pointer;
}

ul:nth-child(odd) {
  background-color: #eee;
}

ul li {
  list-style: none;
}

ul.header {
  background-color: #ccc;
  cursor: default;
}

ul.header li {
  font-weight: bold;
}

li img {
  height: 16px;
  margin: 5px;
}

</style>
