<script setup>
import {computed, onMounted} from "vue";
import VueApexCharts from 'vue3-apexcharts'
import {useActivitiesStore} from "@/stores/activitiesStore.js";
import {useSessionsStore} from "@/stores/sessionsStore.js";
import {storeToRefs} from "pinia";

const props = defineProps(['showActivities', 'monthCount'])
const activitiesStore = useActivitiesStore()
const sessionsStore = useSessionsStore()

const {sessions} = storeToRefs(sessionsStore)
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

const series = computed(() => {
  let tmp = []
  for (const activity of activities.value) {
    if (props.showActivities.includes(activity.id)) {
      let data = []
      for (const month of months.value) {
        data.push(sessions.value.filter(s => s.date.substring(0, 7) === month && s.activityid === activity.id).length)
      }
      tmp.push({name: activity.name, data})
    }
  }
  return tmp
})

const options = computed(() => ({
    chart: {
      type: 'bar',
      stacked: true,
      toolbar: {
        show: true,
        tools: {
          pan: true,
          zoom: false,
          zoomin: false,
          zoomout: false,
          reset: true,
          download: false,
          selection: false,
        }
      },
      zoom: {
        enabled: true,
        type: 'x',
      },
      pan: {
        enabled: true,
        type: 'x',
      },
    },
    xaxis: {
      categories: months.value,
      min: months.value[months.value.length - 5],
      max: months.value[months.value.length - 1],
      range: props.monthCount - 1, // antal steg synliga åt gången (0-indexerat, 4 = 5 staplar)
      tickPlacement: 'on',
    },
    yaxis: {
      stepSize: 1,
    },
    legend: {
      show: true,
      showForSingleSeries: true,
      position: 'right',
      offsetY: 40
    },
    plotOptions: {
      bar: {
        dataLabels: {
          total: {
            enabled: true,
          }
        }
      }
    }
  })
)

onMounted(async () => {
  await activitiesStore.getAll()
  await sessionsStore.getAll()

})


</script>

<template>
  <VueApexCharts
    type="bar"
    :options
    :series
  />
</template>

<style scoped>

</style>
