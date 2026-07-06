<script setup lang="ts">
/**
 * "Datos del Solicitante": grid de campos del solicitante con verificación por
 * campo (VerifiableField + botones inline para email/teléfono), comparación INE
 * y edición de teléfono (solo SUPER_ADMIN). Extraído de AdminApplicationDetail.vue.
 *
 * application se recibe por prop (objeto completo: el template lee application.applicant.*
 * y el composable useFieldVerification lee application.field_verifications). Los helpers
 * de LECTURA vienen del composable; las acciones de ESCRITURA se emiten y el padre las
 * mapea 1:1 a sus handlers (verifyData/openRejectDataModal/openUnverifyModal/...),
 * preservando el flujo optimista + fetchApplication(). El componente NO muta estado.
 */
import { VerifiableField } from '@/modules/admin/components/application-detail'
import { formatDate, formatPhone } from '@/utils/formatters'
import { useFieldVerification } from '@/modules/admin/composables/useFieldVerification'
import type { Application, VerifiableFieldKey } from '@/modules/admin/views/panel/applicationDetail.types'

const props = defineProps<{
  application: Application
  isForeigner: boolean
  birthStateDisplay: string
  ineComparison: {
    rows: Array<{ label: string; confirmed: string; ocr: string; renapo: string; diff: boolean }>
    ineValid: boolean | null
    curpValid: boolean | null
    verifiedAt: string | null
    hasDiffs: boolean
  } | null
  isVerifyingData: boolean
  reverifyingIne: boolean
  isEditingPhone: boolean
  canReviewDocs: boolean
  isSuperAdmin: boolean
}>()

const emit = defineEmits<{
  (e: 'verify', field: VerifiableFieldKey, action: 'verify' | 'unverify'): void
  (e: 'reject', field: VerifiableFieldKey): void
  (e: 'unreject', field: VerifiableFieldKey): void
  (e: 'edit-phone'): void
  (e: 'reverify-ine'): void
  (e: 'show-ine-detail'): void
}>()

const { isFieldVerified, isFieldRejected, isFieldPending, getFieldVerification, isFieldLocked } =
  useFieldVerification(() => props.application)
</script>

<template>
  <div class="border border-gray-200 rounded-lg">
    <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <h3 class="text-sm font-semibold text-gray-900">Datos del Solicitante</h3>
        <span v-if="isForeigner" class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded">
          Extranjero
        </span>
        <!-- Acceso a la verificación de INE (abre el modal) -->
        <button
          v-if="ineComparison"
          type="button"
          class="inline-flex items-center gap-1.5 text-xs px-2 py-0.5 rounded border transition-colors"
          :class="ineComparison.hasDiffs ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-gray-200 text-gray-600 hover:bg-gray-100'"
          @click="emit('show-ine-detail')"
        >
          <span class="font-medium">INE</span>
          <span :class="ineComparison.ineValid ? 'text-green-600' : 'text-gray-400'">{{ ineComparison.ineValid === true ? '✓' : ineComparison.ineValid === false ? '✕' : '—' }}</span>
          <span>{{ ineComparison.hasDiffs ? 'Revisar diferencias' : 'Ver detalle' }}</span>
        </button>
        <!-- Sin verificación previa: dispararla (Nubarium caído en onboarding) -->
        <button
          v-else-if="canReviewDocs"
          type="button"
          class="inline-flex items-center gap-1.5 text-xs px-2 py-0.5 rounded border border-dashed border-gray-300 text-primary-600 hover:bg-gray-100 disabled:opacity-60 disabled:cursor-not-allowed"
          :disabled="reverifyingIne"
          @click="emit('reverify-ine')"
        >
          <svg v-if="reverifyingIne" class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
          </svg>
          {{ reverifyingIne ? 'Verificando…' : 'Verificar INE' }}
        </button>
      </div>
      <div class="flex items-center gap-3 text-xs text-gray-500">
        <span class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-gray-300"></span>
          Vacío
        </span>
        <span class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-blue-500"></span>
          Completado
        </span>
        <span class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-green-500"></span>
          Verificado
        </span>
        <span class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
          Pendiente
        </span>
        <span class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-red-500"></span>
          Rechazado
        </span>
      </div>
    </div>
    <div class="p-3">
      <div class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
        <!-- Nombre -->
        <VerifiableField
          label="Nombre"
          :value="application.applicant.full_name"
          field-key="first_name"
          :verification="getFieldVerification('first_name')"
          :is-locked="isFieldLocked('first_name')"
          :is-verifying="isVerifyingData"
          :can-verify="true"
          @verify="(action) => emit('verify', 'first_name', action)"
          @reject="emit('reject', 'first_name')"
          @unreject="emit('unreject', 'first_name')"
        />
        <!-- Nacionalidad / Entidad de Nacimiento -->
        <div class="group relative">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span
              class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
              :class="isForeigner ? 'bg-blue-500' : isFieldRejected('birth_state') ? 'bg-red-500' : isFieldVerified('birth_state') ? 'bg-green-500' : isFieldPending('birth_state') ? 'bg-yellow-500' : birthStateDisplay ? 'bg-blue-500' : 'bg-gray-300'"
            ></span>
            <span class="text-xs text-gray-500">{{ isForeigner ? 'Nacionalidad' : 'Entidad de Nacimiento' }}</span>
          </div>
          <p class="font-medium text-gray-900 flex items-center gap-1.5">
            <span v-if="isForeigner" class="text-xl">{{ application.applicant.nationality_info?.flag || '🌍' }}</span>
            <span v-if="isForeigner">{{ application.applicant.nationality_info?.name || application.applicant.nationality || '—' }}</span>
            <span v-else>{{ birthStateDisplay }}</span>
            <span v-if="isForeigner" class="text-xs font-medium bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">
              Extranjero
            </span>
          </p>
        </div>
        <!-- Estado civil (capturado en el onboarding) -->
        <div class="group relative">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span class="w-2 h-2 rounded-full flex-shrink-0" :class="application.applicant.marital_status_label ? 'bg-blue-500' : 'bg-gray-300'"></span>
            <span class="text-xs text-gray-500">Estado civil</span>
          </div>
          <p class="font-medium text-gray-900">{{ application.applicant.marital_status_label || '—' }}</p>
        </div>
        <!-- Nivel educativo (capturado en el onboarding) -->
        <div class="group relative">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span class="w-2 h-2 rounded-full flex-shrink-0" :class="application.applicant.education_level_label ? 'bg-blue-500' : 'bg-gray-300'"></span>
            <span class="text-xs text-gray-500">Nivel educativo</span>
          </div>
          <p class="font-medium text-gray-900">{{ application.applicant.education_level_label || '—' }}</p>
        </div>
        <!-- Email -->
        <div class="group relative">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span
              class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
              :class="isFieldRejected('email') ? 'bg-red-500' : isFieldVerified('email') ? 'bg-green-500' : isFieldPending('email') ? 'bg-yellow-500' : application.applicant.email ? 'bg-blue-500' : 'bg-gray-300'"
            ></span>
            <span class="text-xs text-gray-500">Email</span>
            <div v-if="application.applicant.email" class="opacity-0 group-hover:opacity-100 transition-opacity ml-auto flex items-center gap-0.5">
              <button
                v-if="!isFieldVerified('email') && !isFieldRejected('email')"
                class="p-0.5 rounded hover:bg-green-100 text-gray-400 hover:text-green-600"
                :disabled="isVerifyingData"
                title="Verificar dato"
                @click="emit('verify', 'email', 'verify')"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <button
                v-if="isFieldVerified('email')"
                class="p-0.5 rounded hover:bg-gray-100 text-green-600"
                :disabled="isVerifyingData"
                title="Quitar verificación"
                @click="emit('verify', 'email', 'unverify')"
              >
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </button>
              <button
                v-if="!isFieldRejected('email')"
                class="p-0.5 rounded hover:bg-red-100 text-gray-400 hover:text-red-600"
                :disabled="isVerifyingData"
                title="Rechazar dato"
                @click="emit('reject', 'email')"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <button
                v-if="isFieldRejected('email')"
                class="p-0.5 rounded hover:bg-gray-100 text-red-600"
                :disabled="isVerifyingData"
                title="Quitar rechazo"
                @click="emit('unreject', 'email')"
              >
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>
          <p class="font-medium text-gray-900 truncate">{{ application.applicant.email || '—' }}</p>
          <p v-if="isFieldRejected('email')" class="text-xs text-red-600 mt-0.5">
            ⚠ {{ getFieldVerification('email')?.rejection_reason }}
          </p>
        </div>
        <!-- Teléfono -->
        <div class="group relative">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span
              class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
              :class="isFieldRejected('phone') ? 'bg-red-500' : isFieldVerified('phone') ? 'bg-green-500' : isFieldPending('phone') ? 'bg-yellow-500' : application.applicant.phone ? 'bg-blue-500' : 'bg-gray-300'"
            ></span>
            <span class="text-xs text-gray-500">Teléfono</span>
            <svg v-if="isFieldLocked('phone')" class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20" title="Verificado por OTP - No modificable">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
            </svg>
            <!-- Editar teléfono (solo SUPER_ADMIN, uso de pruebas: libera el número) -->
            <button
              v-if="isSuperAdmin"
              class="p-0.5 rounded hover:bg-blue-100 text-gray-400 hover:text-blue-600"
              :disabled="isEditingPhone"
              title="Editar teléfono (pruebas: libera el número para volver a registrarlo)"
              @click="emit('edit-phone')"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <div v-if="application.applicant.phone && !isFieldLocked('phone')" class="opacity-0 group-hover:opacity-100 transition-opacity ml-auto flex items-center gap-0.5">
              <button
                v-if="!isFieldVerified('phone') && !isFieldRejected('phone')"
                class="p-0.5 rounded hover:bg-green-100 text-gray-400 hover:text-green-600"
                :disabled="isVerifyingData"
                title="Verificar dato"
                @click="emit('verify', 'phone', 'verify')"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <button
                v-if="isFieldVerified('phone')"
                class="p-0.5 rounded hover:bg-gray-100 text-green-600"
                :disabled="isVerifyingData"
                title="Quitar verificación"
                @click="emit('verify', 'phone', 'unverify')"
              >
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </button>
              <button
                v-if="!isFieldRejected('phone')"
                class="p-0.5 rounded hover:bg-red-100 text-gray-400 hover:text-red-600"
                :disabled="isVerifyingData"
                title="Rechazar dato"
                @click="emit('reject', 'phone')"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <button
                v-if="isFieldRejected('phone')"
                class="p-0.5 rounded hover:bg-gray-100 text-red-600"
                :disabled="isVerifyingData"
                title="Quitar rechazo"
                @click="emit('unreject', 'phone')"
              >
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
            <!-- Method badge for locked phone -->
            <div v-if="isFieldLocked('phone')" class="ml-auto">
              <span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                {{ getFieldVerification('phone')?.method_label || 'OTP' }}
              </span>
            </div>
          </div>
          <p class="font-medium text-gray-900">{{ formatPhone(application.applicant.phone) }}</p>
          <p v-if="isFieldRejected('phone')" class="text-xs text-red-600 mt-0.5">
            ⚠ {{ getFieldVerification('phone')?.rejection_reason }}
          </p>
          <p v-if="isFieldLocked('phone')" class="text-[10px] text-gray-500 mt-0.5">
            Verificado automáticamente - No modificable
          </p>
        </div>
        <!-- CURP (solo para nacionales) — piloto de VerifiableField -->
        <VerifiableField
          v-if="!isForeigner"
          label="CURP"
          :value="application.applicant.curp"
          field-key="curp"
          :verification="getFieldVerification('curp')"
          :is-locked="isFieldLocked('curp')"
          :is-verifying="isVerifyingData"
          :can-verify="true"
          mono
          @verify="(action) => emit('verify', 'curp', action)"
          @reject="emit('reject', 'curp')"
          @unreject="emit('unreject', 'curp')"
        />
        <!-- RFC -->
        <VerifiableField
          label="RFC"
          :value="application.applicant.rfc"
          field-key="rfc"
          :verification="getFieldVerification('rfc')"
          :is-locked="isFieldLocked('rfc')"
          :is-verifying="isVerifyingData"
          :can-verify="true"
          mono
          @verify="(action) => emit('verify', 'rfc', action)"
          @reject="emit('reject', 'rfc')"
          @unreject="emit('unreject', 'rfc')"
        />
        <!-- Número de Pasaporte (solo para extranjeros) -->
        <VerifiableField
          v-if="isForeigner"
          label="Número de Pasaporte"
          :value="application.applicant.passport_number"
          field-key="passport_number"
          :verification="getFieldVerification('passport_number')"
          :is-locked="isFieldLocked('passport_number')"
          :is-verifying="isVerifyingData"
          :can-verify="true"
          mono
          @verify="(action) => emit('verify', 'passport_number', action)"
          @reject="emit('reject', 'passport_number')"
          @unreject="emit('unreject', 'passport_number')"
        />
        <!-- Fecha de Emisión (solo para extranjeros) -->
        <VerifiableField
          v-if="isForeigner"
          label="Fecha de Emisión"
          :value="application.applicant.passport_issue_date ? formatDate(application.applicant.passport_issue_date) : null"
          field-key="passport_issue_date"
          :verification="getFieldVerification('passport_issue_date')"
          :is-locked="isFieldLocked('passport_issue_date')"
          :is-verifying="isVerifyingData"
          :can-verify="true"
          @verify="(action) => emit('verify', 'passport_issue_date', action)"
          @reject="emit('reject', 'passport_issue_date')"
          @unreject="emit('unreject', 'passport_issue_date')"
        />
        <!-- Fecha de Expiración (solo para extranjeros) -->
        <VerifiableField
          v-if="isForeigner"
          label="Fecha de Expiración"
          :value="application.applicant.passport_expiry_date ? formatDate(application.applicant.passport_expiry_date) : null"
          field-key="passport_expiry_date"
          :verification="getFieldVerification('passport_expiry_date')"
          :is-locked="isFieldLocked('passport_expiry_date')"
          :is-verifying="isVerifyingData"
          :can-verify="true"
          @verify="(action) => emit('verify', 'passport_expiry_date', action)"
          @reject="emit('reject', 'passport_expiry_date')"
          @unreject="emit('unreject', 'passport_expiry_date')"
        />
        <!-- Clave INE (solo para nacionales) -->
        <VerifiableField
          v-if="!isForeigner"
          label="Clave INE"
          :value="application.applicant.ine_clave"
          field-key="ine_clave"
          :verification="getFieldVerification('ine_clave')"
          :is-locked="isFieldLocked('ine_clave')"
          :is-verifying="isVerifyingData"
          :can-verify="true"
          mono
          @verify="(action) => emit('verify', 'ine_clave', action)"
          @reject="emit('reject', 'ine_clave')"
          @unreject="emit('unreject', 'ine_clave')"
        />
        <!-- OCR del INE (capturado en onboarding sin proveedor KYC) -->
        <div v-if="application.applicant.ine_ocr" class="group relative">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span
              class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
              :class="isFieldRejected('ine_ocr') ? 'bg-red-500' : isFieldVerified('ine_ocr') ? 'bg-green-500' : isFieldPending('ine_ocr') ? 'bg-yellow-500' : 'bg-blue-500'"
            ></span>
            <span class="text-xs text-gray-500">OCR (INE)</span>
          </div>
          <p class="font-mono text-sm text-gray-900">{{ application.applicant.ine_ocr }}</p>
        </div>
        <!-- Folio / CIC del INE -->
        <div
          v-if="application.applicant.ine_folio && application.applicant.ine_folio !== application.applicant.ine_clave"
          class="group relative"
        >
          <div class="flex items-center gap-1.5 mb-0.5">
            <span
              class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
              :class="isFieldRejected('ine_folio') ? 'bg-red-500' : isFieldVerified('ine_folio') ? 'bg-green-500' : isFieldPending('ine_folio') ? 'bg-yellow-500' : 'bg-blue-500'"
            ></span>
            <span class="text-xs text-gray-500">Folio (INE)</span>
          </div>
          <p class="font-mono text-sm text-gray-900">{{ application.applicant.ine_folio }}</p>
        </div>
        <!-- Fecha Nacimiento -->
        <VerifiableField
          label="Fecha Nacimiento"
          :value="application.applicant.birth_date ? formatDate(application.applicant.birth_date) : null"
          field-key="birth_date"
          :verification="getFieldVerification('birth_date')"
          :is-locked="isFieldLocked('birth_date')"
          :is-verifying="isVerifyingData"
          :can-verify="true"
          @verify="(action) => emit('verify', 'birth_date', action)"
          @reject="emit('reject', 'birth_date')"
          @unreject="emit('unreject', 'birth_date')"
        />
        <!-- Género (capturado en el onboarding) -->
        <div class="group relative">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span class="w-2 h-2 rounded-full flex-shrink-0 transition-colors" :class="isFieldRejected('gender') ? 'bg-red-500' : isFieldVerified('gender') ? 'bg-green-500' : isFieldPending('gender') ? 'bg-yellow-500' : application.applicant.gender ? 'bg-blue-500' : 'bg-gray-300'"></span>
            <span class="text-xs text-gray-500">Género</span>
          </div>
          <p class="font-medium text-gray-900">
            {{ application.applicant.gender === 'M' ? 'Masculino' : application.applicant.gender === 'F' ? 'Femenino' : (application.applicant.gender || '—') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
