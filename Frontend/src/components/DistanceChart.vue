<script setup>
import VueApexCharts from 'vue3-apexcharts'
import {onMounted, ref} from "vue";
import {useStatisticsStore} from "@/stores/statisticsStore.js";
import {storeToRefs} from "pinia";
import {useSessionsStore} from "@/stores/sessionsStore.js";

const props = defineProps(['monthCount', 'activityId'])
const statisticsStore = useStatisticsStore()
const {months} = storeToRefs(statisticsStore)
const sessionsStore = useSessionsStore()

const options = ref({
  chart: {
    type: 'bar',
  },
  yaxis: {
    stepSize: undefined,
  },
  xaxis: {
    categories: months.value,
    min: months.value[0],
    max: months.value[months.value.length - 1],
    range: props.monthCount - 1,
    tickPlacement: 'on',
  }
})

const series = ref([{
  name: 'ser1',
  data: [9600, 120, 8900, 7500, 650, 4500, 120]
}])

onMounted(() => {
  sessionsStore.getAll()
  /*
      .then(()=>{
        series.value.data=statisticsStore.distancePerMonth(props.activityId)
      })
  */
})
</script>

<template>
  <VueApexCharts type="bar" :options :series/>
</template>

<style scoped>

</style>
