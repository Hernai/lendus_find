<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore, useTenantStore, useProfileStore, useApplicantStore } from '@/stores'
import type { BankAccount } from '@/types/applicant'
import BankAccountCard from '@/components/BankAccountCard.vue'
import AddBankAccountModal from '@/components/AddBankAccountModal.vue'
import { v2 } from '@/services/v2'
import { logger } from '@/utils/logger'
import {
  formatDate,
  formatMoney,
  formatPhone,
  formatGender,
  formatMaritalStatus,
  formatEmploymentType,
  formatHousingType,
  formatSeniority,
} from '@/utils/formatters'

const log = logger.child('Profile')
const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const profileStore = useProfileStore()
// Keep applicantStore for bank accounts and photo operations (not yet in V2 profile)
const applicantStore = useApplicantStore()

const isLoading = ref(true)
const bankAccounts = ref<BankAccount[]>([])
const showAddBankModal = ref(false)
const profilePhotoUrl = ref<string | null>(null)
const profilePhotoVerified = ref(false)
const uploadingPhoto = ref(false)
const photoError = ref<string | null>(null)
const photoInputRef = ref<HTMLInputElement | null>(null)
const activeApplicationId = ref<string | null>(null)
const showPhotoViewer = ref(false)

const profile = computed(() => profileStore.profile)

const userName = computed(() => {
  const pd = profile.value?.personal_data
  if (pd?.first_name) {
    const name = pd.first_name.toLowerCase()
    return name.charAt(0).toUpperCase() + name.slice(1)
  }
  return authStore.user?.email?.split('@')[0] || 'Usuario'
})

const fullName = computed(() => {
  const pd = profile.value?.personal_data
  if (!pd) return ''
  return pd.full_name || `${pd.first_name || ''} ${pd.last_name_1 || ''} ${pd.last_name_2 || ''}`.trim()
})

// Use tenant options for dynamic labels (fallback to centralized formatters)
const getGenderLabel = (gender: string | null | undefined) => {
  if (!gender) return '-'
  const option = tenantStore.options.gender.find(o => o.value === gender)
  return option?.label || formatGender(gender)
}

const getMaritalStatusLabel = (status: string | null | undefined) => {
  if (!status) return '-'
  const option = tenantStore.options.marital_status.find(o => o.value === status)
  return option?.label || formatMaritalStatus(status)
}

const getEmploymentTypeLabel = (type: string | null | undefined) => {
  if (!type) return '-'
  const option = tenantStore.options.employment_type.find(o => o.value === type)
  return option?.label || formatEmploymentType(type)
}

const getHousingTypeLabel = (type: string | null | undefined) => {
  if (!type) return '-'
  const option = tenantStore.options.housing_type.find(o => o.value === type)
  return option?.label || formatHousingType(type)
}

// Load data
const loadBankAccounts = async () => {
  try {
    bankAccounts.value = await applicantStore.loadBankAccounts()
  } catch (e) {
    log.error('Failed to load bank accounts:', e)
    bankAccounts.value = []
  }
}

const loadApplicationAndPhoto = async () => {
  try {
    const response = await v2.applicant.application.list()
    const firstApp = response.data[0]
    if (firstApp) {
      // Get the most recent application
      activeApplicationId.value = firstApp.id
      // Try to load the profile photo
      const result = await applicantStore.getProfilePhotoUrl(activeApplicationId.value)
      profilePhotoUrl.value = result.url
      profilePhotoVerified.value = result.isVerified
    }
  } catch (e) {
    log.error('Failed to load application/photo:', e)
  }
}

onMounted(async () => {
  await tenantStore.loadConfig()
  await profileStore.loadProfile()
  await Promise.all([
    loadBankAccounts(),
    loadApplicationAndPhoto()
  ])
  isLoading.value = false
})

// Photo actions
const openPhotoViewer = () => {
  if (profilePhotoUrl.value) {
    showPhotoViewer.value = true
  }
}

const triggerPhotoUpload = () => {
  if (profilePhotoVerified.value) {
    photoError.value = 'La foto ya está verificada y no se puede cambiar'
    return
  }
  photoInputRef.value?.click()
}

const handlePhotoSelect = async (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  // Check if photo is verified
  if (profilePhotoVerified.value) {
    photoError.value = 'La foto ya está verificada y no se puede cambiar'
    return
  }

  // Validate
  if (!['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)) {
    photoError.value = 'Solo se permiten imágenes (JPG, PNG, WebP)'
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    photoError.value = 'La imagen no debe exceder 5MB'
    return
  }

  if (!activeApplicationId.value) {
    photoError.value = 'No hay solicitud activa para subir la foto'
    return
  }

  photoError.value = null
  uploadingPhoto.value = true

  // Show preview immediately
  const previewUrl = URL.createObjectURL(file)
  profilePhotoUrl.value = previewUrl

  try {
    // Upload to backend as SELFIE document
    await applicantStore.uploadProfilePhoto(activeApplicationId.value, file)
    // Clean up preview URL
    URL.revokeObjectURL(previewUrl)
    // Reload the photo with proper authentication (creates blob URL)
    const result = await applicantStore.getProfilePhotoUrl(activeApplicationId.value)
    profilePhotoUrl.value = result.url
    profilePhotoVerified.value = result.isVerified
  } catch (e: unknown) {
    log.error('Failed to upload photo:', e)
    // Check if the error is from the server about verified document
    const errorMessage = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    if (errorMessage?.includes('verificado')) {
      photoError.value = errorMessage
      profilePhotoVerified.value = true
    } else {
      photoError.value = 'Error al subir la foto'
    }
    // Revert to previous photo or null
    profilePhotoUrl.value = null
    URL.revokeObjectURL(previewUrl)
  } finally {
    uploadingPhoto.value = false
    // Reset input so same file can be selected again
    input.value = ''
  }
}

// Bank account actions
const setPrimaryAccount = async (accountId: string) => {
  try {
    await applicantStore.setPrimaryBankAccount(accountId)
    await loadBankAccounts()
  } catch (e) {
    log.error('Failed to set primary:', e)
  }
}

const deleteAccount = async (accountId: string) => {
  try {
    await applicantStore.deleteBankAccount(accountId)
    await loadBankAccounts()
  } catch (e) {
    log.error('Failed to delete account:', e)
  }
}

const onAccountSaved = async () => {
  showAddBankModal.value = false
  await loadBankAccounts()
}

const goBack = () => {
  router.push('/dashboard')
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-gradient-to-br from-primary-600 to-primary-700 px-4 pt-3 pb-12">
      <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-3">
          <button
            class="flex items-center gap-1.5 px-2.5 py-1.5 bg-white/10 rounded-lg text-white text-xs hover:bg-white/20 transition-colors"
            @click="goBack"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver
          </button>
          <button
            class="flex items-center gap-1.5 px-2.5 py-1.5 bg-white/10 rounded-lg text-white text-xs hover:bg-white/20 transition-colors"
            @click="handleLogout"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Salir
          </button>
        </div>

        <h1 class="text-xl font-bold text-white">Mi Perfil</h1>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-2xl mx-auto px-4 -mt-10 pb-8">
      <!-- Loading -->
      <div v-if="isLoading" class="bg-white rounded-2xl shadow-lg p-8 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto" />
        <p class="text-gray-500 mt-4">Cargando tu perfil...</p>
      </div>

      <template v-else>
        <!-- Profile Header Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-4">
          <div class="flex flex-col items-center">
            <!-- Profile Photo -->
            <div class="relative mb-4">
              <!-- Photo display (clickable to view) -->
              <button
                class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden border-4 shadow-lg transition-all"
                :class="[
                  profilePhotoVerified ? 'border-green-400' : 'border-white',
                  profilePhotoUrl ? 'hover:ring-4 hover:ring-primary-200 cursor-pointer' : 'cursor-default'
                ]"
                @click="profilePhotoUrl ? openPhotoViewer() : triggerPhotoUpload()"
                :disabled="uploadingPhoto"
              >
                <img
                  v-if="profilePhotoUrl"
                  :src="profilePhotoUrl"
                  alt="Foto de perfil"
                  class="w-full h-full object-cover"
                />
                <template v-else>
                  <svg v-if="!uploadingPhoto" class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                  </svg>
                  <div v-else class="animate-spin w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full" />
                </template>
              </button>
              <!-- Verified badge (green checkmark) - not clickable -->
              <div
                v-if="profilePhotoVerified"
                class="absolute bottom-0 right-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center shadow-md pointer-events-none"
              >
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
              </div>
              <!-- Camera button (clickable to upload, only if not verified) -->
              <button
                v-else
                class="absolute bottom-0 right-0 w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center shadow-md hover:bg-primary-700 active:bg-primary-800 transition-colors"
                @click.stop="triggerPhotoUpload"
                :disabled="uploadingPhoto"
              >
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </button>
              <input
                ref="photoInputRef"
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/webp"
                class="hidden"
                @change="handlePhotoSelect"
              />
            </div>

            <p v-if="photoError" class="text-red-500 text-sm mb-2">{{ photoError }}</p>

            <!-- Name & CURP -->
            <h2 class="text-xl font-bold text-gray-900 text-center">{{ fullName || 'Sin nombre' }}</h2>
            <p v-if="profile?.identifications?.curp" class="text-gray-500 text-sm mt-1">CURP: {{ profile.identifications.curp }}</p>
          </div>
        </div>

        <!-- Personal Data Section -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-4">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900">Datos Personales</h3>
            </div>
          </div>

          <div class="space-y-3 text-sm">
            <div class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">Nombre completo</span>
              <span class="text-gray-900 font-medium text-right">{{ fullName || '-' }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">RFC</span>
              <span class="text-gray-900 font-medium">{{ profile?.identifications?.rfc || '-' }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">Fecha de nacimiento</span>
              <span class="text-gray-900 font-medium">{{ formatDate(profile?.personal_data?.birth_date) }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">Género</span>
              <span class="text-gray-900 font-medium">{{ profile?.personal_data?.gender_label || getGenderLabel(profile?.personal_data?.gender) }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">Estado civil</span>
              <span class="text-gray-900 font-medium">{{ profile?.personal_data?.marital_status_label || getMaritalStatusLabel(profile?.personal_data?.marital_status) }}</span>
            </div>
            <div class="flex justify-between py-2">
              <span class="text-gray-500">Teléfono</span>
              <span class="text-gray-900 font-medium">{{ formatPhone(authStore.user?.phone) }}</span>
            </div>
          </div>
        </div>

        <!-- Address Section -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-4">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900">Dirección</h3>
            </div>
          </div>

          <div v-if="profile?.address" class="text-sm text-gray-700">
            <p class="font-medium">
              {{ profile.address.street }} {{ profile.address.ext_number }}
              <span v-if="profile.address.int_number">, Int {{ profile.address.int_number }}</span>
            </p>
            <p class="text-gray-500 mt-1">
              Col. {{ profile.address.neighborhood }}, CP {{ profile.address.postal_code }}
            </p>
            <p class="text-gray-500">
              {{ profile.address.municipality || profile.address.city }}, {{ profile.address.state }}
            </p>
            <div v-if="profile.address.housing_type" class="mt-3 pt-3 border-t border-gray-100">
              <span class="text-gray-500">Tipo de vivienda:</span>
              <span class="ml-2 text-gray-900 font-medium">{{ getHousingTypeLabel(profile.address.housing_type) }}</span>
            </div>
          </div>
          <p v-else class="text-gray-500 text-sm">Sin dirección registrada</p>
        </div>

        <!-- Employment Section -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-4">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900">Empleo</h3>
            </div>
          </div>

          <div v-if="profile?.employment" class="space-y-3 text-sm">
            <div class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">Tipo</span>
              <span class="text-gray-900 font-medium">{{ getEmploymentTypeLabel(profile.employment.employment_type) }}</span>
            </div>
            <div v-if="profile.employment.company_name" class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">Empresa</span>
              <span class="text-gray-900 font-medium text-right">{{ profile.employment.company_name }}</span>
            </div>
            <div v-if="profile.employment.position" class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">Puesto</span>
              <span class="text-gray-900 font-medium">{{ profile.employment.position }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-100">
              <span class="text-gray-500">Antigüedad</span>
              <span class="text-gray-900 font-medium">{{ formatSeniority(profile.employment.seniority_months) }}</span>
            </div>
            <div class="flex justify-between py-2">
              <span class="text-gray-500">Ingreso mensual</span>
              <span class="text-gray-900 font-medium">{{ formatMoney(profile.employment.monthly_income) }}</span>
            </div>
          </div>
          <p v-else class="text-gray-500 text-sm">Sin información laboral registrada</p>
        </div>

        <!-- Bank Accounts Section -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-4">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900">Cuentas Bancarias</h3>
            </div>
            <button
              class="flex items-center gap-1 px-3 py-1.5 bg-primary-50 text-primary-600 rounded-lg text-sm font-medium hover:bg-primary-100 transition-colors"
              @click="showAddBankModal = true"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Agregar
            </button>
          </div>

          <div v-if="bankAccounts.length === 0" class="text-center py-8">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
            </div>
            <p class="text-gray-500 text-sm">No tienes cuentas bancarias registradas.</p>
            <p class="text-gray-400 text-xs mt-1">Agrega una para recibir tu crédito.</p>
          </div>

          <div v-else class="space-y-3">
            <BankAccountCard
              v-for="account in bankAccounts"
              :key="account.id"
              :account="account"
              @set-primary="setPrimaryAccount"
              @delete="deleteAccount"
            />
          </div>
        </div>

        <!-- Back to Dashboard Button -->
        <button
          class="w-full py-3 bg-white text-gray-600 rounded-xl font-medium shadow-sm hover:bg-gray-50 transition-colors"
          @click="goBack"
        >
          Volver al inicio
        </button>
      </template>
    </main>

    <!-- Add Bank Account Modal -->
    <AddBankAccountModal
      v-if="showAddBankModal"
      @close="showAddBankModal = false"
      @saved="onAccountSaved"
    />

    <!-- Photo Viewer Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showPhotoViewer && profilePhotoUrl"
          class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
          @click="showPhotoViewer = false"
        >
          <!-- Close button -->
          <button
            class="absolute top-4 right-4 w-10 h-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center transition-colors"
            @click="showPhotoViewer = false"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Photo container -->
          <div class="relative max-w-2xl w-full" @click.stop>
            <img
              :src="profilePhotoUrl"
              alt="Foto de perfil"
              class="w-full h-auto max-h-[80vh] object-contain rounded-2xl"
            />

            <!-- Verification badge -->
            <div
              v-if="profilePhotoVerified"
              class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-green-500 text-white px-4 py-2 rounded-full shadow-lg"
            >
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
              </svg>
              <span class="font-medium">Foto verificada</span>
            </div>

            <!-- Change photo button (only if not verified) -->
            <button
              v-if="!profilePhotoVerified"
              class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-white text-gray-900 px-4 py-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors"
              @click="showPhotoViewer = false; triggerPhotoUpload()"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span class="font-medium">Cambiar foto</span>
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
