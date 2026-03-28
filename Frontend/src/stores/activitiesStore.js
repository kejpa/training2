import {defineStore} from "pinia";
import {ref} from "vue";
import APIServices from "@/services/APIServices.ts";

export const useActivitiesStore = defineStore('activities', () => {
  const activities = ref([])
const initial={
  id: null,
  name: '',
  emoji: '',
  log_distance: false,
  log_duration: false,
  distance_unit: 'm'
}
  async function getAll() {
    let data = await APIServices.get('activities')
    activities.value = data.data.activities
  }

  async function saveActivity(activity) {
    if(activity.id){
      await updateActivity(activity.id, activity)
    } else {
      await addActivity(activity)
    }
    getAll().then(
      () => {
        console.log('Activities saved')
      }
    )
  }
  async function addActivity(activity) {
    let data=await APIServices.post('activities', activity)
    activities.value.push(data.data.activity)
  }

  async function deleteActivity(id) {
    await APIServices.delete('activities/'+id)
    activities.value = activities.value.filter(itm => itm.id !== id)
  }

  async function updateActivity(id, activity) {
    await APIServices.put('activities/'+id, activity)
    activities.value = activities.value.map(itm => {
      if (itm.id === id) {
        return activity
      } else {
        return itm
      }
    })
  }

  async function deleteActivity(id) {
    await APIServices.delete('activities/'+id)
    activities.value = activities.value.filter(itm => itm.id !== id)
  }

  function getInitial() {
    return {...initial}
  }
  return {activities,getInitial, getAll, saveActivity, deleteActivity}
})
