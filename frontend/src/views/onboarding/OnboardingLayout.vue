<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useApplicationStore } from '@/stores'
import { AppProgressBar } from '@/components/common'

const route = useRoute()
const applicationStore = useApplicationStore()

const currentStep = computed(() => (route.meta.step as number) || 1)
const totalSteps = computed(() => applicationStore.totalSteps)
const stepTitle = computed(() => (route.meta.title as string) || '')
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Header with Progress -->
    <header class="bg-white px-4 py-3 border-b sticky top-0 z-50">
      <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-2">
          <router-link to="/" class="p-1 -ml-1">
            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </router-link>
          <span class="text-sm text-gray-500">Paso {{ currentStep }} de {{ totalSteps }}</span>
          <div class="w-6" />
        </div>
        <AppProgressBar :current="currentStep" :total="totalSteps" :show-label="false" />
      </div>
    </header>

    <!-- Content -->
    <main class="flex-1 pb-24">
      <router-view />
    </main>
  </div>
</template>
