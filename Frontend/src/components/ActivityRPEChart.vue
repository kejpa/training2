<script setup>
import {computed, onMounted, ref, watch} from "vue";
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
const chartRef = ref(null)

// Ansträngningsdiagram
const series = computed(() => statisticsStore.excerssionPerMonth(props.activityId))

const options = {
  chart: {
    type: 'bar',
    offsetX: 0,
    sparkline: {
      enabled: false
    },
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
  legend: {
    show: true,
    showForSingleSeries: true,
    position: 'top',
  },
  xaxis: {
    categories: months.value,
    min: months.value[0],
    max: months.value[months.value.length - 1],
    range: props.monthCount - 1,
    tickPlacement: 'on',
    labels: {
      offsetX: 0
    }
  },
  yaxis: {
    stepSize: 1,
  },
  dataLabels: {
    enabled: true,
  },
  plotOptions: {
    bar: {
      dataLabels: {
        total: {
          enabled: true,
        }
      }
    }
  },
  tooltip: {
    y: {
      formatter: (val) => `${val}`,
      title: {
        formatter: (seriesName) => seriesName
      }
    }
  },
  grid: {
    padding: {
      left: 30,
      right: 20
    }
  }
}

onMounted(() => {
  let p = [
    sessionsStorage.getAll(),
    activitiesStorage.getAll(),
  ]
  Promise.all(p).then(() => {
    activity.value = activities.value.find(itm => itm.id === props.activityId)
  })
})

watch(props, () => {
  chartRef.value?.updateOptions({
    xaxis: {
      categories: months.value,
      min: months.value[0],
      max: months.value[months.value.length - 1],
      range: props.monthCount - 1,
      tickPlacement: 'on',
    },
  })
})
</script>

<template>
  <h3>Ansträngning per månad</h3>
  <div style="height: 30vh">
  <VueApexCharts ref="chartRef"
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
