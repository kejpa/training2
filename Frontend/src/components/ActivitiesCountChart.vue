<script setup>
import {computed} from "vue";
import VueApexCharts from 'vue3-apexcharts'
import {useActivitiesStore} from "@/stores/activitiesStore.js";
import {storeToRefs} from "pinia";
import {useStatisticsStore} from "@/stores/statisticsStore.js";

const props = defineProps(['monthCount'])
const activitiesStore = useActivitiesStore()
const statisticsStore = useStatisticsStore()

const {months} = storeToRefs(statisticsStore)
const {activities} = storeToRefs(activitiesStore)

const series = computed(() =>
  activities.value.map(activity => ({
    name: activity.name,
    data: statisticsStore.countPerMonth(activity.id)
  }))
)

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
      range: props.monthCount - 1,
      tickPlacement: 'on',
    },
    yaxis: {
      stepSize: 1,
    },
    legend: {
      show: true,
      showForSingleSeries: true,
      position: 'top',
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

</script>

<template>
  <VueApexCharts
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
