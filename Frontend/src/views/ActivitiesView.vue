<script setup>
import {onMounted, ref} from 'vue'
import {useActivitiesStore} from '@/stores/activitiesStore.js'
import {storeToRefs} from 'pinia'
import edit from '@/assets/icons/edit.svg'
import remove from '@/assets/icons/delete.png'
import waste from '@/assets/icons/waste.png'
import check from '@/assets/icons/check.svg'
import up from '@/assets/icons/upp.jpg'
import {useToastsStore} from "@/stores/toastsStore.js";

const activitiesStore = useActivitiesStore()
const {activities} = storeToRefs(activitiesStore)

const activity = ref({})
const emojis = ref([
  '🏊‍♂️',
  '🤽‍♀️',
  '🏄‍♀️',
  '🚣‍♀️',
  '🛶',
  '⛵',
  '🏋️‍♀️',
  '🤸',
  '💪',
  '🏃',
  '🚶‍♂️',
  '🧗',
  '🚴',
  '🚵',
  '🧘‍♀️',
  '⚽',
  '🏀',
  '🎾',
  '🏐',
  '🏓',
  '🏸',
  '🏒',
  '🏑',
  '🥍',
  '🏏',
  '⛷️',
  '🏂',
  '⛸️',
  '🛷',
  '🤼‍♀️',
  '🥊',
  '🥋',
  '🤺',
  '⭐',
  '✨',
  '🔥',
  '💯',
  '❤️',
])

onMounted(() => {
  activitiesStore.getAll()
  activity.value = activitiesStore.getInitial()
})

async function saveActivity() {
  if (activity.value.order === -1) {
    activity.value.order = activities.value.length
  }
  await activitiesStore.saveActivity(activity.value)
  activity.value = activitiesStore.getInitial()
  useToastsStore().addToast('success', 'Aktiviteten har sparats')
}

async function removeActivity(act) {
  await activitiesStore.deleteActivity(act.id)
  useToastsStore().addToast('success', 'Aktiviteten har raderats')
}

function moveUp(index) {
  if (index <= 0) return

  // Byt order-värden
  const temp = activities.value[index].order
  activities.value[index].order = activities.value[index - 1].order
  activities.value[index - 1].order = temp
  // Sortera om
  activities.value.sort((a, b) => a.order - b.order)

  // Spara aktiviteterna
  activitiesStore.saveActivity(activities.value[index])
  activitiesStore.saveActivity(activities.value[index - 1])
}

function moveDown(index) {
  if (index >= activities.value.length - 1) return

  // Byt order-värden
  const temp = activities.value[index].sortorder
  activities.value[index].sortorder = activities.value[index + 1].sortorder
  activities.value[index + 1].sortorder = temp
  // Sortera om
  activities.value.sort((a, b) => a.sortorder - b.sortorder)

  // Spara aktiviteterna
  activitiesStore.saveActivity(activities.value[index])
  activitiesStore.saveActivity(activities.value[index + 1])
}
</script>

<template>
  <h2>Aktiviteter</h2>
  <div id="form">
    <label
    >Emoji
      <select v-model="activity.emoji">
        <option v-for="e in emojis" :key="e">{{ e }}</option>
      </select></label
    >
    <label>Aktivitet <input v-model="activity.name" size="20"/></label>
    <label>
      <input type="checkbox" v-model="activity.log_distance"/> Logga distans
      <span v-if="activity.log_distance === true"
      >Distansenhet
        <select v-model="activity.distance_unit">
          <option>m</option>
          <option>km</option>
          <option>mil</option>
        </select>
      </span>
    </label>
    <label><input type="checkbox" v-model="activity.log_duration" value="true"/> Logga tid</label>
    <div>
      <button @click="saveActivity">Spara</button>
      <button @click="activity = activitiesStore.getInitial()">Ny</button>
    </div>
  </div>
  <hr/>
  <div id="list" v-if="activities.length > 0">
    <ul class="header">
      <li>Emoji</li>
      <li>Name</li>
      <li>Log distance</li>
      <li>Distance unit</li>
      <li>Log time</li>
      <li>&nbsp;</li>
    </ul>
    <ul v-for="act in activities" :key="act">
      <li>{{ act.emoji }}</li>
      <li>{{ act.name }}</li>
      <li class="center"><img :src="act.log_distance ? check : remove" alt="log"/></li>
      <li class="center">{{ act.distance_unit }}</li>
      <li class="center"><img :src="act.log_duration ? check : remove" alt="log"/></li>
      <li>
        <img :src="edit" title="Redigera aktivitet" alt="Redigera" @click="activity = { ...act }"/>
        <img :src="waste" title="Radera aktivitet" alt="Radera" @click="removeActivity(act)"/>
      </li>
      <li>
        <img v-if="act.sortorder!==0" :src="up" title="Flytta upp" alt="Flytta upp"
             @click="moveUp(act.sortorder)"/>
        <img v-if="act.sortorder!==activities.length-1" :src="up" class="down" title="Flytta ner"
             alt="Flytta ner" @click="moveDown(act.sortorder)"/>
        {{ act.sortorder }}
      </li>
    </ul>
  </div>
</template>

<style scoped>
h2 {
  margin: auto;
}

#form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: auto;
  margin-bottom: 10px;
}

button {
  margin-left: 10px;
}

hr {
  margin-bottom: 10px;
}

#list {
  max-height: 30vh;
  overflow-y: auto;
}

ul {
  width: clamp(200px, 90vw, 1000px);
  margin: auto;
  display: grid;
  grid-template-columns: 2.5em 1fr 1fr 1fr 1fr 1fr 1fr;
  gap: 10px;
  list-style: none;
  padding: 0;
}

ul li {
  list-style: none;
}

li:nth-child(1) {
  padding-left: 3px;
  text-align: center;
}

li.center {
  text-align: center;
}

li img {
  height: 16px;
}

.down {
  rotate: 180deg;
}
</style>
