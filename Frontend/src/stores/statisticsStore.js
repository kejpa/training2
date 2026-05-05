// stores/statisticsStore.js
import { computed } from 'vue'
import { defineStore, storeToRefs } from 'pinia'
import { useSessionsStore } from './sessionsStore'

export const useStatisticsStore = defineStore('statistics', () => {
  const sessionsStore = useSessionsStore()
  const { sessions } = storeToRefs(sessionsStore)

  const months = computed(() => {
    if (sessions.value.length === 0) return []
    const firstDate = sessions.value
      .map(s => s.date.substring(0, 7))
      .sort()[0]
    const today = new Date().toISOString().substring(0, 7)
    const tmp = []
    let [y, m] = firstDate.split('-').map(Number)
    const [endY, endM] = today.split('-').map(Number)
    while (y < endY || (y === endY && m <= endM)) {
      tmp.push(`${y}-${String(m).padStart(2, '0')}`)
      m++
      if (m > 12) { m = 1; y++ }
    }
    return tmp
  })

  function countPerMonth(activityId) {
    return months.value.map(month =>
      sessions.value.filter(s =>
        s.date.substring(0, 7) === month && s.activityid === activityId
      ).length
    )
  }

  function distancePerMonth(activityId) {
    return months.value.map(month =>
      sessions.value
        .filter(s => s.date.substring(0, 7) === month && s.activityid === activityId)
        .reduce((sum, s) => sum + (s.distance ?? 0), 0)
    )
  }

  return {
    months,
    countPerMonth,
    distancePerMonth,
  }
})
