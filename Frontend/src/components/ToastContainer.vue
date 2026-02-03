<script setup>
import { storeToRefs } from 'pinia'
import ToastItem from './ToastItem.vue'
import { useToastsStore } from '@/stores/toastsStore.js'

const { toasts } = storeToRefs(useToastsStore())
const { remove, stopTimer, resetTimer } = useToastsStore()
</script>
<template>
  <div class="onTop">
    <ToastItem
      v-for="toast in toasts"
      :toast="toast"
      :key="toast"
      @remove="remove(toast)"
      v-on:mouseenter="stopTimer(toast)"
      v-on:mouseout="resetTimer(toast)"
      @touchstart="stopTimer(toast)"
      @touchend="resetTimer(toast)"
    />
  </div>
</template>
<style scoped>
.onTop {
  position: absolute;
  display: flex;
  flex-direction: column;
  margin-top: 5%;
  margin-left: 2%;
  width: 96%;
  max-width: calc(1100px - 5%);
}
</style>
