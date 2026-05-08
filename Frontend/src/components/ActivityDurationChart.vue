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

// Varaktighetsdiagram
const series = computed(() => [{
  data: statisticsStore.durationPerMonth(props.activityId)
}])

const options = {
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
    labels: {
      formatter: function (val) {
        const h = Math.floor(val / 60)
        const m = val % 60
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
      }
    },
  },
  dataLabels: {
    enabled: true,
    formatter: function (val) {
      const h = Math.floor(val / 60)
      const m = val % 60
      return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
    }
  },
  plotOptions: {
    bar: {
      dataLabels: {
        total: {
          enabled: false,
        }
      }
    }
  },
  tooltip: {
    y: {
      formatter: function (val) {
        const h = Math.floor(val / 60)
        const m = val % 60
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
      },
      title: {
        formatter: () => undefined
      }
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

watch(props, () => ({
    xaxis: {
      categories: months.value,
      min: months.value[0],
      max: months.value[months.value.length - 1],
      range: props.monthCount - 1,
      tickPlacement: 'on',
    },
  })
)
</script>

<template>
  <h3>Varaktighet per månad</h3>
  <VueApexCharts ref="chartRef"
                 type="bar"
                 :options
                 :series
  />
</template>

<style scoped>
h3 {
  text-align: center;
}
</style>
