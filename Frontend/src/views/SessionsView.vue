<script setup>

import {onMounted, ref} from "vue";
import {useSessionsStore} from "@/stores/sessionsStore.js";
import {useActivitiesStore} from "@/stores/activitiesStore.js";
import {storeToRefs} from "pinia";
import {useRoute} from "vue-router";

const route = useRoute()
const sessionsStore = useSessionsStore()
const activitiesStore = useActivitiesStore()
const session = ref({})
const {activities} = storeToRefs(activitiesStore)

onMounted(async () => {
  await activitiesStore.getAll()
  // Om id finns, ladda det passet, annars skapa nytt
  if (route.params.id) {
    // Ladda session med det id:t
    session.value = await sessionsStore.getSession(route.params.id)
  } else {
    session.value = sessionsStore.getInitial()
  }
})

async function saveSession() {
  if (!activities.value.find(itm => itm.id === session.value.activityid)?.log_duration) {
    session.value.duration = null
  }
  if (!activities.value.find(itm => itm.id === session.value.activityid)?.log_distance) {
    session.value.distance = null
  }
  await sessionsStore.saveSession(session.value)
  session.value = sessionsStore.getInitial();
}

async function removeSession() {
  await sessionsStore.deleteSession(session.value.id);
  session.value = sessionsStore.getInitial();
}
</script>

<template>
  <h2>Träningspass</h2>
  <div id="form">
    <label>
      Träning:
      <select v-model="session.activityid">
        <option v-for="act in activities" :value="act.id">{{ `${act.emoji}  ${act.name}` }}</option>
      </select>
    </label>
    <label>
      Datum: <input type="date" v-model="session.date"/>
    </label>
    <label v-if="activities.find(itm => itm.id===session.activityid)?.log_duration ?? false">
      Tid: <input type="time" v-model="session.duration"/>
    </label>
    <label v-if="activities.find(itm => itm.id===session.activityid)?.log_distance ?? false">
      Distans: <input type="text" pattern="[0-9.]*" size="5" v-model="session.distance"/>
      {{ activities.find(itm => itm.id === session.activityid)?.distance_unit ?? '' }}
    </label>
    <label>
      Beskrivning <br>
      <textarea v-model="session.description"></textarea>
    </label>
    <label>
      Rpe: <select v-model="session.rpe">
      <option v-for="i in [1,2,3,4,5,6,7,8,9,10]" :value="i">{{ i }}</option>
    </select>
    </label>
    <div>
      <button @click="saveSession">Spara</button>
      <button @click="session = sessionsStore.getInitial();">Ny</button>
      <button v-if="session.id" @click="removeSession">Radera</button>
    </div>
  </div>
</template>

<style scoped>
@media (min-width: 1024px) {

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

  textarea {
    width: 600px;
    height: 250px;
  }

  button {
    margin-left: 10px;
    font-size: 1.1em;
    padding: .2em;
    min-width: 4em;
  }
}
</style>
