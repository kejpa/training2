<script setup>
import {computed, onMounted, ref} from "vue";
import VueApexCharts from 'vue3-apexcharts'
import {storeToRefs} from "pinia";
import {useStatisticsStore} from "@/stores/statisticsStore.js";
import {useSessionsStore} from "@/stores/sessionsStore.js";
import {useActivitiesStore} from "@/stores/activitiesStore.js";

const props = defineProps(['activityId', 'monthCount'])
const statisticsStore = useStatisticsStore()
const {months} = storeToRefs(statisticsStore)

const sessionsStorage = useSessionsStore()
const activitiesStorage = useActivitiesStore()
const {activities} = storeToRefs(activitiesStorage)
const activity = ref({})

// Distansdiagram
const series = computed(() => [{
  data: statisticsStore.distancePerMonth(props.activityId)
}])

const options = computed(() => {
    return ({
      chart: {
        type: 'bar',
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
        min: months.value[0],
        max: months.value[months.value.length - 1],
        range: props.monthCount - 1,
        tickPlacement: 'on',
      },
      yaxis: {
        title: {
          text: `[${activity.value.distance_unit}]`,
          style: {
            fontSize: '1.2em',
          },
        }
      },
      tooltip: {
        y: {
          formatter: (val) => `${val} ${activity.value.distance_unit}`,
          title: {
            formatter: () => undefined
          }
        }
      },
      grid: {
        padding: {
          left: 30,
          right: 20
        }
      }
    });
  }
)

onMounted(() => {
  let p = [
    sessionsStorage.getAll(),
    activitiesStorage.getAll(),
  ]
  Promise.all(p).then(() => {
    activity.value = activities.value.find(itm => itm.id === props.activityId)
  })
})
</script>

<template>
  <h3>Distans per månad</h3>
  <div style="height: 30vh">
  <VueApexCharts
    type="bar"
    :options
    :series
    height="100%"
  />
  </div>
</template>

<style scoped>
h3 {
  text-align: center;
}
</style>
