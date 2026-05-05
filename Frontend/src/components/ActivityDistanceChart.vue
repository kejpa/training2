<script setup>
import {computed, ref, watch} from "vue";
import VueApexCharts from 'vue3-apexcharts'
import {storeToRefs} from "pinia";
import {useStatisticsStore} from "@/stores/statisticsStore.js";

const props = defineProps(['activityId', 'monthCount'])
const statisticsStore = useStatisticsStore()

const {months} = storeToRefs(statisticsStore)

// Distansdiagram
const series = computed(() => [{
  data: statisticsStore.distancePerMonth(props.activityId)
}])

const options = ref({
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
    categories: [],
    min: undefined,
    max: undefined,
    range: props.monthCount - 1,
    tickPlacement: 'on',
  },
  yaxis: {
    stepSize: 1,
  },
})

watch(months, (newMonths) => {
  options.value = {
    ...options.value,
    xaxis: {
      ...options.value.xaxis,
      categories: newMonths,
      min: newMonths[newMonths.length - 5],
      max: newMonths[newMonths.length - 1],
    }
  }
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
h3 {
  text-align: center;
}
</style>
