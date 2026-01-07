<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useApplicationStore, useTenantStore } from '@/stores'
import { AppProgressBar } from '@/components/common'
import type { PaymentFrequency } from '@/types'

const route = useRoute()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()

const isInitializing = ref(true)

const currentStep = computed(() => (route.meta.step as number) || 1)
const totalSteps = computed(() => applicationStore.totalSteps)
const stepTitle = computed(() => (route.meta.title as string) || '')

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
      // Create new application from pending data
      const params = JSON.parse(pendingApp) as {
        product_id: string
        requested_amount: number
        term_months: number
        payment_frequency: PaymentFrequency
      }

      console.log('📝 Creating application from pending data:', params)

      // Find and set the product
      const product = tenantStore.products.find(p => p.id === params.product_id)
      console.log('🏷️ Found product:', product?.id || 'NOT FOUND')

      if (product) {
        applicationStore.setSelectedProduct(product)
      }

      // Run simulation first to populate store
      await applicationStore.runSimulation({
        product_id: params.product_id,
        amount: params.requested_amount,
        term_months: params.term_months,
        payment_frequency: params.payment_frequency
      })

      // Create the application
      console.log('📝 About to create application with params:', params)
      let newApp = null
      try {
        newApp = await applicationStore.createApplication({
          product_id: params.product_id,
          requested_amount: params.requested_amount,
          term_months: params.term_months,
          payment_frequency: params.payment_frequency
        })
        console.log('✅ Application created:', newApp)
        console.log('✅ Application ID:', newApp?.id)
        // @ts-ignore - Vue type inference issue
        console.log('✅ Store currentApplication:', applicationStore.currentApplication?.id)
      } catch (createError) {
        console.error('❌ Failed to create application:', createError)
      }

      // Save the application ID for recovery after refresh
      if (newApp?.id && newApp.id !== 'null' && newApp.id !== 'undefined') {
        localStorage.setItem('current_application_id', newApp.id)
      } else {
        console.warn('⚠️ Not saving invalid application ID:', newApp?.id)
      }

      // Clear the pending data
      localStorage.removeItem('pending_application')
    } else if (!applicationStore.currentApplication) {
      // No pending app and no current application in store
      console.log('🔍 No pending app, looking for existing...')

      // First try to load from saved application ID
      if (savedAppId) {
        console.log('📂 Loading from saved ID:', savedAppId)
        try {
          await applicationStore.loadApplication(savedAppId)
          applicationStore.restoreProgress()
          console.log('✅ Loaded from saved ID')
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
          console.log('✅ Loaded draft application')
        } else {
          console.log('⚠️ No draft applications found')
        }
      }
    }

    // FALLBACK: If we still don't have an application by this point, create one
    // This handles cases where pending_application was cleared or never set
    if (!applicationStore.currentApplication) {
      console.log('🆕 Creating fallback application...')

      // We need a product - use the first active product if none selected
      let product = applicationStore.selectedProduct || tenantStore.activeProducts[0]
      if (!product) {
        console.error('❌ No products available to create application')
      } else {
        // Use simulation data if available, or defaults
        const sim = applicationStore.simulation
        const params = {
          product_id: product.id,
          requested_amount: sim?.requested_amount || product.rules.min_amount || 10000,
          term_months: sim?.term_months || product.rules.min_term_months || 12,
          payment_frequency: (sim?.payment_frequency || 'MONTHLY') as PaymentFrequency
        }

        console.log('📝 Creating application with fallback params:', params)

        // Ensure simulation is run first
        await applicationStore.runSimulation({
          product_id: params.product_id,
          amount: params.requested_amount,
          term_months: params.term_months,
          payment_frequency: params.payment_frequency
        })

        try {
          const newApp = await applicationStore.createApplication(params)
          console.log('✅ Fallback application created:', newApp?.id)

          if (newApp?.id && newApp.id !== 'null' && newApp.id !== 'undefined') {
            localStorage.setItem('current_application_id', newApp.id)
          } else {
            console.warn('⚠️ Fallback application has invalid ID:', newApp?.id)
          }
        } catch (createErr) {
          console.error('❌ Failed to create fallback application:', createErr)
        }
      }
    }

    console.log('🏁 Final state:', {
      currentApplication: applicationStore.currentApplication?.id || null,
      status: applicationStore.currentApplication?.status || null
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
      <!-- Loading state while initializing application -->
      <div v-if="isInitializing" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-4"></div>
        <p class="text-gray-500">Preparando tu solicitud...</p>
      </div>
      <router-view v-else />
    </main>
  </div>
</template>
