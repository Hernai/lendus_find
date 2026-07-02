<script setup lang="ts">
import { computed, ref, onUnmounted } from 'vue'
import applicationService, { type NubariumValidationData } from '@/services/v2/application.staff.service'

// Resultado persistido de la validación de CLABE con Nubarium (lo guarda el
// webhook en verification_data['nubarium_clabe']). Queda visible aunque se
// cierre el modal.
interface ClabeValidationSummary {
  status: 'completed' | 'failed' | 'pending'
  message_code: number | null
  message: string | null
  similarity: number | null
  holder_name_real: string | null
  bank: string | null
  validation_code: string | null
  validation_id: string | null
  validated_at: string | null
}

interface BankAccount {
  id: string
  type: string
  bank_name: string
  bank_code: string
  clabe: string
  account_type: string
  account_type_label?: string
  holder_name: string
  holder_rfc?: string
  is_primary: boolean
  is_own_account: boolean
  is_verified: boolean
  verified_at?: string | null
  verification_method?: string | null
  verified_by_nubarium?: boolean
  clabe_validation?: ClabeValidationSummary | null
  created_at?: string
}

const props = defineProps<{
  accounts: BankAccount[]
  canVerify: boolean
  canEdit?: boolean
  applicationId: string
}>()

const emit = defineEmits<{
  (e: 'verify', account: BankAccount): void
  (e: 'unverify', account: BankAccount): void
  (e: 'refresh'): void
}>()

const stats = computed(() => ({
  total: props.accounts.length,
  verified: props.accounts.filter(ba => ba.is_verified).length,
}))

// --- Edición del titular (solo SUPER_ADMIN, vía canEdit) ---
const showEditModal = ref(false)
const editAccount = ref<BankAccount | null>(null)
const editHolderName = ref('')
const editHolderRfc = ref('')
const editSaving = ref(false)
const editError = ref('')

const openEdit = (account: BankAccount) => {
  editAccount.value = account
  editHolderName.value = account.holder_name ?? ''
  editHolderRfc.value = account.holder_rfc ?? ''
  editError.value = ''
  showEditModal.value = true
}

const saveEdit = async () => {
  if (!editAccount.value) return
  const name = editHolderName.value.trim()
  if (!name) { editError.value = 'El nombre del titular es requerido.'; return }
  editSaving.value = true
  editError.value = ''
  try {
    await applicationService.updateBankAccount(props.applicationId, editAccount.value.id, {
      holder_name: name,
      holder_rfc: editHolderRfc.value.trim() || null,
    })
    showEditModal.value = false
    emit('refresh')
  } catch {
    editError.value = 'No se pudo actualizar el titular.'
  } finally {
    editSaving.value = false
  }
}

// --- Validación CLABE con Nubarium (asíncrona por webhook) ---
const validatingId = ref<string | null>(null)
const showNubariumModal = ref(false)
const nubariumResult = ref<NubariumValidationData | null>(null)
const nubariumError = ref('')
const autoVerified = ref(false)
let nubariumPollCancelled = false

// Vista tipada de los campos relevantes del resultado de Nubarium (validate-clabe).
interface ClabeResultData {
  messageCode?: number
  message?: string
  data?: {
    similarity?: number
    spei?: {
      beneficiary?: { name?: string; receivingBank?: string }
    }
  }
}

// Interpreta el resultado en 3 casos: coincide / cuenta válida sin coincidencia
// de nombre / inválida o error. messageCode 0 = match, 3 = "Match not found".
const validationOutcome = computed(() => {
  const v = nubariumResult.value
  if (!v) return null
  const res = (v.result ?? {}) as ClabeResultData
  const code = res.messageCode
  const holder = res.data?.spei?.beneficiary?.name ?? null
  const bank = res.data?.spei?.beneficiary?.receivingBank ?? null
  const similarity = typeof res.data?.similarity === 'number' ? res.data.similarity : null

  if (v.status === 'completed' || code === 0) {
    return { label: 'CLABE válida — el titular coincide', badge: 'bg-green-100 text-green-800', holder, bank, similarity }
  }
  if (code === 3) {
    return { label: 'CLABE válida, pero el titular NO coincide', badge: 'bg-amber-100 text-amber-800', holder, bank, similarity }
  }
  return { label: v.error || res.message || 'CLABE no válida o error', badge: 'bg-red-100 text-red-700', holder, bank, similarity }
})

// Interpreta el resultado PERSISTIDO (guardado en la cuenta) para mostrarlo en
// la tarjeta. Misma lógica de 3 estados que el modal.
const clabeOutcome = (cv: ClabeValidationSummary) => {
  const code = cv.message_code
  if (cv.status === 'completed' || code === 0) {
    return { label: 'CLABE válida — el titular coincide', badge: 'bg-green-100 text-green-800' }
  }
  if (code === 3) {
    return { label: 'CLABE válida, pero el titular NO coincide', badge: 'bg-amber-100 text-amber-800' }
  }
  return { label: cv.message || 'CLABE no válida o error', badge: 'bg-red-100 text-red-700' }
}

const formatDateTime = (iso?: string | null) => {
  if (!iso) return ''
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' })
}

// --- Detalle de la validación persistida (visible para TODOS los usuarios) ---
const showDetailModal = ref(false)
const detailValidation = ref<ClabeValidationSummary | null>(null)

const openDetail = (cv: ClabeValidationSummary) => {
  detailValidation.value = cv
  showDetailModal.value = true
}

const validateWithNubarium = async (account: BankAccount) => {
  validatingId.value = account.id
  nubariumError.value = ''
  nubariumResult.value = null
  autoVerified.value = false
  nubariumPollCancelled = false
  try {
    const res = await applicationService.validateBankAccountClabe(props.applicationId, account.id)
    const id = res.data?.validation_id
    if (!id) throw new Error('sin id')
    await pollNubarium(id, account)
  } catch {
    if (!nubariumPollCancelled) {
      nubariumError.value = 'No se pudo iniciar la validación con Nubarium'
      showNubariumModal.value = true
    }
  } finally {
    validatingId.value = null
  }
}

// Nubarium responde por webhook; sondeamos ~60s (20 intentos × 3s).
const pollNubarium = async (id: string, account: BankAccount) => {
  for (let i = 0; i < 20 && !nubariumPollCancelled; i++) {
    const res = await applicationService.getNubariumValidation(id)
    const data = res.data
    if (data && data.status !== 'pending') {
      if (nubariumPollCancelled) return
      nubariumResult.value = data
      // La verificación automática (cuando el titular coincide) la hace el
      // webhook en el servidor; aquí solo reflejamos el resultado. Refrescamos
      // para traer is_verified y el resultado persistido en la tarjeta.
      autoVerified.value = data.status === 'completed' && !account.is_verified
      emit('refresh')
      showNubariumModal.value = true
      return
    }
    await new Promise((resolve) => setTimeout(resolve, 3000))
  }
  if (!nubariumPollCancelled) {
    nubariumError.value = 'La validación sigue en proceso; revisa más tarde.'
    showNubariumModal.value = true
  }
}

onUnmounted(() => { nubariumPollCancelled = true })
</script>

<template>
  <div>
    <!-- Bank Account Stats -->
    <div class="flex items-center gap-4 mb-4 text-sm text-gray-500">
      <span>Total: <b class="text-gray-900">{{ stats.total }}</b></span>
      <span>Verificadas: <b class="text-gray-900">{{ stats.verified }}</b></span>
    </div>

    <div v-if="accounts.length === 0" class="text-center py-6 text-gray-500 text-sm">
      No hay cuentas bancarias registradas
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="account in accounts"
        :key="account.id"
        class="border border-gray-200 rounded-lg p-4"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-gray-900">{{ account.bank_name }}</span>
                <span
                  v-if="account.is_primary"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                >
                  Principal
                </span>
                <span
                  v-if="account.is_verified"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                >
                  Verificada
                </span>
                <span
                  v-else
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600"
                >
                  Sin verificar
                </span>
              </div>
              <p class="text-xs text-gray-500">{{ account.account_type_label || account.account_type }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">CLABE</span>
            <span class="text-gray-900 font-mono">{{ account.clabe }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Titular</span>
            <span class="text-gray-900 text-right">{{ account.holder_name }}</span>
          </div>
          <div v-if="account.holder_rfc" class="flex justify-between">
            <span class="text-gray-500">RFC</span>
            <span class="text-gray-900 font-mono">{{ account.holder_rfc }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Cuenta propia</span>
            <span class="text-gray-900">{{ account.is_own_account ? 'Sí' : 'No' }}</span>
          </div>
        </div>

        <!-- Estado de verificación / validación. Visible para TODOS los usuarios:
             muestra la fecha y hora de la última verificación y, si hay detalle
             de Nubarium, es clickable para abrir el detalle completo. -->
        <div
          v-if="account.is_verified || account.clabe_validation"
          class="mt-3 pt-3 border-t border-gray-100"
        >
          <!-- Con detalle de Nubarium: clickable -->
          <button
            v-if="account.clabe_validation"
            type="button"
            class="w-full flex items-center justify-between gap-3 text-left rounded-lg hover:bg-gray-50 -mx-1.5 px-1.5 py-1.5 transition-colors"
            @click="openDetail(account.clabe_validation)"
          >
            <span class="flex items-center gap-2.5 min-w-0">
              <span
                :class="['w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0', account.is_verified ? 'bg-green-100' : 'bg-amber-100']"
              >
                <svg v-if="account.is_verified" class="w-4 h-4 text-green-700" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                </svg>
                <svg v-else class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
              </span>
              <span class="min-w-0">
                <span class="block text-sm font-medium text-gray-900">
                  {{ account.is_verified
                    ? (account.verified_by_nubarium ? 'Verificada automáticamente (Nubarium)' : 'Verificada manualmente')
                    : 'Validada con Nubarium · sin coincidencia de nombre' }}
                </span>
                <span class="block text-xs text-gray-500">
                  {{ formatDateTime(account.verified_at || account.clabe_validation.validated_at) }}
                </span>
              </span>
            </span>
            <span class="text-xs font-medium text-primary-600 inline-flex items-center gap-0.5 flex-shrink-0">
              Ver detalle
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </span>
          </button>

          <!-- Verificada sin detalle de Nubarium (manual o previa): estado + fecha -->
          <div v-else class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
              <svg class="w-4 h-4 text-green-700" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
              </svg>
            </span>
            <span>
              <span class="block text-sm font-medium text-gray-900">
                {{ account.verified_by_nubarium ? 'Verificada automáticamente (Nubarium)' : 'Verificada manualmente' }}
              </span>
              <span class="block text-xs text-gray-500">{{ formatDateTime(account.verified_at) }}</span>
            </span>
          </div>
        </div>

        <!-- Verification actions -->
        <div v-if="canVerify || canEdit" class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap gap-2">
          <button
            v-if="canVerify && !account.is_verified"
            class="flex-1 px-3 py-1.5 text-sm text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition-colors font-medium"
            @click="emit('verify', account)"
          >
            {{ account.clabe_validation ? 'Validar manualmente' : 'Verificar' }}
          </button>
          <button
            v-else-if="canVerify && account.is_verified && (!account.verified_by_nubarium || canEdit)"
            class="flex-1 px-3 py-1.5 text-sm text-yellow-700 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors font-medium"
            @click="emit('unverify', account)"
          >
            Quitar verificación
          </button>
          <!-- Validada por Nubarium: el analista no puede quitarla (solo super admin). -->
          <span
            v-else-if="account.is_verified && account.verified_by_nubarium && !canEdit"
            class="flex-1 px-3 py-1.5 text-xs text-gray-500 bg-gray-50 rounded-lg inline-flex items-center justify-center text-center"
          >
            Validada por Nubarium · solo un super admin puede quitarla
          </span>
          <!-- Ya verificada automáticamente por Nubarium: ver la última respuesta
               en vez de volver a validar. -->
          <button
            v-if="account.verified_by_nubarium && account.clabe_validation"
            class="flex-1 px-3 py-1.5 text-sm text-primary-700 bg-primary-50 hover:bg-primary-100 rounded-lg transition-colors font-medium inline-flex items-center justify-center gap-1.5"
            @click="openDetail(account.clabe_validation)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Ver respuesta
          </button>
          <!-- Validar la CLABE contra el banco con Nubarium (si aún no está
               verificada automáticamente). -->
          <button
            v-else-if="canVerify"
            class="flex-1 px-3 py-1.5 text-sm text-primary-700 bg-primary-50 hover:bg-primary-100 rounded-lg transition-colors font-medium disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center justify-center gap-1.5"
            :disabled="validatingId === account.id"
            @click="validateWithNubarium(account)"
          >
            <svg v-if="validatingId === account.id" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ validatingId === account.id ? 'Validando…' : 'Validar con Nubarium' }}
          </button>
          <!-- Editar titular (solo SUPER_ADMIN) -->
          <button
            v-if="canEdit"
            class="flex-1 px-3 py-1.5 text-sm text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium inline-flex items-center justify-center gap-1.5"
            @click="openEdit(account)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Editar titular
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: resultado de la validación de CLABE con Nubarium -->
  <Teleport to="body">
    <div
      v-if="showNubariumModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="showNubariumModal = false"
    >
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Validación CLABE con Nubarium</h3>
          <button class="text-gray-400 hover:text-gray-600" @click="showNubariumModal = false">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4">
          <div v-if="nubariumError" class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm">
            {{ nubariumError }}
          </div>

          <template v-else-if="nubariumResult && validationOutcome">
            <div class="flex items-center gap-2 flex-wrap">
              <span :class="['inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium', validationOutcome.badge]">
                {{ validationOutcome.label }}
              </span>
              <span v-if="nubariumResult.validation_code" class="text-xs text-gray-500 font-mono">
                código: {{ nubariumResult.validation_code }}
              </span>
            </div>

            <p v-if="autoVerified" class="text-sm text-green-700 flex items-center gap-1.5">
              <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
              </svg>
              Cuenta marcada como verificada automáticamente.
            </p>

            <!-- Datos clave de la validación (titular real + banco + similitud) -->
            <div
              v-if="validationOutcome.holder || validationOutcome.similarity !== null"
              class="border border-gray-200 rounded-lg divide-y divide-gray-100"
            >
              <div v-if="validationOutcome.holder" class="flex justify-between gap-4 px-3 py-2 text-sm">
                <span class="text-gray-500 flex-shrink-0">Titular real de la cuenta</span>
                <span class="text-gray-900 text-right font-medium">{{ validationOutcome.holder }}</span>
              </div>
              <div v-if="validationOutcome.bank" class="flex justify-between gap-4 px-3 py-2 text-sm">
                <span class="text-gray-500 flex-shrink-0">Banco</span>
                <span class="text-gray-900 text-right">{{ validationOutcome.bank }}</span>
              </div>
              <div v-if="validationOutcome.similarity !== null" class="flex justify-between gap-4 px-3 py-2 text-sm">
                <span class="text-gray-500 flex-shrink-0">Coincidencia de nombre</span>
                <span class="text-gray-900 text-right font-medium">{{ Math.round(validationOutcome.similarity * 100) }}%</span>
              </div>
            </div>

            <!-- Cadena cruda completa -->
            <details class="text-xs">
              <summary class="cursor-pointer text-gray-500 select-none">Ver respuesta cruda de Nubarium</summary>
              <pre class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-3 overflow-x-auto text-gray-700">{{ JSON.stringify(nubariumResult.result, null, 2) }}</pre>
            </details>
          </template>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
          <button
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            @click="showNubariumModal = false"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Modal: editar titular de la cuenta (solo SUPER_ADMIN) -->
  <Teleport to="body">
    <div
      v-if="showEditModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="showEditModal = false"
    >
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Editar titular de la cuenta</h3>
          <button class="text-gray-400 hover:text-gray-600" @click="showEditModal = false">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div v-if="editError" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
            {{ editError }}
          </div>

          <p v-if="editAccount" class="text-xs text-gray-500">
            {{ editAccount.bank_name }} · CLABE <span class="font-mono">{{ editAccount.clabe }}</span>
          </p>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del titular *</label>
            <input
              v-model="editHolderName"
              type="text"
              maxlength="200"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm uppercase focus:ring-primary-500 focus:border-primary-500"
              placeholder="NOMBRE COMPLETO"
            />
          </div>

          <!-- Atajo: usar el titular real que reportó el banco vía Nubarium -->
          <button
            v-if="editAccount?.clabe_validation?.holder_name_real && editAccount.clabe_validation.holder_name_real !== editHolderName"
            type="button"
            class="text-xs text-primary-700 hover:underline"
            @click="editHolderName = editAccount?.clabe_validation?.holder_name_real || editHolderName"
          >
            Usar titular real del banco: {{ editAccount.clabe_validation.holder_name_real }}
          </button>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">RFC del titular <span class="text-gray-400">(opcional)</span></label>
            <input
              v-model="editHolderRfc"
              type="text"
              maxlength="13"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono uppercase focus:ring-primary-500 focus:border-primary-500"
              placeholder="XAXX010101000"
            />
          </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-2">
          <button
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            :disabled="editSaving"
            @click="showEditModal = false"
          >
            Cancelar
          </button>
          <button
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="editSaving"
            @click="saveEdit"
          >
            {{ editSaving ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Modal: detalle de la última validación de CLABE (visible para todos) -->
  <Teleport to="body">
    <div
      v-if="showDetailModal && detailValidation"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="showDetailModal = false"
    >
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Validación de CLABE</h3>
          <button class="text-gray-400 hover:text-gray-600" @click="showDetailModal = false">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div class="flex items-center gap-2 flex-wrap">
            <span :class="['inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium', clabeOutcome(detailValidation).badge]">
              {{ clabeOutcome(detailValidation).label }}
            </span>
            <span v-if="detailValidation.validation_code" class="text-xs text-gray-500 font-mono">
              código: {{ detailValidation.validation_code }}
            </span>
          </div>

          <div class="border border-gray-200 rounded-lg divide-y divide-gray-100">
            <div v-if="detailValidation.holder_name_real" class="flex justify-between gap-4 px-3 py-2 text-sm">
              <span class="text-gray-500 flex-shrink-0">Titular real (banco)</span>
              <span class="text-gray-900 text-right font-medium">{{ detailValidation.holder_name_real }}</span>
            </div>
            <div v-if="detailValidation.bank" class="flex justify-between gap-4 px-3 py-2 text-sm">
              <span class="text-gray-500 flex-shrink-0">Banco</span>
              <span class="text-gray-900 text-right">{{ detailValidation.bank }}</span>
            </div>
            <div v-if="detailValidation.similarity !== null" class="flex justify-between gap-4 px-3 py-2 text-sm">
              <span class="text-gray-500 flex-shrink-0">Coincidencia de nombre</span>
              <span class="text-gray-900 text-right font-medium">{{ Math.round((detailValidation.similarity ?? 0) * 100) }}%</span>
            </div>
            <div v-if="detailValidation.validated_at" class="flex justify-between gap-4 px-3 py-2 text-sm">
              <span class="text-gray-500 flex-shrink-0">Fecha de validación</span>
              <span class="text-gray-900 text-right">{{ formatDateTime(detailValidation.validated_at) }}</span>
            </div>
          </div>

          <p v-if="detailValidation.message" class="text-xs text-gray-500">
            Mensaje de Nubarium: {{ detailValidation.message }}
          </p>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
          <button
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            @click="showDetailModal = false"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
