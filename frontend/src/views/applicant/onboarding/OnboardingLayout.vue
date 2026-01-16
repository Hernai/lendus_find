<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApplicationStore, useTenantStore, useAuthStore, useOnboardingStore } from '@/stores'
import { AppProgressBar } from '@/components/common'
import type { PaymentFrequency, Application } from '@/types'

const route = useRoute()
const router = useRouter()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const authStore = useAuthStore()
const onboardingStore = useOnboardingStore()

const isInitializing = ref(true)

const currentStep = computed(() => (route.meta.step as number) || 1)
const totalSteps = computed(() => applicationStore.totalSteps)
const stepTitle = computed(() => (route.meta.title as string) || '')

// Handle exit button click
const handleExit = () => {
  // Clear all onboarding data
  onboardingStore.reset()
  authStore.clearOnboardingCache()

  // Navigate based on auth state
  if (authStore.isAuthenticated) {
    router.push('/dashboard')
  } else {
    router.push('/')
  }
}

// Initialize application from pending data or load existing draft
onMounted(async () => {
  try {
    // Ensure tenant config is loaded first
    await tenantStore.loadConfig()

    // Check if there's a pending application to create
    const pendingApp = localStorage.getItem('pending_application')
    // Check if we have a saved application ID from a previous session
    let savedAppId = localStorage.getItem('current_application_id')

    // Clean up invalid saved IDs
    if (savedAppId === 'null' || savedAppId === 'undefined' || savedAppId === '') {
      console.log('🧹 Cleaning up invalid savedAppId:', savedAppId)
      localStorage.removeItem('current_application_id')
      savedAppId = null
    }

    console.log('🔧 OnboardingLayout init:', {
      pendingApp: pendingApp ? JSON.parse(pendingApp) : null,
      savedAppId,
      hasCurrentApp: !!applicationStore.currentApplication,
      products: tenantStore.products.length
    })

    if (pendingApp && !applicationStore.currentApplication) {
      // Prepare for application creation (will happen after step 1 completes)
      const params = JSON.parse(pendingApp) as {
        product_id: string
        requested_amount: number
        term_months: number
        payment_frequency: PaymentFrequency
      }

      console.log('📝 Pending application detected:', params)
      console.log('⏳ Application will be created after completing personal data (step 1)')

      // Find and set the product
      const product = tenantStore.products.find(p => p.id === params.product_id)
      console.log('🏷️ Found product:', product?.id || 'NOT FOUND')

      if (product) {
        applicationStore.setSelectedProduct(product)
      }

      // Run simulation to pre-populate store (user will see this in step 5)
      try {
        await applicationStore.runSimulation({
          product_id: params.product_id,
          amount: params.requested_amount,
          term_months: params.term_months,
          payment_frequency: params.payment_frequency
        })
        console.log('✅ Simulation ready for step 5')
      } catch (simError) {
        console.error('❌ Failed to run simulation:', simError)
      }

      // NOTE: Application creation will happen in Step1PersonalData.vue after applicant is created
      // pending_application will be cleared by Step1 after successful creation
    } else if (!applicationStore.currentApplication) {
      // No pending app and no current application in store
      console.log('🔍 No pending app, looking for existing...')

      // First try to load from saved application ID
      if (savedAppId) {
        console.log('📂 Loading from saved ID:', savedAppId)
        try {
          await applicationStore.loadApplication(savedAppId)

          // Check if the loaded application is in a terminal/non-editable status
          const loadedApp = applicationStore.currentApplication as Application | null
          const terminalStatuses = ['CANCELLED', 'REJECTED', 'APPROVED', 'SYNCED', 'SUBMITTED']

          if (loadedApp && terminalStatuses.includes(loadedApp.status)) {
            console.log('⚠️ Loaded application is in terminal status:', loadedApp.status)
            console.log('🧹 Clearing reference to allow creating a new application')
            // Clear the reference - this application cannot be modified
            applicationStore.reset()
            localStorage.removeItem('current_application_id')
          } else {
            applicationStore.restoreProgress()
            console.log('✅ Loaded from saved ID')

            // Set the selected product from the loaded application
            if (loadedApp?.product_id) {
              const product = tenantStore.products.find(p => p.id === loadedApp.product_id)
              if (product) {
                console.log('🏷️ Setting product from loaded application:', product.name)
                applicationStore.setSelectedProduct(product)
              }
            }
          }
        } catch (e) {
          // Application might have been submitted or deleted
          console.warn('❌ Could not load saved application:', e)
          localStorage.removeItem('current_application_id')
        }
      }

      // If still no application, try to find a draft from backend
      if (!applicationStore.currentApplication) {
        console.log('🔍 Searching for draft applications...')
        const applications = await applicationStore.loadApplications()
        console.log('📋 Found applications:', applications.map(a => ({ id: a.id, status: a.status })))

        // Find draft with valid ID
        const draftApp = applications.find(app =>
          app.status === 'DRAFT' && app.id && app.id !== 'null' && app.id !== 'undefined'
        )

        if (draftApp) {
          console.log('📂 Loading draft:', draftApp.id)
          // Load the full application details
          await applicationStore.loadApplication(draftApp.id)
          // Save for future recovery
          localStorage.setItem('current_application_id', draftApp.id)
          // Restore progress (step)
          applicationStore.restoreProgress()

          // Set the selected product from the loaded draft
          const loadedDraft = applicationStore.currentApplication as Application | null
          if (loadedDraft?.product_id) {
            const product = tenantStore.products.find(p => p.id === loadedDraft.product_id)
            if (product) {
              console.log('🏷️ Setting product from draft application:', product.name)
              applicationStore.setSelectedProduct(product)
            }
          }
          console.log('✅ Loaded draft application')
        } else {
          console.log('⚠️ No draft applications found')
        }
      }
    }

    // NOTE: Application creation is deferred to Step1PersonalData.vue
    // It will be created AFTER the applicant record exists (after step 1 completes)
    if (!applicationStore.currentApplication) {
      console.log('ℹ️ No current application - will be created after completing step 1')
    }

    console.log('🏁 Final state:', {
      currentApplication: applicationStore.currentApplication?.id || null,
      status: applicationStore.currentApplication?.status || null,
      selectedProduct: applicationStore.selectedProduct?.name || null,
      required_documents: applicationStore.selectedProduct?.required_documents || []
    })
  } catch (error) {
    console.error('❌ Failed to initialize application:', error)
  } finally {
    isInitializing.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Header with Progress -->
    <header class="bg-white px-4 py-3 border-b sticky top-0 z-50">
      <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-2">
          <button @click="handleExit" class="p-1 -ml-1 hover:bg-gray-100 rounded transition-colors">
            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <span class="text-sm text-gray-500">Paso {{ currentStep }} de {{ totalSteps }}</span>
          <div class="w-6" />
        </div>
        <AppProgressBar :current="currentStep" :total="totalSteps" :show-label="false" />
      </div>
    </header>

    <!-- Content -->
    <main class="flex-1 pb-24">
      <!-- Loading state while initializing application -->
      <div v-if="isInitializing" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-4"></div>
        <p class="text-gray-500">Preparando tu solicitud...</p>
      </div>
      <router-view v-else />
    </main>
  </div>
</template>
