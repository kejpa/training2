<script setup>
const props = defineProps(['toast'])
</script>
<template>
  <div :class="props.toast.type">
    <span class="close" @click="$emit('remove')">×</span>{{ props.toast.message }}
  </div>
</template>
<style scoped>
div {
  margin: 5px;
  padding: 0.5em 0.5em 0.3em 0.2em;
  white-space: pre-line;
}
.close {
  float: right;
  font-weight: 700;
  line-height: 1;
  color: inherit;
}
.close:hover {
  background-color: red;
  color: wheat;
}
.error {
  background-color: pink;
  color: darkred;
  border: 1px solid darkred;
}
.info {
  background-color: blue;
  color: whitesmoke;
  border: 1px solid navy;
}
.success {
  background-color: lime;
  color: darkgreen;
  border: 1px solid darkgreen;
}
</style>
