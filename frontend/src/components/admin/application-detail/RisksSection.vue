<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import applicationService, {
  type ApplicationRisks,
  type RiskContactItem,
  type RiskFieldVerification,
} from '@/services/v2/application.staff.service'

const props = defineProps<{ applicationId: string }>()

const loading = ref(true)
const error = ref('')
const risks = ref<ApplicationRisks | null>(null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await applicationService.getRisks(props.applicationId)
    risks.value = res.data ?? null
  } catch {
    error.value = 'No se pudieron cargar los riesgos.'
  } finally {
    loading.value = false
  }
}
onMounted(load)

// Ejecutar / reintentar el riesgo de contacto a demanda (si el servicio está activo).
const running = ref<'phone' | 'email' | null>(null)
async function runRisk(type: 'phone' | 'email') {
  if (running.value) return
  running.value = type
  try {
    await applicationService.runContactRisk(props.applicationId, type)
    await load()
  } catch {
    error.value = 'No se pudo ejecutar la consulta de riesgo.'
  } finally {
    running.value = null
  }
}

// Texto del botón según el estado actual de la evaluación.
function runLabel(item: RiskContactItem | null, type: 'phone' | 'email'): string {
  if (running.value === type) return 'Consultando…'
  if (!item) return 'Consultar riesgo'
  return item.status === 'completed' ? 'Actualizar' : 'Reintentar'
}

// Color del nivel de riesgo (acepta very-low/low/moderate/high y LOW/MEDIUM/HIGH,
// y bandas tipo "051 Very Low").
function levelBadge(level?: string | null): string {
  const v = (level ?? '').toLowerCase()
  if (/(very[ -]?low|^low|\blow\b)/.test(v)) return 'bg-green-100 text-green-800'
  if (/(moderate|medium)/.test(v)) return 'bg-amber-100 text-amber-800'
  if (/high/.test(v)) return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-600'
}

function fmtDate(iso?: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' })
}

// Lectura segura de campos anidados del raw de Nubarium.
function raw(item: RiskContactItem | null, path: string): unknown {
  if (!item?.result) return null
  return path.split('.').reduce<unknown>((acc, k) => {
    if (acc && typeof acc === 'object') return (acc as Record<string, unknown>)[k]
    return undefined
  }, item.result)
}

const phone = computed(() => risks.value?.contact_risk?.phone ?? null)
const email = computed(() => risks.value?.contact_risk?.email ?? null)

const identityFields = computed(() => Object.entries(risks.value?.identity ?? {}))
const biometricFields = computed(() => Object.entries(risks.value?.biometrics ?? {}))

const FIELD_LABELS: Record<string, string> = {
  curp: 'CURP', rfc: 'RFC', ine: 'INE', ine_clave: 'Clave INE',
  face_match: 'Comparación facial', liveness: 'Prueba de vida',
}
const label = (k: string) => FIELD_LABELS[k] ?? k

function fieldVerified(f: RiskFieldVerification): boolean {
  return f.verified === true || f.status === 'VERIFIED'
}
function fieldScore(f: RiskFieldVerification): number | null {
  const s = (f.metadata?.score ?? f.metadata?.face_match_score ?? f.metadata?.liveness_score) as number | undefined
  return typeof s === 'number' ? s : null
}
</script>

<template>
  <div class="space-y-4">
    <div v-if="loading" class="flex items-center justify-center py-10 text-gray-500 text-sm">
      Cargando riesgos…
    </div>
    <div v-else-if="error" class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm">
      {{ error }}
    </div>

    <template v-else-if="risks">
      <!-- Riesgo de contacto (Nubarium Phone/Email Risk) -->
      <div class="border border-gray-200 rounded-lg">
        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
          <h3 class="text-sm font-semibold text-gray-900">Riesgo de contacto (Nubarium)</h3>
        </div>
        <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-3">
          <!-- Teléfono -->
          <div class="border border-gray-100 rounded-lg p-3">
            <p class="text-xs text-gray-500 mb-1">Teléfono</p>
            <template v-if="phone && phone.status === 'completed'">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', levelBadge(phone.level)]">
                  {{ phone.level || '—' }}
                </span>
                <span v-if="phone.score !== null" class="text-sm font-bold text-gray-900">score {{ phone.score }}</span>
                <span v-if="phone.recommendation" class="text-xs text-gray-500">· {{ phone.recommendation }}</span>
              </div>
              <div class="mt-1 text-xs text-gray-500">
                <span v-if="raw(phone, 'phone_type.description')">{{ raw(phone, 'phone_type.description') }}</span>
                <span v-if="raw(phone, 'carrier.name')"> · {{ raw(phone, 'carrier.name') }}</span>
              </div>
              <p class="text-[11px] text-gray-400 mt-1">{{ phone.identifier }} · {{ fmtDate(phone.created_at) }}</p>
            </template>
            <p v-else-if="phone" class="text-sm text-red-600">Falló: {{ phone.error || '—' }}</p>
            <p v-else class="text-sm text-gray-400">Sin evaluación.</p>
            <button
              v-if="risks.contact_risk.phone_enabled"
              type="button"
              class="mt-2 text-xs font-medium text-primary-600 hover:text-primary-700 disabled:opacity-60 disabled:cursor-not-allowed"
              :disabled="running === 'phone'"
              @click="runRisk('phone')"
            >
              {{ runLabel(phone, 'phone') }}
            </button>
          </div>

          <!-- Email -->
          <div class="border border-gray-100 rounded-lg p-3">
            <p class="text-xs text-gray-500 mb-1">Correo</p>
            <template v-if="email && email.status === 'completed'">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', levelBadge(email.level)]">
                  {{ email.level || '—' }}
                </span>
                <span v-if="email.score !== null" class="text-sm font-bold text-gray-900">score {{ email.score }}</span>
              </div>
              <div class="mt-1 text-xs text-gray-500">
                <span v-if="raw(email, 'query.results.0.status')">Entregabilidad: {{ raw(email, 'query.results.0.status') }}</span>
              </div>
              <p class="text-[11px] text-gray-400 mt-1">{{ email.identifier }} · {{ fmtDate(email.created_at) }}</p>
            </template>
            <p v-else-if="email" class="text-sm text-red-600">Falló: {{ email.error || '—' }}</p>
            <p v-else class="text-sm text-gray-400">No aplica / sin evaluación.</p>
            <button
              v-if="risks.contact_risk.email_enabled"
              type="button"
              class="mt-2 text-xs font-medium text-primary-600 hover:text-primary-700 disabled:opacity-60 disabled:cursor-not-allowed"
              :disabled="running === 'email'"
              @click="runRisk('email')"
            >
              {{ runLabel(email, 'email') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Identidad (KYC) + Biometría (SDK) -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="border border-gray-200 rounded-lg">
          <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Identidad</h3>
            <span class="text-xs text-gray-500">KYC: {{ risks.kyc_status || '—' }}</span>
          </div>
          <div class="p-3 space-y-2">
            <div v-if="identityFields.length === 0" class="text-sm text-gray-400">Sin validaciones de identidad.</div>
            <div v-for="[k, f] in identityFields" :key="k" class="flex items-center justify-between text-sm">
              <span class="text-gray-600">{{ label(k) }}</span>
              <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', fieldVerified(f) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                {{ fieldVerified(f) ? 'Verificado' : (f.status || 'Pendiente') }}
              </span>
            </div>
          </div>
        </div>

        <div class="border border-gray-200 rounded-lg">
          <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Biometría (SDK)</h3>
          </div>
          <div class="p-3 space-y-2">
            <div v-if="biometricFields.length === 0" class="text-sm text-gray-400">Sin biometría.</div>
            <div v-for="[k, f] in biometricFields" :key="k" class="flex items-center justify-between text-sm">
              <span class="text-gray-600">{{ label(k) }}</span>
              <span class="flex items-center gap-2">
                <span v-if="fieldScore(f) !== null" class="text-xs text-gray-500">{{ fieldScore(f) }}%</span>
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', fieldVerified(f) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                  {{ fieldVerified(f) ? 'OK' : (f.status || 'Pendiente') }}
                </span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Validación bancaria (CLABE) -->
      <div class="border border-gray-200 rounded-lg">
        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
          <h3 class="text-sm font-semibold text-gray-900">Validación bancaria</h3>
        </div>
        <div class="p-3 space-y-2">
          <div v-if="risks.bank.length === 0" class="text-sm text-gray-400">Sin validaciones de CLABE.</div>
          <div v-for="(b, i) in risks.bank" :key="i" class="flex items-center justify-between text-sm">
            <span class="text-gray-600">{{ b.bank_name }} · <span class="font-mono">{{ b.clabe }}</span></span>
            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', b.is_verified ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
              {{ b.is_verified ? 'Verificada' : 'Sin verificar' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Próximamente -->
      <div class="border border-dashed border-gray-200 rounded-lg p-3">
        <p class="text-xs text-gray-400">
          Próximamente: listas PLD, Buró de Crédito y Círculo de Crédito.
        </p>
      </div>
    </template>
  </div>
</template>
