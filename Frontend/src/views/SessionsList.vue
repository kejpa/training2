<script setup>
import { useSessionsStore } from '@/stores/sessionsStore.js'
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useActivitiesStore } from '@/stores/activitiesStore.js'

const sessionsStore = useSessionsStore()
const activitiesStore = useActivitiesStore()
const { sessions } = storeToRefs(sessionsStore)
const { activities } = storeToRefs(activitiesStore)

onMounted(async () => {
  await activitiesStore.getAll()
  await sessionsStore.getAll()
})

const sortedSessions = computed(() => {
  return [...sessions.value].sort((a, b) => {
    const dateA = new Date(a.date)
    const dateB = new Date(b.date)
    return dateB - dateA
  })
})
</script>

<template>
  <ul class="header">
    <li class="activity">Aktivitet</li>
    <li class="date">Datum</li>
    <li class="distance">Distans</li>
    <li class="duration">Tid</li>
    <li class="desc">Beskrivning</li>
    <li class="rpe">rpe</li>
  </ul>
  <ul
    v-for="session in sortedSessions"
    :key="session.id"
    @click="$router.push(`/sessions/${session.id}`)"
  >
    <li class="activity">
      {{
        `${activities.find((itm) => itm.id === session.activityid)?.emoji} ${activities.find((itm) => itm.id === session.activityid)?.name}`
      }}
    </li>
    <li class="date">{{ session.date }}</li>
    <li class="right distance">
      {{
        session.distance
          ? `${session.distance} ${activities.find((itm) => itm.id === session.activityid)?.distance_unit} `
          : ''
      }}
    </li>
    <li class="right duration">
      {{ session.duration ? `${session.duration.substring(0, 5)}` : '' }}
    </li>
    <li class="desc">{{ session.description }}</li>
    <li class="right rpe">{{ session.rpe }}</li>
  </ul>
</template>

<style scoped>
ul {
  display: grid;
  grid-template-columns: 28vw 1fr 9vw;
  grid-template-rows: auto auto auto 1fr;
  grid-template-areas:
    'Activity  Description Rpe'
    'Date  Description .'
    'Distance  Description .'
    'Duration  Description .';
  column-gap: 5px;
  list-style: none;
  padding: 0;
  cursor: pointer;
}

.activity {
  grid-area: Activity;
}

.date {
  grid-area: Date;
}

.distance {
  grid-area: Distance;
}

.duration {
  grid-area: Duration;
}

.desc {
  grid-area: Description;
  white-space: break-spaces;
}

.rpe {
  grid-area: Rpe;
}

ul li {
  list-style: none;
  padding: 0 5px;
  vertical-align: text-top;
}

ul li.right {
  text-align: right;
}

li img {
  height: 16px;
  margin: 5px;
}

@media (min-width: 650px) {
  ul {
    display: grid;
    grid-template-columns: 8rem 8rem 4.1rem 3.5rem 1fr 2rem;
    grid-template-areas: 'Activity Date Distance Duration Description Rpe';
    gap: 10px;
    list-style: none;
    padding: 0;
    cursor: pointer;
  }
}
</style>
