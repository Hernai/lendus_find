<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { RouterView } from 'vue-router'
import { useUiStore } from '@/stores'
import InstallPwaBanner from '@/components/common/InstallPwaBanner.vue'

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
  <InstallPwaBanner />
</template>

<style>
/* Global app styles handled in main.css */
</style>
