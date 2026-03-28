import {defineStore} from "pinia";
import {ref} from "vue";
import APIServices from "@/services/APIServices.ts";

export const useSessionsStore = defineStore('sessions', () => {
  const sessions = ref([])
const initial={
  id: null,
  activityid:null,
  distance:0,
  date:(new Date()).toISOString().split('T')[0],
  duration:"01:00",
  rpe:null,
  description:''
}
  async function getAll() {
    let data = await APIServices.get('sessions')
    sessions.value = data.data.sessions
  }
  async function getSession(id) {
    let data = await APIServices.get(`sessions/${id}`)
    return  {...data.data.session}
  }

  async function saveSession(session) {
    if(session.id){
      await updateSession(session.id, session)
    } else {
      await addSession(session)
    }
    getAll().then(
      () => {
        console.log('Activities saved')
      }
    )
  }
  async function addSession(session) {
    let data=await APIServices.post('sessions', session)
    sessions.value.push(data.data.session)
  }

  async function updateSession(id, session) {
    await APIServices.put('sessions/'+id, session)
    sessions.value = sessions.value.map(itm => {
      if (itm.id === id) {
        return session
      } else {
        return itm
      }
    })
  }

  async function deleteSession(id) {
    await APIServices.delete('sessions/'+id)
    sessions.value = sessions.value.filter(itm => itm.id !== id)
  }

  function getInitial() {
    return {...initial}
  }
  return {sessions,getInitial, getAll, saveSession, deleteSession, getSession}
})
