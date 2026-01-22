<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore, useTenantStore, useApplicationStore } from '@/stores'
import { AppButton } from '@/components/common'

const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const applicationStore = useApplicationStore()

const pin = ref('')
const confirmPin = ref('')
const error = ref('')
const currentInput = ref<'pin' | 'confirm'>('pin')

// Get tenant slug from route params or store
const getTenantSlug = (): string | undefined => {
  const routeTenant = router.currentRoute.value.params.tenant as string
  return routeTenant || tenantStore.slug || undefined
}

const isValid = computed(() => {
  return pin.value.length === 4 && confirmPin.value.length === 4 && pin.value === confirmPin.value
})

const handlePinInput = (digit: string) => {
  if (currentInput.value === 'pin') {
    if (pin.value.length < 4) {
      pin.value += digit
      if (pin.value.length === 4) {
        currentInput.value = 'confirm'
      }
    }
  } else {
    if (confirmPin.value.length < 4) {
      confirmPin.value += digit
    }
  }
  error.value = ''
}

const handleDelete = () => {
  if (currentInput.value === 'confirm') {
    if (confirmPin.value.length > 0) {
      confirmPin.value = confirmPin.value.slice(0, -1)
    } else {
      currentInput.value = 'pin'
      pin.value = pin.value.slice(0, -1)
    }
  } else {
    pin.value = pin.value.slice(0, -1)
  }
}

const handleSubmit = async () => {
  if (!isValid.value) {
    if (pin.value !== confirmPin.value) {
      error.value = 'Los NIP no coinciden'
      confirmPin.value = ''
      currentInput.value = 'confirm'
    }
    return
  }

  const result = await authStore.setupPin(pin.value)

  if (result.success) {
    // Check if user has completed registration (has applicant)
    await authStore.checkAuth()

    const redirect = router.currentRoute.value.query.redirect as string
    const tenantSlug = getTenantSlug()

    if (redirect) {
      router.push(redirect)
    } else if (!authStore.hasApplicant) {
      // User is new, redirect to onboarding
      // Initialize application store to restore saved data from landing page
      applicationStore.init()

      // Check if user already has product selected (from landing page)
      const hasProductSelected = applicationStore.selectedProduct !== null || applicationStore.simulation !== null

      console.log('🔍 [PIN Setup] Checking product selection state', {
        selectedProduct: applicationStore.selectedProduct?.name || 'null',
        hasSimulation: applicationStore.simulation !== null,
        hasProductSelected
      })

      if (hasProductSelected) {
        // User came from landing with product selected, skip simulator
        console.log('✅ [PIN Setup] User has product - skipping simulator → verification')
        if (tenantSlug) {
          router.push(`/${tenantSlug}/solicitud/verificacion`)
        } else {
          router.push('/solicitud/verificacion')
        }
      } else {
        // No product selected, start with simulator
        console.log('❌ [PIN Setup] No product - starting with simulator')
        if (tenantSlug) {
          router.push(`/${tenantSlug}/solicitud`)
        } else {
          router.push('/solicitud')
        }
      }
    } else {
      // User exists, redirect to dashboard
      if (tenantSlug) {
        router.push(`/${tenantSlug}/dashboard`)
      } else {
        router.push('/dashboard')
      }
    }
  } else {
    error.value = result.error || 'Error al configurar el NIP'
    pin.value = ''
    confirmPin.value = ''
    currentInput.value = 'pin'
  }
}

const skipSetup = async () => {
  // Mark that user doesn't need PIN setup anymore (they chose to skip it)
  authStore.needsPinSetup = false

  // Check if user has completed registration (has applicant)
  await authStore.checkAuth()

  const redirect = router.currentRoute.value.query.redirect as string
  const tenantSlug = getTenantSlug()

  if (redirect) {
    router.push(redirect)
  } else if (!authStore.hasApplicant) {
    // User is new, redirect to onboarding
    // Initialize application store to restore saved data from landing page
    applicationStore.init()

    // Check if user already has product selected (from landing page)
    const hasProductSelected = applicationStore.selectedProduct !== null || applicationStore.simulation !== null

    console.log('🔍 [PIN Skip] Checking product selection state', {
      selectedProduct: applicationStore.selectedProduct?.name || 'null',
      hasSimulation: applicationStore.simulation !== null,
      hasProductSelected
    })

    if (hasProductSelected) {
      // User came from landing with product selected, skip simulator
      console.log('✅ [PIN Skip] User has product - skipping simulator → verification')
      if (tenantSlug) {
        router.push(`/${tenantSlug}/solicitud/verificacion`)
      } else {
        router.push('/solicitud/verificacion')
      }
    } else {
      // No product selected, start with simulator
      console.log('❌ [PIN Skip] No product - starting with simulator')
      if (tenantSlug) {
        router.push(`/${tenantSlug}/solicitud`)
      } else {
        router.push('/solicitud')
      }
    }
  } else {
    // User exists, redirect to dashboard
    if (tenantSlug) {
      router.push(`/${tenantSlug}/dashboard`)
    } else {
      router.push('/dashboard')
    }
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <div class="flex-1 flex flex-col px-6 py-8">
      <div class="mx-auto w-full max-w-md">
        <!-- Icon -->
        <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mb-6 mx-auto">
          <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>

        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">
          Crea tu NIP de acceso
        </h1>
        <p class="text-gray-500 text-center mb-2">
          {{ currentInput === 'pin' ? 'Ingresa un NIP de 4 dígitos' : 'Confirma tu NIP' }}
        </p>
        <p class="text-xs text-gray-400 text-center mb-6">
          El NIP es opcional. Puedes omitir este paso y configurarlo después.
        </p>

        <!-- PIN Display -->
        <div class="flex justify-center gap-3 mb-8">
          <div
            v-for="i in 4"
            :key="i"
            class="w-14 h-14 rounded-xl border-2 flex items-center justify-center transition-all"
            :class="{
              'border-primary-500 bg-primary-50': currentInput === 'pin' && pin.length === i - 1,
              'border-green-500 bg-green-50': currentInput === 'confirm' && confirmPin.length === i - 1,
              'border-gray-300': currentInput === 'pin' ? pin.length < i - 1 : confirmPin.length < i - 1,
              'border-gray-900 bg-gray-100': currentInput === 'pin' ? pin.length >= i : confirmPin.length >= i
            }"
          >
            <div
              v-if="currentInput === 'pin' ? pin.length >= i : confirmPin.length >= i"
              class="w-3 h-3 rounded-full bg-gray-900"
            />
          </div>
        </div>

        <!-- Status indicator -->
        <div class="flex justify-center gap-2 mb-6">
          <div
            class="w-2 h-2 rounded-full transition-colors"
            :class="pin.length === 4 ? 'bg-primary-600' : 'bg-gray-300'"
          />
          <div
            class="w-2 h-2 rounded-full transition-colors"
            :class="confirmPin.length === 4 && pin === confirmPin ? 'bg-primary-600' : 'bg-gray-300'"
          />
        </div>

        <!-- Error -->
        <p v-if="error" class="text-red-500 text-sm text-center mb-4">
          {{ error }}
        </p>

        <!-- Numpad -->
        <div class="grid grid-cols-3 gap-3 max-w-xs mx-auto">
          <button
            v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
            :key="digit"
            class="h-16 text-2xl font-semibold text-gray-900 bg-white rounded-xl shadow-sm hover:bg-gray-50 active:bg-gray-100 transition-colors"
            @click="handlePinInput(digit)"
          >
            {{ digit }}
          </button>

          <button
            class="h-16 text-sm font-medium text-gray-500 hover:bg-gray-100 rounded-xl transition-colors"
            @click="skipSetup"
          >
            Omitir
          </button>

          <button
            class="h-16 text-2xl font-semibold text-gray-900 bg-white rounded-xl shadow-sm hover:bg-gray-50 active:bg-gray-100 transition-colors"
            @click="handlePinInput('0')"
          >
            0
          </button>

          <button
            class="h-16 flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-xl transition-colors"
            @click="handleDelete"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z" />
            </svg>
          </button>
        </div>

        <!-- Submit button -->
        <div class="mt-8">
          <AppButton
            variant="primary"
            size="lg"
            class="w-full"
            :disabled="!isValid || authStore.isLoading"
            :loading="authStore.isLoading"
            @click="handleSubmit"
          >
            Guardar NIP
          </AppButton>
        </div>

        <!-- Info -->
        <p class="mt-6 text-xs text-gray-500 text-center">
          Este NIP te permitirá iniciar sesión sin necesidad de recibir un código SMS cada vez.
        </p>
      </div>
    </div>
  </div>
</template>
