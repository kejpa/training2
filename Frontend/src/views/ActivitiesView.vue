<script setup>
import {onMounted, ref} from "vue";
import {useActivitiesStore} from "@/stores/activitiesStore.js";
import {storeToRefs} from "pinia";
import edit from "@/assets/icons/edit.svg"
import remove from "@/assets/icons/delete.png"
import waste from "@/assets/icons/waste.png"
import check from "@/assets/icons/check.svg"

const activitiesStore = useActivitiesStore()
const {activities} = storeToRefs(activitiesStore)

const activity = ref({})
const emojis = ref(['🏊‍♂️', '🤽‍♀️', '🏄‍♀️', '🚣‍♀️', '🛶', '⛵', '🏋️‍♀️', '🤸', '💪',
  '🏃', '🚶‍♂️', '🧗', , '🚴', '🚵', '🧘‍♀️', '⚽', '🏀', '🎾', '🏐', '🏓',
  '🏸', '🏒', '🏑', '🥍', '🏏', '⛷️', '🏂', '⛸️', '🛷', '🤼‍♀️', '🥊', '🥋', '🤺',
  '⭐', '✨', '🔥', '💯', '❤️'
])

onMounted(() => {
  activitiesStore.getAll()
  activity.value = activitiesStore.getInitial();
  }
)

async function saveActivity() {
  await activitiesStore.saveActivity(activity.value)
  activity.value = activitiesStore.getInitial();
}
async function removeActivity(act) {
  await activitiesStore.deleteActivity(act.id);
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
      <button @click="activity=activitiesStore.getInitial()">Ny</button>
    </div>
  </div>

  <ul v-if="activities.length>0" class="header">
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
    <li><img :src="act.log_distance ? check : remove" alt="log"></li>
    <li>{{ act.distance_unit }}</li>
    <li><img :src="act.log_time ? check : remove" alt="log"></li>
    <li><img :src="edit" alt="Redigera" @click="activity={...act}"> <img :src="waste" alt="Radera" @click="removeActivity(act)"></li>
  </ul>
</template>

<style scoped>
#form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 10px;
}
button {
  margin-left: 10px;
}

ul {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr;
  gap: 10px;
  list-style: none;
  padding: 0;
  background-color: #888;
}

ul:nth-child(odd) {
  background-color: #eee;
}

ul li {
  list-style: none;
}

ul.header {
  background-color: #ccc;
}

ul.header li {
  font-weight: bold;
}

li img {
  height: 16px;
  margin: 5px;
}

</style>
