<script setup>
import {ref} from "vue";
import {useActivitiesStore} from "@/stores/activitiesStore.js";
import {storeToRefs} from "pinia";

const activitiesStore = useActivitiesStore()
const activities = storeToRefs(activitiesStore)

const activity = ref({
  id: null,
  name: '',
  emoji: '',
  log_distance: false,
  log_time: false,
  distance_unit: 'm'
})
const emojis = ref(['🏊‍♂️', '🤽‍♀️', '🏄‍♀️', '🚣‍♀️', '🛶', '⛵', '🏋️‍♀️', '🤸', '💪',
  '🏃', '🚶‍♂️', '🧗', , '🚴', '🚵', '🧘‍♀️', '⚽', '🏀', '🎾', '🏐', '🏓',
  '🏸', '🏒', '🏑', '🥍', '🏏', '⛷️', '🏂', '⛸️', '🛷', '🤼‍♀️', '🥊', '🥋', '🤺',
  '⭐', '✨', '🔥', '💯', '❤️'
])

async function saveActivity() {
  await activitiesStore.saveActivity(activity.value)
}

</script>

<template>
  <h2>Aktiviteter</h2>
  <div id="form">
    <label>Emoji <select v-model="activity.emoji">
      <option v-for="e in emojis" :key="e">{{ e }}</option>
    </select></label>
    <label>Aktivitet <input v-model="activity.name" size="20"></label>
    <label>
      <input type="checkbox" v-model="activity.log_distance"> Logga distans
      <span v-if="activity.log_distance===true">Distansenhet
        <select v-model="activity.distance_unit">
      <option>m</option>
      <option>km</option>
      <option>mil</option>
    </select>
      </span>
    </label>
    <label><input type="checkbox" v-model="activity.log_time" value="true"> Logga tid</label>
    <div>
      <button @click="saveActivity">Spara</button>
    </div>
  </div>

  <ul v-for="act in activities" :key="act">
    <li>{{ act.emoji }}</li>
    <li>{{ act.name }}</li>
    <li>{{ act.log_distance ? '' : '' }}</li>
    <li>{{ act.distance_unit }}</li>
    <li>{{ act.log_time ? '' : '' }}</li>
    <li><img src="" alt="Redigera"> <img src="" alt="Radera"></li>
  </ul>
</template>

<style scoped>
#form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

li img {
  height: 16px;
}

</style>
