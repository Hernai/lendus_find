<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { RouterView } from 'vue-router'
import { useUiStore } from '@/stores'

const uiStore = useUiStore()

onMounted(() => {
  uiStore.initResizeListener()
})

onUnmounted(() => {
  uiStore.destroyResizeListener()
})
</script>

<template>
  <RouterView />
</template>

<style>
/* Global app styles handled in main.css */
</style>
