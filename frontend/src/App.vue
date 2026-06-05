<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { RouterView } from 'vue-router'
import { useUiStore } from '@/stores'
import InstallPwaBanner from '@/components/common/InstallPwaBanner.vue'
import PerformanceWidget from '@/components/dev/PerformanceWidget.vue'
import { captureNavigationTiming } from '@/utils/perf'

const uiStore = useUiStore()

onMounted(() => {
  uiStore.initResizeListener()
  captureNavigationTiming()
})

onUnmounted(() => {
  uiStore.destroyResizeListener()
})
</script>

<template>
  <RouterView />
  <InstallPwaBanner />
  <PerformanceWidget />
</template>

<style>
/* Global app styles handled in main.css */
</style>
